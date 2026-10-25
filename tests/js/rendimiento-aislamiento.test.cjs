const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ruta = '../Performance/k6/seguridad.mjs';
const ejemplo = () => ({ url: 'http://127.0.0.1:8089', base: 'dhacer_eco_limp_demo_f9b_simulada',
    autorizacion: 'Solo metadatos simulados', vence: '2999-01-01T00:00:00Z', operaciones: ['medir'] });

test('valida solo la configuración simulada, sin enviar solicitudes', async () => {
    const { validarDestino } = await import(ruta);
    assert.equal(validarDestino(ejemplo(), 'medir'), 'http://127.0.0.1:8089');
});
for (const [caso, campo, valor] of [
    ['puerto operativo', 'url', 'http://127.0.0.1:8000'],
    ['servidor externo', 'url', 'https://example.com:8449'],
    ['credenciales en URL', 'url', 'http://u:p@127.0.0.1:8089'],
    ['base real', 'base', 'software_gestion_web_microempresa'],
    ['respaldo protegido', 'base', 'ecolimp_respaldo_prueba'],
    ['autorización caducada', 'vence', '2000-01-01'],
    ['operación no autorizada', 'operaciones', []],
]) {
    test(`k6 rechaza ${caso} sin tráfico`, async () => {
        const { validarDestino } = await import(ruta);
        assert.throws(() => validarDestino({ ...ejemplo(), [campo]: valor }, 'medir'));
    });
}

test('el alcance pasivo incluye el detalle numérico y excluye otros orígenes y operaciones', () => {
    const yaml = fs.readFileSync(path.join(__dirname, '../Performance/seguridad/zap-pasivo.example.yaml'), 'utf8');
    const patron = yaml.match(/includePaths:\s*\n\s*- '([^']+)'/)[1];
    const permitido = new RegExp(`^(?:${patron})$`);
    for (const ruta of ['/login', '/pedidos', '/pedidos/123', '/productos?search=sintetico', '/cumplimiento/lista-preparacion', '/envasados']) {
        assert.equal(permitido.test('http://127.0.0.1:8089' + ruta), true);
    }
    for (const url of ['http://127.0.0.1:8000/pedidos', 'https://example.com/pedidos', 'http://127.0.0.1:8089/pedidos/nuevo', 'http://127.0.0.1:8089/logout']) {
        assert.equal(permitido.test(url), false);
    }
    assert.doesNotMatch(yaml, /type:\s*(activeScan|spider)\b/);
});
