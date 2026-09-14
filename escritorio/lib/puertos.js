'use strict';

const net = require('node:net');

/** Comprueba si un puerto TCP está libre en la interfaz indicada. */
function puertoDisponible(puerto, host = '127.0.0.1') {
    return new Promise((resolve) => {
        const servidor = net.createServer();
        servidor.unref();
        servidor.once('error', () => resolve(false));
        servidor.listen({ port: puerto, host, exclusive: true }, () => {
            servidor.close(() => resolve(true));
        });
    });
}

/**
 * Devuelve el primer puerto libre a partir de `desde` (inclusive), probando
 * como máximo `intentos` puertos consecutivos.
 */
async function puertoLibre(desde, { host = '127.0.0.1', intentos = 200, preferido = null } = {}) {
    if (preferido && (await puertoDisponible(preferido, host))) {
        return preferido;
    }

    for (let puerto = desde; puerto < desde + intentos && puerto <= 65535; puerto++) {
        if (await puertoDisponible(puerto, host)) {
            return puerto;
        }
    }

    throw new Error(`No hay puertos libres entre ${desde} y ${desde + intentos - 1}.`);
}

module.exports = { puertoDisponible, puertoLibre };
