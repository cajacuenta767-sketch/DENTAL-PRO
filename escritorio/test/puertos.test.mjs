import { test } from 'node:test';
import assert from 'node:assert/strict';
import net from 'node:net';
import { puertoLibre, puertoDisponible } from '../lib/puertos.js';

function ocupar(puerto) {
    return new Promise((resolve) => {
        const s = net.createServer();
        s.listen({ port: puerto, host: '127.0.0.1' }, () => resolve(s));
    });
}

test('puertoLibre salta los puertos ocupados', async () => {
    const base = await puertoLibre(41000);
    const ocupado = await ocupar(base);
    try {
        assert.equal(await puertoDisponible(base), false);
        const siguiente = await puertoLibre(base);
        assert.ok(siguiente > base);
        assert.equal(await puertoDisponible(siguiente), true);
    } finally {
        ocupado.close();
    }
});

test('puertoLibre prefiere el puerto anterior si sigue libre', async () => {
    const preferido = await puertoLibre(42000);
    assert.equal(await puertoLibre(43000, { preferido }), preferido);
    const ocupado = await ocupar(preferido);
    try {
        assert.notEqual(await puertoLibre(43000, { preferido }), preferido);
    } finally {
        ocupado.close();
    }
});

test('puertoLibre falla con un mensaje claro si agota los intentos', async () => {
    const p = await puertoLibre(44000);
    const ocupado = await ocupar(p);
    try {
        await assert.rejects(puertoLibre(p, { intentos: 1 }), /No hay puertos libres/);
    } finally {
        ocupado.close();
    }
});
