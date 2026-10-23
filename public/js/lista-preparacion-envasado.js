(() => {
    const dialogo = document.querySelector('#envasado-integrado');
    if (!dialogo) return;
    const formulario = dialogo.querySelector('[data-consultar-envasado]');
    const campo = nombre => formulario.elements.namedItem(nombre);
    const alternativa = campo('configuracion_envasado_id');
    const vista = dialogo.querySelector('[data-vista-previa-envasado]');
    const errores = dialogo.querySelector('[data-error-envasado]');
    const consultar = formulario.querySelector('[type="submit"]');
    let disparador = null;
    let consulta = null;
    let overflowAnterior = '';

    function invalidarVista() {
        consulta?.abort();
        consulta = null;
        consultar.disabled = false;
        formulario.removeAttribute('aria-busy');
        vista.replaceChildren();
    }

    function filtrarOpciones() {
        const id = campo('presentacion_producto_id').value;
        Array.from(alternativa.options).forEach(opcion => {
            if (!opcion.value) return;
            opcion.hidden = opcion.dataset.presentacion !== id;
            opcion.disabled = opcion.hidden;
        });
        if (alternativa.selectedOptions[0]?.disabled) alternativa.value = '';
        Array.from(campo('pedido_id').options).forEach(opcion => {
            if (!opcion.value) return;
            opcion.hidden = !opcion.dataset.presentaciones.split(',').includes(id);
            opcion.disabled = opcion.hidden;
        });
        if (campo('pedido_id').selectedOptions[0]?.disabled) campo('pedido_id').value = '';
        campo('version_configuracion').value = alternativa.selectedOptions[0]?.dataset.version ?? '';
    }

    function abrir(boton, recuperar = false) {
        disparador = boton;
        if (!recuperar) {
            formulario.reset();
            errores.replaceChildren();
        }
        invalidarVista();
        campo('presentacion_producto_id').value = boton.dataset.registrarEnvasado;
        dialogo.querySelector('[data-sku-envasado]').textContent = boton.dataset.sku;
        filtrarOpciones();
        overflowAnterior = document.body.style.overflow;
        dialogo.showModal();
        document.body.style.overflow = 'hidden';
        alternativa.focus();
    }

    document.querySelectorAll('[data-registrar-envasado]').forEach(boton => {
        boton.addEventListener('click', () => abrir(boton));
    });
    dialogo.querySelector('[data-cerrar-envasado]').addEventListener('click', () => dialogo.close());
    dialogo.addEventListener('click', evento => {
        if (evento.target === dialogo) {
            const rectangulo = dialogo.getBoundingClientRect();
            if (evento.clientX < rectangulo.left || evento.clientX > rectangulo.right || evento.clientY < rectangulo.top || evento.clientY > rectangulo.bottom) dialogo.close();
        }
        if (evento.target.closest('[data-editar-envasado]')) {
            invalidarVista();
            campo('cantidad').focus();
        }
    });
    dialogo.addEventListener('close', () => {
        invalidarVista();
        document.body.style.overflow = overflowAnterior;
        disparador?.focus();
    });
    formulario.addEventListener('input', invalidarVista);
    formulario.addEventListener('change', () => {
        invalidarVista();
        filtrarOpciones();
    });
    formulario.addEventListener('submit', async evento => {
        evento.preventDefault();
        invalidarVista();
        errores.replaceChildren();
        filtrarOpciones();
        const actual = new AbortController();
        consulta = actual;
        consultar.disabled = true;
        formulario.setAttribute('aria-busy', 'true');
        try {
            const respuesta = await fetch(formulario.action, {
                method: 'POST', body: new FormData(formulario), signal: actual.signal,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (consulta !== actual) return;
            if (!respuesta.ok || respuesta.redirected) {
                const datos = await respuesta.json().catch(() => ({}));
                if (consulta !== actual) return;
                const mensajes = datos.errors ? Object.values(datos.errors).flat() : [datos.mensaje ?? 'No se pudo consultar. Revisa la sesión y vuelve a intentarlo.'];
                mensajes.forEach(mensaje => {
                    const parrafo = document.createElement('p');
                    parrafo.textContent = mensaje;
                    errores.append(parrafo);
                });
                return;
            }
            const contenido = await respuesta.text();
            if (consulta !== actual) return;
            vista.innerHTML = contenido;
            vista.querySelector('[data-confirmacion-envasado]')?.focus();
        } catch (error) {
            if (error.name !== 'AbortError') errores.textContent = 'No se pudo conectar. No se registró ningún envasado; vuelve a consultar.';
        } finally {
            if (consulta === actual) {
                consultar.disabled = false;
                formulario.removeAttribute('aria-busy');
                consulta = null;
            }
        }
    });
    if (dialogo.dataset.reabrir === '1') {
        const boton = Array.from(document.querySelectorAll('[data-registrar-envasado]')).find(elemento => elemento.dataset.registrarEnvasado === campo('presentacion_producto_id').value);
        if (boton) abrir(boton, true);
    }
})();
