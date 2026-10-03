<?php
if (!defined('ADD_PRODUCT_VIEW')) {
    http_response_code(404);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../includes/favicon.php'; ?>
    <base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07100d">
    <title>Add product | DunkHome Kicks</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-theme2">
    <link rel="stylesheet" href="assets/admin-pages.css?v=20261003-theme2">
    <link rel="stylesheet" href="assets/admin-navigation.css?v=20261003-theme1">
    <style>
        .product-heading { margin-bottom: 26px; }
        .product-heading .admin-subtitle { max-width: 620px; margin-bottom: 0; }
        .product-form-panel { max-width: 1000px; padding: 0; overflow: hidden; border: 1px solid var(--admin-line); border-radius: 16px; background: var(--admin-panel); box-shadow: 0 22px 60px rgba(0,0,0,.18); }
        .product-form-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 22px 24px; border-bottom: 1px solid var(--admin-line); }
        .product-form-head h2 { margin: 0; color: var(--admin-text); font-size: 15px; }
        .product-form-head p { margin: 5px 0 0; color: var(--admin-muted); font-size: 11px; line-height: 1.5; }
        .product-form-icon { width: 42px; height: 42px; flex: 0 0 42px; display: grid; place-items: center; border: 1px solid rgba(121,230,170,.2); border-radius: 12px; color: var(--admin-green); background: rgba(121,230,170,.08); }
        .product-form { padding: 24px; }
        .product-fields { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 18px 16px; }
        .product-field { min-width: 0; margin: 0; }
        .product-field.full { grid-column: 1 / -1; }
        .product-field label,.product-photo label { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 8px; color: var(--admin-text); font-size: 11px; font-weight: 700; }
        .product-field label span { color: var(--admin-muted); font-size: 10px; font-weight: 500; }
        .product-field input,.product-field select,.product-field textarea { width: 100%; min-height: 44px; padding: 11px 12px; border: 1px solid var(--admin-line); border-radius: 9px; outline: none; color: var(--admin-text); background: rgba(255,255,255,.035); font: 12px "DM Sans",sans-serif; transition: border-color .18s,box-shadow .18s,background .18s; }
        .product-field textarea { min-height: 116px; resize: vertical; line-height: 1.6; }
        .product-field input:focus,.product-field select:focus,.product-field textarea:focus { border-color: rgba(121,230,170,.62); background: rgba(121,230,170,.045); box-shadow: 0 0 0 3px rgba(121,230,170,.09); }
        body.light .product-field input,body.light .product-field select,body.light .product-field textarea { background: rgba(255,255,255,.85); }
        body.light .product-field input:focus,body.light .product-field select:focus,body.light .product-field textarea:focus { background: #fff; }
        .product-field select option { color: #18251d; }
        .product-help { margin: 7px 0 0; color: var(--admin-muted); font-size: 10px; line-height: 1.5; }
        .product-photos { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 12px; grid-column: 1 / -1; }
        .product-photo { min-width: 0; padding: 15px; border: 1px dashed rgba(121,230,170,.28); border-radius: 10px; background: rgba(121,230,170,.035); }
        .product-photo label { display: block; }
        .product-photo input { display: block; width: 100%; max-width: 100%; color: var(--admin-muted); font: 10px "DM Sans",sans-serif; }
        .product-photo input::file-selector-button { max-width: 100%; margin: 0 8px 7px 0; padding: 8px 9px; border: 1px solid var(--admin-line); border-radius: 7px; color: var(--admin-text); background: rgba(255,255,255,.06); font: 600 10px "DM Sans",sans-serif; cursor: pointer; }
        .product-form-footer { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--admin-line); }
        .product-form-actions { display: flex; flex-wrap: wrap; gap: 9px; }
        .product-submit { min-width: 150px; }
        .product-message { margin-bottom: 18px; }
        @media(max-width:760px) { .product-photos { grid-template-columns: 1fr; } }
        @media(max-width:640px) {
            .product-heading { align-items: flex-start; flex-direction: column; gap: 7px; margin-bottom: 18px; }
            .product-form-head,.product-form { padding: 18px; }
            .product-fields { grid-template-columns: 1fr; gap: 16px; }
            .product-field.full,.product-photos { grid-column: auto; }
            .product-form-footer { align-items: stretch; flex-direction: column; }
            .product-form-actions { display: grid; grid-template-columns: 1fr 1fr; }
            .product-form-actions .admin-button { width: 100%; justify-content: center; }
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
        <button class="admin-mobile-action" id="productRefreshButton" type="button" title="Reload page" aria-label="Reload page"><i class="fa-solid fa-rotate-right"></i></button>
    </div>
    <header class="admin-topbar" id="adminSidebar">
        <a class="admin-brand" href="<?= h(appUrl('Admin/AdminDashboard.php')) ?>"><img src="image/logo.jpg" alt=""><div class="brand-name">dunkhome_<span>kicks</span></div></a>
        <div class="admin-nav-title">Administration</div>
        <nav class="admin-nav-links"><?php $adminNavigationShowThemeToggle = false; require __DIR__ . '/../includes/nav.php'; ?></nav>
        <div class="admin-sidebar-identity"><div class="admin-sidebar-avatar"><?= h($adminInitials) ?></div><div class="admin-sidebar-user"><strong><?= h($adminEmail) ?></strong><span>Administrator</span></div></div>
    </header>
    <main class="admin-content">
        <div class="product-heading">
            <div><div class="admin-eyebrow">Catalog management</div><h1 class="admin-title">Add product</h1><p class="admin-subtitle">Add a product with three gallery photos. Quantity is intentionally not included in this form or database.</p></div>
        </div>
        <?php if ($message !== ''): ?>
            <div class="admin-message product-message <?= $messageType === 'success' ? 'success' : '' ?>" role="status"><?= h($message) ?></div>
        <?php endif; ?>
        <section class="product-form-panel" aria-labelledby="productDetailsTitle">
            <div class="product-form-head"><div><h2 id="productDetailsTitle">Product details</h2><p>Enter the listing information and add three product photos.</p></div><span class="product-form-icon" aria-hidden="true"><i class="fa-solid fa-box-open"></i></span></div>
            <form class="product-form" method="post" enctype="multipart/form-data">
                <div class="product-fields">
                    <div class="admin-field product-field"><label for="name">Product name</label><input id="name" name="name" required maxlength="180" value="<?= h((string) ($_POST['name'] ?? '')) ?>" placeholder="e.g. Dunk Low Retro"></div>
                    <div class="admin-field product-field"><label for="category">Category</label><select id="category" name="category" required><option value="">Select category</option><?php foreach ($categories as $categoryOption): ?><option value="<?= h($categoryOption) ?>" <?= (string) ($_POST['category'] ?? '') === $categoryOption ? 'selected' : '' ?>><?= h($categoryOption) ?></option><?php endforeach; ?><?php if (!$categories): ?><option disabled>No active categories</option><?php endif; ?></select></div>
                    <div class="admin-field product-field"><label for="price">Price</label><input id="price" name="price" type="number" min="0.01" step="0.01" required value="<?= h((string) ($_POST['price'] ?? '')) ?>" placeholder="0.00"></div>
                    <div class="admin-field product-field full"><label for="description">Description <span>Optional</span></label><textarea id="description" name="description" maxlength="5000" placeholder="Describe the product..."><?= h((string) ($_POST['description'] ?? '')) ?></textarea></div>
                    <div class="product-photos">
                        <div class="product-photo"><label for="photo1">Photo 1</label><input id="photo1" name="photo1" type="file" accept="image/jpeg,image/png,image/webp" required><p class="product-help">JPG, PNG or WEBP. Max 5 MB.</p></div>
                        <div class="product-photo"><label for="photo2">Photo 2</label><input id="photo2" name="photo2" type="file" accept="image/jpeg,image/png,image/webp" required><p class="product-help">JPG, PNG or WEBP. Max 5 MB.</p></div>
                        <div class="product-photo"><label for="photo3">Photo 3</label><input id="photo3" name="photo3" type="file" accept="image/jpeg,image/png,image/webp" required><p class="product-help">JPG, PNG or WEBP. Max 5 MB.</p></div>
                    </div>
                </div>
                <div class="product-form-footer"><span class="product-help">All three product photos are required.</span><div class="product-form-actions"><a class="admin-button" href="<?= h(appUrl('Admin/AdminDashboard.php')) ?>">Cancel</a><button class="admin-button primary product-submit" type="submit"><i class="fa-solid fa-plus"></i> Add product</button></div></div>
            </form>
        </section>
    </main>
    <footer class="admin-footer">DunkHome Kicks administration</footer>
</div>
<script src="assets/dunkhome-ui.js?v=20261001-loader4" defer></script>
<script src="assets/admin-sidebar-drawer.js?v=20261001-1" defer></script>
<script>document.getElementById('productRefreshButton')?.addEventListener('click', () => window.location.reload());</script>
</body>
</html>

