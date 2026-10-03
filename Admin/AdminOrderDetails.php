<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../includes/order_helpers.php';
require_once __DIR__ . '/../Mailer.php';
requireAdmin();
$adminEmail = (string) ($_SESSION['admin_email'] ?? 'Administrator');
$emailName = explode('@', $adminEmail)[0] ?? '';
$adminInitials = strtoupper(substr((string) (preg_replace('/[^a-zA-Z0-9]/', '', $emailName) ?: 'AD'), 0, 2));
$code = trim((string) ($_GET['code'] ?? ''));
$message = '';
$hasOrderCode = tableHasColumn($conn, 'orders', 'order_code');
$lookupValue = $hasOrderCode ? $code : filter_var($code, FILTER_VALIDATE_INT);
$normalizeOrder = static function (?array $row): ?array {
    if (!$row) {
        return null;
    }
    $row['order_code'] = (string) ($row['order_code'] ?? $row['id']);
    $row['mobile'] = (string) ($row['mobile'] ?? $row['customer_phone'] ?? '');
    $row['email'] = (string) ($row['email'] ?? $row['customer_email'] ?? '');
    $row['total'] = (float) ($row['total'] ?? $row['total_amount'] ?? 0);
    $row['address'] = (string) ($row['address'] ?? $row['shipping_address'] ?? '');
    $row['city'] = (string) ($row['city'] ?? '');
    $row['state'] = (string) ($row['state'] ?? '');
    $row['pincode'] = (string) ($row['pincode'] ?? '');
    return $row;
};
$order = null;
if ($lookupValue !== false) {
    $lookupColumn = $hasOrderCode ? 'order_code' : 'id';
    $lookupType = $hasOrderCode ? 's' : 'i';
    $stmt = $conn->prepare("SELECT * FROM orders WHERE {$lookupColumn}=? LIMIT 1");
    $stmt->bind_param($lookupType, $lookupValue);
    $stmt->execute();
    $order = $normalizeOrder($stmt->get_result()->fetch_assoc());
    $stmt->close();
}
if (!$order) { http_response_code(404); }

if ($order && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid request token. Refresh the page and try again.');
    }
    $newStatus = (string) ($_POST['status'] ?? '');
    $note = trim((string) ($_POST['note'] ?? ''));
    if (!in_array($newStatus, orderStatuses(), true) || strlen($note) > 500) {
        $message = 'Choose a valid status and keep notes under 500 characters.';
    } else {
        try {
            $conn->begin_transaction();
            $lock = $conn->prepare('SELECT status FROM orders WHERE id=? FOR UPDATE');
            $lock->bind_param('i', $order['id']);
            $lock->execute();
            $current = $lock->get_result()->fetch_assoc();
            $lock->close();
            if (!$current) throw new RuntimeException('Order no longer exists.');
            $previousStatus = (string) $current['status'];
            if ($newStatus !== $previousStatus) {
                $update = $conn->prepare('UPDATE orders SET status=? WHERE id=?');
                $update->bind_param('si', $newStatus, $order['id']);
                $update->execute();
                $update->close();
                if (tableHasColumn($conn, 'order_status_history', 'new_status')) {
                    $adminId = (int) $_SESSION['admin_id'];
                    $history = $conn->prepare('INSERT INTO order_status_history (order_id,previous_status,new_status,changed_by_type,changed_by_id,note) VALUES (?,?,?,\'admin\',?,?)');
                    $history->bind_param('issis', $order['id'], $previousStatus, $newStatus, $adminId, $note);
                } else {
                    $history = $conn->prepare('INSERT INTO order_status_history (order_id,status,note) VALUES (?,?,?)');
                    $history->bind_param('iss', $order['id'], $newStatus, $note);
                }
                $history->execute();
                $history->close();
            }
            $conn->commit();
            if ($newStatus !== $previousStatus) {
                try {
                    $emailResult = sendDunkHomeOrderStatusEmail($order, $previousStatus, $newStatus, $note);
                    if (($emailResult['status'] ?? '') !== 'success') {
                        error_log('Order status notification was not sent for booking ' . $order['order_code'] . ': ' . ($emailResult['message'] ?? 'Unknown email error.'));
                    }
                } catch (Throwable $emailError) {
                    error_log('Order status notification failed for booking ' . $order['order_code'] . ': ' . $emailError->getMessage());
                }
            }
            header('Location: ' . appUrl('Admin/AdminOrderDetails.php?code=' . rawurlencode($code) . '&updated=1'));
            exit;
        } catch (Throwable $error) {
            try { $conn->rollback(); } catch (Throwable $rollbackError) { error_log('Order update rollback failed: ' . $rollbackError->getMessage()); }
            error_log('Admin order status update failed: ' . $error->getMessage());
            $message = 'Unable to update this order status. Please try again.';
        }
    }
    $lookupColumn = $hasOrderCode ? 'order_code' : 'id';
    $stmt = $conn->prepare("SELECT * FROM orders WHERE {$lookupColumn}=? LIMIT 1");
    $stmt->bind_param($lookupType, $lookupValue);
    $stmt->execute();
    $order = $normalizeOrder($stmt->get_result()->fetch_assoc());
    $stmt->close();
}
if (isset($_GET['updated'])) $message = 'Order status updated.';
$items = [];
$history = [];
if ($order) {
    $itemImageColumn = tableHasColumn($conn, 'order_items', 'product_image') ? 'product_image' : 'NULL AS product_image';
    $itemPriceColumn = tableHasColumn($conn, 'order_items', 'unit_price') ? 'unit_price' : 'price';
    $stmt = $conn->prepare("SELECT product_name,{$itemImageColumn},{$itemPriceColumn} AS unit_price,quantity,subtotal FROM order_items WHERE order_id=? ORDER BY id");
    $stmt->bind_param('i', $order['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $items[] = $row;
    $stmt->close();
    if (tableHasColumn($conn, 'order_status_history', 'new_status')) {
        $historySql = 'SELECT previous_status,new_status,changed_by_type,changed_by_id,note,created_at FROM order_status_history WHERE order_id=? ORDER BY created_at,id';
    } else {
        $historySql = "SELECT NULL AS previous_status,status AS new_status,'system' AS changed_by_type,NULL AS changed_by_id,note,created_at FROM order_status_history WHERE order_id=? ORDER BY created_at,id";
    }
    $stmt = $conn->prepare($historySql);
    $stmt->bind_param('i', $order['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $history[] = $row;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../includes/favicon.php'; ?>
    <base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order details | DunkHome Kicks</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-theme2">
    <link rel="stylesheet" href="assets/admin-pages.css?v=20261003-theme2">
    <link rel="stylesheet" href="assets/admin-navigation.css?v=20261003-theme1">
    <style>
        .order-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; margin-bottom: 22px; }
        .order-heading .admin-title { overflow-wrap: anywhere; }
        .order-heading .admin-subtitle { margin-bottom: 0; }
        .order-back { flex: 0 0 auto; }
        .order-detail-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 14px; }
        .order-detail-panel { min-width: 0; padding: 19px; }
        .order-detail-panel h2 { margin: 0 0 15px; color: var(--admin-text); font-size: 13px; }
        .order-detail-panel h3 { margin: 19px 0 8px; color: var(--admin-text); font-size: 10px; }
        .order-customer-name { margin: 0; color: var(--admin-text); font-size: 12px; font-weight: 700; }
        .order-contact { margin: 7px 0 0; color: var(--admin-muted); font-size: 11px; overflow-wrap: anywhere; }
        .order-address { margin: 0; color: var(--admin-muted); font-size: 11px; line-height: 1.65; overflow-wrap: anywhere; }
        .order-status-display { display: inline-flex; align-items: center; gap: 7px; margin: 0 0 15px; padding: 6px 9px; border: 1px solid var(--admin-line); border-radius: 999px; color: var(--admin-green); background: rgba(121,230,170,.055); font-size: 10px; font-weight: 700; }
        .order-status-display::before { width: 6px; height: 6px; border-radius: 50%; background: var(--admin-green); content: ""; }
        .order-status-form label { display: block; margin-bottom: 6px; color: var(--admin-text); font-size: 10px; font-weight: 700; }
        .order-status-form select,.order-status-form textarea { display: block; width: 100%; min-height: 42px; padding: 10px 11px; border: 1px solid var(--admin-line); border-radius: 8px; outline: none; color: var(--admin-text); background: rgba(255,255,255,.035); font: 11px "DM Sans",sans-serif; }
        .order-status-form select { margin-bottom: 12px; }
        .order-status-form select option { color: #18251d; }
        .order-status-form textarea { min-height: 86px; margin-bottom: 12px; resize: vertical; }
        .order-status-form select:focus,.order-status-form textarea:focus { border-color: rgba(121,230,170,.62); box-shadow: 0 0 0 3px rgba(121,230,170,.09); }
        .order-status-form button { min-height: 38px; }
        .order-items-panel,.order-history-panel { margin-top: 14px; padding: 0; overflow: hidden; }
        .order-section-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 20px; border-bottom: 1px solid var(--admin-line); }
        .order-section-heading h2 { margin: 0; color: var(--admin-text); font-size: 13px; }
        .order-section-heading span { color: var(--admin-muted); font-size: 10px; }
        .order-items { padding: 0 20px; }
        .order-item { display: grid; grid-template-columns: minmax(0,1fr) 80px 110px 110px; align-items: center; gap: 12px; padding: 13px 0; border-bottom: 1px solid var(--admin-line); }
        .order-item:last-child { border-bottom: 0; }
        .order-item-product { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .order-item-image { width: 42px; height: 42px; flex: 0 0 42px; object-fit: cover; border: 1px solid var(--admin-line); border-radius: 7px; background: rgba(255,255,255,.035); }
        .order-item-name { color: var(--admin-text); font-size: 11px; font-weight: 700; overflow-wrap: anywhere; }
        .order-item-label { display: block; margin-bottom: 4px; color: var(--admin-muted); font-size: 8px; font-weight: 800; text-transform: uppercase; }
        .order-item-value { color: var(--admin-text); font-size: 10px; font-variant-numeric: tabular-nums; }
        .order-total-row { display: flex; align-items: center; justify-content: flex-end; gap: 20px; padding: 15px 20px; border-top: 1px solid var(--admin-line); }
        .order-total-row span { color: var(--admin-muted); font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .order-total-row strong { color: var(--admin-green); font-size: 16px; font-variant-numeric: tabular-nums; }
        .order-history { padding: 4px 20px; }
        .order-history-entry { display: grid; grid-template-columns: 12px minmax(0,1fr) auto; gap: 10px; padding: 12px 0; border-bottom: 1px solid var(--admin-line); }
        .order-history-entry:last-child { border-bottom: 0; }
        .order-history-dot { width: 8px; height: 8px; margin-top: 4px; border-radius: 50%; background: var(--admin-green); box-shadow: 0 0 9px rgba(121,230,170,.3); }
        .order-history-entry strong { color: var(--admin-text); font-size: 11px; }
        .order-history-entry p { margin: 4px 0 0; color: var(--admin-muted); font-size: 10px; line-height: 1.5; overflow-wrap: anywhere; }
        .order-history-entry time { color: var(--admin-muted); font-size: 9px; white-space: nowrap; }
        .order-empty-history { padding: 16px 0; color: var(--admin-muted); font-size: 11px; }
        .order-not-found { max-width: 700px; padding: 24px; }
        .order-not-found h1 { margin-top: 0; }
        @media(max-width:760px) { .order-detail-grid { grid-template-columns: 1fr; } }
        @media(max-width:640px) {
            .order-heading { align-items: flex-start; flex-direction: column; gap: 12px; margin-bottom: 18px; }
            .order-heading .admin-title { font-size: 28px; }
            .order-back { width: 100%; justify-content: center; }
            .order-detail-panel { padding: 16px; }
            .order-items { padding: 0 14px; }
            .order-item { grid-template-columns: minmax(0,1fr) repeat(3,minmax(50px,auto)); gap: 8px; }
            .order-item-image { width: 36px; height: 36px; flex-basis: 36px; }
            .order-item-label { font-size: 7px; }
            .order-item-value { font-size: 9px; }
            .order-total-row { padding: 13px 15px; }
            .order-history { padding: 4px 14px; }
            .order-history-entry { grid-template-columns: 12px minmax(0,1fr); }
            .order-history-entry time { grid-column: 2; }
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
        <button class="admin-mobile-action" id="orderRefreshButton" type="button" title="Reload page" aria-label="Reload page"><i class="fa-solid fa-rotate-right"></i></button>
    </div>
    <header class="admin-topbar" id="adminSidebar">
        <a class="admin-brand" href="<?= h(appUrl('Admin/AdminDashboard.php')) ?>"><img src="image/logo.jpeg" alt=""><div class="brand-name">dunkhome_<span>kicks</span></div></a>
        <div class="admin-nav-title">Administration</div>
        <nav class="admin-nav-links"><?php $adminNavigationShowThemeToggle = false; require __DIR__ . '/../includes/nav.php'; ?></nav>
        <div class="admin-sidebar-identity"><div class="admin-sidebar-avatar"><?= h($adminInitials) ?></div><div class="admin-sidebar-user"><strong><?= h($adminEmail) ?></strong><span>Administrator</span></div></div>
    </header>
    <main class="admin-content">
        <?php if (!$order): ?>
            <section class="admin-panel order-not-found"><div class="admin-eyebrow">Order operations</div><h1 class="admin-title">Order not found</h1><p class="admin-subtitle">This order may have been removed or the link is invalid.</p><a class="admin-button" href="<?= h(appUrl('Admin/AdminOrders.php')) ?>"><i class="fa-solid fa-arrow-left"></i> Back to orders</a></section>
        <?php else: ?>
            <?php $statusClass = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', (string) $order['status']), '-')); ?>
            <div class="order-heading"><div><div class="admin-eyebrow">Order operations</div><h1 class="admin-title">Order <?= h((string) $order['order_code']) ?></h1><p class="admin-subtitle">Review customer details, purchased items, and status history.</p></div><a class="admin-button order-back" href="<?= h(appUrl('Admin/AdminOrders.php')) ?>"><i class="fa-solid fa-arrow-left"></i> Back to orders</a></div>
            <?php if ($message !== ''): ?><div class="admin-message success" role="status"><?= h($message) ?></div><?php endif; ?>
            <div class="order-detail-grid">
                <section class="admin-panel order-detail-panel" aria-labelledby="orderCustomerTitle">
                    <h2 id="orderCustomerTitle">Customer</h2>
                    <p class="order-customer-name"><?= h((string) $order['customer_name']) ?></p>
                    <p class="order-contact"><?= h((string) $order['mobile']) ?><br><?= h((string) $order['email']) ?></p>
                    <h3>Delivery address</h3>
                    <p class="order-address"><?= nl2br(h((string) $order['address'])) ?><br><?= h((string) $order['city']) ?>, <?= h((string) $order['state']) ?> <?= h((string) $order['pincode']) ?></p>
                </section>
                <section class="admin-panel order-detail-panel" aria-labelledby="orderStatusTitle">
                    <h2 id="orderStatusTitle">Order status</h2>
                    <p class="order-status-display status-<?= h($statusClass) ?>"><?= h((string) $order['status']) ?></p>
                    <form class="order-status-form" method="post">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                        <label for="status">Update status</label>
                        <select id="status" name="status"><?php foreach (orderStatuses() as $option): ?><option <?= $order['status'] === $option ? 'selected' : '' ?> value="<?= h($option) ?>"><?= h($option) ?></option><?php endforeach; ?></select>
                        <label for="note">Order note <span class="muted">Optional</span></label>
                        <textarea id="note" name="note" maxlength="500" placeholder="Add a note to the order history"></textarea>
                        <button class="admin-button primary" type="submit"><i class="fa-solid fa-check"></i> Save status</button>
                    </form>
                </section>
            </div>
            <section class="admin-panel order-items-panel" aria-labelledby="orderItemsTitle">
                <div class="order-section-heading"><h2 id="orderItemsTitle">Items</h2><span><?= count($items) ?> items</span></div>
                <div class="order-items">
                    <?php foreach ($items as $item): ?>
                        <div class="order-item">
                            <div class="order-item-product"><?php if (!empty($item['product_image'])): ?><img class="order-item-image" src="<?= h(appUrl((string) $item['product_image'])) ?>" alt="" loading="lazy"><?php endif; ?><strong class="order-item-name"><?= h((string) $item['product_name']) ?></strong></div>
                            <div><span class="order-item-label">Qty</span><span class="order-item-value"><?= (int) $item['quantity'] ?></span></div>
                            <div><span class="order-item-label">Unit price</span><span class="order-item-value">₹<?= number_format((float) $item['unit_price'], 2) ?></span></div>
                            <div><span class="order-item-label">Subtotal</span><span class="order-item-value">₹<?= number_format((float) $item['subtotal'], 2) ?></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="order-total-row"><span>Order total</span><strong>₹<?= number_format((float) $order['total'], 2) ?></strong></div>
            </section>
            <section class="admin-panel order-history-panel" aria-labelledby="orderHistoryTitle">
                <div class="order-section-heading"><h2 id="orderHistoryTitle">Status history</h2><span><?= count($history) ?> updates</span></div>
                <div class="order-history">
                    <?php if (!$history): ?><p class="order-empty-history">No status history recorded yet.</p><?php else: ?>
                        <?php foreach ($history as $event): ?>
                            <div class="order-history-entry"><span class="order-history-dot" aria-hidden="true"></span><div><strong><?= h((string) $event['new_status']) ?></strong><p><?= h((string) $event['changed_by_type']) ?><?= !empty($event['note']) ? ' · ' . h((string) $event['note']) : '' ?></p></div><time><?= h((string) $event['created_at']) ?></time></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
    <footer class="admin-footer">DunkHome Kicks administration</footer>
</div>
<script src="assets/dunkhome-ui.js?v=20261001-loader4" defer></script>
<script src="assets/admin-sidebar-drawer.js?v=20261001-1" defer></script>
<script>document.getElementById('orderRefreshButton')?.addEventListener('click', () => window.location.reload());</script>
</body>
</html>