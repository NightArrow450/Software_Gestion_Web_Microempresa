const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

if (process.env.GITHUB_ACTIONS !== 'true' || process.env.RUNNER_ENVIRONMENT !== 'github-hosted' || process.platform !== 'linux') {
    throw new Error('La revisión focalizada solo puede ejecutarse en un runner temporal de GitHub.');
}
const temporal = fs.realpathSync(process.env.RUNNER_TEMP);
assert.equal(process.env.VISUAL_DATABASE, path.join(temporal, 'pr49-visual.sqlite'));
const motores = require(path.join(temporal, 'navegador/node_modules/playwright'));
const salida = path.join(temporal, 'evidencias-pr49');
fs.mkdirSync(salida, {recursive:false});
const antes = process.env.AUDIT_ETAPA === 'antes';
const resultados = [], capturas = [], mediciones = [], errores = [];
const base = 'http://127.0.0.1:8099';
let pagina;

async function caso(nombre, accion) {
    try { await accion(); resultados.push({caso:nombre, estado:'APROBADO'}); }
    catch (error) {
        resultados.push({caso:nombre, estado:'FALLO', detalle:error.message});
        if (pagina) await pagina.screenshot({path:path.join(salida, `fallo-${resultados.length}.png`)}).catch(() => {});
    }
}
async function ir(ruta) {
    const respuesta = await pagina.goto(base + ruta, {waitUntil:'networkidle'});
    assert.equal(respuesta.status(), 200, `HTTP incorrecto en ${ruta}`);
    assert.equal(new URL(pagina.url()).pathname, ruta);
}
async function foto(nombre, elemento) {
    const archivo = `${nombre}.png`;
    if (elemento) await elemento.screenshot({path:path.join(salida, archivo), animations:'disabled'});
    else await pagina.screenshot({path:path.join(salida, archivo), animations:'disabled'});
    capturas.push(archivo);
    const medida = await pagina.evaluate(() => ({ancho:innerWidth, cuerpo:document.body.scrollWidth, documento:document.documentElement.scrollWidth}));
    assert.ok(Math.max(medida.cuerpo, medida.documento) <= medida.ancho + 1, `Desbordamiento en ${nombre}`);
    mediciones.push({pantalla:nombre, ...medida});
}
async function comprobarResumen(r) {
    const resumen = pagina.locator('[data-resumen]');
    if (antes) {
        assert.equal(await resumen.innerText(), `${r.unidades} unidades físicas · ${r.sueltas} sueltas · ${r.agrupadas} agrupadas · ${r.paquetes} paquetes · ${r.espera} en espera · Total S/ ${(r.centimos/100).toFixed(2)}`);
    } else {
        assert.equal(await resumen.locator('dt').count(), 6);
        assert.equal(await resumen.locator('dd').count(), 6);
        assert.equal(await resumen.getAttribute('aria-live'), 'polite');
        assert.equal(await resumen.getAttribute('aria-atomic'), 'true');
        for (const [campo, valor] of Object.entries(r)) {
            assert.equal(await resumen.locator(`[data-resumen-campo="${campo}"]`).innerText(), campo === 'centimos' ? `S/ ${(valor/100).toFixed(2)}` : String(valor));
        }
    }
}
(async () => {
    const motor = process.env.AUDIT_BROWSER;
    assert.ok(['chromium','firefox'].includes(motor));
    const navegador = await motores[motor].launch();
    const contexto = await navegador.newContext({viewport:{width:1366,height:950},locale:'es-PE'});
    await contexto.route('**/*', ruta => {
        const u = new URL(ruta.request().url());
        if (u.origin === base || ['fonts.googleapis.com','fonts.gstatic.com'].includes(u.hostname)) return ruta.continue();
        errores.push(`Destino externo bloqueado: ${u.origin}`); return ruta.abort();
    });
    pagina = await contexto.newPage(); pagina.setDefaultTimeout(12000);
    pagina.on('pageerror', e => errores.push(e.message));
    pagina.on('response', r => {if (r.status() >= 500) errores.push(`HTTP ${r.status()}: ${new URL(r.url()).pathname}`);});
    pagina.on('request', r => {
        if (!['GET','HEAD'].includes(r.method()) && new URL(r.url()).pathname !== '/login') errores.push('Escritura inesperada: ' + new URL(r.url()).pathname);
    });
    await caso('Autenticación con cuenta exclusivamente ficticia', async () => {
        await pagina.goto(base+'/login');
        await pagina.locator('[name=email]').fill('visual@example.test');
        await pagina.locator('[name=password]').fill(process.env.VISUAL_PASSWORD);
        await pagina.locator('button[type=submit]').click(); await pagina.waitForURL('**/dashboard');
    });
    for (const ancho of [360,390,768,1024,1366]) {
        await pagina.setViewportSize({width:ancho,height:950});
        await caso(`Dashboard, total y roles — ${ancho}px`, async () => {
            await ir('/dashboard');
            const tarjeta = pagina.locator('.dashboard-admin');
            assert.equal((await tarjeta.locator('.dashboard-user-count strong').innerText()).trim(), '3');
            assert.ok(await tarjeta.getByRole('link',{name:'Gestionar usuarios',exact:true}).isVisible());
            const estilo = await tarjeta.locator('.dashboard-user-count strong').evaluate(e => ({tamano:parseFloat(getComputedStyle(e).fontSize)}));
            if (!antes) {
                assert.ok(estilo.tamano >= 48, 'El total debe ser más destacado');
                const cuentas = await tarjeta.locator('.dashboard-role-counts strong').allTextContents();
                assert.equal(cuentas.length, 3); assert.equal(cuentas.reduce((s,n) => s+Number(n),0), 3);
            }
            const disponibilidad = pagina.locator('.dashboard-link').filter({has:pagina.getByRole('heading',{name:'Disponibilidad',exact:true})});
            assert.match(await disponibilidad.innerText(), antes ? /Existencias de productos terminados e insumos/ : /Consulta de productos terminados y pedidos pendientes/);
            mediciones.push({pantalla:'usuarios-activos', ancho, ...estilo});
            await foto(`${ancho}-dashboard`, tarjeta);
        });
        await caso(`Resumen vacío, controles y mensajes — ${ancho}px`, async () => {
            await ir('/pedidos/nuevo');
            await comprobarResumen({unidades:0,sueltas:0,agrupadas:0,paquetes:0,espera:0,centimos:0});
            if (!antes) {
                assert.ok(await pagina.getByText('Registrar o confirmar no descuenta stock ni verifica líquido disponible.',{exact:true}).isVisible());
                assert.ok(await pagina.getByText('Las unidades no agrupadas se entregan sueltas. Los paquetes pueden estar incompletos.',{exact:true}).isVisible());
                const pesos = await pagina.locator('[data-resumen]').evaluate(e => ({total:parseFloat(getComputedStyle(e.querySelector('[data-resumen-campo=centimos]')).fontSize), cantidad:parseFloat(getComputedStyle(e.querySelector('[data-resumen-campo=unidades]')).fontSize)}));
                assert.ok(pesos.total > pesos.cantidad);
                const boton = await pagina.locator('.pedido-guardar').evaluate(e => {
                    const p=e.parentElement, estilo=getComputedStyle(p);
                    return {ancho:e.getBoundingClientRect().width, disponible:p.clientWidth-parseFloat(estilo.paddingLeft)-parseFloat(estilo.paddingRight), alto:e.getBoundingClientRect().height};
                });
                assert.ok(boton.alto >= 44);
                if (ancho <= 650) assert.ok(Math.abs(boton.ancho-boton.disponible)<=1, 'Guardar debe ocupar el ancho interior disponible');
                else assert.ok(boton.ancho < boton.disponible, 'En escritorio debe conservar ancho proporcionado');
                mediciones.push({pantalla:'guardar', viewport:ancho, ...boton});
            }
        });
        await caso(`Actualización dinámica de seis indicadores — ${ancho}px`, async () => {
            await pagina.locator('[data-buscar-producto]').fill('quitasarro');
            await pagina.locator('#coincidencias-productos [role=option]').first().click();
            await pagina.waitForFunction(() => document.querySelector('[data-variantes]').options.length === 3);
            await pagina.locator('[data-variantes]').selectOption({label:'Limón'});
            const sku = await pagina.locator('[data-skus] option').nth(1).getAttribute('value');
            await pagina.locator('[data-skus]').selectOption(sku);
            await pagina.locator('[data-cantidad-nueva]').fill('12'); await pagina.locator('[data-agregar-linea]').click();
            await comprobarResumen({unidades:12,sueltas:12,agrupadas:0,paquetes:0,espera:0,centimos:2400});
            await pagina.locator('[data-lineas] input[name$="[cantidad]"]').fill('15');
            await pagina.locator('[data-lineas] input[name$="[cantidad_espera]"]').fill('5');
            await comprobarResumen({unidades:15,sueltas:15,agrupadas:0,paquetes:0,espera:5,centimos:3000});
            await pagina.locator('[data-empaquetar]').check(); await pagina.locator('[data-agregar-grupo]').click();
            await pagina.getByRole('button',{name:'Añadir variante al paquete',exact:true}).click();
            await pagina.getByLabel('SKU del paquete',{exact:true}).selectOption(sku);
            await pagina.getByLabel('Capacidad máxima del paquete').fill('12');
            await pagina.getByLabel('Unidades dentro del paquete',{exact:true}).fill('5');
            await comprobarResumen({unidades:15,sueltas:10,agrupadas:5,paquetes:1,espera:5,centimos:3000});
            await pagina.getByRole('button',{name:'Copiar este paquete',exact:true}).click();
            await pagina.getByLabel('Unidades dentro del paquete',{exact:true}).fill('3');
            const esperado={unidades:15,sueltas:7,agrupadas:8,paquetes:2,espera:5,centimos:3000};
            await comprobarResumen(esperado);
            await pagina.getByLabel('Unidades dentro del paquete',{exact:true}).fill('99');
            assert.match(await pagina.locator('[data-mensaje]').innerText(), /más unidades/);
            await comprobarResumen(esperado);
            await pagina.getByLabel('Unidades dentro del paquete',{exact:true}).fill('3');
            await comprobarResumen(esperado);
            assert.equal(await pagina.locator('[data-mensaje]').innerText(), '');
            const grupos=JSON.parse(await pagina.locator('[data-agrupaciones-json]').inputValue());
            assert.equal(grupos[0].componentes[0].cantidad,5); assert.equal(grupos[1].componentes[0].cantidad,3);
            const seccion = pagina.locator('.form-section').filter({has:pagina.getByRole('heading',{name:'Resumen del pedido',exact:true})});
            await foto(`${ancho}-resumen-pedido`, seccion);
            if (ancho === 390 || ancho === 1366) {
                await pagina.screenshot({path:path.join(salida,`${ancho}-formulario-completo.png`),fullPage:true,animations:'disabled'});
                capturas.push(`${ancho}-formulario-completo.png`);
            }
            mediciones.push({pantalla:'resumen', ancho, valores:esperado});
        });
    }
    await navegador.close();
    if (errores.length) resultados.push({caso:'Errores de navegador, servidor o escrituras inesperadas',estado:'FALLO',detalle:errores});
    fs.writeFileSync(path.join(salida,'resultados.json'),JSON.stringify({commit:process.env.PR_HEAD_SHA,etapa:process.env.AUDIT_ETAPA,motor,base:'SQLite temporal ficticia, sin MySQL ni Laragon',resultados,capturas,mediciones},null,2));
    const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    fs.writeFileSync(path.join(salida,'index.html'),`<!doctype html><html lang="es"><meta charset="utf-8"><title>Dashboard y pedidos — evidencia focalizada</title><style>body{font:16px system-ui;margin:2rem}img{max-width:100%;border:1px solid #ddd}section{margin:2rem 0}</style><h1>Dashboard y pedidos: ${esc(process.env.AUDIT_ETAPA)} / ${esc(motor)}</h1><p>Datos ficticios; sin registrar pedidos ni modificar inventario. <a href="resultados.json">Resultados</a></p><ul>${resultados.map(r=>`<li>${esc(r.estado)} — ${esc(r.caso)} ${esc(r.detalle||'')}</li>`).join('')}</ul>${capturas.map(c=>`<section><h2>${esc(c)}</h2><img loading="lazy" src="${c}" alt="${esc(c)}"></section>`).join('')}</html>`);
    console.log(JSON.stringify(resultados,null,2));
    if (resultados.some(r=>r.estado!=='APROBADO')) process.exitCode=1;
})().catch(e=>{fs.writeFileSync(path.join(salida,'error.txt'),e.stack);console.error(e.message);process.exit(1);});
