<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../includes/category_image_helpers.php';
requireAdmin();

$adminEmail = (string) ($_SESSION['admin_email'] ?? 'Administrator');
$emailName = explode('@', $adminEmail)[0] ?? '';
$adminInitials = strtoupper(substr((string) (preg_replace('/[^a-zA-Z0-9]/', '', $emailName) ?: 'AD'), 0, 2));

$message = '';
$messageType = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $message = 'This request expired. Refresh the page and try again.';
    } else {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');

        if ($categoryId < 1) {
            $message = 'Select a valid category.';
        } elseif ($action === 'activate') {
            $stmt = $conn->prepare('UPDATE categories SET is_active = 1 WHERE id = ? AND is_active = 0');
            if (!$stmt) {
                error_log('Category activation preparation failed: ' . $conn->error);
                $message = 'Unable to activate this category. Please try again.';
            } else {
                $stmt->bind_param('i', $categoryId);
                $updated = $stmt->execute();
                $message = $updated ? 'Category activated.' : 'Unable to activate this category.';
                $messageType = $updated ? 'success' : 'error';
                $stmt->close();
            }
        } elseif ($action === 'edit') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $slugInput = trim((string) ($_POST['slug'] ?? ''));
            $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $slugInput), '-'));
            $description = trim((string) ($_POST['description'] ?? ''));

            if ($name === '' || strlen($name) > 80 || $slug === '' || strlen($slug) > 100) {
                $message = 'Enter a category name and a valid slug.';
            } else {
                $check = $conn->prepare('SELECT id FROM categories WHERE (name = ? OR slug = ?) AND id <> ? LIMIT 1');
                if (!$check) {
                    error_log('Category edit duplicate check preparation failed: ' . $conn->error);
                    $message = 'Unable to check the category details. Please try again.';
                } else {
                    $check->bind_param('ssi', $name, $slug, $categoryId);
                    $check->execute();
                    $check->store_result();
                    $exists = $check->num_rows > 0;
                    $check->close();

                    if ($exists) {
                        $message = 'Another category already uses this name or slug.';
                    } elseif (!$conn->begin_transaction()) {
                        $message = 'Unable to update this category. Please try again.';
                    } else {
                        $current = $conn->prepare('SELECT name, image FROM categories WHERE id = ? LIMIT 1');
                        if (!$current) {
                            $conn->rollback();
                            $message = 'Unable to load this category. Please try again.';
                        } else {
                            $current->bind_param('i', $categoryId);
                            $current->execute();
                            $currentCategory = $current->get_result()->fetch_assoc();
                            $current->close();

                            if (!$currentCategory) {
                                $conn->rollback();
                                $message = 'Category not found.';
                            } else {
                                $imageUpload = storeCategoryImageUpload($_FILES['image'] ?? null);
                                if ($imageUpload['error'] !== null) {
                                    $conn->rollback();
                                    $message = (string) $imageUpload['error'];
                                } else {
                                $oldImagePath = (string) ($currentCategory['image'] ?? '');
                                $newImagePath = $imageUpload['path'];
                                $imagePath = is_string($newImagePath) ? $newImagePath : $oldImagePath;
                                $update = $conn->prepare('UPDATE categories SET name = ?, slug = ?, description = ?, image = ? WHERE id = ?');
                                $updateProducts = $conn->prepare('UPDATE products SET category = ? WHERE category COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci');

                                if (!$update || !$updateProducts) {
                                    $conn->rollback();
                                    if (is_string($newImagePath)) {
                                        removeManagedCategoryImage($newImagePath);
                                    }
                                    $message = 'Unable to prepare this category update. Please try again.';
                                } else {
                                    $oldName = (string) $currentCategory['name'];
                                    $update->bind_param('ssssi', $name, $slug, $description, $imagePath, $categoryId);
                                    $updated = $update->execute();
                                    $update->close();

                                    if ($updated) {
                                        $updateProducts->bind_param('ss', $name, $oldName);
                                        $updatedProducts = $updateProducts->execute();
                                    } else {
                                        $updatedProducts = false;
                                    }
                                    $updateProducts->close();

                                    if ($updated && $updatedProducts && $conn->commit()) {
                                        if (is_string($newImagePath) && $oldImagePath !== '') {
                                            removeManagedCategoryImage($oldImagePath);
                                        }
                                        $message = 'Category updated successfully.';
                                        $messageType = 'success';
                                    } else {
                                        $conn->rollback();
                                        if (is_string($newImagePath)) {
                                            removeManagedCategoryImage($newImagePath);
                                        }
                                        $message = 'Unable to update this category. Check that its name and slug are unique.';
                                    }
                                }
                                }
                            }
                        }
                    }
                }
            }
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare('SELECT name, image FROM categories WHERE id = ? LIMIT 1');
            if (!$stmt) {
                error_log('Category lookup preparation failed: ' . $conn->error);
                $message = 'Unable to load this category. Please try again.';
            } else {
                $stmt->bind_param('i', $categoryId);
                $loaded = $stmt->execute();
                $category = $loaded ? $stmt->get_result()->fetch_assoc() : null;
                $stmt->close();

                if (!$loaded) {
                    error_log('Category lookup failed: ' . $conn->error);
                    $message = 'Unable to load this category. Please try again.';
                } elseif (!$category) {
                    $message = 'Category not found.';
                } else {
                    $productCheck = $conn->prepare('SELECT COUNT(*) AS total FROM products WHERE category COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci');
                    if (!$productCheck) {
                        error_log('Category product check preparation failed: ' . $conn->error);
                        $message = 'Unable to check whether products use this category. Please try again.';
                    } else {
                        $productCheck->bind_param('s', $category['name']);
                        $checked = $productCheck->execute();
                        $productResult = $checked ? $productCheck->get_result()->fetch_assoc() : null;
                        $productCheck->close();

                        if (!$checked || !$productResult) {
                            error_log('Category product check failed: ' . $conn->error);
                            $message = 'Unable to check whether products use this category. Please try again.';
                        } elseif ((int) $productResult['total'] > 0) {
                            $message = 'This category is assigned to products and cannot be deleted. Remove or reassign those products first.';
                        } else {
                            $delete = $conn->prepare('DELETE FROM categories WHERE id = ?');
                            if (!$delete) {
                                error_log('Category delete preparation failed: ' . $conn->error);
                                $message = 'Unable to delete this category. Please try again.';
                            } else {
                                $delete->bind_param('i', $categoryId);
                                $deleted = $delete->execute();
                                $deletedCategory = $deleted && $delete->affected_rows > 0;
                                $message = $deletedCategory ? 'Category deleted.' : 'Unable to delete this category.';
                                $messageType = $deletedCategory ? 'success' : 'error';
                                $delete->close();
                                if ($deletedCategory) {
                                    removeManagedCategoryImage((string) ($category['image'] ?? ''));
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

$categories = [];
$result = $conn->query(
    'SELECT c.id, c.name, c.slug, c.description, c.image, c.is_active, c.created_at,
            COUNT(p.id) AS product_count
     FROM categories c
    LEFT JOIN products p ON p.category COLLATE utf8mb4_unicode_ci = c.name
    GROUP BY c.id, c.name, c.slug, c.description, c.image, c.is_active, c.created_at
     ORDER BY c.name'
);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}
$totalCategoryCount = count($categories);
$activeCategoryCount = 0;
foreach ($categories as $category) {
    if ((int) $category['is_active'] === 1) {
        $activeCategoryCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../includes/favicon.php'; ?>
    <base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage categories | DunkHome Kicks</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-theme2">
    <link rel="stylesheet" href="assets/admin-pages.css?v=20261003-theme2">
    <link rel="stylesheet" href="assets/admin-navigation.css?v=20261003-theme1">
    <style>
        .category-heading { margin-bottom: 24px; }
        .category-heading .admin-subtitle { max-width: 620px; margin-bottom: 0; }
        .category-panel { overflow: hidden; padding: 0; }
        .category-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 20px 22px; border-bottom: 1px solid var(--admin-line); }
        .category-count strong,.category-count span { display: block; }
        .category-count strong { color: var(--admin-text); font-size: 13px; }
        .category-count span { margin-top: 4px; color: var(--admin-muted); font-size: 10px; }
        .category-ident { display:flex; align-items:center; gap:11px; }
        .category-thumbnail { width:44px; height:44px; flex:0 0 44px; border:1px solid var(--admin-line); border-radius:9px; object-fit:cover; background:rgba(255,255,255,.04); }
        .category-add { flex: 0 0 auto; }
        .category-table-wrap { overflow-x: auto; }
        .category-table { width: 100%; min-width: 700px; border-collapse: collapse; }
        .category-table caption { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        .category-table th,.category-table td { padding: 14px 18px; text-align: left; vertical-align: middle; }
        .category-table th { color: var(--admin-muted); font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .category-table td { border-top: 1px solid var(--admin-line); color: var(--admin-text); font-size: 11px; }
        .category-table td:first-child { min-width: 220px; }
        .category-table td:first-child strong { font-size: 12px; }
        .category-table td:first-child small { display: inline-block; max-width: 360px; margin-top: 5px; color: var(--admin-muted); font-size: 10px; line-height: 1.5; }
        .category-table code { color: var(--admin-green); font: 10px "DM Sans",sans-serif; }
        .category-status { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .category-status::before { width: 6px; height: 6px; border-radius: 50%; background: #68766f; content: ""; }
        .category-status.active::before { background: var(--admin-green); box-shadow: 0 0 10px rgba(121,230,170,.42); }
        .category-actions { display: flex; flex-wrap: wrap; gap: 7px; }
        .category-actions .admin-button { min-height: 34px; padding: 7px 10px; font-size: 10px; }
        .category-empty { padding: 36px 22px; color: var(--admin-muted); font-size: 12px; text-align: center; }
        .category-dialog { width: min(520px, calc(100% - 24px)); max-height: calc(100% - 32px); padding: 0; overflow: auto; border: 1px solid var(--admin-line); border-radius: 14px; color: var(--admin-text); background: var(--admin-bg); box-shadow: 0 24px 80px rgba(0,0,0,.42); }
        .category-dialog::backdrop { background: rgba(0,0,0,.62); backdrop-filter: blur(4px); }
        .category-dialog form { padding: 22px; }
        .category-dialog-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .category-dialog-head h2 { margin: 0; color: var(--admin-text); font-size: 17px; }
        .category-dialog-head p { margin: 5px 0 0; color: var(--admin-muted); font-size: 11px; }
        .category-edit-fields { display: grid; gap: 14px; }
        .category-edit-fields label { display: block; margin-bottom: 6px; color: var(--admin-text); font-size: 10px; font-weight: 700; }
        .category-edit-fields input,.category-edit-fields textarea { width: 100%; min-height: 42px; padding: 10px 11px; border: 1px solid var(--admin-line); border-radius: 8px; outline: none; color: var(--admin-text); background: rgba(255,255,255,.035); font: 12px "DM Sans",sans-serif; }
        .category-edit-fields input[type=file] { min-height:58px; padding:8px; border-style:solid; border-color:rgba(121,230,170,.2); border-radius:11px; background:linear-gradient(135deg,rgba(121,230,170,.07),rgba(255,255,255,.025)); color:var(--admin-muted); cursor:pointer; }
        .category-edit-fields input[type=file]::file-selector-button { min-height:39px; margin-right:12px; padding:0 14px; border:0; border-radius:8px; color:#06140d; background:linear-gradient(135deg,#90efbb,#56d993); font:700 11px "DM Sans",sans-serif; cursor:pointer; transition:filter .18s,transform .18s; }
        .category-edit-fields input[type=file]::file-selector-button:hover { filter:brightness(1.06); transform:translateY(-1px); }
        .category-edit-fields input[type=file]:hover { border-color:rgba(121,230,170,.45); }
        .category-edit-fields input[type=file]:focus-visible { outline:2px solid rgba(121,230,170,.65); outline-offset:3px; }
        body.light .category-edit-fields input[type=file] { background:linear-gradient(135deg,rgba(121,230,170,.12),rgba(255,255,255,.95)); }
        .category-edit-fields textarea { min-height: 96px; resize: vertical; }
        .category-edit-image-preview { display:block; width:160px; aspect-ratio:16/10; margin-top:9px; border:1px solid var(--admin-line); border-radius:9px; object-fit:cover; }
        .category-edit-image-preview[hidden] { display:none; }
        .category-edit-help { margin:6px 0 0; color:var(--admin-muted); font-size:10px; line-height:1.5; }
        .category-edit-fields input:focus,.category-edit-fields textarea:focus { border-color: rgba(121,230,170,.62); box-shadow: 0 0 0 3px rgba(121,230,170,.09); }
        .category-dialog-actions { display: flex; justify-content: flex-end; gap: 9px; margin-top: 20px; }
        .category-delete-dialog { width: min(440px, calc(100% - 24px)); }
        .category-delete-dialog form { padding: 24px; }
        .category-delete-content { text-align: center; }
        .category-delete-icon { width: 46px; height: 46px; display: grid; place-items: center; margin: 0 auto 14px; border: 1px solid rgba(255,123,123,.24); border-radius: 14px; color: #ff8989; background: rgba(255,123,123,.09); font-size: 17px; }
        .category-delete-content h2 { margin: 0; color: var(--admin-text); font-size: 18px; }
        .category-delete-content p { margin: 9px 0 0; color: var(--admin-muted); font-size: 11px; line-height: 1.6; overflow-wrap: anywhere; }
        .category-delete-content strong { color: var(--admin-text); }
        .category-delete-content .category-delete-note { margin-top: 12px; padding: 9px 10px; border: 1px solid var(--admin-line); border-radius: 8px; text-align: left; }
        .category-delete-dialog .category-dialog-actions { justify-content: center; }
        @media(max-width:640px) {
            .category-heading { margin-bottom: 18px; }
            .category-toolbar { align-items: stretch; flex-direction: column; gap: 14px; padding: 17px; }
            .category-add { width: 100%; justify-content: center; }
            .category-table-wrap { overflow: visible; }
            .category-table { display: block; min-width: 0; }
            .category-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; }
            .category-table tbody { display: block; padding: 0 12px; }
            .category-table tr { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); border-bottom: 1px solid var(--admin-line); }
            .category-table td { display: block; min-width: 0; padding: 10px; border: 0; overflow-wrap: anywhere; }
            .category-table td::before { display: block; margin-bottom: 5px; color: var(--admin-muted); content: attr(data-label); font-size: 8px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
            .category-table td:first-child { min-width: 0; grid-column: 1 / -1; }
            .category-table td:last-child { grid-column: 1 / -1; }
            .category-actions .admin-button { flex: 1 1 auto; min-width: 76px; }
            .category-dialog form { padding: 18px; }
            .category-dialog-actions { flex-direction: column-reverse; }
            .category-dialog-actions .admin-button { width: 100%; justify-content: center; }
            .category-delete-dialog form { padding: 20px 16px; }
            .category-delete-dialog .category-dialog-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="admin-page admin-sidebar-layout">
    <div class="admin-menu-overlay" id="adminMenuOverlay"></div>
    <button class="admin-mobile-menu" id="adminMobileMenu" type="button" aria-label="Open administration menu" aria-expanded="false">
        <i class="fa-solid fa-bars"></i>
    </button>
    <div class="admin-mobile-actions" aria-label="Admin controls">
        <button class="admin-mobile-action" id="themeToggle" type="button" title="Toggle theme" aria-label="Switch to light theme">☀️</button>
        <a class="admin-mobile-action" href="<?= h(appUrl('Admin/Logout.php?scope=admin')) ?>" title="Sign out" aria-label="Sign out"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
        <button class="admin-mobile-action" id="categoryRefreshButton" type="button" title="Reload page" aria-label="Reload page"><i class="fa-solid fa-rotate-right"></i></button>
    </div>
    <header class="admin-topbar" id="adminSidebar">
        <a class="admin-brand" href="<?= h(appUrl('Admin/AdminDashboard.php')) ?>">
            <img src="image/logo.jpg" alt="">
            <div class="brand-name">dunkhome_<span>kicks</span></div>
        </a>
        <div class="admin-nav-title">Administration</div>
        <nav class="admin-nav-links"><?php $adminNavigationShowThemeToggle = false; require __DIR__ . '/../includes/nav.php'; ?></nav>
        <div class="admin-sidebar-identity">
            <div class="admin-sidebar-avatar"><?= h($adminInitials) ?></div>
            <div class="admin-sidebar-user"><strong><?= h($adminEmail) ?></strong><span>Administrator</span></div>
        </div>
    </header>
    <main class="admin-content">
        <div class="category-heading">
            <div>
                <div class="admin-eyebrow">Catalog management</div>
                <h1 class="admin-title">Manage categories</h1>
                <p class="admin-subtitle">Control which categories are available when adding products.</p>
            </div>
        </div>
        <?php if ($message !== ''): ?>
            <div class="admin-message <?= $messageType === 'success' ? 'success' : '' ?>" role="status"><?= h($message) ?></div>
        <?php endif; ?>
        <section class="admin-panel category-panel" aria-label="Category list">
            <div class="category-toolbar">
                <div class="category-count"><strong><?= $totalCategoryCount ?> categories</strong><span><?= $activeCategoryCount ?> active · Manage availability and product assignments.</span></div>
                <a class="admin-button primary category-add" href="<?= h(appUrl('Admin/AddCategory.php')) ?>"><i class="fa-solid fa-plus"></i> Add category</a>
            </div>
            <?php if (!$categories): ?>
                <div class="category-empty">No categories yet. Add one to make it available in product entry.</div>
            <?php else: ?>
                <div class="category-table-wrap">
                    <table class="category-table">
                        <caption class="sr-only">Categories and their product assignment status</caption>
                        <thead><tr><th>Category</th><th>Slug</th><th>Products</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td data-label="Category"><div class="category-ident"><?php if (!empty($category['image'])): ?><img class="category-thumbnail" src="<?= h(appUrl((string) $category['image'])) ?>" alt=""><?php else: ?><span class="category-thumbnail" aria-hidden="true"></span><?php endif; ?><div><strong><?= h((string) $category['name']) ?></strong><?php if ($category['description']): ?><br><small><?= h((string) $category['description']) ?></small><?php endif; ?></div></div></td>
                                <td data-label="Slug"><code><?= h((string) $category['slug']) ?></code></td>
                                <td data-label="Products"><?= (int) $category['product_count'] ?></td>
                                <td data-label="Status"><span class="category-status <?= (int) $category['is_active'] === 1 ? 'active' : '' ?>"><?= (int) $category['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                                <td data-label="Actions">
                                    <form class="category-actions" method="post">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                        <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                                        <button class="admin-button" type="button" data-category-edit data-id="<?= (int) $category['id'] ?>" data-name="<?= h((string) $category['name']) ?>" data-slug="<?= h((string) $category['slug']) ?>" data-description="<?= h((string) ($category['description'] ?? '')) ?>" data-image="<?= !empty($category['image']) ? h(appUrl((string) $category['image'])) : '' ?>" aria-label="Edit <?= h((string) $category['name']) ?>">Edit</button>
                                        <?php if ((int) $category['is_active'] !== 1): ?>
                                            <button class="admin-button" name="action" value="activate" type="submit" aria-label="Activate <?= h((string) $category['name']) ?>">Activate</button>
                                        <?php endif; ?>
                                        <button class="admin-button danger" type="button" data-delete-category="<?= h((string) $category['name']) ?>" data-id="<?= (int) $category['id'] ?>" aria-label="Delete <?= h((string) $category['name']) ?>">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <dialog class="category-dialog" id="categoryEditDialog" aria-labelledby="categoryEditTitle">
            <form method="post" enctype="multipart/form-data">
                <div class="category-dialog-head">
                    <div>
                        <h2 id="categoryEditTitle">Edit category</h2>
                        <p>Update the category details used across the catalog.</p>
                    </div>
                </div>
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="category_id" id="editCategoryId">
                <input type="hidden" name="action" value="edit">
                <div class="category-edit-fields">
                    <div>
                        <label for="editCategoryName">Category name</label>
                        <input id="editCategoryName" name="name" maxlength="80" required>
                    </div>
                    <div>
                        <label for="editCategorySlug">URL slug</label>
                        <input id="editCategorySlug" name="slug" maxlength="100" required>
                    </div>
                    <div>
                        <label for="editCategoryDescription">Description</label>
                        <textarea id="editCategoryDescription" name="description" maxlength="2000"></textarea>
                    </div>
                    <div>
                        <label for="editCategoryImage">Category image</label>
                        <input id="editCategoryImage" name="image" type="file" accept="image/jpeg,image/png,image/webp">
                        <img class="category-edit-image-preview" id="editCategoryImagePreview" alt="Current category image preview" hidden>
                        <p class="category-edit-help">Choose a new JPG, PNG or WEBP image to replace the current one. Maximum size: 5 MB.</p>
                    </div>
                </div>
                <div class="category-dialog-actions">
                    <button class="admin-button" type="button" id="cancelCategoryEdit">Cancel</button>
                    <button class="admin-button primary" type="submit">Save changes</button>
                </div>
            </form>
        </dialog>
        <dialog class="category-dialog category-delete-dialog" id="categoryDeleteDialog" aria-labelledby="categoryDeleteTitle" aria-describedby="categoryDeleteDescription">
            <form method="post">
                <div class="category-delete-content">
                    <span class="category-delete-icon" aria-hidden="true"><i class="fa-solid fa-trash"></i></span>
                    <h2 id="categoryDeleteTitle">Delete category?</h2>
                    <p id="categoryDeleteDescription">Delete <strong id="categoryDeleteName"></strong>? This action cannot be undone.</p>
                    <p class="category-delete-note">Categories assigned to products cannot be deleted. Remove or reassign those products first.</p>
                </div>
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="category_id" id="deleteCategoryId">
                <input type="hidden" name="action" value="delete">
                <div class="category-dialog-actions">
                    <button class="admin-button" type="button" id="cancelCategoryDelete">Cancel</button>
                    <button class="admin-button danger" type="submit"><i class="fa-solid fa-trash"></i> Delete category</button>
                </div>
            </form>
        </dialog>
    </main>
    <footer class="admin-footer">DunkHome Kicks administration</footer>
</div>
<script src="assets/dunkhome-ui.js?v=20261001-loader4" defer></script>
<script src="assets/admin-sidebar-drawer.js?v=20261001-1" defer></script>
<script>
document.getElementById('categoryRefreshButton')?.addEventListener('click', () => window.location.reload());

const categoryEditDialog = document.getElementById('categoryEditDialog');
document.querySelectorAll('[data-category-edit]').forEach(button => {
    button.addEventListener('click', () => {
        document.getElementById('editCategoryId').value = button.dataset.id;
        document.getElementById('editCategoryName').value = button.dataset.name;
        document.getElementById('editCategorySlug').value = button.dataset.slug;
        document.getElementById('editCategoryDescription').value = button.dataset.description;
        const imageInput = document.getElementById('editCategoryImage');
        const imagePreview = document.getElementById('editCategoryImagePreview');
        imageInput.value = '';
        imagePreview.dataset.currentImage = button.dataset.image || '';
        imagePreview.src = imagePreview.dataset.currentImage;
        imagePreview.hidden = !imagePreview.dataset.currentImage;
        categoryEditDialog.showModal();
        document.getElementById('editCategoryName').focus();
    });
});

document.getElementById('editCategoryImage')?.addEventListener('change', event => {
    const input = event.currentTarget;
    const preview = document.getElementById('editCategoryImagePreview');
    if (!input.files[0]) {
        preview.src = preview.dataset.currentImage || '';
        preview.hidden = !preview.dataset.currentImage;
        return;
    }
    preview.src = URL.createObjectURL(input.files[0]);
    preview.hidden = false;
});

document.getElementById('cancelCategoryEdit')?.addEventListener('click', () => categoryEditDialog.close());
categoryEditDialog?.addEventListener('click', event => {
    if (event.target === categoryEditDialog) categoryEditDialog.close();
});

const categoryDeleteDialog = document.getElementById('categoryDeleteDialog');
document.querySelectorAll('[data-delete-category]').forEach(button => {
    button.addEventListener('click', () => {
        document.getElementById('deleteCategoryId').value = button.dataset.id;
        document.getElementById('categoryDeleteName').textContent = button.dataset.deleteCategory;
        categoryDeleteDialog.showModal();
        document.getElementById('cancelCategoryDelete').focus();
    });
});

document.getElementById('cancelCategoryDelete')?.addEventListener('click', () => categoryDeleteDialog.close());
categoryDeleteDialog?.addEventListener('click', event => {
    if (event.target === categoryDeleteDialog) categoryDeleteDialog.close();
});
</script>
</body>
</html>

