(function (global) {
    'use strict';
    function sumarSalida(sueltas, paquetes) {
        const totales = {}, identidades = new Set();
        function sumar(sku, cantidad) {
            const id = Number(sku), n = Number(cantidad);
            if (!Number.isSafeInteger(id) || id < 1 || !Number.isSafeInteger(n) || n < 0) throw new Error('Las cantidades de salida deben ser unidades enteras no negativas.');
            totales[id] = (totales[id] || 0) + n;
            if (!Number.isSafeInteger(totales[id])) throw new Error('La cantidad supera el límite técnico permitido.');
        }
        for (const fila of sueltas) sumar(fila.sku_id, fila.cantidad);
        for (const paquete of paquetes) {
            if (!paquete.identidad || identidades.has(paquete.identidad)) throw new Error('Un paquete no puede seleccionarse dos veces.');
            identidades.add(paquete.identidad);
            for (const componente of paquete.componentes) sumar(componente.sku_id, componente.cantidad);
        }
        return totales;
    }
    global.DespachoUnidadesUtilidades = {sumarSalida};
    if (typeof document === 'undefined') return;
    document.querySelectorAll('[data-despacho-unidades]').forEach(form => {
        function actualizar() {
            const mensaje = form.querySelector('[data-error-distribucion]');
            try {
                const sueltas = Array.from(form.querySelectorAll('[data-salida-suelta]'), c => ({sku_id: c.dataset.sku, cantidad: c.value}));
                const paquetes = Array.from(form.querySelectorAll('[data-paquete-salida]:checked'), c => ({identidad: c.value, componentes: JSON.parse(c.dataset.componentes)}));
                const totales = sumarSalida(sueltas, paquetes);
                form.querySelectorAll('[data-cantidad-salida]').forEach(c => { c.value = totales[c.dataset.sku] || 0; });
                form.querySelectorAll('[data-total-salida]').forEach(c => { c.textContent = String(totales[c.dataset.sku] || 0); });
                mensaje.textContent = '';
                return true;
            } catch (error) { mensaje.textContent = error.message; return false; }
        }
        form.addEventListener('input', actualizar);
        form.addEventListener('submit', event => { if (!actualizar()) event.preventDefault(); });
        actualizar();
    });
})(typeof globalThis !== 'undefined' ? globalThis : this);