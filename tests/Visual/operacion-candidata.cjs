const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
if (process.env.GITHUB_ACTIONS !== 'true' || process.env.RUNNER_ENVIRONMENT !== 'github-hosted' || process.platform !== 'linux') throw Error('Solo runners efímeros de GitHub.');
const temp = fs.realpathSync(process.env.RUNNER_TEMP);
assert.equal(process.env.VISUAL_DATABASE, path.join(temp, 'pr49-visual.sqlite'));
const { chromium, firefox } = require(path.join(temp, 'navegador/node_modules/playwright'));
const output = path.join(temp, 'evidencias-pr49');
const base = 'http://127.0.0.1:8099';
const resultados = [], errores = [], stock = [];
let page, browser, sku, pedido;
function db(sql, params = []) {
    return JSON.parse(execFileSync('python3', ['-c', 'import sqlite3,json,sys; c=sqlite3.connect("file:"+sys.argv[1]+"?mode=ro",uri=True); c.row_factory=sqlite3.Row; print(json.dumps([dict(r) for r in c.execute(sys.argv[2],json.loads(sys.argv[3])).fetchall()])); c.close()', process.env.VISUAL_DATABASE, sql, JSON.stringify(params)], { encoding: 'utf8' }));
}
function saldo(etapa) {
    const s = Number(db('SELECT cantidad_actual FROM inventarios WHERE presentacion_producto_id=?', [sku])[0].cantidad_actual);
    stock.push({ etapa, sku, saldo: s }); return s;
}
async function ir(url) {
    const r = await page.goto(base + url, { waitUntil: 'networkidle' }); assert.equal(r.status(), 200, url); assert.notEqual(new URL(page.url()).pathname, '/login');
}
async function enviar(form) {
    await form.locator('button[type=submit], button:not([type])').last().click();
    await page.waitForLoadState('networkidle');
    assert.equal(await page.locator('.app-alert.error').count(), 0, await page.locator('body').innerText());
}
async function login(email) {
    await page.goto(base + '/login');
    await page.locator('[name=email]').fill(email);
    await page.locator('[name=password]').fill(process.env.VISUAL_PASSWORD);
    await page.locator('button[type=submit]').click(); await page.waitForURL('**/dashboard');
}
async function cambiar(email) {
    await ir('/dashboard'); await page.locator('form[action$="/logout"] button').click(); await page.waitForURL('**/login'); await login(email);
}
async function caso(nombre, accion) {
    try { await accion(); resultados.push({ caso: nombre, estado: 'APROBADO' }); }
    catch (e) { resultados.push({ caso: nombre, estado: 'FALLO', detalle: e.message }); throw e; }
}
async function captura(nombre) {
    const ancho = await page.evaluate(() => innerWidth);
    const contenido = await page.evaluate(() => Math.max(document.body.scrollWidth, document.documentElement.scrollWidth));
    assert.ok(contenido <= ancho + 1, `Desbordamiento ${nombre}: ${contenido}/${ancho}`);
    await page.screenshot({ path: path.join(output, 'operacion-' + nombre + '.png'), fullPage: true });
}
(async () => {
    browser = await ({ chromium, firefox })[process.env.AUDIT_BROWSER].launch();
    const context = await browser.newContext({ viewport: { width: 1366, height: 950 }, locale: 'es-PE' });
    await context.route('**/*', async route => {
        const u = new URL(route.request().url());
        if (u.origin === base || ['fonts.googleapis.com', 'fonts.gstatic.com'].includes(u.hostname)) await route.continue();
        else { errores.push('Destino externo bloqueado: ' + u.origin); await route.abort(); }
    });
    page = await context.newPage(); page.setDefaultTimeout(15000);
    page.on('pageerror', e => errores.push(e.message));
    page.on('response', r => { if (r.status() >= 500) errores.push(`HTTP ${r.status()}: ${new URL(r.url()).pathname}`); });
    page.on('dialog', d => d.accept());
    const cuentas = db('SELECT id,first_name,last_name,email,password,status,role_id FROM users ORDER BY id');
    const insumos = db('SELECT * FROM insumos ORDER BY id');
    const antesMovimientosInsumos = db('SELECT COUNT(*) n FROM movimientos_inventario WHERE insumo_id IS NOT NULL')[0].n;
    await caso('Comercial registra 54 unidades y dos paquetes incompletos sin cargos ni salidas de stock', async () => {
        await login('comercial.visual@example.test'); await ir('/pedidos/nuevo');
        await page.locator('[data-buscar-cliente]').fill('jose nunez'); await page.locator('#coincidencias-clientes [role=option]').first().click();
        await page.locator('[data-buscar-producto]').fill('quitasarro'); await page.locator('#coincidencias-productos [role=option]').first().click();
        await page.waitForFunction(() => document.querySelector('[data-variantes]').options.length === 3);
        await page.locator('[data-variantes]').selectOption({ label: 'Limón' });
        sku = Number(await page.locator('[data-skus] option').nth(1).getAttribute('value'));
        await page.locator('[data-skus]').selectOption(String(sku));
        const antes = saldo('antes del pedido');
        await page.locator('[data-cantidad-nueva]').fill('54'); await page.locator('[data-agregar-linea]').click();
        await page.locator('[data-empaquetar]').check(); await page.locator('[data-agregar-grupo]').click();
        await page.getByRole('button', { name: 'Añadir variante al paquete', exact: true }).click();
        await page.getByLabel('SKU del paquete', { exact: true }).selectOption(String(sku));
        await page.getByLabel('Capacidad máxima del paquete').fill('12'); await page.getByLabel('Unidades dentro del paquete', { exact: true }).fill('5');
        await page.getByRole('button', { name: 'Copiar este paquete', exact: true }).click(); await page.getByLabel('Unidades dentro del paquete', { exact: true }).fill('3');
        assert.equal(await page.locator('[data-resumen-campo=unidades]').innerText(), '54');
        assert.equal(await page.locator('[data-resumen-campo=agrupadas]').innerText(), '8');
        assert.equal(await page.locator('[data-resumen-campo=sueltas]').innerText(), '46');
        assert.equal(await page.locator('[data-resumen-campo=centimos]').innerText(), 'S/ 108.00');
        for (const width of [360,390,1366]) { await page.setViewportSize({ width, height: 950 }); await captura('pedido-' + width); }
        await page.getByRole('button', { name: 'Guardar pedido por unidades', exact: true }).click(); await page.waitForURL(/\/pedidos\/\d+$/);
        pedido = Number(new URL(page.url()).pathname.split('/').pop());
        const row = db('SELECT numero,total FROM pedidos WHERE id=?', [pedido])[0]; assert.ok(row.numero); assert.equal(Number(row.total),108);
        assert.equal(saldo('pedido registrado'),antes);
        await page.getByRole('button', { name: /Confirmar pedido/ }).click(); await page.waitForLoadState('networkidle');
        assert.equal(db('SELECT estado FROM pedidos WHERE id=?',[pedido])[0].estado,'confirmado');
        assert.equal(saldo('pedido confirmado'),antes);
        assert.equal((await page.request.get(base + '/envasados/nuevo')).status(),403);
        assert.equal((await page.request.get(base + '/roles-permisos')).status(),403);
    });
    await caso('Producción registra 32 unidades terminadas anticipadas sin consumos; recargar no duplica', async () => {
        await cambiar('produccion.visual@example.test');
        assert.equal((await page.request.get(base + '/indicadores')).status(),403);
        assert.equal((await page.request.get(base + '/roles-permisos')).status(),403);
        await ir('/envasados/nuevo');
        assert.equal(await page.locator('[name=configuracion_envasado_id]').count(),0);
        await page.locator('[name=presentacion_producto_id]').selectOption(String(sku)); await page.locator('[name=cantidad]').fill('32');
        await page.locator('[name=observaciones]').fill('Producción anticipada ficticia confirmada por el trabajador simulado.');
        const before=saldo('antes de envasar');
        await page.getByRole('button',{name:'Revisar unidades antes de confirmar',exact:true}).click(); await page.waitForLoadState('networkidle');
        assert.equal(saldo('vista previa de envasado'),before);
        await page.locator('input[type=checkbox][required]').check(); await page.getByRole('button',{name:'Confirmar unidades terminadas',exact:true}).click(); await page.waitForURL(/\/envasados\/\d+$/);
        assert.equal(saldo('envasado confirmado'),before+32); await page.reload(); assert.equal(saldo('envasado recargado'),before+32);
        assert.equal(await page.locator('[name=clasificacion]').count(),0);
        await captura('envasado');
    });
    await caso('Preparación parcial de 32/54 y despacho real de 32 conservan 22 pendientes', async () => {
        await ir('/cumplimiento/lista-preparacion'); await captura('lista-preparacion');
        await ir('/cumplimiento/' + pedido);
        const before=saldo('antes de preparar');
        const form=page.locator('form[action$="/preparacion-real"]');
        await form.locator('[name=cantidad]').fill('32'); await form.locator('[name=incidencia]').selectOption('liquido');
        await form.locator('[name=motivo]').fill('Ensayo ficticio: solo treinta y dos unidades están físicamente preparadas.'); await enviar(form);
        assert.equal(saldo('preparación registrada'),before);
        assert.equal(db('SELECT estado FROM pedidos WHERE id=?',[pedido])[0].estado,'en_preparacion');
        const dispatch=page.locator('[data-despacho-unidades]');
        await dispatch.locator('[data-paquete-salida]').nth(0).check(); await dispatch.locator('[data-paquete-salida]').nth(1).check();
        await dispatch.locator('[data-salida-suelta]').fill('24'); await dispatch.locator('[name=motivo]').fill('Salida física ficticia de ocho agrupadas y veinticuatro sueltas.');
        await enviar(dispatch);
        assert.equal(saldo('despacho de 32'),before-32);
        assert.equal(db('SELECT estado FROM pedidos WHERE id=?',[pedido])[0].estado,'despacho_parcial');
        assert.equal(Number(db('SELECT SUM(l.cantidad) n FROM pedido_despacho_lineas l JOIN pedido_despachos d ON d.id=l.despacho_id WHERE d.pedido_id=?',[pedido])[0].n),32);
        await page.reload(); assert.equal(saldo('despacho recargado'),before-32); await captura('despacho-parcial');
    });
    await caso('Entrega acepta 30 y rechaza 2; retorno no repone hasta aprobación comercial', async () => {
        const before=saldo('antes de entregar');
        await page.getByText('Registrar cantidades aceptadas, rechazadas o pendientes',{exact:true}).click();
        const result=page.locator('form[action$="/resultado-entrega"]');
        await result.locator('[name$="[aceptadas]"]').fill('30'); await result.locator('[name$="[rechazadas]"]').fill('2');
        await result.locator('[name$="[motivo]"]').fill('El receptor ficticio no acepta estas dos unidades; causa para revisión.'); await enviar(result);
        assert.equal(saldo('rechazo registrado'),before);
        await page.getByText('Registrar retorno físico o no recuperación',{exact:true}).click();
        const ret=page.locator('form[action$="/retornos"]'); await ret.locator('[name$="[aptas]"]').fill('1'); await ret.locator('[name$="[danadas]"]').fill('1');
        await ret.locator('[name=observaciones]').fill('Conteo ficticio: una unidad apta y otra dañada físicamente recibidas.'); await enviar(ret);
        assert.equal(saldo('retorno recibido'),before); assert.equal(await page.locator('form[action$="/aprobar-retorno"]').count(),0);
        for(const width of [360,390,1366]) { await page.setViewportSize({width,height:950}); await captura('retorno-'+width); }
        await cambiar('comercial.visual@example.test'); await ir('/cumplimiento/'+pedido);
        const approval=page.locator('form[action$="/aprobar-retorno"]'); await approval.locator('xpath=..').locator('summary').click();
        await approval.locator('[name=cantidad]').fill('1'); await approval.locator('[name=motivo]').fill('Verificación ficticia de una unidad físicamente apta recibida.'); await enviar(approval);
        assert.equal(saldo('una apta aprobada'),before+1); await page.reload(); assert.equal(saldo('aprobación recargada'),before+1);
        assert.equal(await page.locator('form[action$="/rectificar-entrega"]').count(),0);
    });
    await caso('Comercial retira 22 faltantes sin alterar 32 despachadas ni precio original', async () => {
        const before=saldo('antes de retirar faltantes'); await ir('/pedidos/'+pedido); await page.locator('.retiro-faltantes summary').click();
        const form=page.locator('[data-retiro-faltantes]'); await form.locator('input[type=number]').fill('22');
        await form.locator('[name=motivo]').fill('Cliente ficticio retira definitivamente las veintidós unidades no despachadas.'); await form.locator('input[type=checkbox][required]').check();
        await form.getByRole('button',{name:'Confirmar retiro de faltantes'}).click(); await page.waitForLoadState('networkidle');
        assert.equal(saldo('faltantes retirados'),before);
        const versions=db('SELECT datos FROM pedido_versiones WHERE pedido_id=? ORDER BY numero',[pedido]).map(r=>JSON.parse(r.datos));
        assert.equal(versions[0].lineas[0].cantidad,54); assert.equal(versions.at(-1).lineas[0].cantidad,32);
        assert.equal(Number(versions[0].lineas[0].precio_unitario),Number(versions.at(-1).lineas[0].precio_unitario));
        assert.equal(Number(db('SELECT SUM(l.cantidad) n FROM pedido_despacho_lineas l JOIN pedido_despachos d ON d.id=l.despacho_id WHERE d.pedido_id=?',[pedido])[0].n),32);
        await captura('retiro-confirmado');
    });
    await caso('Administrador compensa un error de envasado conservando movimientos originales', async () => {
        await cambiar('visual@example.test');
        const id=db('SELECT id FROM envasados WHERE presentacion_producto_id=? ORDER BY id DESC LIMIT 1',[sku])[0].id;
        await ir('/envasados/'+id); const before=saldo('antes de corregir');
        const form=page.locator('form').filter({has:page.locator('[name=clasificacion]')});
        await form.locator('[name=clasificacion]').selectOption('error_administrativo'); await form.locator('[name=cantidad]').fill('1');
        await form.locator('[name=motivo]').fill('Corrección ficticia de una unidad registrada por error administrativo.'); await enviar(form);
        await page.locator('input[type=checkbox][required]').check(); await page.getByRole('button',{name:'Confirmar compensación de inventario',exact:true}).click(); await page.waitForLoadState('networkidle');
        assert.equal(saldo('compensación registrada'),before-1);
        assert.equal(db('SELECT COUNT(*) n FROM movimientos_inventario WHERE origen_tipo="envasado" AND origen_id=?',[String(id)])[0].n,1);
        await captura('correccion'); await ir('/cumplimiento/'+pedido); assert.equal(await page.locator('form[action$="/rectificar-entrega"]').count(),1);
    });
    await caso('Integridad final: sin consumos, usuarios alterados, stock negativo ni referencias huérfanas', async () => {
        assert.deepEqual(db('SELECT id,first_name,last_name,email,password,status,role_id FROM users ORDER BY id'),cuentas);
        assert.deepEqual(db('SELECT * FROM insumos ORDER BY id'),insumos);
        assert.equal(db('SELECT COUNT(*) n FROM movimientos_inventario WHERE insumo_id IS NOT NULL')[0].n,antesMovimientosInsumos);
        assert.deepEqual(db('PRAGMA foreign_key_check'),[]);
        assert.deepEqual(db('SELECT id FROM inventarios WHERE cantidad_actual < 0'),[]);
        assert.deepEqual(db('SELECT origen_tipo,origen_id,presentacion_producto_id,COUNT(*) n FROM movimientos_inventario WHERE origen_tipo IN ("envasado","despacho_unidades","retorno_pedido","correccion_envasado") GROUP BY origen_tipo,origen_id,presentacion_producto_id HAVING COUNT(*)>1'),[]);
        assert.ok(db('SELECT COUNT(*) n FROM auditoria')[0].n > 0);
        assert.deepEqual(errores,[]);
        await ir('/dashboard'); await page.locator('form[action$="/logout"] button').click(); await page.waitForURL('**/login');
    });
})().catch(async e => {
    console.error(e.message); if(page) await page.screenshot({path:path.join(output,'operacion-fallo.png'),fullPage:true}).catch(()=>{}); process.exitCode=1;
}).finally(async()=>{
    fs.writeFileSync(path.join(output,'operacion-resultados.json'),JSON.stringify({commit:process.env.PR_HEAD_SHA,motor:process.env.AUDIT_BROWSER,resultados,stock,errores,limitaciones:['Simulación automatizada, no UAT.','Navegador en SQLite efímera; concurrencia MySQL en el trabajo independiente de CI.']},null,2));
    if(browser) await browser.close();
    console.log(JSON.stringify(resultados,null,2));
});
