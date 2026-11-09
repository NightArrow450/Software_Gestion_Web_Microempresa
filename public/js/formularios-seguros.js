document.addEventListener('submit', event => {
    const form = event.target;
    if (!form.querySelector('[name="_token_operacion"]')) return;
    queueMicrotask(() => {
        if (event.defaultPrevented) return;
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('[type="submit"]').forEach(button => {
            button.disabled = true;
            button.dataset.envioPendiente = 'true';
        });
    });
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-envio-pendiente]').forEach(button => {
        button.disabled = false;
        delete button.dataset.envioPendiente;
        button.form?.removeAttribute('aria-busy');
    });
});
