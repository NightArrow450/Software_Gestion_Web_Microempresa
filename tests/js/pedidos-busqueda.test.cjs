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

test('precio de empaque variable usa céntimos y conserva precio del tamaño de catálogo', () => {
    const producto = { unidades_por_empaque: 12, precio_unidad: '2.50', precio_empaque: '30.00' };
    assert.equal(util.precioLineaCentimos(producto, 'empaque', 12), 3000);
    assert.equal(util.precioLineaCentimos(producto, 'empaque', 15), 3750);
    assert.equal(util.precioLineaCentimos(producto, 'empaque', 2), 500);
    assert.equal(util.precioLineaCentimos(producto, 'empaque', 1000), 250000);
    assert.equal(util.precioLineaCentimos(producto, 'unidad', 'inválido'), 250);
    for (const tamano of [1, 1001, 2.5, '', 'abc']) {
        assert.equal(util.precioLineaCentimos(producto, 'empaque', tamano), null);
    }
    assert.equal(util.precioLineaCentimos({ ...producto, precio_unidad: null }, 'empaque', 15), null);
    assert.equal(util.precioLineaCentimos({ ...producto, precio_unidad: '0.29' }, 'empaque', 15), 435);
    assert.equal(util.precioLineaCentimos({ ...producto, precio_empaque: '27.00' }, 'empaque', 12), 2700);
});
