(function (global) {
    'use strict';
    function crear({ input, select, lista, estado, mas, url, tipo, fetch: consultar = global.fetch.bind(global) }) {
        let controlador, temporizador, generacion = 0, pagina = 1, resultados = [], activo = -1, cargando = false;
        function cerrar() { lista.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); activo = -1; }
        function cancelar() { clearTimeout(temporizador); controlador?.abort(); generacion++; cargando = false; mas.disabled = false; input.setAttribute('aria-busy', 'false'); }
        function seleccionar(fila) {
            cancelar(); select.replaceChildren();
            const opcion = document.createElement('option'); opcion.value = String(fila.id); opcion.textContent = fila.nombre; select.append(opcion); select.value = String(fila.id);
            input.value = fila.nombre; estado.textContent = `Seleccionado: ${fila.nombre}`; cerrar(); mas.hidden = true;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        function pintar() {
            lista.replaceChildren(); activo = -1;
            resultados.forEach((fila, i) => {
                const opcion = document.createElement('div'); opcion.id = `${lista.id}-${i}`; opcion.setAttribute('role', 'option'); opcion.setAttribute('aria-selected', 'false'); opcion.textContent = fila.nombre;
                opcion.addEventListener('mousedown', e => e.preventDefault()); opcion.addEventListener('click', () => seleccionar(fila)); lista.append(opcion);
            });
            lista.hidden = resultados.length === 0; input.setAttribute('aria-expanded', String(resultados.length > 0)); input.removeAttribute('aria-activedescendant');
        }
        async function buscar(ampliar = false) {
            if (cargando && ampliar) return;
            cancelar(); const actual = generacion; controlador = new AbortController(); cargando = true; mas.disabled = true; input.setAttribute('aria-busy', 'true');
            if (!ampliar) { pagina = 1; resultados = []; cerrar(); mas.hidden = true; }
            estado.textContent = 'Buscando coincidencias…';
            const destino = new URL(url, location.origin); destino.searchParams.set('tipo', tipo); destino.searchParams.set('q', input.value.trim()); destino.searchParams.set('page', String(pagina));
            try {
                const respuesta = await consultar(destino, { signal: controlador.signal, headers: { Accept: 'application/json' } });
                if (!respuesta.ok || respuesta.redirected) throw new Error('No se pudo consultar. Revisa la sesión y vuelve a intentar.');
                const datos = await respuesta.json(); if (actual !== generacion) return;
                resultados = ampliar ? resultados.concat(datos.data.filter(f => !resultados.some(r => r.id === f.id))) : datos.data;
                pagina = datos.current_page + 1; mas.hidden = !datos.next_page_url; pintar();
                estado.textContent = resultados.length ? `${resultados.length} coincidencias visibles. Usa las flechas y Enter para seleccionar.${datos.next_page_url ? ' Hay más resultados.' : ''}` : 'No se encontraron coincidencias.';
            } catch (error) {
                if (actual !== generacion || error.name === 'AbortError') return;
                cerrar(); estado.textContent = 'No se pudo consultar. Revisa la conexión y tu sesión, y vuelve a intentar.';
            } finally {
                if (actual === generacion) { cargando = false; mas.disabled = false; input.setAttribute('aria-busy', 'false'); }
            }
        }
        input.addEventListener('input', () => {
            cancelar(); cerrar(); mas.hidden = true;
            select.value = ''; select.dispatchEvent(new Event('change', { bubbles: true }));
            estado.textContent = 'Escribe para buscar y selecciona una coincidencia.';
            temporizador = setTimeout(() => buscar(), 250);
        });
        input.addEventListener('focus', () => { if (!select.value && !cargando) buscar(); });
        input.addEventListener('blur', () => setTimeout(cerrar, 150));
        input.addEventListener('keydown', e => {
            if (e.key === 'Escape') { cancelar(); cerrar(); return; }
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (lista.hidden) { buscar(); return; }
                activo = activo < 0 ? (e.key === 'ArrowDown' ? 0 : resultados.length - 1) : (activo + (e.key === 'ArrowDown' ? 1 : -1) + resultados.length) % resultados.length;
                Array.from(lista.children).forEach((opcion, i) => opcion.setAttribute('aria-selected', String(i === activo)));
                input.setAttribute('aria-activedescendant', lista.children[activo].id); lista.children[activo].scrollIntoView?.({ block: 'nearest' });
            } else if (e.key === 'Enter' && !lista.hidden) { e.preventDefault(); if (resultados.length) seleccionar(resultados[activo >= 0 ? activo : 0]); }
        });
        mas.addEventListener('mousedown', e => e.preventDefault()); mas.addEventListener('click', () => buscar(true));
        return { buscar, cerrar, cancelar };
    }
    global.PedidosAutocompletar = { crear };
    if (typeof module !== 'undefined' && module.exports) module.exports = { crear };
})(typeof globalThis !== 'undefined' ? globalThis : this);
