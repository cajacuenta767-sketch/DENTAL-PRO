#!/usr/bin/env node
/**
 * Copia el proyecto Laravel listo para producción a escritorio/recursos/app
 * (lo que el instalador empaqueta en resources/app). Sin dependencias.
 *
 *   node escritorio/preparar-app.mjs [--origen <raíz del repo>] [--destino <carpeta>]
 *                                     [--version 2.0.0] [--permitir-dev]
 *
 * Requisitos previos: `composer install --no-dev --optimize-autoloader` y
 * `npm run build` (assets Vite en public/build).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { copiarDirectorio, crearExclusion } = require('./lib/archivos.js');

const aqui = path.dirname(fileURLToPath(import.meta.url));
const args = process.argv.slice(2);
const opcion = (nombre, porDefecto) => {
    const i = args.indexOf(nombre);
    return i >= 0 && args[i + 1] && !args[i + 1].startsWith('--') ? args[i + 1] : porDefecto;
};
const bandera = (nombre) => args.includes(nombre);

const origen = path.resolve(opcion('--origen', path.join(aqui, '..')));
const destino = path.resolve(opcion('--destino', path.join(aqui, 'recursos', 'app')));
const version = opcion('--version', JSON.parse(fs.readFileSync(path.join(aqui, 'package.json'), 'utf8')).version);

function fallo(mensaje) {
    console.error(`\n[preparar-app] ERROR: ${mensaje}\n`);
    process.exit(1);
}

// ---------------------------------------------------------------- comprobaciones
if (!fs.existsSync(path.join(origen, 'artisan'))) {
    fallo(`${origen} no parece un proyecto Laravel (no hay "artisan").`);
}
if (!fs.existsSync(path.join(origen, 'vendor', 'autoload.php'))) {
    fallo('Falta vendor/autoload.php. Ejecuta: composer install --no-dev --optimize-autoloader');
}
if (!fs.existsSync(path.join(origen, 'public', 'build', 'manifest.json'))) {
    fallo('Falta public/build/manifest.json. Ejecuta: npm ci && npm run build');
}
if (fs.existsSync(path.join(origen, 'vendor', 'phpunit')) && !bandera('--permitir-dev')) {
    fallo('vendor/ incluye dependencias de desarrollo (phpunit). Ejecuta composer install --no-dev, o pasa --permitir-dev para una prueba local.');
}
if (path.resolve(destino) === path.resolve(origen) || origen.startsWith(destino + path.sep)) {
    fallo('El destino no puede ser el propio origen.');
}

// ---------------------------------------------------------------- exclusiones
const excluir = crearExclusion([
    // no forman parte de la aplicación en producción
    'node_modules', 'tests', '.git', '.github', 'escritorio', 'movil', 'sitio', 'docs', 'docker',
    'test-results', 'playwright-report', '.phpunit.cache', '.vscode', '.idea', '.fleet', '.zed', '.nova',
    // configuración y secretos locales
    '.env', '.env.backup', '.env.production', '.env.testing', '.env.local', 'auth.json',
    'Homestead.json', 'Homestead.yaml', 'Dockerfile', 'compose.yaml', 'phpunit.xml', 'playwright.config.mjs',
    '.phpunit.result.cache', '.DS_Store', 'Thumbs.db',
    // contenidos privados y caché de la instalación de desarrollo
    'storage/app/private/*', 'storage/app/public/*', 'storage/app/respaldos/*',
    'storage/framework/cache/data/*', 'storage/framework/sessions/*', 'storage/framework/views/*',
    'storage/framework/testing/*', 'storage/logs/*', 'storage/pail', 'storage/*.key',
    'bootstrap/cache/config.php', 'bootstrap/cache/routes-v7.php', 'bootstrap/cache/events.php', 'bootstrap/cache/compiled.php',
    'public/hot', 'public/storage',
    '*.log',
]);

const conservar = (rel, entrada) => {
    // Los .gitignore de storage se conservan para que existan las carpetas.
    if (entrada && entrada.isFile() && path.posix.basename(rel) === '.gitignore') {
        return false;
    }
    return excluir(rel, entrada);
};

// ---------------------------------------------------------------- copia
console.log(`[preparar-app] Origen : ${origen}`);
console.log(`[preparar-app] Destino: ${destino}`);
console.log(`[preparar-app] Versión: ${version}`);

fs.rmSync(destino, { recursive: true, force: true });
fs.mkdirSync(destino, { recursive: true });

const total = copiarDirectorio(origen, destino, {
    excluir: conservar,
    alProgresar: (n) => {
        if (n % 2000 === 0) console.log(`[preparar-app] ${n} archivos copiados…`);
    },
});

// Estructura mínima de storage y bootstrap/cache aunque el origen esté vacío.
for (const sub of [
    'storage/app/private', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions',
    'storage/framework/views', 'storage/framework/testing', 'storage/logs', 'bootstrap/cache',
]) {
    fs.mkdirSync(path.join(destino, sub), { recursive: true });
}

fs.writeFileSync(path.join(destino, 'version.txt'), version + '\n');

// ---------------------------------------------------------------- verificación
const esperados = ['artisan', 'composer.json', 'vendor/autoload.php', 'public/index.php', 'public/build/manifest.json',
    'database/seeders/ProduccionSeeder.php', 'bootstrap/app.php', 'version.txt'];
const faltan = esperados.filter((r) => !fs.existsSync(path.join(destino, r)));
if (faltan.length) {
    fallo(`Faltan archivos en la copia: ${faltan.join(', ')}`);
}
const prohibidos = ['.env', 'node_modules', 'tests', 'escritorio', '.git'].filter((r) => fs.existsSync(path.join(destino, r)));
if (prohibidos.length) {
    fallo(`Se copiaron rutas que debían excluirse: ${prohibidos.join(', ')}`);
}

function tamano(dir) {
    let bytes = 0;
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
        const p = path.join(dir, e.name);
        if (e.isDirectory()) bytes += tamano(p);
        else if (e.isFile()) bytes += fs.statSync(p).size;
    }
    return bytes;
}
console.log(`[preparar-app] Listo: ${total} archivos, ${(tamano(destino) / 1048576).toFixed(1)} MB en ${destino}`);
