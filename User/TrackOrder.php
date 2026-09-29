<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
requireUser();
$code = trim((string) ($_GET['code'] ?? ''));
$userId = (int) $_SESSION['user_id'];
$stmt = $conn->prepare('SELECT id,order_code,status,created_at FROM orders WHERE order_code=? AND user_id=? LIMIT 1');
$stmt->bind_param('si', $code, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();
$history = [];
if ($order) {
    $stmt = $conn->prepare('SELECT new_status,created_at,note FROM order_status_history WHERE order_id=? ORDER BY created_at,id');
    $stmt->bind_param('i', $order['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $history[] = $row;
    $stmt->close();
} else { http_response_code(404); }
?>
<!DOCTYPE html><html lang="en"><head><base href="<?= h(appBaseUrl()) ?>"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Track Booking | DunkHome Kicks</title><link rel="stylesheet" href="assets/dunkhome-ui.css"><style>
:root{--bg:#07100b;--card:rgba(17,28,21,.78);--text:#f4f8f5;--muted:#9aa99f;--green:#22c55e;--border:rgba(134,239,172,.15)}body{margin:0;min-height:100vh;color:var(--text);font-family:"DM Sans",sans-serif;background:radial-gradient(circle at 15% 10%,rgba(34,197,94,.11),transparent 30%),var(--bg)}body.light{--bg:#f5f8f6;--card:#fff;--text:#102016;--muted:#607065;--border:rgba(16,32,22,.1)}a{color:inherit;text-decoration:none}.wrap{width:min(760px,calc(100% - 36px));margin:auto}.top{height:78px;display:flex;align-items:center;justify-content:space-between}.panel{margin:50px 0;padding:26px;border:1px solid var(--border);border-radius:18px;background:var(--card)}.muted,time{color:var(--muted)}.line{border-left:2px solid var(--border);margin:25px 0 0 9px;padding-left:22px}.event{position:relative;padding:0 0 24px}.event:before{content:"";position:absolute;left:-29px;top:3px;width:12px;height:12px;border-radius:50%;background:var(--green);box-shadow:0 0 0 4px rgba(34,197,94,.13)}.event time{display:block;margin:5px 0;font-size:12px}.button{display:inline-block;padding:11px 14px;border:1px solid var(--border);border-radius:9px;margin-top:12px}
</style></head><body><div class="wrap"><header class="top"><a href="index.php">DunkHome Kicks</a><a href="User/OrderHistory.php">My bookings</a></header><main class="panel"><?php if (!$order): ?><h1>Booking not found</h1><p class="muted">This booking is not available in your account.</p><?php else: ?><div class="muted">Booking <?= h((string) $order['order_code']) ?></div><h1><?= h((string) $order['status']) ?></h1><p class="muted">Placed <?= h((string) $order['created_at']) ?></p><div class="line"><?php foreach ($history as $event): ?><article class="event"><strong><?= h((string) $event['new_status']) ?></strong><time><?= h((string) $event['created_at']) ?></time><?php if ($event['note']): ?><div class="muted"><?= h((string) $event['note']) ?></div><?php endif; ?></article><?php endforeach; ?></div><a class="button" href="User/BookingSuccess.php?code=<?= rawurlencode((string) $order['order_code']) ?>">View booking</a><?php endif; ?></main></div><script src="assets/dunkhome-ui.js" defer></script></body></html>