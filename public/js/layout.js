(() => {
    const toggle = document.querySelector('.menu-toggle');
    const sidebar = document.getElementById('app-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const content = document.querySelector('.app-content');
    if (!toggle || !sidebar || !overlay || !content) return;

    const mobile = window.matchMedia('(max-width: 950px)');
    let opened = false;
    const focusable = () => [...sidebar.querySelectorAll('a[href], button:not([disabled]), [tabindex="0"]')]
        .filter(element => element.getClientRects().length);

    function setOpen(value, restoreFocus = true) {
        opened = value && mobile.matches;
        document.body.classList.toggle('menu-open', opened);
        toggle.setAttribute('aria-expanded', String(opened));
        toggle.setAttribute('aria-label', opened ? 'Cerrar menú de navegación' : 'Abrir menú de navegación');
        overlay.hidden = !opened;
        content.inert = opened;
        sidebar.inert = mobile.matches && !opened;
        if (opened) {
            sidebar.setAttribute('role', 'dialog');
            sidebar.setAttribute('aria-modal', 'true');
            (focusable()[0] || sidebar).focus();
        } else {
            sidebar.removeAttribute('role');
            sidebar.removeAttribute('aria-modal');
            if (restoreFocus) {
                (mobile.matches ? toggle : sidebar.querySelector('a')).focus();
            }
        }
    }

    toggle.addEventListener('click', () => setOpen(!opened));
    sidebar.querySelector('.menu-close').addEventListener('click', () => setOpen(false));
    overlay.addEventListener('click', () => setOpen(false));
    sidebar.addEventListener('click', event => {
        if (opened && event.target.closest('a[href]')) setOpen(false);
    });
    document.addEventListener('keydown', event => {
        if (!opened) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
        }
        if (event.key === 'Tab') {
            const items = focusable();
            const first = items[0] || sidebar;
            const last = items.at(-1) || sidebar;
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
    mobile.addEventListener('change', () => setOpen(false,
        opened || sidebar.contains(document.activeElement) || document.activeElement === toggle));
    setOpen(false, false);
})();
