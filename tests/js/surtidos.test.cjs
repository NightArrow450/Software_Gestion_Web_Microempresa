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

function prepararEditor(paquetes) {
    function elemento() {
        return {
            children: [], eventos: {}, dataset: {}, value: '',
            append(...hijos) { this.children.push(...hijos); },
            replaceChildren() { this.children = []; },
            add(opcion) { this.children.push(opcion); },
            addEventListener(nombre, accion) { this.eventos[nombre] = accion; },
            setAttribute() {}, setCustomValidity(mensaje) { this.error = mensaje; },
        };
    }
    const raiz = elemento(), oculto = { value: JSON.stringify(paquetes) };
    const document = { createElement: elemento, querySelectorAll: () => [], dispatchEvent() {} };
    const window = { SurtidosUtilidades: surtidos };
    const ctx = { window, document, Option: function (texto, valor) { this.text = texto; this.value = valor; }, CustomEvent: class {} };
    vm.runInNewContext(fs.readFileSync('public/js/surtidos.js', 'utf8'), ctx);
    let cantidad = paquetes.length;
    const editor = window.EditorSurtidos(raiz, oculto, [{ id: 10, nombre: 'Lavanda', sku: 'LAV' }, { id: 20, nombre: 'Limón', sku: 'LIM' }], 12, () => cantidad);
    return { raiz, oculto, editor, aumentar() { cantidad++; editor.actualizar(); } };
}

test('el editor copia al paquete elegido y una edición posterior no cambia el paquete original', () => {
    const e = prepararEditor([{ estado: 'definida', componentes: [{ presentacion_producto_id: 10, cantidad: 12 }] }, { estado: 'pendiente', componentes: [] }]);
    const controles = e.raiz.children[1], numero = controles.children[1], copiar = controles.children[4];
    numero.value = '2'; numero.eventos.change(); copiar.eventos.click();
    const filas = e.raiz.children[3], cantidad = filas.children[0].children[1];
    cantidad.value = '11'; cantidad.eventos.input();
    const paquetes = JSON.parse(e.oculto.value);
    assert.equal(paquetes[0].componentes[0].cantidad, 12);
    assert.equal(paquetes[1].componentes[0].cantidad, 11);
    assert.notEqual(numero.error, '');
});

test('aumentar paquetes conserva lo definido y agrega únicamente una composición pendiente vacía', () => {
    const e = prepararEditor([{ estado: 'definida', componentes: [{ presentacion_producto_id: 10, cantidad: 12 }] }]);
    e.aumentar();
    assert.deepEqual(JSON.parse(e.oculto.value), [{ estado: 'definida', componentes: [{ presentacion_producto_id: 10, cantidad: 12 }] }, { estado: 'pendiente', componentes: [] }]);
});

test('navegar a un número de paquete inválido no pierde ni crea composiciones', () => {
    const e = prepararEditor([{ estado: 'pendiente', componentes: [] }]);
    const numero = e.raiz.children[1].children[1];
    for (const valor of ['', '0', '1.5', '2', 'texto']) {
        numero.value = valor; numero.eventos.change();
        assert.equal(numero.value, '1');
        assert.deepEqual(JSON.parse(e.oculto.value), [{ estado: 'pendiente', componentes: [] }]);
    }
});
