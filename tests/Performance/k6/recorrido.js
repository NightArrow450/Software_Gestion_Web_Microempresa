import http from 'k6/http';
import { check, fail, sleep } from 'k6';
import crypto from 'k6/crypto';
import exec from 'k6/execution';
import { validarDestino } from './seguridad.mjs';

const manifiesto = JSON.parse(open(__ENV.RENDIMIENTO_MANIFIESTO));
const base = validarDestino(manifiesto, 'medir');
const cuentas = JSON.parse(open(__ENV.RENDIMIENTO_CUENTAS));
if (!Array.isArray(cuentas) || new Set(cuentas.map(c => c.email)).size !== cuentas.length) fail('Las identidades de VU deben ser únicas.');
let autenticado = false;

export function identidad() {
    validarDestino(manifiesto, 'medir');
    if (!__ENV.RENDIMIENTO_CLAVE_CONTROL || !/^[0-9]+$/.test(__ENV.PEDIDO_FICTICIO_ID || '')) {
        fail('Falta clave de control privada o pedido sintético autorizado.');
    }
    const nonce = crypto.sha256(`${Date.now()}-${exec.vu.idInTest}-${Math.random()}`, 'hex');
    const r = http.get(`${base}/__control_rendimiento`, { redirects: 0, headers: { 'X-Control-Nonce': nonce, 'X-Control-Operacion': 'medir' }, tags: { nombre: 'identidad', fase: 'control' } });
    let d;
    try { d = r.json(); } catch (_) { exec.test.abort('Respuesta de identidad inválida; se detiene sin visitar módulos.'); }
    const mensaje = `${manifiesto.autorizacion}|${base}|${manifiesto.base}|medir|${nonce}`;
    if (r.status !== 200 || d.url !== base || d.base !== manifiesto.base || d.autorizacion !== manifiesto.autorizacion
        || d.firma !== crypto.hmac('sha256', __ENV.RENDIMIENTO_CLAVE_CONTROL, mensaje, 'hex')) {
        exec.test.abort('El servidor no acredita la identidad independiente autorizada.');
    }
}

export default function recorrido() {
    identidad();
    if (!autenticado) {
        const cuenta = cuentas[exec.vu.idInTest - 1];
        if (!cuenta || !/^vu[0-9]+@carga\.invalid$/.test(cuenta.email)) fail('Cada VU requiere cuenta ficticia propia.');
        const pagina = http.get(`${base}/login`, { redirects: 0, tags: { nombre: 'login_formulario' } });
        const token = pagina.html().find('input[name="_token"]').first().attr('value');
        if (!token) fail('Login sin CSRF: no se enviarán credenciales.');
        const login = http.post(`${base}/login`, { _token: token, email: cuenta.email, password: cuenta.password }, { redirects: 0, tags: { nombre: 'login' } });
        autenticado = check(login, { 'login redirige al dashboard': r => r.status === 302 && String(r.headers.Location).endsWith('/dashboard') });
        if (!autenticado) fail('No se obtuvo una sesión válida.');
    }
    for (const [nombre, ruta] of [['pedidos', '/pedidos'], ['productos', '/productos?search=sint%C3%A9tico'],
        ['detalle', `/pedidos/${__ENV.PEDIDO_FICTICIO_ID}`], ['preparacion', '/cumplimiento/lista-preparacion'], ['envasados', '/envasados']]) {
        const segundos = (Date.now() - exec.scenario.startTime) / 1000;
        const etapa = exec.scenario.name === 'estres' ? (segundos >= 180 ? 'recuperacion' : segundos >= 150 ? 'descenso' : 'estres') : 'medicion';
        const r = http.get(base + ruta, { redirects: 0, tags: { nombre, fase: 'consulta', etapa, listado: nombre === 'detalle' ? 'no' : 'si' } });
        check(r, { 'consulta autenticada válida': x => x.status === 200 && !String(x.body).includes('name="password"') }, { fase: 'consulta' });
        sleep(1);
    }
}

export const umbrales = {
    'http_req_failed': ['rate<0.01'],
    'http_req_duration{listado:si}': ['p(95)<1000'],
    'http_req_failed{fase:consulta}': ['rate<0.01'],
    'checks': ['rate==1'],
};
