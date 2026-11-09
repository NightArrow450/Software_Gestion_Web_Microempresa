const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const codigo = fs.readFileSync(require('node:path').join(__dirname, '../../public/js/indicadores.js'), 'utf8');
const contexto = { module: { exports: {} } };
vm.runInNewContext(codigo, contexto);
const { admite } = contexto.module.exports;
test('solo activa los filtros que pertenecen al reporte seleccionado', () => {
    const mapa = { comercial: ['pedido_id'], fisicas: ['sku'] };
    assert.equal(admite(mapa, 'comercial', 'sku'), false);
    assert.equal(admite(mapa, 'fisicas', 'sku'), true);
    assert.equal(admite(mapa, 'desconocido', 'sku'), false);
});
test('cambiar reporte oculta y deshabilita filtros ajenos antes de enviar el formulario', () => {
    const controles = ['sku', 'pedido_id', 'historial'].map(name => ({ name, disabled: false }));
    const grupos = controles.map(control => ({ hidden: false, querySelector: () => control }));
    let cambio;
    const selector = { value: 'comercial', addEventListener: (evento, callback) => { cambio = callback; } };
    const form = { dataset: { filtros: JSON.stringify({ comercial: ['pedido_id'], composiciones: ['sku', 'historial'] }) }, querySelector: () => selector, querySelectorAll: () => grupos };
    vm.runInNewContext(codigo, { window: { document: { querySelectorAll: () => [form] } } });
    assert.equal(controles[0].disabled, true);
    assert.equal(controles[1].disabled, false);
    assert.equal(grupos[2].hidden, true);
    selector.value = 'composiciones'; cambio();
    assert.equal(controles[0].disabled, false);
    assert.equal(controles[1].disabled, true);
    assert.equal(grupos[2].hidden, false);
});
