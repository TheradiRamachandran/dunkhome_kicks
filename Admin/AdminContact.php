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
    <base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact | DunkHome Kicks</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261001-loader4">
    <link rel="stylesheet" href="assets/admin-pages.css">
    <link rel="stylesheet" href="assets/admin-navigation.css">
    <style>
        .contact-heading { margin-bottom: 24px; }
        .contact-heading .admin-subtitle { max-width: 620px; margin-bottom: 0; }
        .contact-panel { max-width: 900px; overflow: hidden; padding: 0; }
        .contact-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 21px 23px; border-bottom: 1px solid var(--admin-line); }
        .contact-panel-head h2 { margin: 0; color: var(--admin-text); font-size: 14px; }
        .contact-panel-head p { margin: 5px 0 0; color: var(--admin-muted); font-size: 10px; line-height: 1.5; }
        .contact-panel-icon { width: 42px; height: 42px; flex: 0 0 42px; display: grid; place-items: center; border: 1px solid rgba(121,230,170,.2); border-radius: 12px; color: var(--admin-green); background: rgba(121,230,170,.08); }
        .contact-details { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 12px; padding: 18px 22px; }
        .contact-detail { min-width: 0; padding: 15px; border: 1px solid var(--admin-line); border-radius: 9px; background: rgba(255,255,255,.02); }
        .contact-detail-label { display: flex; align-items: center; gap: 8px; color: var(--admin-muted); font-size: 9px; font-weight: 800; text-transform: uppercase; }
        .contact-detail-label i { color: var(--admin-green); }
        .contact-detail p { margin: 9px 0 0; color: var(--admin-text); font-size: 12px; line-height: 1.6; overflow-wrap: anywhere; }
        .contact-detail .contact-muted { color: var(--admin-muted); font-size: 10px; }
        .contact-empty-note { display: flex; align-items: flex-start; gap: 10px; margin: 0 22px 18px; padding: 12px 13px; border: 1px solid var(--admin-line); border-radius: 8px; color: var(--admin-muted); background: rgba(255,255,255,.02); font-size: 10px; line-height: 1.5; }
        .contact-empty-note i { margin-top: 2px; color: var(--admin-green); }
        .contact-actions { display: flex; flex-wrap: wrap; gap: 9px; padding: 16px 22px; border-top: 1px solid var(--admin-line); }
        @media(max-width:640px) { .contact-heading { margin-bottom: 18px; } .contact-panel-head { padding: 18px; } .contact-details { grid-template-columns: 1fr; padding: 14px 16px; } .contact-empty-note { margin: 0 16px 16px; } .contact-actions { display: grid; grid-template-columns: 1fr; padding: 14px 16px; } .contact-actions .admin-button { width: 100%; justify-content: center; } }
    </style>
</head>
<body>
<div class="admin-page admin-sidebar-layout">
    <div class="admin-menu-overlay" id="adminMenuOverlay"></div>
    <button class="admin-mobile-menu" id="adminMobileMenu" type="button" aria-label="Open administration menu" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
    <div class="admin-mobile-actions" aria-label="Admin controls">
        <button class="admin-mobile-action" id="themeToggle" type="button" title="Toggle theme" aria-label="Switch to light theme">☀️</button>
        <a class="admin-mobile-action" href="<?= h(appUrl('Admin/Logout.php?scope=admin')) ?>" title="Sign out" aria-label="Sign out"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
        <button class="admin-mobile-action" id="contactRefreshButton" type="button" title="Reload page" aria-label="Reload page"><i class="fa-solid fa-rotate-right"></i></button>
    </div>
    <header class="admin-topbar" id="adminSidebar">
        <a class="admin-brand" href="<?= h(appUrl('Admin/AdminDashboard.php')) ?>"><img src="image/logo.jpeg" alt=""><div class="brand-name">dunkhome_<span>kicks</span></div></a>
        <div class="admin-nav-title">Administration</div>
        <nav class="admin-nav-links"><?php $adminNavigationShowThemeToggle = false; require __DIR__ . '/../includes/nav.php'; ?></nav>
        <div class="admin-sidebar-identity"><div class="admin-sidebar-avatar"><?= h($adminInitials) ?></div><div class="admin-sidebar-user"><strong><?= h($adminEmail) ?></strong><span>Administrator</span></div></div>
    </header>
    <main class="admin-content">
        <div class="contact-heading"><div><div class="admin-eyebrow">Storefront</div><h1 class="admin-title">Contact</h1><p class="admin-subtitle">Review the contact details currently shown to customers.</p></div></div>
        <section class="admin-panel contact-panel" aria-labelledby="contactDetailsTitle">
            <div class="contact-panel-head"><div><h2 id="contactDetailsTitle">Customer contact</h2><p>Information displayed on the public contact page.</p></div><span class="contact-panel-icon" aria-hidden="true"><i class="fa-regular fa-address-card"></i></span></div>
            <div class="contact-details">
                <div class="contact-detail"><span class="contact-detail-label"><i class="fa-regular fa-envelope"></i> Support email</span><p>support@dunkhome-kicks.local</p></div>
                <div class="contact-detail"><span class="contact-detail-label"><i class="fa-regular fa-message"></i> Message inbox</span><p class="contact-muted">No contact form submissions are stored by the current site.</p></div>
            </div>
            <div class="contact-empty-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Contact form submissions are not available for review because this site does not currently store messages.</span></div>
            <div class="contact-actions">
                <a class="admin-button primary" href="<?= h(appUrl('User/Contact.php')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> View public contact page</a>
                <a class="admin-button" href="<?= h(appUrl('index.php')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-globe"></i> View website</a>
            </div>
        </section>
    </main>
    <footer class="admin-footer">DunkHome Kicks administration</footer>
</div>
<script src="assets/dunkhome-ui.js?v=20261001-loader4" defer></script>
<script src="assets/admin-sidebar-drawer.js?v=20261001-1" defer></script>
<script>document.getElementById('contactRefreshButton')?.addEventListener('click', () => window.location.reload());</script>
</body>
</html>
