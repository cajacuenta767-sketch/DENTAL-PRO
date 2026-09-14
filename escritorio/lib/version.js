'use strict';

/** Normaliza "v2.0.0\r\n" → "2.0.0". */
function limpiarVersion(texto) {
    return String(texto ?? '').trim().replace(/^v/i, '');
}

/** Compara dos versiones semánticas (solo la parte numérica). -1, 0 o 1. */
function compararVersiones(a, b) {
    const pa = limpiarVersion(a).split('-')[0].split('.').map((n) => parseInt(n, 10) || 0);
    const pb = limpiarVersion(b).split('-')[0].split('.').map((n) => parseInt(n, 10) || 0);
    const largo = Math.max(pa.length, pb.length);
    for (let i = 0; i < largo; i++) {
        const x = pa[i] ?? 0;
        const y = pb[i] ?? 0;
        if (x !== y) {
            return x < y ? -1 : 1;
        }
    }
    return 0;
}

/**
 * ¿Hay que copiar (o recopiar) la aplicación Laravel a la carpeta de datos?
 * Sí cuando no hay copia instalada o cuando la versión empaquetada difiere
 * (también si es menor: reinstalar una versión anterior debe dejar su código).
 */
function necesitaCopiarApp(versionInstalada, versionEmpaquetada) {
    const instalada = limpiarVersion(versionInstalada);
    const empaquetada = limpiarVersion(versionEmpaquetada);
    if (!empaquetada) {
        throw new Error('El paquete no trae version.txt; el instalador está incompleto.');
    }
    return instalada === '' || instalada !== empaquetada;
}

module.exports = { limpiarVersion, compararVersiones, necesitaCopiarApp };
