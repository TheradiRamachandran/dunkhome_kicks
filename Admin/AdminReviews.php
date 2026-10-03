<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
requireAdmin();

$adminEmail = (string) ($_SESSION['admin_email'] ?? 'Administrator');
$emailName = explode('@', $adminEmail)[0] ?? '';
$adminInitials = strtoupper(substr((string) (preg_replace('/[^a-zA-Z0-9]/', '', $emailName) ?: 'AD'), 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../includes/favicon.php'; ?>
    <base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reviews | DunkHome Kicks</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-theme2">
    <link rel="stylesheet" href="assets/admin-pages.css?v=20261003-theme2">
    <link rel="stylesheet" href="assets/admin-navigation.css?v=20261003-theme1">
    <style>
        .reviews-heading { margin-bottom: 24px; }
        .reviews-heading .admin-subtitle { max-width: 620px; margin-bottom: 0; }
        .reviews-empty-panel { max-width: 850px; display: flex; align-items: flex-start; gap: 17px; padding: 22px; }
        .reviews-empty-icon { width: 44px; height: 44px; flex: 0 0 44px; display: grid; place-items: center; border: 1px solid var(--admin-line); border-radius: 11px; color: var(--admin-green); background: rgba(121,230,170,.065); font-size: 15px; }
        .reviews-empty-copy { min-width: 0; }
        .reviews-empty-copy h2 { margin: 0; color: var(--admin-text); font-size: 14px; }
        .reviews-empty-copy p { margin: 7px 0 0; color: var(--admin-muted); font-size: 11px; line-height: 1.6; }
        .reviews-feature-note { display: inline-flex; align-items: center; gap: 6px; margin-top: 13px; color: var(--admin-muted); font-size: 9px; }
        .reviews-feature-note i { color: var(--admin-green); }
        @media(max-width:640px) { .reviews-heading { margin-bottom: 18px; } .reviews-empty-panel { gap: 12px; padding: 17px; } .reviews-empty-icon { width: 38px; height: 38px; flex-basis: 38px; } }
    </style>
</head>
<body>
<div class="admin-page admin-sidebar-layout">
    <div class="admin-menu-overlay" id="adminMenuOverlay"></div>
    <button class="admin-mobile-menu" id="adminMobileMenu" type="button" aria-label="Open administration menu" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
    <div class="admin-mobile-actions" aria-label="Admin controls">
        <button class="admin-mobile-action" id="themeToggle" type="button" title="Toggle theme" aria-label="Switch to light theme">☀️</button>
        <a class="admin-mobile-action" href="<?= h(appUrl('Admin/Logout.php?scope=admin')) ?>" title="Sign out" aria-label="Sign out"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
        <button class="admin-mobile-action" id="reviewsRefreshButton" type="button" title="Reload page" aria-label="Reload page"><i class="fa-solid fa-rotate-right"></i></button>
    </div>
    <header class="admin-topbar" id="adminSidebar">
        <a class="admin-brand" href="<?= h(appUrl('Admin/AdminDashboard.php')) ?>"><img src="image/logo.jpg" alt=""><div class="brand-name">dunkhome_<span>kicks</span></div></a>
        <div class="admin-nav-title">Administration</div>
        <nav class="admin-nav-links"><?php $adminNavigationShowThemeToggle = false; require __DIR__ . '/../includes/nav.php'; ?></nav>
        <div class="admin-sidebar-identity"><div class="admin-sidebar-avatar"><?= h($adminInitials) ?></div><div class="admin-sidebar-user"><strong><?= h($adminEmail) ?></strong><span>Administrator</span></div></div>
    </header>
    <main class="admin-content">
        <div class="reviews-heading"><div><div class="admin-eyebrow">Customer feedback</div><h1 class="admin-title">Reviews</h1><p class="admin-subtitle">Review moderation for customer feedback.</p></div></div>
        <section class="admin-panel reviews-empty-panel" aria-label="Review availability">
            <span class="reviews-empty-icon" aria-hidden="true"><i class="fa-regular fa-star"></i></span>
            <div class="reviews-empty-copy"><h2>No review records are available</h2><p>Product reviews are not currently collected or stored by this site. A review feature and review table are needed before customer feedback can be moderated here.</p><span class="reviews-feature-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Review moderation is not configured</span></div>
        </section>
    </main>
    <footer class="admin-footer">DunkHome Kicks administration</footer>
</div>
<script src="assets/dunkhome-ui.js?v=20261001-loader4" defer></script>
<script src="assets/admin-sidebar-drawer.js?v=20261001-1" defer></script>
<script>document.getElementById('reviewsRefreshButton')?.addEventListener('click', () => window.location.reload());</script>
</body>
</html>

