'use strict';

const fs = require('node:fs');
const path = require('node:path');

/**
 * Copia recursiva de directorios sin dependencias.
 * `excluir` recibe la ruta relativa (con barras "/") y devuelve true para omitirla.
 * `alProgresar` se llama con el número de archivos copiados.
 */
function copiarDirectorio(origen, destino, { excluir = () => false, alProgresar = null } = {}) {
    let copiados = 0;

    const recorrer = (rel) => {
        const desde = rel ? path.join(origen, rel) : origen;
        const hacia = rel ? path.join(destino, rel) : destino;
        fs.mkdirSync(hacia, { recursive: true });

        for (const entrada of fs.readdirSync(desde, { withFileTypes: true })) {
            const relHijo = rel ? `${rel}/${entrada.name}` : entrada.name;
            if (excluir(relHijo, entrada)) {
                continue;
            }
            if (entrada.isDirectory()) {
                recorrer(relHijo);
            } else if (entrada.isFile()) {
                fs.copyFileSync(path.join(desde, entrada.name), path.join(hacia, entrada.name));
                copiados++;
                if (alProgresar && copiados % 200 === 0) {
                    alProgresar(copiados);
                }
            }
            // Enlaces simbólicos y otros tipos se omiten a propósito.
        }
    };

    recorrer('');
    if (alProgresar) {
        alProgresar(copiados);
    }
    return copiados;
}

/** Crea un filtro de exclusión a partir de una lista de patrones simples. */
function crearExclusion(patrones) {
    const reglas = patrones.map((p) => {
        const conBarra = p.endsWith('/');
        const limpio = p.replace(/\/$/, '');
        if (limpio.includes('*')) {
            const regex = new RegExp('^' + limpio.split('*').map(escapar).join('[^/]*') + '$');
            return (rel) => regex.test(rel) || regex.test(path.posix.basename(rel));
        }
        return (rel, entrada) => {
            if (rel === limpio || rel.startsWith(limpio + '/')) {
                return true;
            }
            return !conBarra && !limpio.includes('/') && entrada && !entrada.isDirectory()
                && path.posix.basename(rel) === limpio;
        };
    });
    return (rel, entrada) => reglas.some((r) => r(rel, entrada));
}

function escapar(texto) {
    return texto.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/** Últimas `n` líneas de un archivo de texto (o '' si no existe). */
function colaArchivo(ruta, n = 60) {
    try {
        const contenido = fs.readFileSync(ruta, 'utf8');
        return contenido.split(/\r?\n/).filter((l) => l !== '').slice(-n).join('\n');
    } catch {
        return '';
    }
}

module.exports = { copiarDirectorio, crearExclusion, colaArchivo };
