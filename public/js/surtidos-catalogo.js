document.querySelectorAll('[data-formato-surtido]').forEach(form => {
    const producto = form.querySelector('[name="producto_id"]');
    const actualizar = () => form.querySelectorAll('[data-producto]').forEach(label => {
        label.hidden = label.dataset.producto !== producto.value;
        const input = label.querySelector('input'); input.disabled = label.hidden;
    });
    producto.addEventListener('change', actualizar); actualizar();
});
