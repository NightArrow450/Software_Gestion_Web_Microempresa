/* Cálculos de composición; el servidor vuelve a validar cantidades y SKU. */
(function (raiz, fabrica) {
    const api = fabrica();
    if (typeof module === 'object' && module.exports) module.exports = api;
    raiz.SurtidosUtilidades = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';
    function copiar(paquete) {
        return { estado: paquete.estado, componentes: (paquete.componentes || []).map(c => ({ presentacion_producto_id: c.presentacion_producto_id, cantidad: c.cantidad })) };
    }
    function validar(paquete, tamano, permitidos) {
        let total = 0;
        const vistos = new Set();
        for (const c of paquete.componentes || []) {
            const id = Number(c.presentacion_producto_id), cantidad = Number(c.cantidad);
            if (!Number.isInteger(id) || !permitidos.includes(id) || vistos.has(id) || !Number.isInteger(cantidad) || cantidad < 1) return 'Selecciona SKU distintos y cantidades enteras positivas.';
            vistos.add(id); total += cantidad;
        }
        if (total > tamano || (paquete.estado === 'definida' && total !== tamano)) return `La composición definida debe sumar exactamente ${tamano} unidades.`;
        return '';
    }
    return Object.freeze({ copiar, validar });
});
