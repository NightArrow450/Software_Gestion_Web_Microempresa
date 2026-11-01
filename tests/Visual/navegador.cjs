const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

if (process.env.GITHUB_ACTIONS !== 'true' || process.env.RUNNER_ENVIRONMENT !== 'github-hosted' || process.platform !== 'linux') {
    throw new Error('El navegador solo puede ejecutarse en GitHub Actions temporal.');
}
const temporal = fs.realpathSync(process.env.RUNNER_TEMP);
assert.equal(process.env.VISUAL_DATABASE, path.join(temporal, 'pr49-visual.sqlite'));
const { chromium } = require(path.join(temporal, 'navegador/node_modules/playwright'));
const datos = JSON.parse(fs.readFileSync(path.join(temporal, 'visual-fixture.json'), 'utf8'));
const salida = path.join(temporal, 'evidencias-pr49');
fs.mkdirSync(salida, { recursive: false });
const resultados = [], capturas = [], errores = [];
const base = 'http://127.0.0.1:8099';
let pagina;

async function foto(nombre) {
    const archivo = `${nombre}.png`;
    await pagina.evaluate(async () => { window.scrollTo(0, 0); await document.fonts.ready; });
    await pagina.screenshot({ path: path.join(salida, archivo), fullPage: true, animations: 'disabled' });
    capturas.push(archivo);
    const medidas = await pagina.evaluate(() => ({ ancho: innerWidth, cuerpo: document.body.scrollWidth, documento: document.documentElement.scrollWidth }));
    if (Math.max(medidas.cuerpo, medidas.documento) > medidas.ancho + 1) {
        const diagnostico = await pagina.evaluate(() => [...document.querySelectorAll('body *')].filter(e => {
            const r = e.getBoundingClientRect(); return r.right > innerWidth + 1 && r.width > 0;
        }).slice(-25).map(e => ({ etiqueta: e.tagName, clase: e.className, rect: e.getBoundingClientRect().toJSON(),
            posicion: getComputedStyle(e).position, ancho: getComputedStyle(e).width, desbordamiento: getComputedStyle(e).overflowX })));
        fs.writeFileSync(path.join(salida, `${nombre}-desbordamiento.json`), JSON.stringify(diagnostico, null, 2));
    }
    assert.ok(Math.max(medidas.cuerpo, medidas.documento) <= medidas.ancho + 1, `Desbordamiento horizontal en ${nombre}: ${JSON.stringify(medidas)}`);
}
async function ir(ruta) {
    const respuesta = await pagina.goto(base + ruta, { waitUntil: 'networkidle' });
    assert.equal(respuesta.status(), 200, `HTTP incorrecto en ${ruta}`);
    assert.notEqual(new URL(pagina.url()).pathname, '/login', 'La sesión de prueba se perdió');
}
async function comprobar(nombre, accion) {
    try {
        await accion();
        resultados.push({ caso: nombre, estado: 'APROBADO' });
    } catch (error) {
        resultados.push({ caso: nombre, estado: 'FALLO', detalle: error.message });
        if (pagina) {
            const archivo = `fallo-${resultados.length}.png`;
            await pagina.screenshot({ path: path.join(salida, archivo), fullPage: true }).catch(() => {});
            capturas.push(archivo);
        }
    }
}

(async () => {
    const navegador = await chromium.launch();
    const contexto = await navegador.newContext({ viewport: { width: 1440, height: 1000 }, locale: 'es-PE' });
    await contexto.route('**/*', async ruta => {
        const url = new URL(ruta.request().url());
        if (url.origin === base || ['fonts.googleapis.com', 'fonts.gstatic.com'].includes(url.hostname)) {
            await ruta.continue();
        } else {
            errores.push(`Destino externo bloqueado: ${url.origin}`);
            await ruta.abort();
        }
    });
    pagina = await contexto.newPage();
    pagina.setDefaultTimeout(12000);
    pagina.on('pageerror', error => errores.push(error.message));
    pagina.on('response', r => { if (r.status() >= 500) errores.push(`HTTP ${r.status()}: ${new URL(r.url()).pathname}`); });
    await comprobar('Inicio de sesión real con usuario exclusivamente ficticio', async () => {
        await pagina.goto(base + '/login');
        await pagina.locator('[name=email]').fill('visual@example.test');
        await pagina.locator('[name=password]').fill(process.env.VISUAL_PASSWORD);
        await pagina.locator('button[type=submit]').click();
        await pagina.waitForURL('**/dashboard');
    });
    for (const ancho of [360, 390, 768, 1024, 1440]) {
        await pagina.setViewportSize({ width: ancho, height: 950 });
        await comprobar(`Buscadores, variante, presentación, validación y agrupaciones — ${ancho}px`, async () => {
            await ir('/pedidos/nuevo');
            await pagina.locator('[data-buscar-cliente]').fill('jose nunez');
            await pagina.locator('#coincidencias-clientes [role=option]').first().waitFor({ state: 'visible' });
            await foto(`${ancho}-01-sugerencias-clientes`);
            await pagina.locator('[data-buscar-cliente]').press('ArrowDown');
            await pagina.locator('[data-buscar-cliente]').press('Enter');
            assert.ok(await pagina.locator('[data-clientes]').inputValue());
            await pagina.locator('[data-buscar-producto]').fill('quitasarro');
            await pagina.locator('#coincidencias-productos [role=option]').first().waitFor({ state: 'visible' });
            await foto(`${ancho}-02-sugerencias-productos`);
            await pagina.locator('#coincidencias-productos [role=option]').first().click();
            await pagina.waitForFunction(() => document.querySelector('[data-variantes]').options.length === 3);
            await pagina.locator('[data-variantes]').selectOption({ label: 'Limón' });
            const sku = await pagina.locator('[data-skus] option').nth(1).getAttribute('value');
            await pagina.locator('[data-skus]').selectOption(sku);
            await pagina.locator('[data-cantidad-nueva]').fill('0');
            await pagina.locator('[data-agregar-linea]').click();
            assert.match(await pagina.locator('[data-mensaje]').innerText(), /cantidad entera positiva/);
            await foto(`${ancho}-03-seleccion-y-validacion`);
            await pagina.locator('[data-cantidad-nueva]').fill('12');
            await pagina.locator('[data-agregar-linea]').click();
            assert.match(await pagina.locator('[data-lineas]').innerText(), /Limón/);
            await pagina.locator('[data-empaquetar]').check();
            await pagina.locator('[data-agregar-grupo]').click();
            await pagina.getByRole('button', { name: 'Añadir variante al paquete', exact: true }).click();
            await pagina.getByLabel('SKU del paquete', { exact: true }).selectOption(sku);
            await pagina.getByLabel('Capacidad máxima del paquete').fill('12');
            await pagina.getByLabel('Unidades dentro del paquete', { exact: true }).fill('5');
            await pagina.getByRole('button', { name: 'Copiar este paquete', exact: true }).click();
            await pagina.getByLabel('Unidades dentro del paquete', { exact: true }).fill('3');
            const grupos = JSON.parse(await pagina.locator('[data-agrupaciones-json]').inputValue());
            assert.equal(grupos[0].componentes[0].cantidad, 5);
            assert.equal(grupos[1].componentes[0].cantidad, 3);
            await foto(`${ancho}-04-editor-agrupaciones`);
        });
        const pantallas = [
            ['disponibilidad', `/inventario?pedido_id=${datos.preparacion}`],
            ['materiales-historicos', '/inventario?historico_materiales=1'],
            ['lista-preparacion', '/cumplimiento/lista-preparacion'],
            ['preparacion-despacho-incidencias', `/cumplimiento/${datos.preparacion}`],
            ['entrega-historial-acciones', `/cumplimiento/${datos.parcial}`],
            ['seguimiento', `/seguimiento/${datos.preparacion}`],
            ['trazabilidad', `/seguimiento/${datos.preparacion}/trazabilidad`],
            ['indicadores', '/indicadores'],
            ['edicion-pedido', `/pedidos/${datos.preparacion}/editar`],
        ];
        for (const [nombre, ruta] of pantallas) {
            await comprobar(`${nombre} — ${ancho}px`, async () => {
                await ir(ruta);
                if (nombre === 'entrega-historial-acciones') {
                    await pagina.locator('.entrega-panel details').evaluateAll(detalles => detalles.forEach(d => { d.open = true; }));
                }
                await foto(`${ancho}-${nombre}`);
            });
        }
        await comprobar(`Formulario de retiro — ${ancho}px`, async () => {
            await ir(`/pedidos/${datos.parcial}`);
            await pagina.locator('.retiro-faltantes summary').click();
            await pagina.locator('[data-retiro-faltantes] input[type=number]').fill('22');
            await foto(`${ancho}-retiro-faltantes`);
        });
    }
    await comprobar('Paginación, sin resultados y mensaje de error de búsqueda', async () => {
        await ir('/pedidos/nuevo');
        await pagina.locator('[data-buscar-cliente]').fill('Cliente ficticio');
        await pagina.locator('#coincidencias-clientes [role=option]').nth(19).waitFor();
        const primeraPagina = await pagina.locator('#coincidencias-clientes [role=option]').count();
        await pagina.locator('[data-mas-clientes]').click();
        await pagina.waitForFunction(n => document.querySelectorAll('#coincidencias-clientes [role=option]').length > n, primeraPagina);
        await foto('1440-busqueda-paginada');
        await pagina.locator('[data-buscar-cliente]').fill('ZZZ-no-existe-ficticio');
        await pagina.getByText('No se encontraron coincidencias.', { exact: true }).waitFor();
        await foto('1440-busqueda-sin-resultados');
        await pagina.route('**/pedidos-unidades/catalogo**', ruta => ruta.fulfill({ status: 503, body: '{}' }));
        await pagina.locator('[data-buscar-cliente]').fill('falla controlada');
        await pagina.getByText('No se pudo consultar. Revisa la conexión y tu sesión, y vuelve a intentar.', { exact: true }).waitFor();
        await foto('1440-busqueda-error-controlado');
        await pagina.unroute('**/pedidos-unidades/catalogo**');
        const esperado = errores.findIndex(e => e === 'HTTP 503: /pedidos-unidades/catalogo');
        if (esperado !== -1) errores.splice(esperado, 1);
    });
    await comprobar('Impresión real de dos notas diferentes', async () => {
        await ir(`/pedidos/imprimir-dos?pedido_1=${datos.parcial}&pedido_2=${datos.impresion}`);
        assert.equal(await pagina.locator('.a4-hoja').count(), 1);
        assert.equal(await pagina.locator('.nota').count(), 2);
        await foto('1440-impresion-dos-notas');
        await pagina.emulateMedia({ media: 'print' });
        await pagina.pdf({ path: path.join(salida, 'notas.pdf'), preferCSSPageSize: true, printBackground: true, displayHeaderFooter: false });
        await pagina.emulateMedia({ media: 'screen' });
    });
    await comprobar('Retiro real 54 → 32, validación de límites y confirmación', async () => {
        await ir(`/pedidos/${datos.parcial}`);
        await pagina.locator('.retiro-faltantes summary').click();
        const formulario = pagina.locator('[data-retiro-faltantes]');
        const cantidad = formulario.locator('input[type=number]');
        await cantidad.fill('23');
        assert.equal(await cantidad.evaluate(e => e.checkValidity()), false);
        await cantidad.fill('22');
        await formulario.locator('[name=motivo]').fill('no');
        await formulario.locator('input[type=checkbox][required]').check();
        assert.equal(await formulario.evaluate(e => e.checkValidity()), false);
        await formulario.locator('[name=motivo]').fill('Ensayo ficticio: el cliente ya no desea las veintidós unidades pendientes.');
        await formulario.getByRole('button', { name: 'Confirmar retiro de faltantes' }).click();
        await pagina.waitForLoadState('networkidle');
        assert.equal(await pagina.locator('[data-retiro-faltantes]').count(), 0);
        assert.match(await pagina.locator('body').innerText(), /retir|vigente/i);
        await foto('1440-retiro-confirmado');
    });
    await navegador.close();
    if (errores.length) resultados.push({ caso: 'Errores inesperados de navegador o servidor', estado: 'FALLO', detalle: errores });
    const informe = { commit: process.env.GITHUB_SHA, rama_pr: process.env.PR_HEAD_SHA, motor: 'Chromium / Playwright 1.64.1',
        base: 'SQLite temporal exclusivamente ficticia; conexiones MySQL bloqueadas', resultados, capturas,
        limitaciones: ['No es UAT con trabajadores.', 'Las capturas y el PDF requieren revisión humana de legibilidad.', 'No prueba otros motores de navegador ni impresoras físicas.'] };
    fs.writeFileSync(path.join(salida, 'resultados.json'), JSON.stringify(informe, null, 2));
    const escapar = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    fs.writeFileSync(path.join(salida, 'index.html'), `<!doctype html><html lang="es"><meta charset="utf-8"><title>Evidencias ficticias PR49</title><style>body{font-family:system-ui;margin:2rem}img{max-width:100%;border:1px solid #aaa}section{margin:3rem 0}li{margin:.5rem 0}</style><h1>PR49 — Evidencias con datos ficticios</h1><p>Commit PR: ${escapar(process.env.PR_HEAD_SHA)}</p><p><a href="notas.pdf">PDF A4 horizontal</a> · <a href="resultados.json">Resultados verificables</a> · <a href="retiro-verificado.json">Cantidades verificadas</a></p><section><h2>Vista previa del PDF</h2><img src="notas-a4-horizontal.png" alt="Dos notas independientes en A4 horizontal"></section><ul>${resultados.map(r => `<li>${escapar(r.estado)} — ${escapar(r.caso)} ${escapar(r.detalle || '')}</li>`).join('')}</ul>${capturas.map(c => `<section><h2>${escapar(c)}</h2><a href="${c}"><img loading="lazy" src="${c}" alt="${escapar(c)}"></a></section>`).join('')}</html>`);
    console.log(JSON.stringify(resultados, null, 2));
    if (resultados.some(r => r.estado !== 'APROBADO')) process.exitCode = 1;
})().catch(error => {
    fs.writeFileSync(path.join(salida, 'error.txt'), error.stack);
    console.error(error.message);
    process.exit(1);
});
