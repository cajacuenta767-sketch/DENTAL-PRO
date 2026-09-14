'use strict';

const http = require('node:http');

/** Una petición GET que resuelve con el código de estado (o rechaza si no conecta). */
function estadoHttp(url, tiempoMs = 5000) {
    return new Promise((resolve, reject) => {
        const peticion = http.get(url, { timeout: tiempoMs, headers: { 'User-Agent': 'DENTAL-PRO-escritorio' } }, (res) => {
            res.resume();
            resolve(res.statusCode);
        });
        peticion.on('timeout', () => peticion.destroy(new Error('tiempo de espera agotado')));
        peticion.on('error', reject);
    });
}

/**
 * Espera a que `url` responda con alguno de `estados`. Reintenta cada
 * `intervaloMs` hasta `limiteMs`. `cancelado()` permite abortar la espera
 * (por ejemplo si el proceso del servidor murió).
 */
async function esperarHttp(url, { estados = [200], intervaloMs = 500, limiteMs = 90000, cancelado = () => false, alIntentar = null } = {}) {
    const inicio = Date.now();
    let intento = 0;
    let ultimoError = null;

    while (Date.now() - inicio < limiteMs) {
        if (cancelado()) {
            throw new Error('Espera cancelada: el servidor terminó antes de responder.');
        }
        intento++;
        try {
            const estado = await estadoHttp(url, Math.min(intervaloMs * 4, 5000));
            if (estados.includes(estado)) {
                return estado;
            }
            ultimoError = new Error(`respondió ${estado}`);
        } catch (e) {
            ultimoError = e;
        }
        if (alIntentar) {
            alIntentar(intento, ultimoError);
        }
        await new Promise((r) => setTimeout(r, intervaloMs));
    }

    throw new Error(`${url} no respondió en ${Math.round(limiteMs / 1000)} s (${ultimoError ? ultimoError.message : 'sin respuesta'}).`);
}

module.exports = { estadoHttp, esperarHttp };
