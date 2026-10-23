const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function elemento(datos = {}) {
    return Object.assign({
        dataset: {}, value: '', eventos: {}, children: [], disabled: false,
        addEventListener(nombre, callback) { this.eventos[nombre] = callback; },
        setAttribute() {}, removeAttribute() {},
        replaceChildren() { this.children = []; this.innerHTML = ''; this.textContent = ''; },
        append(hijo) { this.children.push(hijo); },
        focus() { this.enfocado = true; },
    }, datos);
}
function selector(opciones) {
    const select = elemento({ options: opciones });
    Object.defineProperty(select, 'selectedOptions', { get: () => opciones.filter(opcion => opcion.value === select.value) });
    return select;
}
function preparar(fetch = async () => ({ ok: true, text: async () => '<section>Consumos revisados</section>' })) {
    const boton = elemento({ dataset: { registrarEnvasado: '7', sku: 'LAV-1L' } });
    const campos = {
        presentacion_producto_id: elemento(), version_configuracion: elemento(), cantidad: elemento(),
        configuracion_envasado_id: selector([
            { value: '', dataset: {} },
            { value: '11', dataset: { presentacion: '7', version: '3' } },
            { value: '12', dataset: { presentacion: '9', version: '2' } },
        ]),
        pedido_id: selector([
            { value: '', dataset: {} },
            { value: '1', dataset: { presentaciones: '7,9' } },
            { value: '2', dataset: { presentaciones: '9' } },
        ]),
        produccion_desde: elemento({ value: '2026-10-22' }),
    };
    const consultar = elemento();
    const formulario = elemento({
        action: '/envasados/previsualizar', elements: { namedItem: nombre => campos[nombre] },
        querySelector: () => consultar, reset() { campos.cantidad.value = ''; },
    });
    const vista = elemento({ querySelector: () => elemento() });
    const errores = elemento();
    const cerrar = elemento();
    const titulo = elemento();
    const dialogo = elemento({ dataset: { reabrir: '0' },
        showModal() { this.open = true; },
        close() { this.open = false; this.eventos.close(); },
        getBoundingClientRect: () => ({ left: 10, right: 300, top: 10, bottom: 400 }),
        querySelector: consulta => ({
            '[data-consultar-envasado]': formulario, '[data-vista-previa-envasado]': vista,
            '[data-error-envasado]': errores, '[data-cerrar-envasado]': cerrar, '[data-sku-envasado]': titulo,
        })[consulta],
    });
    const document = {
        body: { style: { overflow: 'auto' } }, querySelector: () => dialogo,
        querySelectorAll: () => [boton], createElement: () => elemento(),
    };
    vm.runInNewContext(fs.readFileSync('public/js/lista-preparacion-envasado.js', 'utf8'), {
        document, fetch, AbortController, FormData: class {
            constructor() { this.valores = Object.fromEntries(Object.entries(campos).map(([nombre, campo]) => [nombre, campo.value])); }
        },
    });
    return { boton, campos, formulario, vista, errores, cerrar, dialogo, titulo, document, consultar };
}

test('abrir registra desde la presentación elegida y solo ofrece alternativas y pedidos compatibles', () => {
    const e = preparar();
    e.boton.eventos.click();
    assert.equal(e.dialogo.open, true);
    assert.equal(e.campos.presentacion_producto_id.value, '7');
    assert.equal(e.titulo.textContent, 'LAV-1L');
    assert.equal(e.campos.configuracion_envasado_id.options[1].disabled, false);
    assert.equal(e.campos.configuracion_envasado_id.options[2].disabled, true);
    assert.equal(e.campos.pedido_id.value, '');
    assert.equal(e.campos.pedido_id.options[2].disabled, true);
    assert.equal(e.document.body.style.overflow, 'hidden');
    e.cerrar.eventos.click();
    assert.equal(e.dialogo.open, false);
    assert.equal(e.document.body.style.overflow, 'auto');
    assert.equal(e.boton.enfocado, true);
});

test('vista previa utiliza servidor sin navegar y editar cantidad invalida la confirmación anterior', async () => {
    let enviada;
    const e = preparar(async (url, opciones) => {
        enviada = { url, opciones };
        return { ok: true, text: async () => '<section>Consumo: 5 envases</section>' };
    });
    e.boton.eventos.click();
    e.campos.configuracion_envasado_id.value = '11';
    e.campos.cantidad.value = '5';
    await e.formulario.eventos.submit({ preventDefault() {} });
    assert.equal(enviada.url, '/envasados/previsualizar');
    assert.equal(enviada.opciones.body.valores.version_configuracion, '3');
    assert.equal(enviada.opciones.body.valores.cantidad, '5');
    assert.equal(enviada.opciones.body.valores.produccion_desde, '2026-10-22');
    assert.equal(e.vista.innerHTML, '<section>Consumo: 5 envases</section>');
    assert.equal(e.dialogo.open, true);
    e.formulario.eventos.input();
    assert.equal(e.vista.innerHTML, '');
});

test('errores de validación se muestran como texto sin ofrecer confirmación', async () => {
    const e = preparar(async () => ({ ok: false, json: async () => ({ errors: { materiales: ['Material insuficiente <script>'] } }) }));
    e.boton.eventos.click();
    await e.formulario.eventos.submit({ preventDefault() {} });
    assert.equal(e.errores.children[0].textContent, 'Material insuficiente <script>');
    assert.equal(e.vista.innerHTML, '');
    assert.equal(e.consultar.disabled, false);
});

test('cerrar durante una consulta impide reutilizar una respuesta tardía', async () => {
    let terminar;
    const e = preparar(() => new Promise(resolve => { terminar = resolve; }));
    e.boton.eventos.click();
    const pendiente = e.formulario.eventos.submit({ preventDefault() {} });
    e.cerrar.eventos.click();
    terminar({ ok: true, text: async () => '<section>Respuesta anterior</section>' });
    await pendiente;
    assert.equal(e.vista.innerHTML, '');
    assert.equal(e.dialogo.open, false);
});
