const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

if (process.env.GITHUB_ACTIONS !== 'true' || process.env.RUNNER_ENVIRONMENT !== 'github-hosted' || process.platform !== 'linux') {
    throw new Error('El navegador solo puede ejecutarse en GitHub Actions temporal.');
}
const temporal = fs.realpathSync(process.env.RUNNER_TEMP);
assert.equal(process.env.VISUAL_DATABASE, path.join(temporal, 'pr49-visual.sqlite'));
const motores = require(path.join(temporal, 'navegador/node_modules/playwright'));
const datos = JSON.parse(fs.readFileSync(path.join(temporal, 'visual-fixture.json'), 'utf8'));
const salida = path.join(temporal, 'evidencias-pr49');
fs.mkdirSync(salida, { recursive: false });
const resultados = [], capturas = [], errores = [], mediciones = [];
const base = 'http://127.0.0.1:8099';
let pagina;

async function foto(nombre) {
    const archivo = `${nombre}.png`;
    await pagina.evaluate(async () => { window.scrollTo(0, 0); await document.fonts.ready; });
    const muestra = !/^\d+-/.test(nombre)
        || /^1366-(01-sugerencias-clientes|02-sugerencias-productos|envasado-integrado-previa|producto-editar|dashboard|usuarios|roles|producto-nuevo|clientes|pedidos|envasado-nuevo|envasados|movimientos|auditoria|disponibilidad|lista-preparacion|preparacion-despacho-incidencias|entrega-historial-acciones|indicadores|retiro-faltantes|categorias|rol-nuevo|entrega-retorno-aprobacion|envasado-correccion|producto-detalle|seguimiento)$/.test(nombre)
        || /^390-(envasado-integrado-confirmacion|envasado-integrado-previa|04-editor-agrupaciones|disponibilidad|lista-preparacion|entrega-retorno-aprobacion|retiro-faltantes|producto-nuevo|rol-nuevo|envasado-correccion|login)$/.test(nombre)
        || /^360-(03-seleccion-y-validacion|login)$/.test(nombre)
        || /^(768-dashboard|1024-productos)$/.test(nombre);
    if (muestra) {
        await pagina.screenshot({ path: path.join(salida, archivo), fullPage: await pagina.locator('dialog[open]').count() === 0, animations: 'disabled' });
        capturas.push(archivo);
    }
    const medidas = await pagina.evaluate(() => ({ ancho: innerWidth, cuerpo: document.body.scrollWidth, documento: document.documentElement.scrollWidth }));
    if (Math.max(medidas.cuerpo, medidas.documento) > medidas.ancho + 1) {
        const diagnostico = await pagina.evaluate(() => [...document.querySelectorAll('body *')].filter(e => {
            const r = e.getBoundingClientRect(); return r.right > innerWidth + 1 && r.width > 0;
        }).slice(-25).map(e => ({ etiqueta: e.tagName, clase: e.className, rect: e.getBoundingClientRect().toJSON(),
            posicion: getComputedStyle(e).position, ancho: getComputedStyle(e).width, desbordamiento: getComputedStyle(e).overflowX })));
        fs.writeFileSync(path.join(salida, `${nombre}-desbordamiento.json`), JSON.stringify(diagnostico, null, 2));
    }
    const desbordamiento = Math.max(medidas.cuerpo, medidas.documento) > medidas.ancho + 1;
    mediciones.push({ captura: nombre, ...medidas });
    assert.equal(desbordamiento, false, `Desbordamiento horizontal en ${nombre}: ${JSON.stringify(medidas)}`);
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
    const motor = process.env.AUDIT_BROWSER || 'chromium';
    assert.ok(['chromium', 'firefox'].includes(motor));
    const navegador = await motores[motor].launch();
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
    await comprobar('Navegación móvil y foco con teclado', async () => {
        await pagina.setViewportSize({ width: 390, height: 950 });
        await pagina.locator('.menu-toggle').click();
        assert.equal(await pagina.locator('.menu-toggle').getAttribute('aria-expanded'), 'true');
        assert.equal(await pagina.locator('.app-content').evaluate(e => e.inert), true);
        await pagina.keyboard.press('Escape');
        assert.equal(await pagina.locator('.menu-toggle').getAttribute('aria-expanded'), 'false');
        assert.equal(await pagina.locator('.menu-toggle').evaluate(e => document.activeElement === e), true);
        await pagina.locator('.menu-toggle').click();
        await pagina.locator('.sidebar-overlay').click({ position: { x: 370, y: 300 } });
        assert.equal(await pagina.locator('.menu-toggle').getAttribute('aria-expanded'), 'false');
        if (process.env.AUDIT_ETAPA === 'despues') {
            await pagina.locator('.skip-link').focus();
            await pagina.keyboard.press('Enter');
            assert.equal(await pagina.locator('#contenido-principal').evaluate(e => document.activeElement === e), true);
        }
    });
    for (const ancho of [360, 390, 768, 1024, 1366]) {
        await pagina.setViewportSize({ width: ancho, height: 950 });
        await comprobar(`Buscadores, variante, presentación, validación y agrupaciones — ${ancho}px`, async () => {
            await ir('/pedidos/nuevo');
            assert.equal(await pagina.locator('[data-campo-variante]').isVisible(), false);
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
        await comprobar(`Foco del error de pedido — ${ancho}px`, async () => {
            await ir('/pedidos/nuevo');
            await pagina.locator('[data-pedido-unidades]').evaluate(form => { form.noValidate = true; form.requestSubmit(); });
            const foco = await pagina.locator('[data-mensaje]').evaluate(e => document.activeElement === e);
            assert.equal(foco, true);
            await foto(`${ancho}-foco-error-${process.env.AUDIT_ETAPA}`);
        });
        await comprobar(`Búsqueda de pedido fuera de los primeros veinte — ${ancho}px`, async () => {
            await ir('/inventario');
            const buscador = pagina.locator('[name=pedido_buscar]');
                await buscador.fill('jose nunez');
                await pagina.getByRole('button', { name: 'Consultar', exact: true }).click();
                await pagina.waitForLoadState('networkidle');
                await pagina.locator(`#pedido_id option[value="${datos.preparacion}"]`).waitFor({ state: 'attached' });
                await pagina.locator('#pedido_id').selectOption(String(datos.preparacion));
                await pagina.getByRole('button', { name: 'Consultar', exact: true }).click();
                await pagina.waitForLoadState('networkidle');
                assert.match(await pagina.locator('body').innerText(), /Pendiente activo/i);
                assert.equal(new URL(pagina.url()).searchParams.get('pedido_id'), String(datos.preparacion));
                assert.equal(await pagina.locator('.disponibilidad-tabla tbody tr').first().locator('[data-label="Pendiente activo"] span').innerText(), '24');
            await foto(`${ancho}-busqueda-pedido-${process.env.AUDIT_ETAPA}`);
        });
        await comprobar(`Error de validación sin duplicados — ${ancho}px`, async () => {
            await ir('/pedidos/nuevo');
            await pagina.locator('[data-buscar-producto]').fill('quitasarro');
            await pagina.locator('#coincidencias-productos [role=option]').first().click();
            await pagina.waitForFunction(() => document.querySelector('[data-variantes]').options.length === 3);
            await pagina.locator('[data-variantes]').selectOption({ label: 'Limón' });
            await pagina.locator('[data-skus]').selectOption(await pagina.locator('[data-skus] option').nth(1).getAttribute('value'));
            await pagina.locator('[data-cantidad-nueva]').fill('1');
            await pagina.locator('[data-agregar-linea]').click();
            await pagina.locator('[data-pedido-unidades]').evaluate(form => { form.noValidate = true; });
            await pagina.getByRole('button', { name: 'Guardar pedido por unidades', exact: true }).click();
            await pagina.waitForLoadState('networkidle');
            const erroresGlobales = await pagina.locator('.app-alert.error li').allTextContents();
            assert.ok(erroresGlobales.length > 0, 'Se esperaba una validación del cliente no seleccionado');
            const texto = await pagina.locator('body').innerText();
            for (const error of erroresGlobales) {
                assert.equal(texto.split(error.trim()).length - 1, 1);
            }
            await foto(`${ancho}-validacion-${process.env.AUDIT_ETAPA}`);
        });
        await comprobar(`Editor de variantes y presentaciones — ${ancho}px`, async () => {
            await ir('/productos/nuevo');
            const variantes = await pagina.locator('[data-variant-group]').count();
            await pagina.locator('[data-add-variant]').click();
            assert.equal(await pagina.locator('[data-variant-group]').count(), variantes + 1);
            const presentaciones = await pagina.locator('[data-presentation-list] > .presentation-card').count();
            await pagina.locator('[data-add-presentation]').click();
            assert.equal(await pagina.locator('[data-presentation-list] > .presentation-card').count(), presentaciones + 1);
        });
        await comprobar(`Proporción del checkbox y controles — ${ancho}px`, async () => {
            await ir('/pedidos/nuevo');
            const medidas = await pagina.locator('[data-empaquetar]').evaluate(e => ({ alto: e.getBoundingClientRect().height, ancho: e.getBoundingClientRect().width }));
            mediciones.push({ control: 'Agrupaciones opcionales', viewport: ancho, ...medidas });
            if (process.env.AUDIT_ETAPA === 'despues') {
                assert.ok(medidas.alto >= 18 && medidas.alto <= 24 && medidas.ancho >= 18 && medidas.ancho <= 24);
            }
        });
        const pantallas = [
            ['dashboard', '/dashboard'], ['usuarios', '/usuarios'], ['usuario-nuevo', '/usuarios/nuevo'], ['roles', '/roles-permisos'], ['rol-nuevo', '/roles-permisos/nuevo'], ['permiso-nuevo', '/permisos/nuevo'],
            ['categorias', '/categorias-productos'], ['categoria-nueva', '/categorias-productos/nueva'],
            ['producto-detalle', `/productos/${datos.producto}`], ['producto-editar', `/productos/${datos.producto}/editar`],
            ['cliente-detalle', `/clientes/${datos.cliente}`], ['cliente-editar', `/clientes/${datos.cliente}/editar`],
            ['stock-alertas', '/inventario/stock'], ['movimiento-nuevo', '/inventario/movimientos/nuevo'], ['seguimiento-listado', '/seguimiento'], ['seguimiento-historial', '/seguimiento/historial'],
            ['entrega-retorno-aprobacion', `/cumplimiento/${datos.rechazo}`], ['envasado-correccion', `/envasados/${datos.envasado}`], ['materiales-configuracion', `/presentaciones/${datos.sku}/materiales-envasado`],
            ['productos', '/productos'], ['producto-nuevo', '/productos/nuevo'], ['clientes', '/clientes'], ['cliente-nuevo', '/clientes/nuevo'],
            ['pedidos', '/pedidos'], ['envasado-nuevo', '/envasados/nuevo'], ['envasados', '/envasados'], ['movimientos', '/inventario/movimientos'], ['auditoria', '/auditoria'],
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
                if (nombre === 'disponibilidad' || nombre === 'materiales-historicos') {
                    const botones = await pagina.locator('.filters .s3-actions .btn').evaluateAll(elementos => elementos.map(e => ({ texto: e.textContent.trim(), alto: e.getBoundingClientRect().height, arriba: e.getBoundingClientRect().top, abajo: e.getBoundingClientRect().bottom })));
                    assert.equal(botones.length, 2);
                    assert.ok(botones.every(b => b.alto >= 44 && b.alto <= 64), `Altura inadecuada de Consultar/Limpiar: ${JSON.stringify(botones)}`);
                    if (ancho <= 650) assert.ok(botones[1].arriba - botones[0].abajo >= 8, 'Falta separación entre Consultar y Limpiar');
                    const tarjetas = await pagina.locator('.disponibilidad-tabla').evaluateAll(tablas => tablas.map(t => ({ presentacion: getComputedStyle(t).display,
                        etiquetas: [...t.querySelectorAll('tbody tr:first-child td[data-label]')].map(c => ({ etiqueta: c.dataset.label, valor: c.innerText.trim(),
                            etiquetaVisible: getComputedStyle(c, '::before').content, izquierda: c.getBoundingClientRect().left, derecha: c.getBoundingClientRect().right })),
                        anchoContenedor: t.parentElement.clientWidth, contenidoContenedor: t.parentElement.scrollWidth })));
                    for (const tabla of tarjetas) {
                        assert.equal(tabla.presentacion, ancho <= 650 ? 'block' : 'table');
                        if (ancho <= 650) {
                            assert.ok(tabla.contenidoContenedor <= tabla.anchoContenedor + 1, 'La tarjeta obliga a desplazarse horizontalmente');
                            assert.ok(tabla.etiquetas.every(c => c.valor && c.etiquetaVisible === `"${c.etiqueta}"` && c.izquierda >= 0 && c.derecha <= ancho + 1), 'Faltan etiquetas o valores visibles en las tarjetas');
                        }
                    }
                    if (nombre === 'disponibilidad') {
                        const valores = Object.fromEntries(tarjetas[0].etiquetas.map(c => [c.etiqueta, c.valor]));
                        for (const [etiqueta, valor] of Object.entries({ 'Pendiente total': '24', 'En espera': '0', 'Pendiente activo': '24', 'Stock terminado registrado': '0', 'Faltante activo / total': '24 / 24' })) assert.equal(valores[etiqueta], valor);
                    }
                    await pagina.emulateMedia({ media: 'print' });
                    assert.ok((await pagina.locator('.disponibilidad-tabla').evaluateAll(tablas => tablas.map(t => getComputedStyle(t).display))).every(d => d === 'table'), 'La impresión debe conservar las tablas');
                    await pagina.emulateMedia({ media: 'screen' });
                    mediciones.push({ pantalla: nombre, ancho, botones, tablas: tarjetas });
                }
                if (nombre === 'entrega-historial-acciones' || nombre === 'entrega-retorno-aprobacion') {
                    await pagina.locator('.entrega-panel details').evaluateAll(detalles => detalles.forEach(d => { d.open = true; }));
                }
                if (nombre === 'preparacion-despacho-incidencias') {
                    const separaciones = await pagina.locator('.cumplimiento-tarjeta > form + [data-registrar-envasado]').evaluateAll(botones => botones.map(b => b.getBoundingClientRect().top - b.previousElementSibling.getBoundingClientRect().bottom));
                    assert.ok(separaciones.length > 0 && separaciones.every(distancia => distancia >= 8), 'Los botones de preparación y envasado necesitan separación visual');
                }
                if (process.env.AUDIT_ETAPA === 'despues') {
                    const contraste = await pagina.locator('.page .btn-primary, .page .form-label').evaluateAll(elementos => {
                        const rgb = s => (s.match(/[\d.]+/g) || []).map(Number);
                        const luz = c => c.slice(0, 3).map(v => v / 255).map(v => v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4).reduce((s, v, i) => s + v * [.2126, .7152, .0722][i], 0);
                        return elementos.filter(e => e.getClientRects().length && !e.disabled).map(e => {
                            let padre = e, fondo;
                            do { fondo = rgb(getComputedStyle(padre).backgroundColor); padre = padre.parentElement; } while (padre && (fondo[3] ?? 1) === 0);
                            if ((fondo[3] ?? 1) === 0) fondo = [255, 255, 255];
                            const colores = [luz(rgb(getComputedStyle(e).color)), luz(fondo)].sort((a, b) => a - b);
                            return { texto: e.textContent.trim(), razon: (colores[1] + .05) / (colores[0] + .05) };
                        });
                    });
                    assert.ok(contraste.every(c => c.razon >= 4.5), `Contraste insuficiente: ${JSON.stringify(contraste.filter(c => c.razon < 4.5))}`);
                    mediciones.push({ pantalla: nombre, ancho, contraste });
                }
                await foto(`${ancho}-${nombre}`);
            });
        }
        await comprobar(`Formulario de retiro — ${ancho}px`, async () => {
            await ir(`/pedidos/${datos.parcial}`);
            const historial = pagina.locator('section').filter({ has: pagina.getByRole('heading', { name: 'Historial de estados', exact: true }) }).first();
            const estados = await historial.locator('tbody td:nth-child(2), tbody td:nth-child(3)').allTextContents();
            assert.ok(estados.some(e => e.trim() === 'En preparación') && estados.some(e => e.trim() === 'Despacho parcial'), 'Faltan etiquetas legibles de estados');
            assert.ok(estados.every(e => !e.includes('_')), 'El historial muestra códigos internos');
            await pagina.locator('.retiro-faltantes summary').click();
            await pagina.locator('[data-retiro-faltantes] input[type=number]').fill('22');
            await foto(`${ancho}-retiro-faltantes`);
        });
    }
    await comprobar('Envasado integrado real sin consumo de materiales', async () => {
        await pagina.setViewportSize({ width: 390, height: 950 });
        await ir('/cumplimiento/lista-preparacion');
        await pagina.locator('[data-registrar-envasado]').first().click();
        const dialogo = pagina.locator('dialog[open]');
        await dialogo.locator('[name=cantidad]').fill('2');
        await dialogo.getByRole('button', { name: 'Revisar unidades antes de confirmar', exact: true }).click();
        const confirmacion = dialogo.locator('[data-confirmacion-envasado]');
        await confirmacion.waitFor();
        assert.match(await confirmacion.innerText(), /No se registran consumos ni movimientos de insumos/);
        const checkbox = confirmacion.locator('input[type=checkbox][required]');
        if (process.env.AUDIT_ETAPA === 'despues') assert.ok(await checkbox.evaluate(e => e.getBoundingClientRect().height <= 24));
        const limites = await dialogo.evaluate(e => ({ arriba: e.getBoundingClientRect().top, abajo: e.getBoundingClientRect().bottom, alto: innerHeight }));
        assert.ok(limites.arriba >= 0 && limites.abajo <= limites.alto + 1, 'El diálogo debe permanecer dentro de la pantalla móvil');
        const interior = await dialogo.evaluate(e => ({ visible: e.clientWidth, contenido: e.scrollWidth }));
        mediciones.push({ dialogo: 'envasado', ancho: 390, ...interior });
        if (process.env.AUDIT_ETAPA === 'despues') assert.ok(interior.contenido <= interior.visible + 1, 'El SKU no debe desbordar el interior del diálogo');
        await dialogo.evaluate(e => { e.scrollTop = 0; });
        await foto('390-envasado-integrado-previa');
        await confirmacion.scrollIntoViewIfNeeded();
        await foto('390-envasado-integrado-confirmacion');
        await pagina.setViewportSize({ width: 1366, height: 950 });
        await dialogo.evaluate(e => { e.scrollTop = 0; });
        await foto('1366-envasado-integrado-previa');
        await confirmacion.locator('input[type=checkbox][required]').check();
        await confirmacion.getByRole('button', { name: 'Confirmar unidades terminadas', exact: true }).click();
        await pagina.waitForLoadState('networkidle');
        const textoConfirmacion = 'Unidades terminadas registradas y stock actualizado; no registres otra entrada por esta producción.';
        assert.ok((await pagina.locator('.app-alert.success').innerText()).includes(textoConfirmacion));
        assert.equal((await pagina.locator('body').innerText()).split(textoConfirmacion).length - 1, 1);
        await foto('1366-envasado-integrado-confirmado');
    });
    await comprobar('Paginación, sin resultados y mensaje de error de búsqueda', async () => {
        await ir('/pedidos/nuevo');
        await pagina.locator('[data-buscar-cliente]').fill('Cliente ficticio');
        await pagina.locator('#coincidencias-clientes [role=option]').nth(19).waitFor();
        const primeraPagina = await pagina.locator('#coincidencias-clientes [role=option]').count();
        await pagina.locator('[data-mas-clientes]').click();
        await pagina.waitForFunction(n => document.querySelectorAll('#coincidencias-clientes [role=option]').length > n, primeraPagina);
        await foto('1366-busqueda-paginada');
        await pagina.locator('[data-buscar-cliente]').fill('ZZZ-no-existe-ficticio');
        await pagina.getByText('No se encontraron coincidencias.', { exact: true }).waitFor();
        await foto('1366-busqueda-sin-resultados');
        await pagina.route('**/pedidos-unidades/catalogo**', ruta => ruta.fulfill({ status: 503, body: '{}' }));
        await pagina.locator('[data-buscar-cliente]').fill('falla controlada');
        await pagina.getByText('No se pudo consultar. Revisa la conexión y tu sesión, y vuelve a intentar.', { exact: true }).waitFor();
        await foto('1366-busqueda-error-controlado');
        await pagina.unroute('**/pedidos-unidades/catalogo**');
        const esperado = errores.findIndex(e => e === 'HTTP 503: /pedidos-unidades/catalogo');
        if (esperado !== -1) errores.splice(esperado, 1);
    });
    await comprobar('Impresión real de dos notas diferentes', async () => {
        await ir(`/pedidos/imprimir-dos?pedido_1=${datos.parcial}&pedido_2=${datos.impresion}`);
        assert.equal(await pagina.locator('.a4-hoja').count(), 1);
        assert.equal(await pagina.locator('.nota').count(), 2);
        await foto('1366-impresion-dos-notas');
        await pagina.emulateMedia({ media: 'print' });
        if (motor === 'chromium') await pagina.pdf({ path: path.join(salida, 'notas.pdf'), preferCSSPageSize: true, printBackground: true, displayHeaderFooter: false });
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
        await foto('1366-retiro-confirmado');
    });
    for (const [rol, correo] of [['Operaciones Comerciales', 'comercial.visual@example.test'], ['Producción/Reparto', 'produccion.visual@example.test']]) {
        await comprobar(`Permisos operativos reales en navegador — ${rol}`, async () => {
            await ir('/dashboard');
            await pagina.locator('form[action$="/logout"] button').click();
            await pagina.waitForURL('**/login');
            await pagina.locator('[name=email]').fill(correo);
            await pagina.locator('[name=password]').fill(process.env.VISUAL_PASSWORD);
            await pagina.locator('button[type=submit]').click();
            await pagina.waitForURL('**/dashboard');
            if (rol === 'Operaciones Comerciales') {
                assert.equal((await pagina.request.get(base + '/envasados/nuevo')).status(), 403);
                await ir('/envasados');
            } else {
                await ir('/envasados/nuevo');
            }
            assert.equal((await pagina.request.get(base + '/roles-permisos')).status(), 403);
            if (rol === 'Producción/Reparto') {
                assert.equal((await pagina.request.get(base + '/indicadores')).status(), 403);
            }
            await foto(`1366-rol-${rol === 'Producción/Reparto' ? 'produccion' : 'comercial'}`);
        });
    }
    await pagina.locator('form[action$="/logout"] button').click();
    await pagina.waitForURL('**/login');
    for (const ancho of [360, 390, 768, 1024, 1366]) {
        await comprobar(`Login y controles — ${ancho}px`, async () => {
            await pagina.setViewportSize({ width: ancho, height: 950 });
            await pagina.locator('#password').fill('ClaveFicticiaSoloVisual');
            await pagina.locator('#password-toggle').click();
            assert.equal(await pagina.locator('#password').getAttribute('type'), 'text');
            await pagina.locator('#password-toggle').click();
            assert.equal(await pagina.locator('#password').getAttribute('type'), 'password');
            await foto(`${ancho}-login`);
        });
    }
    await navegador.close();
    if (errores.length) resultados.push({ caso: 'Errores inesperados de navegador o servidor', estado: 'FALLO', detalle: errores });
    const informe = { commit: process.env.GITHUB_SHA, rama_pr: process.env.PR_HEAD_SHA, motor: `${motor} / Playwright 1.64.1`, etapa: process.env.AUDIT_ETAPA,
        base: 'SQLite temporal exclusivamente ficticia; conexiones MySQL bloqueadas', resultados, capturas, mediciones,
        limitaciones: ['No es UAT con trabajadores.', 'Las capturas y el PDF requieren revisión humana de legibilidad.', 'Chrome y Edge de marca, impresoras físicas y UAT: PENDIENTE DE EJECUCIÓN.'] };
    fs.writeFileSync(path.join(salida, 'resultados.json'), JSON.stringify(informe, null, 2));
    const escapar = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    fs.writeFileSync(path.join(salida, 'index.html'), `<!doctype html><html lang="es"><meta charset="utf-8"><title>Auditoría visual ficticia</title><style>body{font-family:system-ui;margin:2rem}img{max-width:100%;border:1px solid #aaa}section{margin:3rem 0}li{margin:.5rem 0}</style><h1>Auditoría — Evidencias con datos ficticios</h1><p>Commit PR: ${escapar(process.env.PR_HEAD_SHA)}</p><p>${motor === 'chromium' ? '<a href="notas.pdf">PDF A4 horizontal</a>' : 'PDF disponible en el artefacto Chromium'} · <a href="resultados.json">Resultados verificables</a> · <a href="retiro-verificado.json">Cantidades verificadas</a></p>${motor === 'chromium' ? '<section><h2>Vista previa del PDF</h2><img src="notas-a4-horizontal.png" alt="Dos notas independientes en A4 horizontal"></section>' : ''}<ul>${resultados.map(r => `<li>${escapar(r.estado)} — ${escapar(r.caso)} ${escapar(r.detalle || '')}</li>`).join('')}</ul>${capturas.map(c => `<section><h2>${escapar(c)}</h2><a href="${c}"><img loading="lazy" src="${c}" alt="${escapar(c)}"></a></section>`).join('')}</html>`);
    console.log(JSON.stringify(resultados, null, 2));
    if (resultados.some(r => r.estado !== 'APROBADO')) process.exitCode = 1;
})().catch(error => {
    fs.writeFileSync(path.join(salida, 'error.txt'), error.stack);
    console.error(error.message);
    process.exit(1);
});
