<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../includes/order_helpers.php';
requireAdmin();
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
<!DOCTYPE html><html lang="en"><head><base href="<?= h(appBaseUrl()) ?>"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Order Details | DunkHome Kicks</title><link rel="stylesheet" href="assets/dunkhome-ui.css"><link rel="stylesheet" href="assets/admin-pages.css"><link rel="stylesheet" href="assets/admin-navigation.css"><style>
:root{--bg:#07100b;--card:rgba(17,28,21,.78);--text:#f4f8f5;--muted:#9aa99f;--green:#22c55e;--border:rgba(134,239,172,.15)}body{margin:0;min-height:100vh;color:var(--text);font-family:"DM Sans",sans-serif;background:radial-gradient(circle at 15% 10%,rgba(34,197,94,.11),transparent 30%),var(--bg)}body.light{--bg:#f5f8f6;--card:#fff;--text:#102016;--muted:#607065;--border:rgba(16,32,22,.1)}a{color:inherit;text-decoration:none}.wrap{width:min(1000px,calc(100% - 36px));margin:auto}.top{height:78px;display:flex;align-items:center;justify-content:space-between}.panel{margin:18px 0;padding:22px;border:1px solid var(--border);border-radius:17px;background:var(--card)}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.muted,time{color:var(--muted)}.line{display:flex;justify-content:space-between;gap:14px;padding:12px 0;border-bottom:1px solid var(--border)}input,select,textarea,button{padding:11px;border:1px solid var(--border);border-radius:9px;background:transparent;color:var(--text);font:inherit}textarea{display:block;width:100%;box-sizing:border-box;margin:10px 0;min-height:72px}button{cursor:pointer;background:var(--green);color:#041008;font-weight:800}.message{color:#9ff0bd}@media(max-width:680px){.grid{grid-template-columns:1fr}}
</style></head><body><div class="wrap"><header class="top"><a class="admin-brand" href="Admin/AdminDashboard.php">DunkHome <span>Kicks</span></a><nav class="admin-nav-links"><?php require __DIR__ . '/../includes/nav.php'; ?></nav></header><main><?php if (!$order): ?><section class="panel"><h1>Order not found</h1></section><?php else: ?><h1><?= h((string) $order['order_code']) ?></h1><?php if ($message): ?><p class="message" role="status"><?= h($message) ?></p><?php endif; ?><div class="grid"><section class="panel"><h2>Customer</h2><p><?= h((string) $order['customer_name']) ?><br><?= h((string) $order['mobile']) ?><br><?= h((string) $order['email']) ?></p><h3>Delivery address</h3><p class="muted"><?= nl2br(h((string) $order['address'])) ?><br><?= h((string) $order['city']) ?>, <?= h((string) $order['state']) ?> <?= h((string) $order['pincode']) ?></p></section><section class="panel"><h2>Status</h2><p><?= h((string) $order['status']) ?></p><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><label for="status">Update status</label><select id="status" name="status"><?php foreach (orderStatuses() as $option): ?><option <?= $order['status'] === $option ? 'selected' : '' ?> value="<?= h($option) ?>"><?= h($option) ?></option><?php endforeach; ?></select><textarea name="note" maxlength="500" placeholder="Optional note for order history"></textarea><button type="submit">Save status</button></form></section></div><section class="panel"><h2>Products</h2><?php foreach ($items as $item): ?><div class="line"><span><?= h((string) $item['product_name']) ?> · <?= (int) $item['quantity'] ?> × ₹<?= number_format((float) $item['unit_price'], 2) ?></span><strong>₹<?= number_format((float) $item['subtotal'], 2) ?></strong></div><?php endforeach; ?><p><strong>Total ₹<?= number_format((float) $order['total'], 2) ?></strong></p></section><section class="panel"><h2>Status history</h2><?php foreach ($history as $event): ?><div class="line"><span><strong><?= h((string) $event['new_status']) ?></strong><br><small class="muted"><?= h((string) $event['changed_by_type']) ?> · <?= h((string) ($event['note'] ?? '')) ?></small></span><time><?= h((string) $event['created_at']) ?></time></div><?php endforeach; ?></section><?php endif; ?></main></div><script src="assets/dunkhome-ui.js" defer></script></body></html>