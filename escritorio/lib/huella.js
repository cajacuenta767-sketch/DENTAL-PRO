'use strict';

const crypto = require('node:crypto');

/**
 * Huella del equipo para CONTROL (escritorio): sha256(hostname + id de máquina)
 * recortado a 32 caracteres. Si no hay id de máquina se usa solo el hostname.
 */
function calcularHuella(hostname, machineGuid = '') {
    const base = String(hostname || '').trim().toLowerCase() + String(machineGuid || '').trim().toLowerCase();
    if (!base) {
        throw new Error('No se pudo calcular la huella: hostname vacío.');
    }
    return crypto.createHash('sha256').update(base, 'utf8').digest('hex').slice(0, 32);
}

/** Extrae el MachineGuid de la salida de `reg query HKLM\...\Cryptography /v MachineGuid`. */
function extraerMachineGuid(salidaReg) {
    const m = /MachineGuid\s+REG_SZ\s+([0-9a-fA-F-]{36})/i.exec(String(salidaReg || ''));
    return m ? m[1].toLowerCase() : null;
}

/**
 * Obtiene el MachineGuid de Windows con `reg query`. `ejecutar` es una función
 * (comando, args) => Promise<string> para poder probarla sin Windows.
 */
async function obtenerMachineGuid(ejecutar) {
    try {
        const salida = await ejecutar('reg', [
            'query', 'HKLM\\SOFTWARE\\Microsoft\\Cryptography', '/v', 'MachineGuid', '/reg:64',
        ]);
        return extraerMachineGuid(salida);
    } catch {
        return null;
    }
}

module.exports = { calcularHuella, extraerMachineGuid, obtenerMachineGuid };
