const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const contexto = {};
vm.runInNewContext(fs.readFileSync(require('node:path').resolve(__dirname, '../../public/js/pedidos-busqueda-utils.js'), 'utf8'), contexto);
const util = contexto.BusquedaPedidos;

test('la búsqueda ignora tildes y mayúsculas', () => {
    const presentacion = { nombre: 'Quitasarro Ácido Extra Fuerte · 1 L', sku: 'QUITA-001' };
    assert.equal(util.coincide(presentacion, 'acido'), true);
    assert.equal(util.coincide(presentacion, 'ÁCIDO'), true);
    assert.equal(util.coincide(presentacion, 'quita-001'), true);
    assert.equal(util.coincide(presentacion, 'jabón'), false);
});

test('sumar un SKU y modalidad repetidos acumula cantidad válida', () => {
    assert.equal(util.cantidadAcumulada(4, 3), 7);
    assert.equal(util.cantidadAcumulada(9999, 2), null);
    assert.equal(util.cantidadAcumulada('abc', 2), null);
});

test('la identidad distingue cajas de 12 y 15 e ignora tamaño en unidad', () => {
    assert.notEqual(util.claveLinea(1, 'empaque', 12), util.claveLinea(1, 'empaque', 15));
    assert.equal(util.claveLinea('1', 'empaque', '15'), util.claveLinea(1, 'empaque', 15));
    assert.equal(util.claveLinea(1, 'unidad', 12), util.claveLinea(1, 'unidad', 15));
    assert.notEqual(util.claveLinea(1, 'unidad', 12), util.claveLinea(1, 'empaque', 12));
});
