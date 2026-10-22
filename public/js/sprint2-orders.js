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

    // Cliente: reutiliza la lista de clientes activos que ya entrega Laravel.
    const clienteSelector = document.getElementById('cliente_id');
    const clienteBuscador = document.getElementById('buscar-cliente');
    const clientesResultados = document.getElementById('resultados-clientes');
    const clientes = config.clientes || [];
    if (clienteSelector && clienteBuscador && clientesResultados) {
        clienteBuscador.hidden = false;
        clienteSelector.hidden = true;
        clienteSelector.required = false;
        clienteBuscador.required = true;
        const inicial = clientes.find(c => String(c.id) === clienteSelector.value);
        clienteBuscador.value = inicial ? inicial.nombre : '';
        if (!inicial) clienteBuscador.setCustomValidity('Selecciona un cliente activo de la lista.');

        function cerrarClientes() {
            clientesResultados.hidden = true;
            clientesResultados.replaceChildren();
            clienteBuscador.setAttribute('aria-expanded', 'false');
        }

        function mostrarClientes() {
            cerrarClientes();
            const texto = window.BusquedaPedidos.normalizar(clienteBuscador.value);
            const encontrados = clientes.filter(c =>
                window.BusquedaPedidos.normalizar(`${c.nombre} ${c.documento || ''} ${c.mercado || ''}`).includes(texto)
            );
            encontrados.slice(0, 10).forEach(cliente => {
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.className = 's2-sugerencia';
                boton.setAttribute('role', 'option');
                const titulo = document.createElement('span');
                titulo.className = 's2-sugerencia-titulo';
                titulo.textContent = cliente.nombre;
                const detalle = document.createElement('span');
                detalle.className = 's2-sugerencia-detalle';
                detalle.textContent = [cliente.documento, cliente.mercado].filter(Boolean).join(' · ') || 'Sin datos adicionales';
                boton.append(titulo, detalle);
                boton.addEventListener('click', () => {
                    clienteSelector.value = String(cliente.id);
                    clienteBuscador.value = cliente.nombre;
                    clienteBuscador.setCustomValidity('');
                    cerrarClientes();
                    buscar.focus();
                });
                clientesResultados.append(boton);
            });
            clientesResultados.hidden = encontrados.length === 0;
            clienteBuscador.setAttribute('aria-expanded', encontrados.length ? 'true' : 'false');
        }
        clienteBuscador.addEventListener('input', () => {
            clienteSelector.value = '';
            clienteBuscador.setCustomValidity('Selecciona un cliente activo de la lista.');
            mostrarClientes();
        });
        clienteBuscador.addEventListener('focus', mostrarClientes);
        clienteBuscador.addEventListener('keydown', event => {
            if (event.key === 'Escape') cerrarClientes();
            if (event.key === 'Enter' && !clientesResultados.hidden) {
                const primero = clientesResultados.querySelector('button');
                if (primero) { event.preventDefault(); primero.click(); }
            }
        });
        document.addEventListener('click', event => {
            if (!document.querySelector('.s2-cliente-busqueda').contains(event.target)) cerrarClientes();
        });
    }

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
            const tamano = fila.querySelector('.js-tamano-empaque');
            const esEmpaque = modalidad === 'empaque';
            fila.querySelector('.js-campo-empaque').hidden = !esEmpaque;
            tamano.disabled = !esEmpaque;
            fila.querySelector('.js-descripcion-empaque').textContent = esEmpaque
                ? `${producto?.tipo_empaque || 'Empaque'} de ${tamano.value || '…'}` : '';
            const precio = window.BusquedaPedidos.precioLineaCentimos(producto, modalidad, tamano.value);
            const centimos = precio === null ? NaN : precio;
            const importe = Number.isInteger(cantidad) && cantidad > 0 && Number.isFinite(centimos) ? centimos * cantidad : 0;
            fila.querySelector('.js-precio').value = Number.isFinite(centimos) ? (centimos / 100).toFixed(2) : 'Sin precio';
            fila.querySelector('.js-importe').value = (importe / 100).toFixed(2);
            fila.querySelector('.js-cantidad').setCustomValidity(importe > 99999999 ? 'El importe de una línea no puede superar S/ 999 999,99.' : '');
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
        const tamano = datos.unidades_por_empaque ?? producto.unidades_por_empaque;
        const clave = window.BusquedaPedidos.claveLinea(producto.id, datos.modalidad || 'unidad', tamano);
        const repetida = Array.from(contenedor.querySelectorAll('.s2-product-row')).find(fila =>
            claveFila(fila) === clave
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
        const tamanoCampo = fila.querySelector('.js-tamano-empaque');
        tamanoCampo.value = tamano ?? '';
        tamanoCampo.id = `tamano-empaque-${indice}`;
        fila.querySelector('.js-label-empaque').htmlFor = tamanoCampo.id;
        contenedor.appendChild(fragmento);
        const actual = contenedor.lastElementChild;
        configurarModalidad(actual, datos.modalidad || 'unidad');
        let modalidadAnterior = actual.querySelector('.js-modalidad').value;
        let tamanoAnterior = tamanoCampo.value;
        function consolidarDuplicado() {
            if (actual.querySelector('.js-modalidad').value === 'empaque' && !tamanoCampo.checkValidity()) {
                recalcular();
                return;
            }
            const existente = Array.from(contenedor.querySelectorAll('.s2-product-row')).find(fila =>
                fila !== actual && claveFila(fila) === claveFila(actual)
            );
            if (existente) {
                const suma = window.BusquedaPedidos.cantidadAcumulada(existente.querySelector('.js-cantidad').value, actual.querySelector('.js-cantidad').value);
                if (suma === null) {
                    actual.querySelector('.js-modalidad').value = modalidadAnterior;
                    tamanoCampo.value = tamanoAnterior;
                    recalcular();
                    aviso.textContent = 'La cantidad acumulada excede el máximo de 10 000.';
                    return;
                }
                existente.querySelector('.js-cantidad').value = String(suma);
                actual.remove();
                existente.querySelector('.js-cantidad').focus();
            } else {
                modalidadAnterior = actual.querySelector('.js-modalidad').value;
                tamanoAnterior = tamanoCampo.value;
            }
            recalcular();
        }
        actual.querySelector('.js-modalidad').addEventListener('change', () => {
            recalcular();
            consolidarDuplicado();
        });
        tamanoCampo.addEventListener('input', recalcular);
        tamanoCampo.addEventListener('change', consolidarDuplicado);
        actual.querySelector('.js-cantidad').addEventListener('input', recalcular);
        actual.querySelector('.js-eliminar').addEventListener('click', () => {
            actual.remove();
            recalcular();
            buscar.focus();
        });
        recalcular();
        return actual;
    }

    function claveFila(fila) {
        return window.BusquedaPedidos.claveLinea(
            fila.querySelector('.js-presentacion').value,
            fila.querySelector('.js-modalidad').value,
            fila.querySelector('.js-tamano-empaque').value
        );
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
            detalle.textContent = `${p.sku} · ${modalidad === 'unidad' ? 'Unidad' : `${p.tipo_empaque || 'Empaque'} de ${p.unidades_por_empaque}`} · S/ ${Number(precio).toFixed(2)}`;
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
