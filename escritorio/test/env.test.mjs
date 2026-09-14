import { test } from 'node:test';
import assert from 'node:assert/strict';
import { generarEnv, actualizarEnv, leerEnv, formatearValor, valoresDinamicos } from '../lib/env.js';

const opciones = {
    puertoWeb: 8181,
    puertoDb: 5433,
    passwordDb: 'abc123DEF456',
    version: '2.0.0',
    rutaPg: 'C:\\Program Files\\DENTAL-PRO\\resources\\runtime\\pgsql\\bin',
    huella: 'a'.repeat(32),
    controlUrl: 'https://control.ejemplo.com',
    controlClavePublica: 'MCowBQYDK2VwAyEA+base64==',
};

test('generarEnv produce un .env de producción completo', () => {
    const env = leerEnv(generarEnv(opciones));
    assert.equal(env.APP_ENV, 'production');
    assert.equal(env.APP_DEBUG, 'false');
    assert.equal(env.APP_URL, 'http://127.0.0.1:8181');
    assert.equal(env.APP_VERSION, '2.0.0');
    assert.equal(env.DB_CONNECTION, 'pgsql');
    assert.equal(env.DB_PORT, '5433');
    assert.equal(env.DB_PASSWORD, 'abc123DEF456');
    assert.equal(env.DB_DATABASE, 'odontosuite');
    assert.equal(env.SESSION_DRIVER, 'file');
    assert.equal(env.QUEUE_CONNECTION, 'sync');
    assert.equal(env.CACHE_STORE, 'file');
    assert.equal(env.MAIL_MAILER, 'log');
    assert.equal(env.RESPALDOS_RUTA_PG, opciones.rutaPg);
    assert.equal(env.CONTROL_ACTIVO, 'true');
    assert.equal(env.CONTROL_URL, opciones.controlUrl);
    assert.equal(env.CONTROL_CLAVE_PUBLICA, opciones.controlClavePublica);
    assert.equal(env.CONTROL_HUELLA, opciones.huella);
    assert.equal(env.CONTROL_LICENCIA, '');
    assert.equal(env.APP_KEY, '');
});

test('generarEnv exige las opciones obligatorias', () => {
    assert.throws(() => generarEnv({ ...opciones, passwordDb: '' }), /passwordDb/);
});

test('los valores con espacios o barras invertidas se entrecomillan y se leen igual', () => {
    assert.equal(formatearValor('C:\\Ruta con espacios\\bin'), '"C:\\\\Ruta con espacios\\\\bin"');
    assert.equal(formatearValor('simple/valor:8181'), 'simple/valor:8181');
    assert.equal(formatearValor(true), 'true');
    assert.equal(formatearValor(null), '');
    const env = leerEnv('X="C:\\\\Ruta con espacios\\\\bin" # comentario\nY=valor # comentario\nZ=\'lit"eral\'\nW="a \\"b\\" c"');
    assert.equal(env.X, 'C:\\Ruta con espacios\\bin');
    assert.equal(env.Y, 'valor');
    assert.equal(env.Z, 'lit"eral');
    assert.equal(env.W, 'a "b" c');
});

test('actualizarEnv reemplaza claves existentes y añade las nuevas sin tocar el resto', () => {
    const original = '# comentario\nAPP_KEY=base64:xyz\nDB_PORT=5433 # (*)\nCONTROL_LICENCIA=CTL-1111-2222-3333-4444\n\n';
    const nuevo = actualizarEnv(original, { DB_PORT: 5440, APP_URL: 'http://127.0.0.1:8182' });
    assert.equal(nuevo, '# comentario\nAPP_KEY=base64:xyz\nDB_PORT=5440\nCONTROL_LICENCIA=CTL-1111-2222-3333-4444\nAPP_URL=http://127.0.0.1:8182\n');
    const env = leerEnv(nuevo);
    assert.equal(env.APP_KEY, 'base64:xyz');
    assert.equal(env.CONTROL_LICENCIA, 'CTL-1111-2222-3333-4444');
});

test('valoresDinamicos no pisa CONTROL_URL cuando no hay valor configurado', () => {
    const v = valoresDinamicos({ ...opciones, controlUrl: '', controlClavePublica: '' });
    assert.equal(v.CONTROL_URL, undefined);
    assert.equal(v.CONTROL_HUELLA, opciones.huella);
    assert.equal(v.DB_PORT, 5433);
});
