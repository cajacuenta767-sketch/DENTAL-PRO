import { test } from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import { esperarHttp } from '../lib/http.js';

test('esperarHttp espera hasta que el servidor responda 200', async () => {
    let listo = false;
    const servidor = http.createServer((req, res) => {
        res.statusCode = listo ? 200 : 503;
        res.end('x');
    });
    await new Promise((r) => servidor.listen(0, '127.0.0.1', r));
    const url = `http://127.0.0.1:${servidor.address().port}/login`;
    setTimeout(() => { listo = true; }, 300);
    const estado = await esperarHttp(url, { intervaloMs: 50, limiteMs: 5000 });
    assert.equal(estado, 200);
    servidor.close();
});

test('esperarHttp falla con mensaje claro si nadie responde o se cancela', async () => {
    await assert.rejects(esperarHttp('http://127.0.0.1:1/login', { intervaloMs: 20, limiteMs: 200 }), /no respondió/);
    await assert.rejects(esperarHttp('http://127.0.0.1:1/login', { intervaloMs: 20, limiteMs: 2000, cancelado: () => true }), /cancelada/);
});
