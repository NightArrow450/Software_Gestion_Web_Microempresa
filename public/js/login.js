document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('password-toggle');
    const input = document.getElementById('password');
    const icon = document.getElementById('eye-icon');
    button.addEventListener('click', () => {
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        icon.textContent = visible ? 'visibility_off' : 'visibility';
        button.setAttribute('aria-pressed', String(visible));
        button.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
});