<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reviews | DunkHome Kicks</title>
    <link rel="stylesheet" href="assets/dunkhome-ui.css">
    <link rel="stylesheet" href="assets/admin-pages.css">
    <link rel="stylesheet" href="assets/admin-navigation.css">
</head>
<body>
<div class="admin-page">
    <header class="admin-topbar">
        <a class="admin-brand" href="Admin/AdminDashboard.php">DunkHome <span>Kicks</span></a>
        <nav class="admin-nav-links"><?php require __DIR__ . '/../includes/nav.php'; ?></nav>
    </header>
    <main class="admin-content">
        <div class="admin-eyebrow">Customer feedback</div>
        <h1 class="admin-title">Reviews</h1>
        <p class="admin-subtitle">Product reviews are not currently collected or stored by this site.</p>
        <section class="admin-panel">
            <div class="admin-empty">
                <strong>No review records are available.</strong><br>
                A customer review feature and review table must be added before reviews can be moderated here.
            </div>
        </section>
    </main>
    <footer class="admin-footer">DunkHome Kicks administration</footer>
</div>
<script src="assets/dunkhome-ui.js" defer></script>
</body>
</html>
