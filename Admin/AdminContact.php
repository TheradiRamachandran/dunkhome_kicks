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
    <title>Contact page | DunkHome Kicks</title>
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
        <div class="admin-eyebrow">Storefront</div>
        <h1 class="admin-title">Contact page</h1>
        <p class="admin-subtitle">Review the contact details currently shown to customers.</p>
        <section class="admin-panel">
            <div class="admin-grid">
                <div><strong>Support email</strong><p>support@dunkhome-kicks.local</p></div>
                <div><strong>Message inbox</strong><p>No contact form submissions are stored by the current site.</p></div>
            </div>
            <div class="admin-toolbar">
                <a class="admin-button primary" href="User/Contact.php" target="_blank" rel="noopener">View public contact page</a>
                <a class="admin-button" href="index.php" target="_blank" rel="noopener">View website</a>
            </div>
        </section>
    </main>
    <footer class="admin-footer">DunkHome Kicks administration</footer>
</div>
<script src="assets/dunkhome-ui.js" defer></script>
</body>
</html>
