const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const contexto = {};
vm.runInNewContext(fs.readFileSync(require('node:path').resolve(__dirname, '../../public/js/surtidos-utils.js'), 'utf8'), contexto);
const surtidos = contexto.SurtidosUtilidades;

test('copiar una composición conserva cantidades y permite editar cada paquete independientemente', () => {
    const original = { estado: 'definida', componentes: [{ presentacion_producto_id: 10, cantidad: 5 }, { presentacion_producto_id: 20, cantidad: 4 }, { presentacion_producto_id: 30, cantidad: 3 }] };
    const copia = surtidos.copiar(original);
    assert.deepEqual(JSON.parse(JSON.stringify(copia)), original);
    copia.componentes[0].cantidad = 2;
    copia.componentes[1].cantidad = 7;
    assert.equal(original.componentes[0].cantidad, 5);
    assert.equal(surtidos.validar(copia, 12, [10, 20, 30]), '');
});

test('la composición pendiente no inventa aromas y una definida exige la suma exacta por paquete', () => {
    assert.equal(surtidos.validar({ estado: 'pendiente', componentes: [] }, 12, [10, 20]), '');
    for (const cantidad of [11, 13]) {
        assert.notEqual(surtidos.validar({ estado: 'definida', componentes: [{ presentacion_producto_id: 10, cantidad }] }, 12, [10, 20]), '');
    }
});

test('rechaza SKU ajenos, repetidos y cantidades no enteras sin repartir unidades automáticamente', () => {
    for (const componentes of [[{ presentacion_producto_id: 99, cantidad: 12 }], [{ presentacion_producto_id: 10, cantidad: 6 }, { presentacion_producto_id: 10, cantidad: 6 }], [{ presentacion_producto_id: 10, cantidad: 11.5 }]]) {
        assert.notEqual(surtidos.validar({ estado: 'definida', componentes }, 12, [10, 20]), '');
    }
});
