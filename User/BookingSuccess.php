<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
requireUser();

$code = trim((string) ($_GET['code'] ?? ''));
$userId = (int) $_SESSION['user_id'];
$order = null;
$items = [];
$history = [];

if (preg_match('/^DHK-[0-9]{8}-[A-F0-9]{6}$/', $code)) {
    $stmt = $conn->prepare('SELECT * FROM orders WHERE order_code=? AND user_id=? LIMIT 1');
    $stmt->bind_param('si', $code, $userId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($order) {
    $stmt = $conn->prepare('SELECT product_name,product_image,unit_price,quantity,subtotal FROM order_items WHERE order_id=? ORDER BY id');
    $stmt->bind_param('i', $order['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $items[] = $row;
    $stmt->close();

    $stmt = $conn->prepare('SELECT new_status,created_at,note FROM order_status_history WHERE order_id=? ORDER BY created_at,id');
    $stmt->bind_param('i', $order['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $history[] = $row;
    $stmt->close();
} else {
    http_response_code(404);
}

$message = "DunkHome Kicks - New Booking\nOrder ID: " . ($order['order_code'] ?? '')
    . "\nCustomer: " . ($order['customer_name'] ?? '')
    . "\nMobile: " . ($order['mobile'] ?? '')
    . "\nEmail: " . ($order['email'] ?? '')
    . "\nTotal: INR " . ($order['total'] ?? '')
    . "\nAddress: " . ($order ? $order['address'] . ', ' . $order['city'] . ', ' . $order['state'] . ' ' . $order['pincode'] : '');
?>
<!DOCTYPE html>
<html lang="en"><head><base href="<?= h(appBaseUrl()) ?>"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#07100d"><title>Booking Confirmation | DunkHome Kicks</title><link rel="icon" type="image/jpeg" href="image/logo.jpeg"><link rel="stylesheet" href="assets/dunkhome-ui.css"><style>
:root{--bg:#07100b;--card:rgba(17,28,21,.78);--text:#f4f8f5;--muted:#9aa99f;--green:#22c55e;--border:rgba(134,239,172,.15)}body{margin:0;color:var(--text);font-family:"DM Sans",sans-serif;background:radial-gradient(circle at 15% 10%,rgba(34,197,94,.11),transparent 30%),var(--bg)}body.light{--bg:#f5f8f6;--card:#fff;--text:#102016;--muted:#607065;--border:rgba(16,32,22,.1)}a{color:inherit;text-decoration:none}.wrap{width:min(900px,calc(100% - 36px));margin:auto}.top{min-height:78px;display:flex;align-items:center;justify-content:space-between}.brand{font-weight:800}.brand span{color:var(--green)}main{padding:40px 0 75px}.panel{padding:25px;margin:15px 0;border:1px solid var(--border);border-radius:18px;background:var(--card)}.success{color:var(--green);font-weight:800}.code{font-size:22px;font-weight:800;overflow-wrap:anywhere}.muted{color:var(--muted);line-height:1.7}.item{display:grid;grid-template-columns:70px 1fr auto;gap:14px;align-items:center;padding:12px 0;border-bottom:1px solid var(--border)}.item img{width:70px;aspect-ratio:1;object-fit:cover;border-radius:10px}.actions{display:flex;flex-wrap:wrap;gap:10px}.button{display:inline-block;padding:12px 15px;border:1px solid var(--border);border-radius:10px;font-weight:700}.primary{background:var(--green);color:#041008;border-color:var(--green)}.timeline{border-left:2px solid var(--border);margin:20px 0 10px 8px;padding-left:20px}.event{position:relative;padding:0 0 20px}.event:before{content:"";position:absolute;left:-27px;top:4px;width:10px;height:10px;border-radius:50%;background:var(--green);box-shadow:0 0 0 4px rgba(34,197,94,.12)}.event time{display:block;font-size:12px;color:var(--muted)}.columns{display:grid;grid-template-columns:1fr 1fr;gap:15px}@media(max-width:620px){.columns{grid-template-columns:1fr}.item{grid-template-columns:56px 1fr}.item>strong{grid-column:2}.item img{width:56px}}
</style></head><body><div class="wrap"><header class="top"><a class="brand" href="index.php">DunkHome <span>Kicks</span></a><a href="User/OrderHistory.php">My bookings</a></header><main>
<?php if (!$order): ?><section class="panel"><h1>Booking not found</h1><p class="muted">This booking does not exist or is not available in your account.</p><a class="button primary" href="User/Products.php">Browse products</a></section>
<?php else: ?><section class="panel"><div class="success">Booking successful</div><h1>Thank you for your order.</h1><p class="muted">Your booking reference</p><div class="code"><?= h((string) $order['order_code']) ?></div><p class="muted">Placed <?= h((string) $order['created_at']) ?> · Status: <?= h((string) $order['status']) ?></p></section>
<div class="columns"><section class="panel"><h2>Products</h2><?php foreach ($items as $item): ?><div class="item"><img src="<?= h((string) $item['product_image']) ?>" alt=""><span><?= h((string) $item['product_name']) ?><br><small class="muted">₹<?= number_format((float) $item['unit_price'], 2) ?> × <?= (int) $item['quantity'] ?></small></span><strong>₹<?= number_format((float) $item['subtotal'], 2) ?></strong></div><?php endforeach; ?><p><strong>Total: ₹<?= number_format((float) $order['total'], 2) ?></strong></p></section>
<section class="panel"><h2>Delivery details</h2><p><?= h((string) $order['customer_name']) ?><br><?= h((string) $order['mobile']) ?><br><?= h((string) $order['email']) ?></p><p class="muted"><?= nl2br(h((string) $order['address'])) ?><br><?= h((string) $order['city']) ?>, <?= h((string) $order['state']) ?> <?= h((string) $order['pincode']) ?></p></section></div>
<section class="panel"><h2>Order status</h2><div class="timeline"><?php foreach ($history as $event): ?><div class="event"><strong><?= h((string) $event['new_status']) ?></strong><time><?= h((string) $event['created_at']) ?></time><?php if (!empty($event['note'])): ?><span class="muted"><?= h((string) $event['note']) ?></span><?php endif; ?></div><?php endforeach; ?></div><div class="actions"><a class="button primary" href="User/TrackOrder.php?code=<?= rawurlencode((string) $order['order_code']) ?>">Track booking</a><a class="button" href="User/Products.php">Continue shopping</a><a class="button" href="javascript:window.print()">Print</a><a class="button" target="_blank" rel="noopener" href="https://wa.me/919566589111?text=<?= rawurlencode($message) ?>">Notify DunkHome on WhatsApp</a></div></section><?php endif; ?>
</main></div><script src="assets/dunkhome-ui.js" defer></script><style>@media print{body{background:#fff!important;color:#111!important}.top,.actions,.dh-theme-trigger{display:none!important}.panel{background:#fff!important;box-shadow:none!important;break-inside:avoid}}</style></body></html>