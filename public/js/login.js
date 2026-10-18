tailwind.config = {
            darkMode: "class",

            theme: {
                extend: {

                    colors: {
                        "on-secondary": "#ffffff",
                        "on-error": "#ffffff",
                        "on-secondary-fixed-variant": "#00504a",
                        "surface-bright": "#faf8ff",
                        "secondary-container": "#99efe5",
                        "on-primary-fixed-variant": "#005137",
                        "error-container": "#ffdad6",
                        "on-secondary-fixed": "#00201d",
                        "error": "#ba1a1a",
                        "on-primary-fixed": "#002114",
                        "on-primary-container": "#f5fff7",
                        "tertiary-container": "#a36700",
                        "inverse-on-surface": "#eef0ff",
                        "outline": "#6d7a72",
                        "on-secondary-container": "#006f67",
                        "on-tertiary-fixed-variant": "#653e00",
                        "surface-container-lowest": "#ffffff",
                        "surface-container-low": "#f2f3ff",
                        "on-background": "#131b2e",
                        "surface-container-high": "#e2e7ff",
                        "on-error-container": "#93000a",
                        "on-tertiary-container": "#fffbff",
                        "background": "#faf8ff",
                        "secondary": "#006a63",
                        "surface-variant": "#dae2fd",
                        "primary": "#006948",
                        "surface-container": "#eaedff",
                        "on-tertiary": "#ffffff",
                        "inverse-surface": "#283044",
                        "surface-dim": "#d2d9f4",
                        "outline-variant": "#bccac0",
                        "inverse-primary": "#68dba9",
                        "secondary-fixed": "#9cf2e8",
                        "surface": "#faf8ff",
                        "on-tertiary-fixed": "#2a1700",
                        "on-primary": "#ffffff",
                        "tertiary-fixed": "#ffddb8",
                        "secondary-fixed-dim": "#80d5cb",
                        "primary-fixed": "#85f8c4",
                        "primary-container": "#00855d",
                        "tertiary-fixed-dim": "#ffb95f",
                        "on-surface-variant": "#3d4a42",
                        "surface-tint": "#006c4a",
                        "tertiary": "#825100",
                        "primary-fixed-dim": "#68dba9",
                        "surface-container-highest": "#dae2fd",
                        "on-surface": "#131b2e"
                    },

                    borderRadius: {
                        DEFAULT: "0.25rem",
                        lg: "0.5rem",
                        xl: "0.75rem",
                        full: "9999px"
                    },

                    fontFamily: {
                        inter: ["Inter", "sans-serif"]
                    }

                }
            }
        };

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