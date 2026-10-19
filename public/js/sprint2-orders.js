(() => {
    'use strict';
    const config = window.configuracionSprint2;
    if (!config) return;
    const presentaciones = config.presentaciones || [];
    const contenedor = document.getElementById('filas-pedido');
    const plantilla = document.getElementById('plantilla-fila');
    const totalElemento = document.getElementById('s2-total');
    const buscar = document.getElementById('buscar-presentacion');
    const resultados = document.getElementById('resultados-presentaciones');
    const aviso = document.getElementById('aviso-buscador');
    let indice = 0;
    let opcionActiva = -1;

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
        const cantidadLineas = contenedor.children.length;
        if (cantidadLineas > 0 && cantidadLineas < 18) {
            aviso.textContent = `${cantidadLineas} de 18 líneas utilizadas.`;
        }
    }

    function configurarModalidad(fila, preferencia) {
        const id = fila.querySelector('.js-presentacion').value;
        const producto = presentaciones.find(p => String(p.id) === id);
        const selector = fila.querySelector('.js-modalidad');
        selector.replaceChildren();
        if (producto?.unidad && producto.precio_unidad !== null) selector.add(new Option('Unidad', 'unidad'));
        if (producto?.empaque && producto.precio_empaque !== null) selector.add(new Option('Empaque', 'empaque'));
        if (!selector.options.length) selector.add(new Option('Sin forma de venta', ''));
        if (Array.from(selector.options).some(option => option.value === preferencia)) selector.value = preferencia;
        recalcular();
    }

    function agregarFila(datos) {
        const producto = presentaciones.find(p => String(p.id) === String(datos.presentacion_producto_id));
        if (!producto) return null;
        const repetida = Array.from(contenedor.querySelectorAll('.s2-product-row')).find(fila =>
            fila.querySelector('.js-presentacion').value === String(producto.id) &&
            fila.querySelector('.js-modalidad').value === (datos.modalidad || 'unidad')
        );
        if (repetida) {
            const cantidadCampo = repetida.querySelector('.js-cantidad');
            const nuevaCantidad = window.BusquedaPedidos.cantidadAcumulada(cantidadCampo.value, datos.cantidad || 1);
            if (nuevaCantidad === null) {
                aviso.textContent = 'Una línea no puede superar las 10 000 unidades o empaques.';
                cantidadCampo.focus();
                return null;
            }
            cantidadCampo.value = String(nuevaCantidad);
            recalcular();
            cantidadCampo.focus();
            return repetida;
        }
        if (contenedor.children.length >= 18) {
            aviso.textContent = 'La nota física admite como máximo 18 líneas. Elimina una línea para continuar.';
            return null;
        }
        const fragmento = plantilla.content.cloneNode(true);
        const fila = fragmento.querySelector('.s2-product-row');
        fila.querySelectorAll('[name]').forEach(elemento => elemento.name = elemento.name.replaceAll('__INDEX__', String(indice)));
        indice++;
        fila.querySelector('.js-presentacion').value = producto.id;
        fila.querySelector('.js-nombre-producto').textContent = `${producto.nombre} | ${producto.sku}`;
        fila.querySelector('.js-cantidad').value = datos.cantidad || 1;
        contenedor.appendChild(fragmento);
        const actual = contenedor.lastElementChild;
        configurarModalidad(actual, datos.modalidad || 'unidad');
        let modalidadAnterior = actual.querySelector('.js-modalidad').value;
        actual.querySelector('.js-modalidad').addEventListener('change', () => {
            const existente = Array.from(contenedor.querySelectorAll('.s2-product-row')).find(fila =>
                fila !== actual &&
                fila.querySelector('.js-presentacion').value === actual.querySelector('.js-presentacion').value &&
                fila.querySelector('.js-modalidad').value === actual.querySelector('.js-modalidad').value
            );
            if (existente) {
                const suma = window.BusquedaPedidos.cantidadAcumulada(existente.querySelector('.js-cantidad').value, actual.querySelector('.js-cantidad').value);
                if (suma === null) {
                    actual.querySelector('.js-modalidad').value = modalidadAnterior;
                    aviso.textContent = 'La cantidad acumulada excede el máximo de 10 000.';
                    return;
                }
                existente.querySelector('.js-cantidad').value = String(suma);
                actual.remove();
                existente.querySelector('.js-cantidad').focus();
            } else {
                modalidadAnterior = actual.querySelector('.js-modalidad').value;
            }
            recalcular();
        });
        actual.querySelector('.js-cantidad').addEventListener('input', recalcular);
        actual.querySelector('.js-eliminar').addEventListener('click', () => {
            actual.remove();
            recalcular();
            buscar.focus();
        });
        recalcular();
        return actual;
    }

    function cerrarResultados() {
        resultados.replaceChildren();
        resultados.hidden = true;
        buscar.setAttribute('aria-expanded', 'false');
        opcionActiva = -1;
        buscar.removeAttribute('aria-activedescendant');
    }

    function elegir(presentacion, modalidad) {
        const fila = agregarFila({ presentacion_producto_id: presentacion.id, modalidad, cantidad: 1 });
        if (!fila) return;
        cerrarResultados();
        buscar.value = '';
        buscar.focus();
    }

    function mostrarResultados() {
        cerrarResultados();
        const texto = window.BusquedaPedidos.normalizar(buscar.value);
        if (!texto) return;
        const opciones = [];
        presentaciones.forEach(p => {
            if (!window.BusquedaPedidos.coincide(p, texto)) return;
            if (p.unidad && p.precio_unidad !== null) opciones.push([p, 'unidad', p.precio_unidad]);
            if (p.empaque && p.precio_empaque !== null) opciones.push([p, 'empaque', p.precio_empaque]);
        });
        opciones.slice(0, 12).forEach(([p, modalidad, precio]) => {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 's2-sugerencia';
            boton.setAttribute('role', 'option');
            boton.id = `s2-resultado-${resultados.children.length}`;
            const titulo = document.createElement('span');
            titulo.className = 's2-sugerencia-titulo';
            titulo.textContent = p.nombre;
            const detalle = document.createElement('span');
            detalle.className = 's2-sugerencia-detalle';
            detalle.textContent = `${p.sku} · ${modalidad === 'unidad' ? 'Unidad' : 'Empaque'} · S/ ${Number(precio).toFixed(2)}`;
            boton.append(titulo, detalle);
            boton.addEventListener('click', () => elegir(p, modalidad));
            resultados.append(boton);
        });
        resultados.hidden = false;
        buscar.setAttribute('aria-expanded', 'true');
        aviso.textContent = opciones.length ? `${opciones.length} coincidencias; se muestran hasta 12.` : 'No hay coincidencias.';
        if (!opciones.length) cerrarResultados();
    }

    buscar.addEventListener('input', mostrarResultados);
    buscar.addEventListener('focus', mostrarResultados);
    buscar.addEventListener('keydown', event => {
        const botones = Array.from(resultados.querySelectorAll('.s2-sugerencia'));
        if (!botones.length) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            opcionActiva = (opcionActiva + (event.key === 'ArrowDown' ? 1 : -1) + botones.length) % botones.length;
            botones.forEach((b, i) => {
                b.classList.toggle('is-active', i === opcionActiva);
                b.setAttribute('aria-selected', i === opcionActiva ? 'true' : 'false');
            });
            buscar.setAttribute('aria-activedescendant', botones[opcionActiva].id);
            botones[opcionActiva].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && !resultados.hidden) {
            event.preventDefault();
            botones[Math.max(opcionActiva, 0)].click();
        } else if (event.key === 'Escape' || event.key === 'Tab') cerrarResultados();
    });
    document.addEventListener('click', event => {
        if (!document.getElementById('busqueda-productos').contains(event.target)) cerrarResultados();
    });
    document.getElementById('agregar-fila').addEventListener('click', () => buscar.focus());
    (Array.isArray(config.filas) ? config.filas : []).forEach(agregarFila);
    document.getElementById('form-pedido').addEventListener('submit', event => {
        if (contenedor.children.length) return;
        event.preventDefault();
        aviso.textContent = 'Debes agregar al menos un producto.';
        buscar.focus();
    });
})();
