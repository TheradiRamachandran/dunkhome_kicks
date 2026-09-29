<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
requireAdmin();

$message = '';
$messageType = 'error';
$query = trim((string) ($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $isActive = (int) ($_POST['is_active'] ?? 0);

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $message = 'This request expired. Refresh the page and try again.';
    } elseif ($productId < 1 || !in_array($isActive, [0, 1], true)) {
        $message = 'Select a valid product status.';
    } else {
        $stmt = $conn->prepare('UPDATE products SET is_active = ? WHERE id = ?');
        $stmt->bind_param('ii', $isActive, $productId);
        $updated = $stmt->execute();
        $stmt->close();
        $message = $updated ? 'Product visibility updated.' : 'Unable to update this product.';
        $messageType = $updated ? 'success' : 'error';
    }
}

$products = [];
if ($query === '') {
    $result = $conn->query('SELECT id, name, category, price, is_active, created_at FROM products ORDER BY created_at DESC LIMIT 100');
} else {
    $stmt = $conn->prepare('SELECT id, name, category, price, is_active, created_at FROM products WHERE name LIKE ? OR category LIKE ? ORDER BY created_at DESC LIMIT 100');
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
        <div class="admin-eyebrow">Catalog</div>
        <h1 class="admin-title">Manage products</h1>
        <p class="admin-subtitle">Search the catalog and control which products are visible in the storefront.</p>
        <?php if ($message !== ''): ?>
            <div class="admin-message <?= $messageType === 'success' ? 'success' : '' ?>" role="status"><?= h($message) ?></div>
        <?php endif; ?>
        <section class="admin-panel">
            <div class="admin-toolbar">
                <form class="admin-inline-form" method="get">
                    <input type="search" name="q" value="<?= h($query) ?>" placeholder="Search name or category" aria-label="Search products">
                    <button class="admin-button" type="submit">Search</button>
                </form>
                <a class="admin-button primary" href="Admin/AddProduct.php">Add product</a>
            </div>
            <?php if (!$products): ?>
                <div class="admin-empty">No products match this search.</div>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Added</th><th>Visibility</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><strong><?= h((string) $product['name']) ?></strong></td>
                                <td><?= h((string) $product['category']) ?></td>
                                <td>₹<?= number_format((float) $product['price'], 2) ?></td>
                                <td><?= h((string) $product['created_at']) ?></td>
                                <td><?= (int) $product['is_active'] === 1 ? 'Visible' : 'Hidden' ?></td>
                                <td>
                                    <form class="admin-inline-form" method="post">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                        <input type="hidden" name="is_active" value="<?= (int) $product['is_active'] === 1 ? 0 : 1 ?>">
                                        <button class="admin-button" type="submit"><?= (int) $product['is_active'] === 1 ? 'Hide' : 'Publish' ?></button>
                                    </form>
                                </td>
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
<script src="assets/dunkhome-ui.js" defer></script>
</body>
</html>
