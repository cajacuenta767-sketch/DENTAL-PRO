'use strict';

/**
 * Lectura y escritura del archivo .env de Laravel sin dependencias.
 * Conserva comentarios, orden y valores que el launcher no gestiona.
 */

const PATRON_LINEA = /^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=(.*)$/;

/** Convierte un valor a la sintaxis .env (con comillas cuando hace falta). */
function formatearValor(valor) {
    if (valor === null || valor === undefined) {
        return '';
    }
    if (typeof valor === 'boolean') {
        return valor ? 'true' : 'false';
    }
    const texto = String(valor);
    if (texto === '') {
        return '';
    }
    if (/^[A-Za-z0-9_./:@+=,-]+$/.test(texto)) {
        return texto;
    }
    return '"' + texto.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"';
}

/** Interpreta el valor de una línea .env (quita comillas y escapes). */
function interpretarValor(crudo) {
    let texto = crudo.trim();
    if (texto.startsWith('"')) {
        let salida = '';
        for (let i = 1; i < texto.length; i++) {
            const c = texto[i];
            if (c === '\\' && i + 1 < texto.length) {
                salida += texto[++i];
            } else if (c === '"') {
                return salida; // lo que sigue (p. ej. un comentario) se ignora
            } else {
                salida += c;
            }
        }
        return salida;
    }
    if (texto.startsWith("'")) {
        const fin = texto.indexOf("'", 1);
        return fin > 0 ? texto.slice(1, fin) : texto.slice(1);
    }
    const comentario = texto.indexOf(' #');
    if (comentario >= 0) {
        texto = texto.slice(0, comentario).trim();
    }
    return texto;
}

/** Devuelve un objeto { CLAVE: valor } a partir del contenido de un .env. */
function leerEnv(contenido) {
    const valores = {};
    for (const linea of String(contenido ?? '').split(/\r?\n/)) {
        const m = PATRON_LINEA.exec(linea);
        if (m) {
            valores[m[1]] = interpretarValor(m[2]);
        }
    }
    return valores;
}

/**
 * Reemplaza (o añade al final) las claves indicadas en un .env existente,
 * sin tocar el resto de líneas. Devuelve el nuevo contenido.
 */
function actualizarEnv(contenido, valores) {
    const pendientes = new Map(Object.entries(valores));
    const lineas = String(contenido ?? '').split(/\r?\n/);
    const salida = lineas.map((linea) => {
        const m = PATRON_LINEA.exec(linea);
        if (m && pendientes.has(m[1])) {
            const valor = pendientes.get(m[1]);
            pendientes.delete(m[1]);
            return `${m[1]}=${formatearValor(valor)}`;
        }
        return linea;
    });

    while (salida.length && salida[salida.length - 1].trim() === '') {
        salida.pop();
    }
    for (const [clave, valor] of pendientes) {
        salida.push(`${clave}=${formatearValor(valor)}`);
    }
    return salida.join('\n') + '\n';
}

/**
 * Genera el .env completo de la instalación de escritorio.
 * `o` (opciones) lleva puertos, rutas, contraseña y datos de CONTROL.
 */
function generarEnv(o) {
    const requerido = ['puertoWeb', 'puertoDb', 'passwordDb', 'version', 'huella'];
    for (const clave of requerido) {
        if (o[clave] === undefined || o[clave] === null || o[clave] === '') {
            throw new Error(`generarEnv: falta la opción "${clave}".`);
        }
    }

    const secciones = [
        ['# DENTAL-PRO · configuración generada por la aplicación de escritorio.',
            '# Puedes editar este archivo. Estas claves se recalculan en cada arranque:',
            '#   APP_URL, APP_VERSION, DB_PORT, RESPALDOS_RUTA_PG, CONTROL_ACTIVO, CONTROL_HUELLA',
            '#   y CONTROL_URL / CONTROL_CLAVE_PUBLICA (desde config.json de la aplicación).'],
        [
            ['APP_NAME', o.nombreApp || 'DENTAL-PRO'],
            ['APP_ENV', 'production'],
            ['APP_KEY', o.appKey || ''],
            ['APP_DEBUG', false],
            ['APP_URL', `http://127.0.0.1:${o.puertoWeb}`, '*'],
            ['APP_VERSION', o.version, '*'],
            ['APP_LOCALE', 'es'],
            ['APP_FALLBACK_LOCALE', 'es'],
            ['APP_FAKER_LOCALE', 'es_ES'],
            ['APP_MAINTENANCE_DRIVER', 'file'],
            ['BCRYPT_ROUNDS', 12],
        ],
        [
            ['LOG_CHANNEL', 'daily'],
            ['LOG_STACK', 'single'],
            ['LOG_DEPRECATIONS_CHANNEL', 'null'],
            ['LOG_LEVEL', 'warning'],
            ['LOG_DAILY_DAYS', 14],
        ],
        [
            ['DB_CONNECTION', 'pgsql'],
            ['DB_HOST', '127.0.0.1'],
            ['DB_PORT', o.puertoDb, '*'],
            ['DB_DATABASE', o.baseDatos || 'odontosuite'],
            ['DB_USERNAME', o.usuarioDb || 'postgres'],
            ['DB_PASSWORD', o.passwordDb],
            ['DB_SCHEMA', 'public'],
            ['DB_SSLMODE', 'disable'],
        ],
        [
            ['SESSION_DRIVER', 'file'],
            ['SESSION_LIFETIME', 480],
            ['SESSION_ENCRYPT', false],
            ['SESSION_PATH', '/'],
            ['SESSION_DOMAIN', 'null'],
            ['BROADCAST_CONNECTION', 'log'],
            ['FILESYSTEM_DISK', 'local'],
            ['QUEUE_CONNECTION', 'sync'],
            ['CACHE_STORE', 'file'],
        ],
        [
            ['MAIL_MAILER', 'log'],
            ['MAIL_FROM_ADDRESS', 'no-reply@dental-pro.local'],
            ['MAIL_FROM_NAME', '${APP_NAME}'],
        ],
        [
            ['CLINICA_MONEDA', o.moneda || 'BOB'],
            ['CLINICA_ZONA_HORARIA', o.zonaHoraria || 'America/Lima'],
            ['MENSAJERIA_PROVEEDOR', 'log'],
            ['MENSAJERIA_PREFIJO_PAIS', o.prefijoPais || '591'],
            ['FACTURACION_PROVEEDOR', 'ninguno'],
            ['PASARELA_PROVEEDOR', 'simulado'],
        ],
        [
            ['RESPALDOS_CONSERVAR', 14],
            ['RESPALDOS_RUTA_PG', o.rutaPg || ''],
        ],
        [
            ['CONTROL_ACTIVO', true, '*'],
            ['CONTROL_URL', o.controlUrl || '', '*'],
            ['CONTROL_CLAVE_PUBLICA', o.controlClavePublica || '', '*'],
            ['CONTROL_HUELLA', o.huella, '*'],
            ['CONTROL_LICENCIA', o.controlLicencia || ''],
        ],
    ];

    const lineas = [];
    for (const seccion of secciones) {
        for (const item of seccion) {
            if (typeof item === 'string') {
                lineas.push(item);
            } else {
                const [clave, valor] = item;
                lineas.push(`${clave}=${formatearValor(valor)}`);
            }
        }
        lineas.push('');
    }
    return lineas.join('\n');
}

/** Claves que el launcher recalcula en cada arranque. */
function valoresDinamicos(o) {
    const valores = {
        APP_URL: `http://127.0.0.1:${o.puertoWeb}`,
        APP_VERSION: o.version,
        DB_PORT: o.puertoDb,
        RESPALDOS_RUTA_PG: o.rutaPg || '',
        CONTROL_ACTIVO: true,
        CONTROL_HUELLA: o.huella,
    };
    if (o.controlUrl) {
        valores.CONTROL_URL = o.controlUrl;
    }
    if (o.controlClavePublica) {
        valores.CONTROL_CLAVE_PUBLICA = o.controlClavePublica;
    }
    return valores;
}

module.exports = { formatearValor, interpretarValor, leerEnv, actualizarEnv, generarEnv, valoresDinamicos };
