<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
requireAdmin();

$adminEmail = (string) ($_SESSION['admin_email'] ?? 'Administrator');
$emailName = explode('@', $adminEmail)[0] ?? '';
$adminInitials = strtoupper(substr((string) (preg_replace('/[^a-zA-Z0-9]/', '', $emailName) ?: 'AD'), 0, 2));

$query = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT u.id, u.email, u.created_at, COUNT(o.id) AS order_count
        FROM users u
        LEFT JOIN orders o ON o.user_id = u.id';

if ($query !== '') {
    $sql .= ' WHERE u.email LIKE ?';
}
$sql .= ' GROUP BY u.id, u.email, u.created_at ORDER BY u.created_at DESC LIMIT 100';

$stmt = $conn->prepare($sql);
if ($query !== '') {
    $search = '%' . $query . '%';
    $stmt->bind_param('s', $search);
}
$stmt->execute();
$result = $stmt->get_result();
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage users | DunkHome Kicks</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261001-loader4">
    <link rel="stylesheet" href="assets/admin-pages.css">
    <link rel="stylesheet" href="assets/admin-navigation.css">
    <style>
        .users-heading { margin-bottom: 24px; }
        .users-heading .admin-subtitle { max-width: 620px; margin-bottom: 0; }
        .users-panel { overflow: hidden; padding: 0; }
        .users-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 20px 22px; border-bottom: 1px solid var(--admin-line); }
        .users-count strong,.users-count span { display: block; }
        .users-count strong { color: var(--admin-text); font-size: 13px; }
        .users-count span { margin-top: 4px; color: var(--admin-muted); font-size: 10px; }
        .users-search { display: flex; align-items: center; gap: 7px; }
        .users-search input { width: min(300px, 32vw); min-height: 38px; padding: 9px 11px; border: 1px solid var(--admin-line); border-radius: 8px; outline: none; color: var(--admin-text); background: rgba(255,255,255,.035); font: 11px "DM Sans",sans-serif; }
        .users-search input:focus { border-color: rgba(121,230,170,.62); box-shadow: 0 0 0 3px rgba(121,230,170,.09); }
        .users-table-wrap { overflow-x: auto; }
        .users-table { width: 100%; min-width: 620px; border-collapse: collapse; }
        .users-table caption { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        .users-table th,.users-table td { padding: 14px 18px; text-align: left; vertical-align: middle; }
        .users-table th { color: var(--admin-muted); font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .users-table td { border-top: 1px solid var(--admin-line); color: var(--admin-text); font-size: 11px; }
        .user-account { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .user-account-icon { width: 34px; height: 34px; flex: 0 0 34px; display: grid; place-items: center; border: 1px solid rgba(121,230,170,.18); border-radius: 9px; color: var(--admin-green); background: rgba(121,230,170,.06); }
        .user-email { overflow-wrap: anywhere; color: var(--admin-text); font-size: 11px; font-weight: 700; }
        .user-id,.user-orders { font-variant-numeric: tabular-nums; }
        .user-orders { color: var(--admin-green); font-weight: 700; }
        .user-registered { color: var(--admin-muted); white-space: nowrap; font-size: 10px; }
        .users-empty { padding: 36px 22px; color: var(--admin-muted); font-size: 12px; text-align: center; }
        @media(max-width:800px) { .users-toolbar { align-items: stretch; flex-direction: column; } .users-search input { width: 100%; } }
        @media(max-width:640px) {
            .users-heading { margin-bottom: 18px; }
            .users-toolbar { padding: 17px; }
            .users-search { display: grid; grid-template-columns: minmax(0,1fr) auto; }
            .users-search .admin-button { justify-content: center; }
            .users-table-wrap { overflow: visible; }
            .users-table { display: block; min-width: 0; }
            .users-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; }
            .users-table tbody { display: block; padding: 0 12px; }
            .users-table tr { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); border-bottom: 1px solid var(--admin-line); }
            .users-table td { display: block; min-width: 0; padding: 10px; border: 0; overflow-wrap: anywhere; }
            .users-table td::before { display: block; margin-bottom: 5px; color: var(--admin-muted); content: attr(data-label); font-size: 8px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
            .users-table td:first-child { grid-column: 1 / -1; }
            .user-registered { white-space: normal; }
        }
    </style>
</head>
<body>
<div class="admin-page admin-sidebar-layout">
    <div class="admin-menu-overlay" id="adminMenuOverlay"></div>
    <button class="admin-mobile-menu" id="adminMobileMenu" type="button" aria-label="Open administration menu" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
    <div class="admin-mobile-actions" aria-label="Admin controls">
        <button class="admin-mobile-action" id="themeToggle" type="button" title="Toggle theme" aria-label="Switch to light theme">☀️</button>
        <a class="admin-mobile-action" href="<?= h(appUrl('Admin/Logout.php?scope=admin')) ?>" title="Sign out" aria-label="Sign out"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
        <button class="admin-mobile-action" id="usersRefreshButton" type="button" title="Reload page" aria-label="Reload page"><i class="fa-solid fa-rotate-right"></i></button>
    </div>
    <header class="admin-topbar" id="adminSidebar">
        <a class="admin-brand" href="<?= h(appUrl('Admin/AdminDashboard.php')) ?>"><img src="image/logo.jpeg" alt=""><div class="brand-name">dunkhome_<span>kicks</span></div></a>
        <div class="admin-nav-title">Administration</div>
        <nav class="admin-nav-links"><?php $adminNavigationShowThemeToggle = false; require __DIR__ . '/../includes/nav.php'; ?></nav>
        <div class="admin-sidebar-identity"><div class="admin-sidebar-avatar"><?= h($adminInitials) ?></div><div class="admin-sidebar-user"><strong><?= h($adminEmail) ?></strong><span>Administrator</span></div></div>
    </header>
    <main class="admin-content">
        <div class="users-heading"><div><div class="admin-eyebrow">Customer accounts</div><h1 class="admin-title">Manage users</h1><p class="admin-subtitle">Review registered accounts and their order activity.</p></div></div>
        <section class="admin-panel users-panel" aria-label="Registered users">
            <div class="users-toolbar">
                <div class="users-count"><strong><?= count($users) ?><?= count($users) === 100 ? '+' : '' ?> accounts shown</strong><span><?= $query !== '' ? 'Search results for “' . h($query) . '”' : 'Latest customer registrations' ?></span></div>
                <form class="users-search" method="get" role="search">
                    <input type="search" name="q" value="<?= h($query) ?>" placeholder="Search email address" aria-label="Search users">
                    <button class="admin-button" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                </form>
            </div>
            <?php if (!$users): ?>
                <div class="users-empty">No users match this search.</div>
            <?php else: ?>
                <div class="users-table-wrap">
                    <table class="users-table">
                        <caption class="sr-only">Registered users and order activity</caption>
                        <thead><tr><th>Account</th><th>User ID</th><th>Orders</th><th>Registered</th></tr></thead>
                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td data-label="Account"><div class="user-account"><span class="user-account-icon" aria-hidden="true"><i class="fa-regular fa-user"></i></span><strong class="user-email"><?= h((string) $user['email']) ?></strong></div></td>
                                <td data-label="User ID"><span class="user-id">#<?= (int) $user['id'] ?></span></td>
                                <td data-label="Orders"><span class="user-orders"><?= (int) $user['order_count'] ?></span></td>
                                <td data-label="Registered"><time class="user-registered" datetime="<?= h((string) $user['created_at']) ?>"><?= h((string) $user['created_at']) ?></time></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <footer class="admin-footer">DunkHome Kicks administration</footer>
</div>
<script src="assets/dunkhome-ui.js?v=20261001-loader4" defer></script>
<script src="assets/admin-sidebar-drawer.js?v=20261001-1" defer></script>
<script>document.getElementById('usersRefreshButton')?.addEventListener('click', () => window.location.reload());</script>
</body>
</html>
