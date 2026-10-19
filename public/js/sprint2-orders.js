(() => {
    'use strict';
    const config = window.configuracionSprint2;
    if (!config) return;
    const presentaciones = config.presentaciones || [];
    const contenedor = document.getElementById('filas-pedido');
    const plantilla = document.getElementById('plantilla-fila');
    const totalElemento = document.getElementById('s2-total');
    let indice = 0;

    const fechaPedido = document.getElementById('fecha_pedido');
    const fechaEntrega = document.getElementById('fecha_entrega');
    function limitarEntrega() {
        fechaEntrega.min = fechaPedido.value;
        const limite = new Date(`${fechaPedido.value}T12:00:00Z`);
        if (Number.isNaN(limite.getTime())) {
            fechaEntrega.removeAttribute('max');
            return;
        }
        limite.setUTCDate(limite.getUTCDate() + 90);
        fechaEntrega.max = limite.toISOString().slice(0, 10);
    }
    fechaPedido.addEventListener('change', limitarEntrega);
    limitarEntrega();

    function recalcular() {
        let totalCentimos = 0;
        contenedor.querySelectorAll('.s2-product-row').forEach(fila => {
            const producto = presentaciones.find(p => String(p.id) === fila.querySelector('.js-presentacion').value);
            const modalidad = fila.querySelector('.js-modalidad').value;
            const cantidad = Number(fila.querySelector('.js-cantidad').value);
            const precio = producto && (modalidad === 'empaque' ? producto.precio_empaque : producto.precio_unidad);
            const centimos = precio === null || precio === undefined || precio === '' ? NaN : Math.round(Number(precio) * 100);
            const importe = Number.isInteger(cantidad) && cantidad > 0 && Number.isFinite(centimos) ? centimos * cantidad : 0;
            fila.querySelector('.js-precio').value = Number.isFinite(centimos) ? (centimos / 100).toFixed(2) : 'Sin precio';
            fila.querySelector('.js-importe').value = (importe / 100).toFixed(2);
            totalCentimos += importe;
        });
        totalElemento.textContent = 'S/ ' + (totalCentimos / 100).toFixed(2);
    }

    function configurarModalidad(fila, preferencia) {
        const id = fila.querySelector('.js-presentacion').value;
        const producto = presentaciones.find(p => String(p.id) === id);
        const selector = fila.querySelector('.js-modalidad');
        selector.replaceChildren();
        if (producto?.unidad) selector.add(new Option('Unidad', 'unidad'));
        if (producto?.empaque) selector.add(new Option('Empaque', 'empaque'));
        if (!selector.options.length) selector.add(new Option('Sin forma de venta', ''));
        if (Array.from(selector.options).some(option => option.value === preferencia)) selector.value = preferencia;
        recalcular();
    }

    function agregarFila(datos = {}) {
        const fragmento = plantilla.content.cloneNode(true);
        const fila = fragmento.querySelector('.s2-product-row');
        fila.querySelectorAll('[name]').forEach(elemento => elemento.name = elemento.name.replaceAll('__INDEX__', String(indice)));
        indice++;
        const selector = fila.querySelector('.js-presentacion');
        selector.add(new Option('Selecciona una presentación', ''));
        presentaciones.forEach(p => selector.add(new Option(p.nombre + ' | ' + p.sku, String(p.id))));
        selector.value = String(datos.presentacion_producto_id || '');
        fila.querySelector('.js-cantidad').value = datos.cantidad || 1;
        contenedor.appendChild(fragmento);
        const actual = contenedor.lastElementChild;
        configurarModalidad(actual, datos.modalidad || 'unidad');
        actual.querySelector('.js-presentacion').addEventListener('change', () => configurarModalidad(actual, 'unidad'));
        actual.querySelector('.js-modalidad').addEventListener('change', recalcular);
        actual.querySelector('.js-cantidad').addEventListener('input', recalcular);
        actual.querySelector('.js-eliminar').addEventListener('click', () => {
            if (contenedor.children.length <= 1) return;
            actual.remove();
            recalcular();
        });
        recalcular();
    }
    document.getElementById('agregar-fila').addEventListener('click', () => agregarFila());
    (Array.isArray(config.filas) && config.filas.length ? config.filas : [{}]).forEach(agregarFila);
})();
