(() => {
    'use strict';
    const util = window.SurtidosUtilidades;
    function elemento(etiqueta, texto, clase) {
        const e = document.createElement(etiqueta);
        if (texto !== undefined) e.textContent = texto;
        if (clase) e.className = clase;
        return e;
    }
    function boton(texto, accion) {
        const b = elemento('button', texto, 'btn btn-secondary'); b.type = 'button'; b.addEventListener('click', accion); return b;
    }
    function editor(raiz, oculto, skus, tamano, cantidad) {
        let paquetes;
        try { paquetes = JSON.parse(oculto.value || '[]').map(util.copiar); } catch { paquetes = []; }
        let actual = 0;
        const controles = elemento('div', undefined, 'surtidos-controles');
        const numero = elemento('input'); numero.type = 'number'; numero.min = '1'; numero.value = '1'; numero.className = 'form-control'; numero.setAttribute('aria-label', 'Número de paquete a editar');
        const copia = elemento('input'); copia.type = 'number'; copia.min = '1'; copia.value = '1'; copia.className = 'form-control'; copia.setAttribute('aria-label', 'Número de paquete de origen para copiar');
        const estado = elemento('select', undefined, 'form-control'); estado.add(new Option('Composición pendiente', 'pendiente')); estado.add(new Option('Composición definida', 'definida'));
        estado.setAttribute('aria-label', 'Estado de la composición del paquete');
        const filas = elemento('div'), resumen = elemento('p', undefined, 's2-note'); resumen.setAttribute('aria-live', 'polite');
        const titulo = elemento('strong');
        function ajustar() {
            const q = Number(cantidad());
            if (!Number.isInteger(q) || q < 1 || q > 10000) return;
            while (paquetes.length < q) paquetes.push({ estado: 'pendiente', componentes: [] });
            if (paquetes.length > q) paquetes.length = q;
            actual = Math.max(0, Math.min(actual, q - 1)); numero.max = copia.max = String(q); numero.value = String(actual + 1);
        }
        function guardar() {
            ajustar();
            oculto.value = JSON.stringify(paquetes);
            const errores = paquetes.map(p => util.validar(p, tamano, skus.map(s => Number(s.id))));
            numero.setCustomValidity(errores.find(Boolean) || '');
            const pendientes = paquetes.filter(p => p.estado !== 'definida').length;
            const suma = (paquetes[actual]?.componentes || []).reduce((s, c) => s + (Number(c.cantidad) || 0), 0);
            resumen.textContent = `Paquete ${actual + 1}: ${suma} de ${tamano} unidades. ${pendientes} paquete(s) pendientes de definir. ${errores[actual] || ''}`;
            document.dispatchEvent(new CustomEvent('pedido:surtido-cambio'));
        }
        function pintar() {
            ajustar(); titulo.textContent = `Paquete ${actual + 1} de ${paquetes.length}`; estado.value = paquetes[actual]?.estado || 'pendiente'; filas.replaceChildren();
            (paquetes[actual]?.componentes || []).forEach((c, indice) => {
                const fila = elemento('div', undefined, 'surtido-componente');
                const selector = elemento('select', undefined, 'form-control'); selector.add(new Option('Selecciona un aroma / SKU', ''));
                skus.forEach(s => selector.add(new Option(`${s.nombre} · ${s.sku}`, String(s.id)))); selector.value = String(c.presentacion_producto_id || ''); selector.setAttribute('aria-label', 'Aroma y SKU del componente');
                const unidades = elemento('input'); unidades.type = 'number'; unidades.min = '1'; unidades.max = String(tamano); unidades.step = '1'; unidades.value = c.cantidad || ''; unidades.className = 'form-control'; unidades.setAttribute('aria-label', 'Unidades de este aroma');
                selector.addEventListener('change', () => { c.presentacion_producto_id = Number(selector.value); guardar(); });
                unidades.addEventListener('input', () => { c.cantidad = Number(unidades.value); guardar(); });
                fila.append(selector, unidades, boton('Quitar aroma', () => { paquetes[actual].componentes.splice(indice, 1); pintar(); })); filas.append(fila);
            }); guardar();
        }
        numero.addEventListener('change', () => { actual = Number(numero.value) - 1; pintar(); });
        estado.addEventListener('change', () => { paquetes[actual].estado = estado.value; guardar(); });
        controles.append(elemento('span', 'Editar paquete'), numero, elemento('span', 'Copiar desde'), copia,
            boton('Copiar composición', () => {
                const origen = Number(copia.value) - 1;
                if (!Number.isInteger(origen) || origen < 0 || origen >= paquetes.length) return;
                paquetes[actual] = util.copiar(paquetes[origen]); pintar();
            }));
        raiz.append(titulo, controles, estado, filas, boton('Añadir aroma', () => { paquetes[actual].componentes.push({ presentacion_producto_id: '', cantidad: '' }); pintar(); }), resumen);
        pintar();
        return { actualizar: pintar };
    }
    window.EditorSurtidos = editor;
    document.querySelectorAll('[data-editor-surtido]').forEach(raiz => {
        const oculto = document.getElementById(raiz.dataset.input);
        editor(raiz, oculto, JSON.parse(raiz.dataset.skus), Number(raiz.dataset.tamano), () => Number(raiz.dataset.cantidad));
    });
    const config = window.configuracionSprint2;
    if (!config) return;
    const contenedor = document.getElementById('filas-pedido');
    let indice = 100000;
    function agregar(datos) {
        const f = (config.formatosSurtidos || []).find(f => Number(f.id) === Number(datos.formato_surtido_id));
        if (!f || contenedor.children.length >= 18) return;
        const prefijo = `detalles[${indice++}]`, fila = elemento('div', undefined, 'form-section s2-surtido-row');
        function ocultar(campo, valor) { const e = elemento('input'); e.type = 'hidden'; e.name = `${prefijo}[${campo}]`; e.value = valor; fila.append(e); return e; }
        ocultar('tipo_linea', 'surtido'); ocultar('formato_surtido_id', f.id); ocultar('modalidad', 'empaque'); if (datos.id) ocultar('id', datos.id);
        const tamano = Number(datos.unidades_por_empaque || f.unidades_por_empaque), precio = Number(datos.precio_unitario ?? f.precio_empaque);
        ocultar('unidades_por_empaque', tamano);
        fila.append(elemento('h3', `${f.nombre} · Surtido de ${tamano}`));
        const cantidad = elemento('input'); cantidad.type = 'number'; cantidad.name = `${prefijo}[cantidad]`; cantidad.min = '1'; cantidad.max = '10000'; cantidad.step = '1'; cantidad.value = datos.cantidad || 1; cantidad.className = 'form-control'; cantidad.setAttribute('aria-label', 'Cantidad de paquetes surtidos');
        const label = elemento('label', 'Cantidad de paquetes'); label.append(cantidad); fila.append(label);
        const importe = elemento('p', undefined, 's2-note'); fila.append(importe);
        const oculto = ocultar('paquetes_json', datos.paquetes_json || '[]'), raiz = elemento('div'); fila.append(raiz);
        const instancia = editor(raiz, oculto, f.skus, tamano, () => Number(cantidad.value));
        function recalcular() { const centimos = Math.round(precio * 100) * Number(cantidad.value); fila.dataset.importeCentimos = String(centimos || 0); importe.textContent = `Precio por paquete S/ ${precio.toFixed(2)} · Importe S/ ${(centimos / 100).toFixed(2)}. Los aromas no agregan otro importe.`; cantidad.setCustomValidity(centimos > 99999999 ? 'El importe de la línea supera el máximo permitido.' : ''); }
        cantidad.addEventListener('input', () => { recalcular(); instancia.actualizar(); });
        fila.append(boton('Eliminar línea surtida', () => { fila.remove(); document.dispatchEvent(new CustomEvent('pedido:surtido-cambio')); }));
        contenedor.append(fila); recalcular(); document.dispatchEvent(new CustomEvent('pedido:surtido-cambio'));
    }
    window.PedidosSurtidos = { agregar };
    document.getElementById('agregar-surtido')?.addEventListener('click', () => agregar({ formato_surtido_id: document.getElementById('formato-surtido').value, cantidad: 1 }));
})();
