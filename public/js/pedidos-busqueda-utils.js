/* Utilidades puras compartidas por el formulario y sus pruebas de JavaScript. */
(function (raiz, fabrica) {
    'use strict';
    const utilidades = fabrica();
    if (typeof module === 'object' && module.exports) module.exports = utilidades;
    raiz.BusquedaPedidos = utilidades;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';
    function normalizar(texto) {
        return String(texto ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
    }
    function coincide(producto, texto) {
        return normalizar(`${producto.nombre} ${producto.sku}`).includes(normalizar(texto));
    }
    function cantidadAcumulada(actual, adicional) {
        const total = Number(actual) + Number(adicional);
        return Number.isSafeInteger(total) && total >= 1 && total <= 10000 ? total : null;
    }
    function claveLinea(presentacionId, modalidad, tamano) {
        return `${Number(presentacionId)}|${modalidad}|${modalidad === 'empaque' ? Number(tamano) : ''}`;
    }
    return Object.freeze({ normalizar, coincide, cantidadAcumulada, claveLinea });
});
