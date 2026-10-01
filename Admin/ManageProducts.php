<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
requireAdmin();

$adminEmail = (string) ($_SESSION['admin_email'] ?? 'Administrator');
$emailName = explode('@', $adminEmail)[0] ?? '';
$adminInitials = strtoupper(substr((string) (preg_replace('/[^a-zA-Z0-9]/', '', $emailName) ?: 'AD'), 0, 2));

$message = '';
$messageType = 'error';
$query = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));

function removeManagedProductImage(string $relativePath): void
{
    $uploadDirectory = realpath(__DIR__ . '/../uploads/products');
    $imagePath = realpath(__DIR__ . '/../' . ltrim($relativePath, '/\\'));

    if ($uploadDirectory !== false && $imagePath !== false && strpos($imagePath, $uploadDirectory . DIRECTORY_SEPARATOR) === 0 && is_file($imagePath)) {
        @unlink($imagePath);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? 'visibility');

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $message = 'This request expired. Refresh the page and try again.';
    } elseif ($productId < 1) {
        $message = 'Select a valid product.';
    } elseif ($action === 'visibility') {
        $isActive = (int) ($_POST['is_active'] ?? -1);
        if (!in_array($isActive, [0, 1], true)) {
            $message = 'Select a valid product status.';
        } else {
            $stmt = $conn->prepare('UPDATE products SET is_active = ? WHERE id = ?');
            if (!$stmt) {
                error_log('Product visibility update preparation failed: ' . $conn->error);
                $message = 'Unable to update this product. Please try again.';
            } else {
                $stmt->bind_param('ii', $isActive, $productId);
                $updated = $stmt->execute();
                $stmt->close();
                $message = $updated ? 'Product visibility updated.' : 'Unable to update this product.';
                $messageType = $updated ? 'success' : 'error';
            }
        }
    } elseif ($action === 'edit') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($name === '' || strlen($name) > 180 || $category === '' || $price === false || $price <= 0) {
            $message = 'Enter a product name, category, and valid price.';
        } else {
            $categoryCheck = $conn->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
            if (!$categoryCheck) {
                error_log('Product category check preparation failed: ' . $conn->error);
                $message = 'Unable to validate the product category. Please try again.';
            } else {
                $categoryCheck->bind_param('s', $category);
                $categoryCheck->execute();
                $categoryCheck->store_result();
                $categoryExists = $categoryCheck->num_rows > 0;
                $categoryCheck->close();

                if (!$categoryExists) {
                    $message = 'Select a category that exists.';
                } else {
                    $currentStmt = $conn->prepare('SELECT image1, image2, image3 FROM products WHERE id = ? LIMIT 1');
                    if (!$currentStmt) {
                        error_log('Product image lookup preparation failed: ' . $conn->error);
                        $message = 'Unable to load the existing product photos. Please try again.';
                    } else {
                        $currentStmt->bind_param('i', $productId);
                        $loaded = $currentStmt->execute();
                        $currentProduct = $loaded ? $currentStmt->get_result()->fetch_assoc() : null;
                        $currentStmt->close();

                        if (!$loaded || !$currentProduct) {
                            $message = 'Product not found.';
                        } else {
                            $imagePaths = [
                                'image1' => (string) $currentProduct['image1'],
                                'image2' => (string) $currentProduct['image2'],
                                'image3' => (string) $currentProduct['image3'],
                            ];
                            $newImages = [];
                            $imageError = '';
                            $allowedImageTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

                            foreach (['photo1' => 'image1', 'photo2' => 'image2', 'photo3' => 'image3'] as $fieldName => $imageColumn) {
                                $file = $_FILES[$fieldName] ?? null;
                                if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                                    continue;
                                }
                                if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 5 * 1024 * 1024 || @getimagesize((string) ($file['tmp_name'] ?? '')) === false) {
                                    $imageError = 'Each replacement photo must be JPG, PNG or WEBP and no larger than 5 MB.';
                                    break;
                                }

                                $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
                                if (!isset($allowedImageTypes[$mime])) {
                                    $imageError = 'Each replacement photo must be JPG, PNG or WEBP and no larger than 5 MB.';
                                    break;
                                }

                                $uploadDirectory = __DIR__ . '/../uploads/products';
                                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true)) {
                                    $imageError = 'Unable to save the replacement photo. Please try again.';
                                    break;
                                }
                                $filename = bin2hex(random_bytes(12)) . '.' . $allowedImageTypes[$mime];
                                $destination = $uploadDirectory . '/' . $filename;
                                if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
                                    $imageError = 'Unable to save the replacement photo. Please try again.';
                                    break;
                                }

                                $imagePaths[$imageColumn] = 'uploads/products/' . $filename;
                                $newImages[] = $imagePaths[$imageColumn];
                            }

                            if ($imageError !== '') {
                                foreach ($newImages as $newImage) {
                                    removeManagedProductImage($newImage);
                                }
                                $message = $imageError;
                            } else {
                                $stmt = $conn->prepare('UPDATE products SET name = ?, category = ?, price = ?, description = ?, image1 = ?, image2 = ?, image3 = ? WHERE id = ?');
                                if (!$stmt) {
                                    error_log('Product edit preparation failed: ' . $conn->error);
                                    foreach ($newImages as $newImage) {
                                        removeManagedProductImage($newImage);
                                    }
                                    $message = 'Unable to prepare this product update. Please try again.';
                                } else {
                                    $stmt->bind_param('ssdssssi', $name, $category, $price, $description, $imagePaths['image1'], $imagePaths['image2'], $imagePaths['image3'], $productId);
                                    $updated = $stmt->execute();
                                    $stmt->close();

                                    if ($updated) {
                                        foreach (['image1', 'image2', 'image3'] as $imageColumn) {
                                            if ($imagePaths[$imageColumn] !== (string) $currentProduct[$imageColumn]) {
                                                removeManagedProductImage((string) $currentProduct[$imageColumn]);
                                            }
                                        }
                                        $message = 'Product updated successfully.';
                                        $messageType = 'success';
                                    } else {
                                        foreach ($newImages as $newImage) {
                                            removeManagedProductImage($newImage);
                                        }
                                        $message = 'Unable to update this product.';
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare('SELECT image1, image2, image3 FROM products WHERE id = ? LIMIT 1');
        if (!$stmt) {
            error_log('Product delete lookup preparation failed: ' . $conn->error);
            $message = 'Unable to load this product. Please try again.';
        } else {
            $stmt->bind_param('i', $productId);
            $loaded = $stmt->execute();
            $productImages = $loaded ? $stmt->get_result()->fetch_assoc() : null;
            $stmt->close();

            if (!$loaded) {
                error_log('Product delete lookup failed: ' . $conn->error);
                $message = 'Unable to load this product. Please try again.';
            } elseif (!$productImages) {
                $message = 'Product not found.';
            } else {
                $delete = $conn->prepare('DELETE FROM products WHERE id = ?');
                if (!$delete) {
                    error_log('Product delete preparation failed: ' . $conn->error);
                    $message = 'Unable to delete this product. Please try again.';
                } else {
                    $delete->bind_param('i', $productId);
                    $deleted = $delete->execute();
                    $deleteError = $delete->error;
                    $affectedRows = $delete->affected_rows;
                    $delete->close();

                    if (!$deleted) {
                        error_log('Product delete failed: ' . $deleteError);
                        $message = 'Unable to delete this product. Please try again.';
                    } elseif ($affectedRows === 0) {
                        $message = 'Product not found.';
                    } else {
                        foreach (['image1', 'image2', 'image3'] as $imageColumn) {
                            removeManagedProductImage((string) $productImages[$imageColumn]);
                        }
                        $message = 'Product deleted successfully.';
                        $messageType = 'success';
                    }
                }
            }
        }
    } else {
        $message = 'Select a valid product action.';
    }
}

$categoryOptions = [];
$categoryResult = $conn->query('SELECT name FROM categories ORDER BY name');
if ($categoryResult) {
    while ($categoryRow = $categoryResult->fetch_assoc()) {
        $categoryOptions[] = (string) $categoryRow['name'];
    }
}

$products = [];
if ($query === '') {
    $result = $conn->query('SELECT id, name, category, price, description, image1, image2, image3, is_active, created_at FROM products ORDER BY created_at DESC LIMIT 100');
} else {
    $stmt = $conn->prepare('SELECT id, name, category, price, description, image1, image2, image3, is_active, created_at FROM products WHERE name LIKE ? OR category LIKE ? ORDER BY created_at DESC LIMIT 100');
    $search = '%' . $query . '%';
    $stmt->bind_param('ss', $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
}
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
if (isset($stmt) && $query !== '') {
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage products | DunkHome Kicks</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261001-loader4">
    <link rel="stylesheet" href="assets/admin-pages.css">
    <link rel="stylesheet" href="assets/admin-navigation.css">
    <style>
        .products-heading { margin-bottom: 24px; }
        .products-heading .admin-subtitle { max-width: 620px; margin-bottom: 0; }
        .products-panel { overflow: hidden; padding: 0; }
        .products-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 20px 22px; border-bottom: 1px solid var(--admin-line); }
        .products-count strong,.products-count span { display: block; }
        .products-count strong { color: var(--admin-text); font-size: 13px; }
        .products-count span { margin-top: 4px; color: var(--admin-muted); font-size: 10px; }
        .products-toolbar-actions { display: flex; align-items: center; gap: 9px; }
        .products-search { display: flex; align-items: center; gap: 7px; }
        .products-search input { width: min(270px, 30vw); min-height: 38px; padding: 9px 11px; border: 1px solid var(--admin-line); border-radius: 8px; outline: none; color: var(--admin-text); background: rgba(255,255,255,.035); font: 11px "DM Sans",sans-serif; }
        .products-search input:focus { border-color: rgba(121,230,170,.62); box-shadow: 0 0 0 3px rgba(121,230,170,.09); }
        .products-table-wrap { overflow-x: auto; }
        .products-table { width: 100%; min-width: 780px; border-collapse: collapse; }
        .products-table caption { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        .products-table th,.products-table td { padding: 13px 16px; text-align: left; vertical-align: middle; }
        .products-table th { color: var(--admin-muted); font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .products-table td { border-top: 1px solid var(--admin-line); color: var(--admin-text); font-size: 11px; }
        .product-info { display: flex; align-items: center; gap: 11px; min-width: 190px; }
        .product-thumbnail { width: 44px; height: 44px; flex: 0 0 44px; object-fit: cover; border: 1px solid var(--admin-line); border-radius: 8px; background: rgba(255,255,255,.04); }
        .product-name { color: var(--admin-text); font-size: 11px; font-weight: 700; }
        .product-category { color: var(--admin-muted); }
        .product-price { white-space: nowrap; font-variant-numeric: tabular-nums; }
        .product-date { color: var(--admin-muted); white-space: nowrap; font-size: 10px; }
        .product-visibility { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .product-visibility::before { width: 6px; height: 6px; border-radius: 50%; background: #68766f; content: ""; }
        .product-visibility.visible::before { background: var(--admin-green); box-shadow: 0 0 10px rgba(121,230,170,.42); }
        .product-row-action { min-width: 82px; min-height: 34px; padding: 7px 10px; font-size: 10px; }
        .product-row-actions { display: flex; flex-wrap: wrap; gap: 6px; }
        .product-row-actions form { margin: 0; }
        .product-dialog { width: min(520px, calc(100% - 24px)); max-height: calc(100% - 32px); padding: 0; overflow: auto; border: 1px solid var(--admin-line); border-radius: 14px; color: var(--admin-text); background: var(--admin-bg); box-shadow: 0 24px 80px rgba(0,0,0,.42); }
        .product-dialog::backdrop { background: rgba(0,0,0,.62); backdrop-filter: blur(4px); }
        .product-dialog form { padding: 22px; }
        .product-dialog-heading { margin-bottom: 20px; }
        .product-dialog-heading h2 { margin: 0; color: var(--admin-text); font-size: 17px; }
        .product-dialog-heading p { margin: 5px 0 0; color: var(--admin-muted); font-size: 11px; line-height: 1.5; }
        .product-dialog-fields { display: grid; gap: 14px; }
        .product-dialog-fields label { display: block; margin-bottom: 6px; color: var(--admin-text); font-size: 10px; font-weight: 700; }
        .product-dialog-fields input,.product-dialog-fields select,.product-dialog-fields textarea { width: 100%; min-height: 42px; padding: 10px 11px; border: 1px solid var(--admin-line); border-radius: 8px; outline: none; color: var(--admin-text); background: rgba(255,255,255,.035); font: 12px "DM Sans",sans-serif; }
        .product-dialog-fields textarea { min-height: 96px; resize: vertical; }
        .product-dialog-fields input:focus,.product-dialog-fields select:focus,.product-dialog-fields textarea:focus { border-color: rgba(121,230,170,.62); box-shadow: 0 0 0 3px rgba(121,230,170,.09); }
        .product-edit-images { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 10px; }
        .product-edit-image { min-width: 0; }
        .product-edit-image img { display: block; width: 100%; aspect-ratio: 4 / 3; margin-bottom: 9px; border: 1px solid var(--admin-line); border-radius: 8px; object-fit: cover; background: rgba(255,255,255,.04); }
        .product-edit-image input { min-width: 0; min-height: 36px; padding: 5px; font-size: 10px; }
        .product-edit-image span { display: block; margin-top: 5px; color: var(--admin-muted); font-size: 9px; line-height: 1.4; }
        .product-dialog-actions { display: flex; justify-content: flex-end; gap: 9px; margin-top: 20px; }
        .product-delete-content { text-align: center; }
        .product-delete-icon { width: 46px; height: 46px; display: grid; place-items: center; margin: 0 auto 14px; border: 1px solid rgba(255,123,123,.24); border-radius: 14px; color: #ff8989; background: rgba(255,123,123,.09); font-size: 17px; }
        .product-delete-content h2 { margin: 0; color: var(--admin-text); font-size: 18px; }
        .product-delete-content p { margin: 9px 0 0; color: var(--admin-muted); font-size: 11px; line-height: 1.6; overflow-wrap: anywhere; }
        .product-delete-content strong { color: var(--admin-text); }
        .product-delete-dialog .product-dialog-actions { justify-content: center; }
        .product-edit-dialog { width: min(820px, calc(100% - 28px)); max-height: min(92vh, 900px); background: var(--admin-panel); }
        .product-edit-form { padding: 0 !important; }
        .product-edit-heading { display: flex; align-items: center; gap: 14px; margin: 0; padding: 22px 25px; border-bottom: 1px solid var(--admin-line); background: rgba(121,230,170,.035); }
        .product-edit-heading-icon { width: 44px; height: 44px; flex: 0 0 44px; display: grid; place-items: center; border: 1px solid rgba(121,230,170,.22); border-radius: 12px; color: var(--admin-green); background: rgba(121,230,170,.09); font-size: 15px; }
        .product-dialog-kicker { display: block; margin-bottom: 5px; color: var(--admin-green); font-size: 8px; font-weight: 800; }
        .product-edit-heading h2 { font-size: 19px; }
        .product-edit-heading p { margin-top: 4px; }
        .product-edit-body { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap: 22px; padding: 22px 25px; }
        .product-edit-details { display: grid; align-content: start; gap: 15px; }
        .product-edit-details h3,.product-edit-photos h3 { margin: 0 0 2px; color: var(--admin-text); font-size: 11px; }
        .product-edit-details .product-dialog-fields { gap: 13px; }
        .product-edit-details .product-dialog-fields > div { min-width: 0; }
        .product-edit-details .product-dialog-fields > div:first-child,.product-edit-details .product-dialog-fields > div:last-child { grid-column: 1 / -1; }
        .product-edit-details .product-dialog-fields { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .product-edit-photos { display: grid; align-content: start; gap: 10px; }
        .product-edit-images { display: grid; grid-template-columns: 1fr; gap: 9px; }
        .product-edit-image { display: grid; grid-template-columns: 92px minmax(0,1fr); gap: 4px 11px; align-items: center; min-width: 0; padding: 9px; border: 1px solid var(--admin-line); border-radius: 9px; background: rgba(255,255,255,.025); }
        .product-edit-image img { grid-row: 1 / 4; display: block; width: 92px; height: 68px; margin: 0; border: 1px solid var(--admin-line); border-radius: 6px; object-fit: cover; background: rgba(255,255,255,.04); }
        .product-edit-image label { margin: 0; }
        .product-edit-image input { width: 100%; min-width: 0; min-height: 32px; padding: 4px; border: 1px dashed rgba(121,230,170,.3); border-radius: 6px; color: var(--admin-muted); background: rgba(121,230,170,.025); font: 9px "DM Sans",sans-serif; }
        .product-edit-image input::file-selector-button { max-width: 100%; margin-right: 7px; padding: 6px 8px; border: 1px solid var(--admin-line); border-radius: 5px; color: var(--admin-text); background: rgba(255,255,255,.06); font: 600 9px "DM Sans",sans-serif; cursor: pointer; }
        .product-edit-image span { margin: 0; color: var(--admin-muted); font-size: 8px; line-height: 1.35; }
        .product-edit-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 0; padding: 15px 25px; border-top: 1px solid var(--admin-line); background: rgba(0,0,0,.08); }
        .product-edit-actions .product-help { margin: 0; }
        .product-edit-actions .product-dialog-actions { margin: 0; }
        .products-empty { padding: 36px 22px; color: var(--admin-muted); font-size: 12px; text-align: center; }
        @media(max-width:800px) { .products-toolbar { align-items: stretch; flex-direction: column; } .products-toolbar-actions { justify-content: space-between; } .products-search { flex: 1; } .products-search input { width: 100%; } }
        @media(max-width:640px) {
            .products-heading { margin-bottom: 18px; }
            .products-toolbar { padding: 17px; }
            .products-toolbar-actions { align-items: stretch; flex-direction: column; }
            .products-search { display: grid; grid-template-columns: minmax(0,1fr) auto; }
            .products-search .admin-button,.products-toolbar-actions > .admin-button { justify-content: center; }
            .products-table-wrap { overflow: visible; }
            .products-table { display: block; min-width: 0; }
            .products-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; }
            .products-table tbody { display: block; padding: 0 12px; }
            .products-table tr { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); border-bottom: 1px solid var(--admin-line); }
            .products-table td { display: block; min-width: 0; padding: 10px; border: 0; overflow-wrap: anywhere; }
            .products-table td::before { display: block; margin-bottom: 5px; color: var(--admin-muted); content: attr(data-label); font-size: 8px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
            .products-table td:first-child,.products-table td:last-child { grid-column: 1 / -1; }
            .product-info { min-width: 0; }
            .product-date { white-space: normal; }
            .product-row-actions { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); }
            .product-row-actions .admin-button { width: 100%; min-width: 0; padding: 7px 5px; justify-content: center; }
            .product-dialog:not(.product-edit-dialog) form { padding: 18px; }
            .product-edit-body { grid-template-columns: 1fr; gap: 20px; padding: 18px; }
            .product-edit-heading { padding: 18px; }
            .product-edit-actions { align-items: stretch; flex-direction: column; padding: 16px 18px; }
            .product-edit-actions .product-help { text-align: center; }
            .product-edit-actions .product-dialog-actions { flex-direction: row; }
            .product-edit-actions .product-dialog-actions .admin-button { width: auto; flex: 1; }
            .product-dialog-actions { flex-direction: column-reverse; }
            .product-dialog-actions .admin-button { width: 100%; justify-content: center; }
            .product-delete-dialog .product-dialog-actions { flex-direction: column; }
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
        <a class="admin-brand" href="<?= h(appUrl('Admin/AdminDashboard.php')) ?>"><img src="image/logo.jpeg" alt=""><div class="brand-name">dunkhome_<span>kicks</span></div></a>
        <div class="admin-nav-title">Administration</div>
        <nav class="admin-nav-links"><?php $adminNavigationShowThemeToggle = false; require __DIR__ . '/../includes/nav.php'; ?></nav>
        <div class="admin-sidebar-identity"><div class="admin-sidebar-avatar"><?= h($adminInitials) ?></div><div class="admin-sidebar-user"><strong><?= h($adminEmail) ?></strong><span>Administrator</span></div></div>
    </header>
    <main class="admin-content">
        <div class="products-heading"><div><div class="admin-eyebrow">Catalog management</div><h1 class="admin-title">Manage products</h1><p class="admin-subtitle">Search the catalog and control which products are visible in the storefront.</p></div></div>
        <?php if ($message !== ''): ?>
            <div class="admin-message <?= $messageType === 'success' ? 'success' : '' ?>" role="status"><?= h($message) ?></div>
        <?php endif; ?>
        <section class="admin-panel products-panel" aria-label="Product list">
            <div class="products-toolbar">
                <div class="products-count"><strong><?= count($products) ?> products shown</strong><span><?= $query !== '' ? 'Search results for “' . h($query) . '”' : 'Latest catalog entries' ?></span></div>
                <div class="products-toolbar-actions">
                    <form class="products-search" method="get" role="search">
                        <input type="search" name="q" value="<?= h($query) ?>" placeholder="Search name or category" aria-label="Search products" list="productCategorySuggestions">
                        <datalist id="productCategorySuggestions">
                            <?php foreach ($categoryOptions as $categoryOption): ?>
                                <option value="<?= h($categoryOption) ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <button class="admin-button" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                    </form>
                    <a class="admin-button primary" href="<?= h(appUrl('Admin/AddProduct.php')) ?>"><i class="fa-solid fa-plus"></i> Add product</a>
                </div>
            </div>
            <?php if (!$products): ?>
                <div class="products-empty">No products match this search.</div>
            <?php else: ?>
                <div class="products-table-wrap">
                    <table class="products-table">
                        <caption class="sr-only">Products, prices, dates, and storefront visibility</caption>
                        <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Added</th><th>Visibility</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td data-label="Product"><div class="product-info"><?php if (!empty($product['image1'])): ?><img class="product-thumbnail" src="<?= h(appUrl((string) $product['image1'])) ?>" alt="" loading="lazy"><?php endif; ?><strong class="product-name"><?= h((string) $product['name']) ?></strong></div></td>
                                <td data-label="Category"><span class="product-category"><?= h((string) $product['category']) ?></span></td>
                                <td data-label="Price"><span class="product-price">₹<?= number_format((float) $product['price'], 2) ?></span></td>
                                <td data-label="Added"><time class="product-date" datetime="<?= h((string) $product['created_at']) ?>"><?= h((string) $product['created_at']) ?></time></td>
                                <td data-label="Visibility"><span class="product-visibility <?= (int) $product['is_active'] === 1 ? 'visible' : '' ?>"><?= (int) $product['is_active'] === 1 ? 'Visible' : 'Hidden' ?></span></td>
                                <td data-label="Actions">
                                    <div class="product-row-actions">
                                        <button class="admin-button product-row-action" type="button" data-product-edit data-id="<?= (int) $product['id'] ?>" data-name="<?= h((string) $product['name']) ?>" data-category="<?= h((string) $product['category']) ?>" data-price="<?= h((string) $product['price']) ?>" data-description="<?= h((string) ($product['description'] ?? '')) ?>" data-image1="<?= h(appUrl((string) $product['image1'])) ?>" data-image2="<?= h(appUrl((string) $product['image2'])) ?>" data-image3="<?= h(appUrl((string) $product['image3'])) ?>" aria-label="Edit <?= h((string) $product['name']) ?>">Edit</button>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                            <input type="hidden" name="action" value="visibility">
                                            <input type="hidden" name="is_active" value="<?= (int) $product['is_active'] === 1 ? 0 : 1 ?>">
                                            <?php if ($query !== ''): ?><input type="hidden" name="q" value="<?= h($query) ?>"><?php endif; ?>
                                            <button class="admin-button product-row-action" type="submit"><?= (int) $product['is_active'] === 1 ? 'Hide' : 'Publish' ?></button>
                                        </form>
                                        <button class="admin-button danger product-row-action" type="button" data-product-delete data-id="<?= (int) $product['id'] ?>" data-name="<?= h((string) $product['name']) ?>" aria-label="Delete <?= h((string) $product['name']) ?>">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <dialog class="product-dialog product-edit-dialog" id="productEditDialog" aria-labelledby="productEditTitle">
            <form class="product-edit-form" method="post" enctype="multipart/form-data">
                <div class="product-edit-heading"><span class="product-edit-heading-icon" aria-hidden="true"><i class="fa-solid fa-pen-to-square"></i></span><div><span class="product-dialog-kicker">PRODUCT LISTING</span><h2 id="productEditTitle">Edit product</h2><p>Update details and replace photos without leaving the catalog.</p></div></div>
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="product_id" id="editProductId">
                <input type="hidden" name="action" value="edit">
                <?php if ($query !== ''): ?><input type="hidden" name="q" value="<?= h($query) ?>"><?php endif; ?>
                <div class="product-edit-body">
                    <section class="product-edit-details" aria-label="Product details">
                        <h3>Listing information</h3>
                        <div class="product-dialog-fields">
                            <div><label for="editProductName">Product name</label><input id="editProductName" name="name" maxlength="180" required></div>
                            <div><label for="editProductCategory">Category</label><select id="editProductCategory" name="category" required><?php foreach ($categoryOptions as $categoryOption): ?><option value="<?= h($categoryOption) ?>"><?= h($categoryOption) ?></option><?php endforeach; ?></select></div>
                            <div><label for="editProductPrice">Price</label><input id="editProductPrice" name="price" type="number" min="0.01" step="0.01" required></div>
                            <div><label for="editProductDescription">Description</label><textarea id="editProductDescription" name="description" maxlength="5000"></textarea></div>
                        </div>
                    </section>
                    <section class="product-edit-photos" aria-labelledby="productEditPhotosTitle">
                        <div><h3 id="productEditPhotosTitle">Product photos</h3><p class="product-help">Select a replacement or leave the photo unchanged.</p></div>
                        <div class="product-edit-images">
                            <div class="product-edit-image"><img id="editProductImage1" alt="Current product photo 1"><label for="editProductPhoto1">Replace photo 1</label><input id="editProductPhoto1" name="photo1" type="file" accept="image/jpeg,image/png,image/webp"><span>JPG, PNG or WEBP, max 5 MB.</span></div>
                            <div class="product-edit-image"><img id="editProductImage2" alt="Current product photo 2"><label for="editProductPhoto2">Replace photo 2</label><input id="editProductPhoto2" name="photo2" type="file" accept="image/jpeg,image/png,image/webp"><span>JPG, PNG or WEBP, max 5 MB.</span></div>
                            <div class="product-edit-image"><img id="editProductImage3" alt="Current product photo 3"><label for="editProductPhoto3">Replace photo 3</label><input id="editProductPhoto3" name="photo3" type="file" accept="image/jpeg,image/png,image/webp"><span>JPG, PNG or WEBP, max 5 MB.</span></div>
                        </div>
                    </section>
                </div>
                <div class="product-edit-actions"><span class="product-help">Changes apply to the storefront product listing.</span><div class="product-dialog-actions"><button class="admin-button" type="button" id="cancelProductEdit">Cancel</button><button class="admin-button primary" type="submit"><i class="fa-solid fa-check"></i> Save changes</button></div></div>
            </form>
        </dialog>
        <dialog class="product-dialog product-delete-dialog" id="productDeleteDialog" aria-labelledby="productDeleteTitle" aria-describedby="productDeleteDescription">
            <form method="post">
                <div class="product-delete-content"><span class="product-delete-icon" aria-hidden="true"><i class="fa-solid fa-trash"></i></span><h2 id="productDeleteTitle">Delete product?</h2><p id="productDeleteDescription">Delete <strong id="productDeleteName"></strong>? Its saved product photos will also be removed.</p></div>
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="product_id" id="deleteProductId">
                <input type="hidden" name="action" value="delete">
                <?php if ($query !== ''): ?><input type="hidden" name="q" value="<?= h($query) ?>"><?php endif; ?>
                <div class="product-dialog-actions"><button class="admin-button" type="button" id="cancelProductDelete">Cancel</button><button class="admin-button danger" type="submit"><i class="fa-solid fa-trash"></i> Delete product</button></div>
            </form>
        </dialog>
    </main>
    <footer class="admin-footer">DunkHome Kicks administration</footer>
</div>
<script src="assets/dunkhome-ui.js?v=20261001-loader4" defer></script>
<script src="assets/admin-sidebar-drawer.js?v=20261001-1" defer></script>
<script>
document.getElementById('productRefreshButton')?.addEventListener('click', () => window.location.reload());

const productEditDialog = document.getElementById('productEditDialog');
document.querySelectorAll('[data-product-edit]').forEach(button => {
    button.addEventListener('click', () => {
        document.getElementById('editProductId').value = button.dataset.id;
        document.getElementById('editProductName').value = button.dataset.name;
        document.getElementById('editProductCategory').value = button.dataset.category;
        document.getElementById('editProductPrice').value = button.dataset.price;
        document.getElementById('editProductDescription').value = button.dataset.description;
        document.getElementById('editProductImage1').src = button.dataset.image1;
        document.getElementById('editProductImage2').src = button.dataset.image2;
        document.getElementById('editProductImage3').src = button.dataset.image3;
        document.querySelectorAll('#productEditDialog input[type="file"]').forEach(input => { input.value = ''; });
        productEditDialog.showModal();
        document.getElementById('editProductName').focus();
    });
});
document.getElementById('cancelProductEdit')?.addEventListener('click', () => productEditDialog.close());
productEditDialog?.addEventListener('click', event => {
    if (event.target === productEditDialog) productEditDialog.close();
});

const productDeleteDialog = document.getElementById('productDeleteDialog');
document.querySelectorAll('[data-product-delete]').forEach(button => {
    button.addEventListener('click', () => {
        document.getElementById('deleteProductId').value = button.dataset.id;
        document.getElementById('productDeleteName').textContent = button.dataset.name;
        productDeleteDialog.showModal();
        document.getElementById('cancelProductDelete').focus();
    });
});
document.getElementById('cancelProductDelete')?.addEventListener('click', () => productDeleteDialog.close());
productDeleteDialog?.addEventListener('click', event => {
    if (event.target === productDeleteDialog) productDeleteDialog.close();
});
</script>
</body>
</html>
