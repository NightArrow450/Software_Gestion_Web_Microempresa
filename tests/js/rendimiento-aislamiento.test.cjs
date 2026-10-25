const test = require('node:test');
const assert = require('node:assert/strict');
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
