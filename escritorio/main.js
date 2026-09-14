'use strict';

/**
 * DENTAL-PRO · lanzador de escritorio para Windows.
 *
 * Arranca un PostgreSQL privado y el servidor PHP integrado, prepara la
 * aplicación Laravel en la carpeta de datos del usuario y la muestra en una
 * ventana de Electron. Toda la lógica pura (puertos, .env, huella, versiones,
 * copia de archivos, espera HTTP) vive en ./lib y tiene pruebas propias.
 */

const { app, BrowserWindow, Menu, Tray, shell, dialog, ipcMain, nativeImage } = require('electron');
const path = require('node:path');
const fs = require('node:fs');
const os = require('node:os');
const crypto = require('node:crypto');
const { spawn, execFile } = require('node:child_process');
const lib = require('./lib');

const NOMBRE = 'DENTAL-PRO';
const ID_APP = 'com.dentalpro.escritorio';
const ES_WINDOWS = process.platform === 'win32';
const EXE = ES_WINDOWS ? '.exe' : '';
const VERSION = app.getVersion();
const BASE_DATOS = 'odontosuite';
const PUERTO_PG_DESDE = 5433;
const PUERTO_WEB_DESDE = 8181;

// ------------------------------------------------------------------ rutas
const dirDatos = process.env.LOCALAPPDATA
    ? path.join(process.env.LOCALAPPDATA, NOMBRE)
    : app.getPath('userData');

const rutas = {
    datos: dirDatos,
    app: path.join(dirDatos, 'app'),
    pg: path.join(dirDatos, 'datos', 'pgsql'),
    archivos: path.join(dirDatos, 'archivos'),
    logs: path.join(dirDatos, 'logs'),
    env: path.join(dirDatos, '.env'), // copia espejo; el canónico es app/.env
    envApp: path.join(dirDatos, 'app', '.env'),
    config: path.join(dirDatos, 'config.json'),
    temporal: path.join(dirDatos, 'datos', 'tmp'),
    logEscritorio: path.join(dirDatos, 'logs', 'escritorio.log'),
    logPostgres: path.join(dirDatos, 'logs', 'postgres.log'),
    logServidor: path.join(dirDatos, 'logs', 'servidor-php.log'),
    logTareas: path.join(dirDatos, 'logs', 'tareas.log'),
};

// El perfil de Chromium (caché, cookies) también queda dentro de la carpeta de datos.
app.setPath('userData', path.join(dirDatos, 'electron'));
app.setAppUserModelId(ID_APP);

const recursos = app.isPackaged ? process.resourcesPath : path.join(__dirname, 'recursos');
const runtime = {
    php: path.join(recursos, 'runtime', 'php', 'php' + EXE),
    phpIni: path.join(recursos, 'runtime', 'php', 'php.ini'),
    pgBin: path.join(recursos, 'runtime', 'pgsql', 'bin'),
    appEmpaquetada: path.join(recursos, 'app'),
};
const icono = nativeImage.createFromPath(path.join(__dirname, ES_WINDOWS ? 'icono.ico' : 'icono.png'));

// ------------------------------------------------------------------ estado
let ventanaCarga = null;
let ventana = null;
let bandeja = null;
let procesoServidor = null;
let temporizadorTareas = null;
let tareaEnCurso = false;
let saliendo = false;
let limpiezaHecha = false;
let postgresIniciado = false;
let urlBase = '';
let avisoBandejaMostrado = false;

// ------------------------------------------------------------------ registro
function asegurarDirectorios() {
    for (const dir of [rutas.datos, path.dirname(rutas.pg), rutas.archivos, rutas.logs, rutas.temporal]) {
        fs.mkdirSync(dir, { recursive: true });
    }
    // Esqueleto de storage de Laravel (LARAVEL_STORAGE_PATH apunta aquí).
    for (const sub of ['app/private', 'app/public', 'app/control', 'app/respaldos', 'framework/cache/data', 'framework/sessions', 'framework/views', 'framework/testing', 'logs']) {
        fs.mkdirSync(path.join(rutas.archivos, sub), { recursive: true });
    }
}

function registrar(nivel, mensaje) {
    const linea = `[${new Date().toISOString().replace('T', ' ').slice(0, 19)}] [${nivel.toUpperCase()}] ${mensaje}`;
    try {
        fs.appendFileSync(rutas.logEscritorio, linea + os.EOL);
    } catch {
        // sin sitio donde escribir: se muestra solo en consola
    }
    if (nivel === 'error') {
        console.error(linea);
    } else {
        console.log(linea);
    }
}

function anexarLog(ruta, texto) {
    if (!texto) {
        return;
    }
    try {
        fs.appendFileSync(ruta, texto.endsWith('\n') ? texto : texto + os.EOL);
    } catch {
        // ignorar
    }
}

// ------------------------------------------------------------------ config
function leerJson(ruta) {
    try {
        return JSON.parse(fs.readFileSync(ruta, 'utf8'));
    } catch {
        return null;
    }
}

/** Valores por defecto del paquete, sobreescribibles en %LOCALAPPDATA%\DENTAL-PRO\config.json. */
function cargarConfig() {
    const base = leerJson(path.join(__dirname, 'config.json')) ?? {};
    const local = leerJson(rutas.config) ?? {};
    return { ...base, ...local };
}

// ------------------------------------------------------------------ procesos
/**
 * php.ini del runtime usa rutas relativas (extension_dir="ext"); aquí se escribe
 * un .ini adicional con rutas absolutas (depende de dónde se instaló) que PHP
 * carga vía PHP_INI_SCAN_DIR. Lo hereda también el `php -S` que lanza artisan serve.
 */
function prepararIniAdicional() {
    const dir = path.join(rutas.datos, 'datos', 'php.d');
    fs.mkdirSync(dir, { recursive: true });
    const dirPhp = path.dirname(runtime.php);
    const cacert = path.join(dirPhp, 'cacert.pem');
    const lineas = ['; Generado por DENTAL-PRO en cada arranque; no editar.'];
    if (fs.existsSync(path.join(dirPhp, 'ext'))) {
        lineas.push(`extension_dir="${path.join(dirPhp, 'ext')}"`);
    }
    if (fs.existsSync(cacert)) {
        lineas.push(`curl.cainfo="${cacert}"`, `openssl.cafile="${cacert}"`);
    }
    lineas.push(`sys_temp_dir="${rutas.temporal}"`, `upload_tmp_dir="${rutas.temporal}"`, `error_log="${path.join(rutas.logs, 'php-errores.log')}"`, '');
    fs.writeFileSync(path.join(dir, 'dental-pro.ini'), lineas.join(os.EOL));
    return dir;
}

let dirIniAdicional = null;

function entornoProcesos(extra = {}) {
    const separador = ES_WINDOWS ? ';' : ':';
    if (!dirIniAdicional) {
        dirIniAdicional = prepararIniAdicional();
    }
    return {
        ...process.env,
        PATH: runtime.pgBin + separador + path.dirname(runtime.php) + separador + (process.env.PATH ?? ''),
        // Con separador inicial, PHP añade el directorio al de escaneo por defecto en vez de sustituirlo.
        PHP_INI_SCAN_DIR: (ES_WINDOWS ? ';' : ':') + dirIniAdicional,
        LARAVEL_STORAGE_PATH: rutas.archivos,
        TMP: rutas.temporal,
        TEMP: rutas.temporal,
        ...extra,
    };
}

function argsPhp(...args) {
    return fs.existsSync(runtime.phpIni) ? ['-c', runtime.phpIni, ...args] : args;
}

function binPg(nombre) {
    const ruta = path.join(runtime.pgBin, nombre + EXE);
    return fs.existsSync(ruta) ? ruta : nombre; // en desarrollo (Linux) se usa el PATH
}

function rutaPhp() {
    return fs.existsSync(runtime.php) ? runtime.php : 'php';
}

/**
 * Ejecuta un comando y devuelve { codigo, salida }. Rechaza si el código de
 * salida no es 0, salvo `permitirFallo`. La salida se anexa a `log` si se indica.
 */
function ejecutar(comando, args, { cwd = rutas.datos, env = null, log = null, permitirFallo = false, tiempoMs = 0, descripcion = null } = {}) {
    const etiqueta = descripcion ?? `${path.basename(comando)} ${args.join(' ')}`;
    registrar('info', `> ${etiqueta}`);
    return new Promise((resolve, reject) => {
        let salida = '';
        let hijo;
        try {
            hijo = spawn(comando, args, { cwd, env: env ?? entornoProcesos(), windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
        } catch (e) {
            reject(new Error(`No se pudo ejecutar ${etiqueta}: ${e.message}`));
            return;
        }
        let temporizador = null;
        if (tiempoMs > 0) {
            temporizador = setTimeout(() => {
                matarProceso(hijo);
                salida += `\n[tiempo de espera agotado tras ${Math.round(tiempoMs / 1000)} s]`;
            }, tiempoMs);
        }
        const recoger = (trozo) => {
            const texto = trozo.toString('utf8');
            salida += texto;
            if (log) {
                anexarLog(log, texto);
            }
        };
        hijo.stdout.on('data', recoger);
        hijo.stderr.on('data', recoger);
        hijo.on('error', (e) => {
            if (temporizador) clearTimeout(temporizador);
            reject(new Error(`No se pudo ejecutar ${etiqueta}: ${e.message}`));
        });
        hijo.on('close', (codigo) => {
            if (temporizador) clearTimeout(temporizador);
            if (codigo === 0 || permitirFallo) {
                resolve({ codigo, salida });
            } else {
                const cola = salida.trim().split(/\r?\n/).slice(-15).join('\n');
                reject(Object.assign(new Error(`${etiqueta} terminó con código ${codigo}${codigo === 3221225781 ? ' (falta una DLL: instala Microsoft Visual C++ Redistributable 2015-2022 x64)' : ''}.\n${cola}`), { codigo, salida }));
            }
        });
    });
}

function matarProceso(hijo) {
    if (!hijo || hijo.exitCode !== null || hijo.killed) {
        return;
    }
    if (ES_WINDOWS) {
        // /T mata también a los hijos (artisan serve lanza un php -S aparte).
        try {
            execFile('taskkill', ['/pid', String(hijo.pid), '/T', '/F'], { windowsHide: true }, () => {});
        } catch {
            hijo.kill();
        }
    } else {
        hijo.kill('SIGTERM');
    }
}

function artisan(args, opciones = {}) {
    return ejecutar(rutaPhp(), argsPhp('artisan', ...args, '--no-interaction', '--no-ansi'), {
        cwd: rutas.app,
        descripcion: `php artisan ${args.join(' ')}`,
        ...opciones,
    });
}

// ------------------------------------------------------------------ ventana de carga
function progreso(paso, texto, porcentaje) {
    registrar('info', `${paso}: ${texto}`);
    if (ventanaCarga && !ventanaCarga.isDestroyed()) {
        ventanaCarga.webContents.send('progreso', { paso, texto, porcentaje });
    }
}

function resumenRegistros() {
    const partes = [];
    for (const [nombre, ruta] of [['escritorio.log', rutas.logEscritorio], ['postgres.log', rutas.logPostgres], ['servidor-php.log', rutas.logServidor]]) {
        const cola = lib.colaArchivo(ruta, 40);
        if (cola) {
            partes.push(`----- ${nombre} -----\n${cola}`);
        }
    }
    return partes.join('\n\n');
}

function mostrarError(titulo, detalle) {
    registrar('error', `${titulo}: ${detalle}`);
    if (ventanaCarga && !ventanaCarga.isDestroyed()) {
        ventanaCarga.setContentSize(720, 640);
        ventanaCarga.center();
        ventanaCarga.webContents.send('error', { titulo, detalle, registro: resumenRegistros(), rutaLogs: rutas.logs });
        ventanaCarga.show();
        ventanaCarga.focus();
    } else {
        dialog.showErrorBox(`${NOMBRE} · ${titulo}`, `${detalle}\n\nRegistros en: ${rutas.logs}`);
    }
}

function crearVentanaCarga() {
    ventanaCarga = new BrowserWindow({
        width: 560,
        height: 400,
        resizable: false,
        frame: false,
        show: false,
        title: NOMBRE,
        icon: icono,
        backgroundColor: '#f3f6f8',
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            contextIsolation: true,
            nodeIntegration: false,
            sandbox: true,
        },
    });
    ventanaCarga.setMenuBarVisibility(false);
    ventanaCarga.loadFile(path.join(__dirname, 'ventana-carga.html'));
    ventanaCarga.once('ready-to-show', () => ventanaCarga.show());
    ventanaCarga.on('closed', () => {
        ventanaCarga = null;
    });
}

function cerrarVentanaCarga() {
    if (ventanaCarga && !ventanaCarga.isDestroyed()) {
        ventanaCarga.close();
    }
    ventanaCarga = null;
}

ipcMain.handle('info', () => ({ version: VERSION, rutaDatos: rutas.datos, rutaLogs: rutas.logs }));
ipcMain.on('abrir-registros', () => shell.openPath(rutas.logs));
ipcMain.on('abrir-carpeta-datos', () => shell.openPath(rutas.datos));
ipcMain.on('reintentar', () => {
    app.relaunch();
    app.exit(0);
});
ipcMain.on('salir', () => app.quit());

// ------------------------------------------------------------------ pasos de arranque
async function comprobarRuntime() {
    progreso('runtime', 'Comprobando PHP y PostgreSQL integrados…', 5);
    if (ES_WINDOWS && !fs.existsSync(runtime.php)) {
        throw new Error(`No se encontró PHP en ${runtime.php}. Reinstala ${NOMBRE}.`);
    }
    if (ES_WINDOWS && !fs.existsSync(path.join(runtime.pgBin, 'pg_ctl.exe'))) {
        throw new Error(`No se encontró PostgreSQL en ${runtime.pgBin}. Reinstala ${NOMBRE}.`);
    }
    if (!fs.existsSync(path.join(runtime.appEmpaquetada, 'artisan'))) {
        throw new Error(`No se encontró la aplicación en ${runtime.appEmpaquetada}. Reinstala ${NOMBRE}.`);
    }
    const { salida } = await ejecutar(rutaPhp(), argsPhp('-v'), { tiempoMs: 30000, descripcion: 'php -v' });
    registrar('info', salida.split(/\r?\n/)[0]);
    const modulos = (await ejecutar(rutaPhp(), argsPhp('-m'), { tiempoMs: 30000, descripcion: 'php -m' })).salida.toLowerCase();
    const faltan = ['pdo_pgsql', 'mbstring', 'openssl', 'gd', 'fileinfo'].filter((m) => !modulos.includes(m));
    if (faltan.length) {
        throw new Error(`A PHP le faltan extensiones: ${faltan.join(', ')}. Revisa runtime/php/php.ini.`);
    }
}

async function sincronizarApp() {
    const versionEmpaquetada = fs.existsSync(path.join(runtime.appEmpaquetada, 'version.txt'))
        ? fs.readFileSync(path.join(runtime.appEmpaquetada, 'version.txt'), 'utf8')
        : '';
    const versionInstalada = fs.existsSync(path.join(rutas.app, 'version.txt'))
        ? fs.readFileSync(path.join(rutas.app, 'version.txt'), 'utf8')
        : '';

    if (!lib.necesitaCopiarApp(versionInstalada, versionEmpaquetada)) {
        progreso('app', `Aplicación ${lib.limpiarVersion(versionInstalada)} lista.`, 15);
        return false;
    }

    progreso('app', `Instalando la aplicación ${lib.limpiarVersion(versionEmpaquetada)}… (esto puede tardar un minuto)`, 10);

    // El .env (clave de la app, contraseña de la base, licencia) sobrevive a la actualización.
    let envPrevio = null;
    if (fs.existsSync(rutas.envApp)) {
        envPrevio = fs.readFileSync(rutas.envApp, 'utf8');
    } else if (fs.existsSync(rutas.env)) {
        envPrevio = fs.readFileSync(rutas.env, 'utf8');
    }

    const nuevo = rutas.app + '.nuevo';
    const anterior = rutas.app + '.anterior';
    fs.rmSync(nuevo, { recursive: true, force: true });
    fs.rmSync(anterior, { recursive: true, force: true });

    const total = lib.copiarDirectorio(runtime.appEmpaquetada, nuevo, {
        excluir: (rel) => rel === '.env',
        alProgresar: (n) => progreso('app', `Copiando la aplicación… ${n} archivos`, 10 + Math.min(10, n / 800)),
    });

    try {
        if (fs.existsSync(rutas.app)) {
            fs.renameSync(rutas.app, anterior);
        }
        fs.renameSync(nuevo, rutas.app);
    } catch (e) {
        throw new Error(`No se pudo reemplazar la carpeta de la aplicación (${rutas.app}). Cierra cualquier proceso php.exe de ${NOMBRE} y vuelve a abrir. Detalle: ${e.message}`);
    }
    fs.rm(anterior, { recursive: true, force: true }, () => {});

    if (envPrevio) {
        fs.writeFileSync(rutas.envApp, envPrevio);
    }
    registrar('info', `Aplicación copiada: ${total} archivos.`);
    return true;
}

/** Arranca (o crea) el clúster PostgreSQL. Devuelve { puerto, passwordNueva, creado }. */
async function iniciarPostgres(puertoPreferido) {
    const pgCtl = binPg('pg_ctl');
    let passwordNueva = null;
    const creado = !fs.existsSync(path.join(rutas.pg, 'PG_VERSION'));

    if (creado) {
        progreso('pg', 'Creando la base de datos por primera vez…', 25);
        passwordNueva = crypto.randomBytes(20).toString('hex');
        const pwfile = path.join(rutas.temporal, 'pw-' + process.pid + '.txt');
        fs.writeFileSync(pwfile, passwordNueva + '\n');
        try {
            await ejecutar(binPg('initdb'), [
                '-D', rutas.pg, '-U', 'postgres', '-A', 'scram-sha-256', '--pwfile', pwfile,
                '--encoding=UTF8', '--locale=C', '--no-instructions',
            ], { log: rutas.logPostgres, descripcion: 'initdb' });
        } finally {
            fs.rmSync(pwfile, { force: true });
        }
    }

    // ¿Sigue en marcha de una sesión anterior que no se cerró bien?
    const estado = await ejecutar(pgCtl, ['status', '-D', rutas.pg], { permitirFallo: true, descripcion: 'pg_ctl status' });
    if (estado.codigo === 0) {
        const pid = lib.colaArchivo(path.join(rutas.pg, 'postmaster.pid'), 100).split(/\r?\n/);
        const puertoActivo = parseInt(pid[3], 10);
        if (puertoActivo > 0 && !(await lib.puertoDisponible(puertoActivo))) {
            registrar('warn', `PostgreSQL ya estaba en marcha en el puerto ${puertoActivo}; se reutiliza.`);
            postgresIniciado = true;
            return { puerto: puertoActivo, passwordNueva, creado };
        }
    }

    const puerto = await lib.puertoLibre(PUERTO_PG_DESDE, { preferido: puertoPreferido });
    progreso('pg', `Iniciando PostgreSQL en el puerto ${puerto}…`, 30);
    await ejecutar(pgCtl, [
        'start', '-D', rutas.pg, '-w', '-t', '120', '-l', rutas.logPostgres,
        '-o', `-p ${puerto} -c listen_addresses=127.0.0.1`,
    ], { log: rutas.logPostgres, descripcion: 'pg_ctl start' });
    postgresIniciado = true;
    return { puerto, passwordNueva, creado };
}

async function detenerPostgres() {
    if (!postgresIniciado) {
        return;
    }
    postgresIniciado = false;
    try {
        await ejecutar(binPg('pg_ctl'), ['stop', '-D', rutas.pg, '-m', 'fast', '-w', '-t', '60'], { log: rutas.logPostgres, permitirFallo: true, tiempoMs: 90000, descripcion: 'pg_ctl stop' });
    } catch (e) {
        registrar('error', `No se pudo detener PostgreSQL: ${e.message}`);
    }
}

/** Crea la base `odontosuite` si no existe (vía PDO, sin depender de createdb/psql). */
async function asegurarBaseDatos(env) {
    const script = path.join(rutas.temporal, 'crear-base.php');
    fs.writeFileSync(script, [
        '<?php',
        "$c = new PDO('pgsql:host=127.0.0.1;port='.getenv('PGPORT').';dbname=postgres', getenv('PGUSER'), getenv('PGPASSWORD'));",
        "$d = getenv('PGDATABASE');",
        "$existe = $c->query('SELECT 1 FROM pg_database WHERE datname = '.$c->quote($d))->fetchColumn();",
        "if ($existe) { echo 'existe'; exit(0); }",
        "$c->exec('CREATE DATABASE \"'.str_replace('\"', '', $d).'\" ENCODING \\'UTF8\\' TEMPLATE template0');",
        "echo 'creada';",
        '',
    ].join('\n'));
    const { salida } = await ejecutar(rutaPhp(), argsPhp(script), {
        env: entornoProcesos({ PGPORT: String(env.DB_PORT), PGUSER: env.DB_USERNAME, PGPASSWORD: env.DB_PASSWORD, PGDATABASE: env.DB_DATABASE }),
        descripcion: 'crear base de datos',
    });
    fs.rmSync(script, { force: true });
    return salida.trim() === 'creada';
}

function prepararEnv({ puertoDb, puertoWeb, passwordNueva, huella, config }) {
    let contenido = null;
    if (fs.existsSync(rutas.envApp)) {
        contenido = fs.readFileSync(rutas.envApp, 'utf8');
    } else if (fs.existsSync(rutas.env)) {
        contenido = fs.readFileSync(rutas.env, 'utf8');
        registrar('warn', 'app/.env no existía; se restauró desde la copia espejo.');
    }

    const comunes = {
        puertoWeb,
        puertoDb,
        version: VERSION,
        // Sin runtime empaquetado (desarrollo) la app usa pg_dump del PATH.
        rutaPg: fs.existsSync(runtime.pgBin) ? runtime.pgBin : '',
        huella,
        controlUrl: config.controlUrl,
        controlClavePublica: config.controlClavePublica,
    };

    if (!contenido) {
        if (!passwordNueva) {
            throw new Error(`No existe ${rutas.envApp} ni su copia ${rutas.env}, y la base de datos ya estaba creada: no se conoce su contraseña. Restaura el .env desde un respaldo o borra la carpeta ${path.dirname(rutas.pg)} para empezar de cero.`);
        }
        contenido = lib.generarEnv({
            ...comunes,
            passwordDb: passwordNueva,
            baseDatos: BASE_DATOS,
            zonaHoraria: config.zonaHoraria,
            moneda: config.moneda,
            prefijoPais: config.prefijoPais,
        });
    } else {
        const dinamicos = lib.valoresDinamicos(comunes);
        if (passwordNueva) {
            dinamicos.DB_PASSWORD = passwordNueva;
        }
        contenido = lib.actualizarEnv(contenido, dinamicos);
    }

    fs.writeFileSync(rutas.envApp, contenido);
    fs.copyFileSync(rutas.envApp, rutas.env);
    return lib.leerEnv(contenido);
}

async function obtenerHuella() {
    const guid = ES_WINDOWS
        ? await lib.obtenerMachineGuid((cmd, args) => new Promise((resolve, reject) => {
            execFile(cmd, args, { windowsHide: true, timeout: 10000 }, (e, stdout) => (e ? reject(e) : resolve(stdout)));
        }))
        : null;
    if (!guid) {
        registrar('warn', 'No se pudo leer MachineGuid; la huella usa solo el nombre del equipo.');
    }
    return lib.calcularHuella(os.hostname(), guid ?? '');
}

async function iniciarServidorWeb(puerto) {
    progreso('web', `Iniciando el servidor en el puerto ${puerto}…`, 80);
    const args = argsPhp('artisan', 'serve', '--host=127.0.0.1', `--port=${puerto}`, '--no-reload', '--no-interaction', '--no-ansi');
    procesoServidor = spawn(rutaPhp(), args, { cwd: rutas.app, env: entornoProcesos(), windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
    const pid = procesoServidor.pid;
    registrar('info', `php artisan serve (pid ${pid})`);
    procesoServidor.stdout.on('data', (d) => anexarLog(rutas.logServidor, d.toString('utf8')));
    procesoServidor.stderr.on('data', (d) => anexarLog(rutas.logServidor, d.toString('utf8')));
    procesoServidor.on('exit', (codigo) => {
        registrar(saliendo ? 'info' : 'error', `El servidor PHP (pid ${pid}) terminó con código ${codigo}.`);
        if (procesoServidor && procesoServidor.pid === pid) {
            procesoServidor = null;
        }
        if (!saliendo) {
            servidorCaido();
        }
    });

    await lib.esperarHttp(`http://127.0.0.1:${puerto}/login`, {
        estados: [200],
        intervaloMs: 500,
        limiteMs: 120000,
        cancelado: () => procesoServidor === null,
        alIntentar: (n) => {
            if (n % 10 === 0) {
                progreso('web', `Esperando al servidor… (${n / 2} s)`, 85);
            }
        },
    });
}

function servidorCaido() {
    const opciones = { type: 'error', buttons: ['Reiniciar', 'Salir'], defaultId: 0, cancelId: 1, title: NOMBRE, message: 'El servidor interno se detuvo de forma inesperada.', detail: `Revisa ${rutas.logServidor}. ¿Quieres reiniciar ${NOMBRE}?` };
    dialog.showMessageBox(ventana && !ventana.isDestroyed() ? ventana : null, opciones).then(({ response }) => {
        if (response === 0) {
            app.relaunch();
        }
        app.quit();
    });
}

function programarTareas() {
    const correr = async () => {
        if (tareaEnCurso || saliendo) {
            return;
        }
        tareaEnCurso = true;
        try {
            await artisan(['schedule:run'], { log: rutas.logTareas, permitirFallo: true, tiempoMs: 10 * 60 * 1000 });
        } catch (e) {
            registrar('error', `schedule:run: ${e.message}`);
        } finally {
            tareaEnCurso = false;
        }
    };
    temporizadorTareas = setInterval(correr, 60 * 1000);
    setTimeout(correr, 15 * 1000);
}

// ------------------------------------------------------------------ ventana principal
function mostrarVentana(ruta = null) {
    if (!ventana || ventana.isDestroyed()) {
        return;
    }
    if (ruta) {
        ventana.loadURL(urlBase + ruta);
    }
    if (ventana.isMinimized()) {
        ventana.restore();
    }
    ventana.show();
    ventana.focus();
}

function crearMenu() {
    const plantilla = [
        {
            label: 'Aplicación',
            submenu: [
                { label: 'Inicio', accelerator: 'Alt+Home', click: () => mostrarVentana('/') },
                { label: 'Recargar', accelerator: 'CmdOrCtrl+R', click: () => ventana && ventana.reload() },
                { type: 'separator' },
                { label: 'Licencia', click: () => mostrarVentana('/licencia') },
                { label: 'Respaldos', click: () => mostrarVentana('/admin/respaldos') },
                { type: 'separator' },
                { label: 'Abrir carpeta de datos', click: () => shell.openPath(rutas.datos) },
                { label: 'Ver registros', click: () => shell.openPath(rutas.logs) },
                { type: 'separator' },
                { label: 'Ocultar a la bandeja', accelerator: 'CmdOrCtrl+W', click: () => ventana && ventana.hide() },
                { label: 'Salir', accelerator: 'CmdOrCtrl+Q', click: () => app.quit() },
            ],
        },
        {
            label: 'Ver',
            submenu: [
                { role: 'zoomIn', label: 'Acercar' },
                { role: 'zoomOut', label: 'Alejar' },
                { role: 'resetZoom', label: 'Tamaño normal' },
                { type: 'separator' },
                { role: 'togglefullscreen', label: 'Pantalla completa' },
                { type: 'separator' },
                { label: 'Imprimir…', accelerator: 'CmdOrCtrl+P', click: () => ventana && ventana.webContents.print() },
            ],
        },
        {
            label: 'Ayuda',
            submenu: [
                { label: `${NOMBRE} ${VERSION}`, enabled: false },
                { label: `Datos en ${rutas.datos}`, enabled: false },
                { label: `Servidor: ${urlBase}`, enabled: false },
                { type: 'separator' },
                { label: 'Herramientas de desarrollo', accelerator: 'F12', click: () => ventana && ventana.webContents.toggleDevTools() },
            ],
        },
    ];
    Menu.setApplicationMenu(Menu.buildFromTemplate(plantilla));
}

function crearBandeja() {
    const imagen = nativeImage.createFromPath(path.join(__dirname, 'icono-32.png'));
    bandeja = new Tray(imagen.isEmpty() ? icono : imagen);
    bandeja.setToolTip(`${NOMBRE} ${VERSION}`);
    bandeja.setContextMenu(Menu.buildFromTemplate([
        { label: `Abrir ${NOMBRE}`, click: () => mostrarVentana() },
        { label: 'Respaldos', click: () => mostrarVentana('/admin/respaldos') },
        { label: 'Licencia', click: () => mostrarVentana('/licencia') },
        { type: 'separator' },
        { label: 'Ver registros', click: () => shell.openPath(rutas.logs) },
        { type: 'separator' },
        { label: 'Salir', click: () => app.quit() },
    ]));
    bandeja.on('click', () => mostrarVentana());
    bandeja.on('double-click', () => mostrarVentana());
}

function crearVentanaPrincipal() {
    ventana = new BrowserWindow({
        width: 1280,
        height: 800,
        minWidth: 960,
        minHeight: 600,
        show: false,
        title: NOMBRE,
        icon: icono,
        backgroundColor: '#f3f6f8',
        webPreferences: {
            contextIsolation: true,
            nodeIntegration: false,
            sandbox: true,
            spellcheck: true,
        },
    });
    ventana.maximize();
    ventana.loadURL(urlBase + '/login');
    ventana.once('ready-to-show', () => {
        ventana.show();
        cerrarVentanaCarga();
    });

    ventana.webContents.setWindowOpenHandler(({ url }) => {
        if (url.startsWith(urlBase)) {
            ventana.loadURL(url);
        } else {
            shell.openExternal(url);
        }
        return { action: 'deny' };
    });
    ventana.webContents.on('will-navigate', (evento, url) => {
        if (!url.startsWith(urlBase)) {
            evento.preventDefault();
            shell.openExternal(url);
        }
    });

    ventana.on('close', (evento) => {
        if (saliendo) {
            return;
        }
        evento.preventDefault();
        ventana.hide();
        if (!avisoBandejaMostrado && bandeja) {
            avisoBandejaMostrado = true;
            if (typeof bandeja.displayBalloon === 'function') {
                bandeja.displayBalloon({ title: NOMBRE, content: 'Sigue funcionando en la bandeja del sistema. Usa "Salir" para cerrarlo del todo.', iconType: 'info' });
            }
        }
    });
    ventana.on('closed', () => {
        ventana = null;
    });
}

// ------------------------------------------------------------------ arranque y cierre
async function arrancar() {
    const inicio = Date.now();
    registrar('info', `===== ${NOMBRE} ${VERSION} · Electron ${process.versions.electron} · ${os.hostname()} =====`);
    registrar('info', `Datos: ${rutas.datos} · Recursos: ${recursos}`);

    const config = cargarConfig();
    await comprobarRuntime();

    const appCopiada = await sincronizarApp();

    const envPrevio = fs.existsSync(rutas.envApp) ? lib.leerEnv(fs.readFileSync(rutas.envApp, 'utf8')) : {};
    const puertoWebPrevio = parseInt((envPrevio.APP_URL ?? '').split(':').pop(), 10) || null;

    const { puerto: puertoDb, passwordNueva, creado } = await iniciarPostgres(parseInt(envPrevio.DB_PORT, 10) || null);

    progreso('env', 'Preparando la configuración…', 40);
    const puertoWeb = await lib.puertoLibre(PUERTO_WEB_DESDE, { preferido: puertoWebPrevio });
    const huella = await obtenerHuella();
    let env = prepararEnv({ puertoDb, puertoWeb, passwordNueva, huella, config });
    registrar('info', `Huella CONTROL: ${huella} · puertos: web ${puertoWeb}, PostgreSQL ${puertoDb}`);

    if (!env.APP_KEY) {
        progreso('env', 'Generando la clave de la aplicación…', 45);
        await artisan(['key:generate', '--force']);
        fs.copyFileSync(rutas.envApp, rutas.env);
        env = lib.leerEnv(fs.readFileSync(rutas.envApp, 'utf8'));
    }

    progreso('db', 'Comprobando la base de datos…', 50);
    const baseCreada = await asegurarBaseDatos(env);

    if (appCopiada) {
        await artisan(['optimize:clear'], { permitirFallo: true });
    }

    progreso('db', 'Actualizando la estructura de la base de datos…', 60);
    await artisan(['migrate', '--force']);

    if (baseCreada || creado) {
        progreso('db', 'Creando los datos iniciales…', 70);
        await artisan(['db:seed', '--class=ProduccionSeeder', '--force']);
    }

    urlBase = `http://127.0.0.1:${puertoWeb}`;
    await iniciarServidorWeb(puertoWeb);

    progreso('listo', 'Abriendo DENTAL-PRO…', 100);
    crearMenu();
    crearBandeja();
    crearVentanaPrincipal();
    programarTareas();
    registrar('info', `Listo en ${Math.round((Date.now() - inicio) / 1000)} s: ${urlBase}`);
}

async function detenerTodo() {
    saliendo = true;
    if (temporizadorTareas) {
        clearInterval(temporizadorTareas);
        temporizadorTareas = null;
    }
    if (procesoServidor) {
        registrar('info', 'Deteniendo el servidor PHP…');
        const hijo = procesoServidor;
        procesoServidor = null;
        matarProceso(hijo);
        await new Promise((r) => setTimeout(r, 500));
    }
    registrar('info', 'Deteniendo PostgreSQL…');
    await detenerPostgres();
    registrar('info', 'Cerrado.');
}

if (!app.requestSingleInstanceLock()) {
    app.quit();
} else {
    app.on('second-instance', () => {
        if (ventana) {
            mostrarVentana();
        } else if (ventanaCarga && !ventanaCarga.isDestroyed()) {
            ventanaCarga.focus();
        }
    });

    app.whenReady().then(async () => {
        try {
            asegurarDirectorios();
        } catch (e) {
            dialog.showErrorBox(NOMBRE, `No se pudo crear la carpeta de datos ${rutas.datos}: ${e.message}`);
            app.exit(1);
            return;
        }
        crearVentanaCarga();
        try {
            await arrancar();
        } catch (e) {
            mostrarError('No se pudo iniciar DENTAL-PRO', e.message);
            // Si PostgreSQL llegó a arrancar, se detiene para no dejarlo huérfano.
            await detenerPostgres();
        }
    });

    app.on('window-all-closed', () => {
        // Se mantiene en la bandeja; "Salir" cierra de verdad.
    });

    app.on('before-quit', (evento) => {
        if (limpiezaHecha) {
            return;
        }
        evento.preventDefault();
        detenerTodo().catch((e) => registrar('error', e.message)).finally(() => {
            limpiezaHecha = true;
            app.quit();
        });
    });

    process.on('uncaughtException', (e) => {
        registrar('error', `Excepción no controlada: ${e.stack || e.message}`);
    });
}
