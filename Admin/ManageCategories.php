<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
requireAdmin();

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
        } elseif ($action === 'toggle') {
            $stmt = $conn->prepare('UPDATE categories SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
            $stmt->bind_param('i', $categoryId);
            $updated = $stmt->execute();
            $message = $updated ? 'Category status updated.' : 'Unable to update this category.';
            $messageType = $updated ? 'success' : 'error';
            $stmt->close();
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare('SELECT name FROM categories WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $categoryId);
            $stmt->execute();
            $category = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$category) {
                $message = 'Category not found.';
            } else {
                $productCheck = $conn->prepare('SELECT COUNT(*) AS total FROM products WHERE category COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci');
                $productCheck->bind_param('s', $category['name']);
                $productCheck->execute();
                $productCount = (int) $productCheck->get_result()->fetch_assoc()['total'];
                $productCheck->close();

                if ($productCount > 0) {
                    $message = 'This category is assigned to products. Deactivate it instead of deleting it.';
                } else {
                    $delete = $conn->prepare('DELETE FROM categories WHERE id = ?');
                    $delete->bind_param('i', $categoryId);
                    $message = $delete->execute() ? 'Category deleted.' : 'Unable to delete this category.';
                    $messageType = $delete->affected_rows > 0 ? 'success' : 'error';
                    $delete->close();
                }
            }
        }
    }
}

$categories = [];
$result = $conn->query(
    'SELECT c.id, c.name, c.slug, c.description, c.is_active, c.created_at,
            COUNT(p.id) AS product_count
     FROM categories c
    LEFT JOIN products p ON p.category COLLATE utf8mb4_unicode_ci = c.name
     GROUP BY c.id, c.name, c.slug, c.description, c.is_active, c.created_at
     ORDER BY c.name'
);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage categories | DunkHome Kicks</title>
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
        <h1 class="admin-title">Manage categories</h1>
        <p class="admin-subtitle">Control which categories are available when adding products.</p>
        <?php if ($message !== ''): ?>
            <div class="admin-message <?= $messageType === 'success' ? 'success' : '' ?>" role="status"><?= h($message) ?></div>
        <?php endif; ?>
        <section class="admin-panel">
            <div class="admin-toolbar"><strong><?= count($categories) ?> categories</strong><a class="admin-button primary" href="Admin/AddCategory.php">Add category</a></div>
            <?php if (!$categories): ?>
                <div class="admin-empty">No categories yet. Add one to make it available in product entry.</div>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Category</th><th>Slug</th><th>Products</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><strong><?= h((string) $category['name']) ?></strong><?php if ($category['description']): ?><br><small><?= h((string) $category['description']) ?></small><?php endif; ?></td>
                                <td><?= h((string) $category['slug']) ?></td>
                                <td><?= (int) $category['product_count'] ?></td>
                                <td><?= (int) $category['is_active'] === 1 ? 'Active' : 'Inactive' ?></td>
                                <td>
                                    <form class="admin-inline-form" method="post">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                        <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                                        <button class="admin-button" name="action" value="toggle" type="submit"><?= (int) $category['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                        <?php if ((int) $category['product_count'] === 0): ?>
                                            <button class="admin-button danger" name="action" value="delete" type="submit" onclick="return confirm('Delete this category?')">Delete</button>
                                        <?php endif; ?>
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
