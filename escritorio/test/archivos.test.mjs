import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { copiarDirectorio, crearExclusion, colaArchivo } from '../lib/archivos.js';

function armar(base, archivos) {
    for (const rel of archivos) {
        const ruta = path.join(base, rel);
        fs.mkdirSync(path.dirname(ruta), { recursive: true });
        fs.writeFileSync(ruta, rel);
    }
}

test('copiarDirectorio respeta las exclusiones y cuenta archivos', () => {
    const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'dp-copia-'));
    const origen = path.join(tmp, 'origen');
    const destino = path.join(tmp, 'destino');
    armar(origen, ['app/a.php', 'vendor/b.php', 'node_modules/x/c.js', '.env', 'storage/app/private/secreto.txt', 'storage/app/.gitignore', 'tests/T.php', 'public/build/app.js']);

    const excluir = crearExclusion(['node_modules', 'tests', '.env', 'storage/app/private/*']);
    const n = copiarDirectorio(origen, destino, { excluir });

    assert.equal(n, 4);
    assert.ok(fs.existsSync(path.join(destino, 'app/a.php')));
    assert.ok(fs.existsSync(path.join(destino, 'vendor/b.php')));
    assert.ok(fs.existsSync(path.join(destino, 'public/build/app.js')));
    assert.ok(fs.existsSync(path.join(destino, 'storage/app/.gitignore')));
    assert.ok(!fs.existsSync(path.join(destino, 'node_modules')));
    assert.ok(!fs.existsSync(path.join(destino, 'tests')));
    assert.ok(!fs.existsSync(path.join(destino, '.env')));
    assert.ok(!fs.existsSync(path.join(destino, 'storage/app/private/secreto.txt')));
    fs.rmSync(tmp, { recursive: true, force: true });
});

test('colaArchivo devuelve las últimas líneas o vacío si no existe', () => {
    const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'dp-cola-'));
    const ruta = path.join(tmp, 'x.log');
    fs.writeFileSync(ruta, 'a\nb\nc\nd\n');
    assert.equal(colaArchivo(ruta, 2), 'c\nd');
    assert.equal(colaArchivo(path.join(tmp, 'nada.log')), '');
    fs.rmSync(tmp, { recursive: true, force: true });
});
