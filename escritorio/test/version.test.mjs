import { test } from 'node:test';
import assert from 'node:assert/strict';
import { necesitaCopiarApp, compararVersiones, limpiarVersion } from '../lib/version.js';

test('necesitaCopiarApp: primera instalación, misma versión, versión distinta', () => {
    assert.equal(necesitaCopiarApp(null, '2.0.0'), true);
    assert.equal(necesitaCopiarApp('', '2.0.0'), true);
    assert.equal(necesitaCopiarApp('2.0.0\r\n', '2.0.0'), false);
    assert.equal(necesitaCopiarApp('2.0.0', 'v2.0.1'), true);
    assert.equal(necesitaCopiarApp('2.1.0', '2.0.0'), true);
    assert.throws(() => necesitaCopiarApp('2.0.0', ''), /version.txt/);
});

test('compararVersiones ordena numéricamente', () => {
    assert.equal(compararVersiones('2.0.0', '2.0.0'), 0);
    assert.equal(compararVersiones('2.0.9', '2.0.10'), -1);
    assert.equal(compararVersiones('v2.1', '2.0.5'), 1);
    assert.equal(limpiarVersion(' v3.2.1 \n'), '3.2.1');
});
