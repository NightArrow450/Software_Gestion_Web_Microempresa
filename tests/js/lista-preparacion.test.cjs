const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function prepararImpresion() {
    const vista = fs.readFileSync(path.resolve(__dirname, '../../resources/views/cumplimiento/lista-preparacion.blade.php'), 'utf8');
    const script = vista.match(/<script>([\s\S]*?)<\/script>/)[1];
    const detalles = [{ open: false }, { open: true }];
    const eventos = {};
    vm.runInNewContext(script, {
        document: { querySelectorAll: () => detalles },
        window: { addEventListener: (nombre, callback) => eventos[nombre] = callback },
    });
    return { detalles, eventos };
}

test('imprimir abre todos los desgloses y después restaura la selección del usuario', () => {
    const { detalles, eventos } = prepararImpresion();
    eventos.beforeprint();
    assert.deepEqual(detalles.map(detalle => detalle.open), [true, true]);
    eventos.afterprint();
    assert.deepEqual(detalles.map(detalle => detalle.open), [false, true]);
});

test('varios avisos de impresión no pierden el estado original del desglose', () => {
    const { detalles, eventos } = prepararImpresion();
    eventos.beforeprint();
    eventos.beforeprint();
    eventos.afterprint();
    assert.deepEqual(detalles.map(detalle => detalle.open), [false, true]);
    eventos.afterprint();
    assert.deepEqual(detalles.map(detalle => detalle.open), [false, true]);
});
