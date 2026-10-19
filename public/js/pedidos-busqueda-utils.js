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
    return Object.freeze({ normalizar, coincide });
});
