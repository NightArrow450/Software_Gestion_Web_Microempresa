(function (root) {
    'use strict';
    function admite(mapa, reporte, campo) {
        return (mapa[reporte] || []).includes(campo);
    }
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { admite };
    }
    if (!root.document) return;
    root.document.querySelectorAll('[data-indicadores]').forEach(function (form) {
        const mapa = JSON.parse(form.dataset.filtros);
        const reporte = form.querySelector('[name=reporte]');
        function actualizar() {
            form.querySelectorAll('[data-filtro]').forEach(function (grupo) {
                const control = grupo.querySelector('input,select');
                const aplicable = admite(mapa, reporte.value, control.name);
                grupo.hidden = !aplicable;
                control.disabled = !aplicable;
            });
        }
        reporte.addEventListener('change', actualizar);
        actualizar();
    });
})(typeof window === 'undefined' ? {} : window);
