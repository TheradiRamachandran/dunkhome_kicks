document.addEventListener('DOMContentLoaded', () => {
    const drawer = document.querySelector('.admin-sidebar-layout, .admin-sidebar-drawer');
    const toggle = document.getElementById('adminMobileMenu');
    const overlay = document.getElementById('adminMenuOverlay');

    if (!drawer || !toggle || !overlay) return;
    const icon = toggle.querySelector('i');

    const updateToggle = isOpen => {
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Close administration menu' : 'Open administration menu');
        toggle.style.display = isOpen ? 'none' : '';
        if (icon) icon.className = 'fa-solid fa-bars';
    };

    const closeDrawer = () => {
        document.body.classList.remove('admin-sidebar-open');
        updateToggle(false);
    };

    toggle.addEventListener('click', () => {
        const isOpen = document.body.classList.toggle('admin-sidebar-open');
        updateToggle(isOpen);
    });

    overlay.addEventListener('click', closeDrawer);
    drawer.querySelectorAll('.admin-nav-links a').forEach(link => {
        link.addEventListener('click', closeDrawer);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeDrawer();
    });
});
