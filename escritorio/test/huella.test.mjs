import { test } from 'node:test';
import assert from 'node:assert/strict';
import crypto from 'node:crypto';
import { calcularHuella, extraerMachineGuid, obtenerMachineGuid } from '../lib/huella.js';

test('la huella es sha256(hostname + MachineGuid) recortada a 32 caracteres', () => {
    const esperada = crypto.createHash('sha256').update('recepcion-pc' + 'c1f2a3b4-1234-5678-9abc-def012345678').digest('hex').slice(0, 32);
    assert.equal(calcularHuella('RECEPCION-PC', 'C1F2A3B4-1234-5678-9ABC-DEF012345678'), esperada);
    assert.equal(calcularHuella('RECEPCION-PC', 'C1F2A3B4-1234-5678-9ABC-DEF012345678').length, 32);
});

test('sin MachineGuid se usa solo el hostname y es determinista', () => {
    assert.equal(calcularHuella('pc'), calcularHuella('PC', null));
    assert.notEqual(calcularHuella('pc'), calcularHuella('pc', 'guid'));
    assert.throws(() => calcularHuella(''), /hostname/);
});

test('extraerMachineGuid interpreta la salida de reg query', () => {
    const salida = '\r\nHKEY_LOCAL_MACHINE\\SOFTWARE\\Microsoft\\Cryptography\r\n    MachineGuid    REG_SZ    C1F2A3B4-1234-5678-9ABC-DEF012345678\r\n\r\n';
    assert.equal(extraerMachineGuid(salida), 'c1f2a3b4-1234-5678-9abc-def012345678');
    assert.equal(extraerMachineGuid('ERROR: The system was unable to find the specified registry key or value.'), null);
});

test('obtenerMachineGuid devuelve null si reg falla', async () => {
    assert.equal(await obtenerMachineGuid(async () => { throw new Error('no existe'); }), null);
    const guid = await obtenerMachineGuid(async (cmd, args) => {
        assert.equal(cmd, 'reg');
        assert.ok(args.includes('MachineGuid'));
        return '    MachineGuid    REG_SZ    00000000-0000-0000-0000-000000000001';
    });
    assert.equal(guid, '00000000-0000-0000-0000-000000000001');
});
