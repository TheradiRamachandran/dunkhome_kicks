document.addEventListener('DOMContentLoaded', () => {
    const drawer = document.querySelector('.admin-sidebar-layout, .admin-sidebar-drawer');
    const toggle = document.getElementById('adminMobileMenu');
    const overlay = document.getElementById('adminMenuOverlay');

    if (!drawer || !toggle || !overlay) return;

    const closeDrawer = () => {
        document.body.classList.remove('admin-sidebar-open');
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', () => {
        const isOpen = document.body.classList.toggle('admin-sidebar-open');
        toggle.setAttribute('aria-expanded', String(isOpen));
    });

    overlay.addEventListener('click', closeDrawer);
    drawer.querySelectorAll('.admin-nav-links a').forEach(link => {
        link.addEventListener('click', closeDrawer);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeDrawer();
    });
});
