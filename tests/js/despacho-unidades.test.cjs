const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const contexto = {};
vm.runInNewContext(fs.readFileSync(path.resolve(__dirname, '../../public/js/despacho-unidades.js'), 'utf8'), contexto);
const {sumarSalida} = contexto.DespachoUnidadesUtilidades;

test('suma paquetes y unidades sueltas una sola vez por SKU', () => {
    const salida = sumarSalida([{sku_id: 1, cantidad: 5}], [
        {identidad: 'paquete-a', componentes: [{sku_id: 2, cantidad: 24}]},
        {identidad: 'paquete-b', componentes: [{sku_id: 1, cantidad: 8}, {sku_id: 3, cantidad: 8}, {sku_id: 4, cantidad: 8}]},
    ]);
    assert.deepEqual(JSON.parse(JSON.stringify(salida)), {'1': 13, '2': 24, '3': 8, '4': 8});
    assert.equal(Object.values(salida).reduce((s, n) => s + n, 0), 53);
});
test('sin paquetes conserva la salida suelta', () => assert.equal(sumarSalida([{sku_id: 1, cantidad: 7}], [])[1], 7));
test('un paquete no puede estar seleccionado dos veces', () => {
    const paquete = {identidad: 'misma-identidad', componentes: [{sku_id: 1, cantidad: 2}]};
    assert.throws(() => sumarSalida([], [paquete, paquete]), /dos veces/);
});
test('rechaza unidades fraccionarias', () => assert.throws(() => sumarSalida([{sku_id: 1, cantidad: 1.5}], []), /enteras/));
test('rechaza cantidades negativas', () => assert.throws(() => sumarSalida([], [{identidad: 'paquete', componentes: [{sku_id: 1, cantidad: -1}]}]), /enteras/));
test('rechaza un SKU inválido', () => assert.throws(() => sumarSalida([{sku_id: 0, cantidad: 1}], []), /enteras/));
