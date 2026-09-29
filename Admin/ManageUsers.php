<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
requireAdmin();

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
        <div class="admin-eyebrow">Customers</div>
        <h1 class="admin-title">Manage users</h1>
        <p class="admin-subtitle">Review registered accounts and their order activity.</p>
        <section class="admin-panel">
            <div class="admin-toolbar">
                <strong><?= count($users) ?><?= count($users) === 100 ? '+' : '' ?> accounts</strong>
                <form class="admin-inline-form" method="get">
                    <input type="search" name="q" value="<?= h($query) ?>" placeholder="Search email" aria-label="Search users">
                    <button class="admin-button" type="submit">Search</button>
                </form>
            </div>
            <?php if (!$users): ?>
                <div class="admin-empty">No users match this search.</div>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Account</th><th>User ID</th><th>Orders</th><th>Registered</th></tr></thead>
                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= h((string) $user['email']) ?></td>
                                <td>#<?= (int) $user['id'] ?></td>
                                <td><?= (int) $user['order_count'] ?></td>
                                <td><?= h((string) $user['created_at']) ?></td>
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
