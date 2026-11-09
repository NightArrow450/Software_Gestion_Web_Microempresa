export function validarDestino(manifiesto, operacion, ahora = Date.now()) {
    if (!manifiesto || !/^https?:\/\/127\.0\.0\.1:(8089|8449)$/.test(manifiesto.url)
        || !/^dhacer_eco_limp_demo_f9b_[a-z0-9_]{1,20}$/.test(manifiesto.base)
        || !manifiesto.autorizacion || !Array.isArray(manifiesto.operaciones) || !manifiesto.operaciones.includes(operacion)
        || !Number.isFinite(Date.parse(manifiesto.vence)) || Date.parse(manifiesto.vence) <= ahora) {
        throw new Error('Destino u operación no autorizados para rendimiento.');
    }
    return manifiesto.url;
}
