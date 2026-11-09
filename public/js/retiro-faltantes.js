(() => {
    const form = document.querySelector('[data-retiro-faltantes]');
    if (!form) return;
    const salida = form.querySelector('[data-resumen-retiro]');
    const actualizar = () => {
        let unidades = 0, reduccion = 0;
        for (const campo of form.querySelectorAll('[data-precio-centimos]')) {
            const n = Number(campo.value);
            if (!Number.isSafeInteger(n) || n < 0 || n > Number(campo.max)) {
                salida.textContent = 'Revisa las cantidades: solo se retiran unidades enteras que nunca fueron despachadas.';
                return;
            }
            unidades += n; reduccion += n * Number(campo.dataset.precioCentimos);
        }
        salida.textContent = `${unidades} unidades a retirar · reducción comercial S/ ${(reduccion / 100).toFixed(2)} · compromiso resultante S/ ${((Number(form.dataset.totalCentimos) - reduccion) / 100).toFixed(2)}. No modifica stock, pagos ni comprobantes.`;
    };
    form.addEventListener('input', actualizar);
    actualizar();
})();
