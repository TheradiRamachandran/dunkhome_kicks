<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../includes/order_helpers.php';
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

    $history = loadOrderStatusHistory($conn, (int) $order['id']);
} else {
    http_response_code(404);
}

$notificationReport = null;
$savedNotifications = $_SESSION['order_notifications'] ?? null;
if ($order && is_array($savedNotifications) && ($savedNotifications['code'] ?? '') === $order['order_code']) {
    $notificationReport = $savedNotifications;
    unset($_SESSION['order_notifications']);
}

?>
<!DOCTYPE html>
<html lang="en"><head>
<?php require __DIR__ . '/../includes/favicon.php'; ?><base href="<?= h(appBaseUrl()) ?>"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#07100d"><title>Booking Confirmation | DunkHome Kicks</title><link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-user-nav25"><style>
:root {
    --confirm-bg: #07100b;
    --confirm-panel: rgba(17, 29, 22, .88);
    --confirm-text: #f4f8f5;
    --confirm-muted: #9aa99f;
    --confirm-green: #8fe9b5;
    --confirm-line: rgba(143, 233, 181, .17);
    --confirm-shadow: 0 24px 70px rgba(0, 0, 0, .2);
}
* { box-sizing: border-box; }
body {
    min-height: 100vh;
    margin: 0;
    color: var(--confirm-text);
    font-family: "Manrope", sans-serif;
    background:
        radial-gradient(ellipse at 8% 10%, rgba(143, 233, 181, .12), transparent 32%),
        radial-gradient(ellipse at 90% 24%, rgba(255, 201, 139, .07), transparent 30%),
        var(--confirm-bg);
}
body.light {
    --confirm-bg: #f3f7f3;
    --confirm-panel: rgba(255, 255, 255, .94);
    --confirm-text: #13231a;
    --confirm-muted: #627267;
    --confirm-green: #26764b;
    --confirm-line: rgba(20, 54, 34, .12);
    --confirm-shadow: 0 22px 60px rgba(22, 45, 31, .08);
}
a { color: inherit; text-decoration: none; }
.confirmation-wrap { width: min(1000px, calc(100% - 40px)); margin: 0 auto; }
.confirmation-main { padding: 136px 0 76px; }
.confirmation-hero, .confirmation-panel {
    border: 1px solid var(--confirm-line);
    border-radius: 23px;
    background: linear-gradient(150deg, var(--confirm-panel), rgba(12, 26, 18, .74));
    box-shadow: var(--confirm-shadow);
}
body.light .confirmation-hero, body.light .confirmation-panel { background: linear-gradient(150deg, #fff, #f1f6f2); }
.confirmation-hero {
    position: relative;
    overflow: hidden;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 26px;
    margin-bottom: 16px;
    padding: clamp(24px, 5vw, 44px);
}
.confirmation-hero::after {
    position: absolute;
    top: -115px;
    right: 40px;
    width: 245px;
    height: 245px;
    border: 1px solid var(--confirm-line);
    border-radius: 50%;
    content: "";
    pointer-events: none;
}
.confirmation-copy, .confirmation-reference { position: relative; z-index: 1; }
.confirmation-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 13px;
    color: var(--confirm-green);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .15em;
    text-transform: uppercase;
}
.confirmation-eyebrow::before { width: 7px; height: 7px; border-radius: 50%; background: var(--confirm-green); content: ""; }
.confirmation-hero h1 { margin: 0; font: clamp(35px, 5vw, 52px)/1.03 "DM Serif Display", serif; letter-spacing: -.04em; }
.confirmation-lead { max-width: 510px; margin: 12px 0 0; color: var(--confirm-muted); font-size: 12px; line-height: 1.75; }
.confirmation-state {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-top: 18px;
    padding: 8px 11px;
    border: 1px solid var(--confirm-line);
    border-radius: 999px;
    color: var(--confirm-green);
    font-size: 10px;
    font-weight: 800;
}
.confirmation-state::before { width: 6px; height: 6px; border-radius: 50%; background: currentColor; content: ""; }
.confirmation-reference {
    min-width: 205px;
    padding: 17px 19px;
    border: 1px solid var(--confirm-line);
    border-radius: 17px;
    background: rgba(143, 233, 181, .06);
}
.reference-label { display: block; margin-bottom: 8px; color: var(--confirm-muted); font-size: 9px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
.reference-code { color: var(--confirm-text); font-size: 17px; font-weight: 800; letter-spacing: .02em; overflow-wrap: anywhere; }
.reference-date { display: block; margin-top: 8px; color: var(--confirm-muted); font-size: 10px; }
.confirmation-actions { display: flex; flex-wrap: wrap; gap: 10px; margin: 0 0 16px; }
.confirmation-button {
    min-height: 45px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 0 17px;
    border: 1px solid var(--confirm-line);
    border-radius: 999px;
    font-size: 10px;
    font-weight: 800;
    transition: transform .2s ease, border-color .2s ease, background .2s ease;
}
.confirmation-button:hover { transform: translateY(-2px); border-color: rgba(143, 233, 181, .48); }
.confirmation-button-primary { border-color: transparent; background: linear-gradient(135deg, #9bf0bd, #59d692); color: #07160d; }
.confirmation-button-secondary { color: var(--confirm-muted); }
.confirmation-panel { min-width: 0; margin: 0 0 16px; padding: clamp(18px, 3vw, 25px); }
.confirmation-panel h2 { margin: 0 0 14px; font: 24px "DM Serif Display", serif; }
.confirmation-columns { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, .9fr); gap: 16px; }
.confirmation-item {
    display: grid;
    grid-template-columns: 58px minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-top: 1px solid var(--confirm-line);
}
.confirmation-item:first-of-type { border-top: 0; padding-top: 0; }
.confirmation-item-image { width: 58px; height: 58px; overflow: hidden; border: 1px solid var(--confirm-line); border-radius: 13px; background: rgba(143, 233, 181, .07); }
.confirmation-item-image img { display: block; width: 100%; height: 100%; object-fit: cover; }
.confirmation-item-name { display: block; margin-bottom: 5px; font-size: 11px; font-weight: 800; line-height: 1.45; overflow-wrap: anywhere; }
.confirmation-item-detail, .confirmation-muted { color: var(--confirm-muted); font-size: 10px; line-height: 1.75; }
.confirmation-item-price { font-size: 11px; font-weight: 800; white-space: nowrap; }
.confirmation-total { display: flex; justify-content: space-between; gap: 12px; margin-top: 8px; padding-top: 15px; border-top: 1px solid var(--confirm-line); font-size: 11px; }
.confirmation-total strong { color: var(--confirm-green); font-size: 17px; }
.delivery-details { margin: 0; font-size: 12px; font-weight: 700; line-height: 1.8; overflow-wrap: anywhere; }
.delivery-address { margin: 10px 0 0; color: var(--confirm-muted); font-size: 11px; line-height: 1.8; overflow-wrap: anywhere; }
.confirmation-timeline { margin: 18px 0 0 8px; padding-left: 20px; border-left: 2px solid var(--confirm-line); }
.confirmation-event { position: relative; padding: 0 0 19px; }
.confirmation-event:last-child { padding-bottom: 0; }
.confirmation-event::before { position: absolute; top: 3px; left: -27px; width: 10px; height: 10px; border: 2px solid var(--confirm-bg); border-radius: 50%; background: var(--confirm-green); box-shadow: 0 0 0 3px rgba(143, 233, 181, .15); content: ""; }
.confirmation-event strong { font-size: 11px; }
.confirmation-event time { display: block; margin: 5px 0; color: var(--confirm-muted); font-size: 10px; }
.confirmation-timeline-empty { display: flex; align-items: flex-start; gap: 11px; padding: 14px; border: 1px solid var(--confirm-line); border-radius: 13px; background: rgba(143, 233, 181, .05); }
.confirmation-timeline-empty::before { width: 8px; height: 8px; flex: 0 0 auto; margin-top: 4px; border-radius: 50%; background: var(--confirm-green); box-shadow: 0 0 0 4px rgba(143, 233, 181, .1); content: ""; }
.confirmation-timeline-empty p { margin: 0; color: var(--confirm-muted); font-size: 11px; line-height: 1.7; }
.confirmation-timeline-empty strong { color: var(--confirm-text); }
.notification-heading { display: flex; align-items: center; gap: 9px; }
.notification-heading svg { width: 20px; height: 20px; color: var(--confirm-green); }
.notification-line { display: grid; grid-template-columns: 130px minmax(0, 1fr); gap: 14px; padding: 11px 0; border-top: 1px solid var(--confirm-line); font-size: 10px; }
.notification-line strong { color: var(--confirm-text); }
.notification-line span { color: var(--confirm-muted); line-height: 1.6; }
.notification-line .delivery-ok { color: var(--confirm-green); }
.notification-line .delivery-error { color: #e9a1a1; }
.confirmation-empty { padding: 36px; text-align: center; }
.confirmation-empty p { color: var(--confirm-muted); font-size: 12px; line-height: 1.7; }
@media (max-width: 760px) {
    .confirmation-columns { grid-template-columns: minmax(0, 1fr); }
    .confirmation-hero { grid-template-columns: minmax(0, 1fr); gap: 18px; }
    .confirmation-reference { width: fit-content; min-width: 0; }
}
@media (max-width: 600px) {
    .confirmation-wrap { width: calc(100% - 28px); }
    .confirmation-main { padding: 105px 0 55px; }
    .confirmation-hero { padding: 23px 19px; border-radius: 19px; }
    .confirmation-hero h1 { font-size: 38px; }
    .confirmation-lead { font-size: 11px; }
    .confirmation-actions { display: grid; grid-template-columns: minmax(0, 1fr); }
    .confirmation-button { width: 100%; min-height: 46px; }
    .confirmation-panel { border-radius: 18px; }
}
@media (max-width: 380px) {
    .confirmation-item { grid-template-columns: 48px minmax(0, 1fr); gap: 10px; }
    .confirmation-item-image { width: 48px; height: 48px; }
    .confirmation-item-price { grid-column: 2; }
    .notification-line { grid-template-columns: minmax(0, 1fr); gap: 4px; }
}
@media (prefers-reduced-motion: reduce) { .confirmation-button { transition: none; } }
</style>    <link rel="stylesheet" href="<?= h(appUrl('assets/user-premium.css?v=20261003-premium1')) ?>"><link rel="stylesheet" href="<?= h(appUrl('assets/dunkhome-footer.css?v=20261003-footer3')) ?>">
</head><body><?php require __DIR__ . '/../includes/user_nav.php'; ?><div class="confirmation-wrap"><main class="confirmation-main">
<?php if (!$order): ?><section class="confirmation-panel confirmation-empty"><div class="confirmation-eyebrow">DunkHome Kicks / Booking</div><h1>Booking not found.</h1><p>This booking does not exist or is not available in your account.</p><a class="confirmation-button confirmation-button-primary" href="User/Products.php">Browse the collection <span aria-hidden="true">↗</span></a></section>
<?php else: ?><section class="confirmation-hero">
    <div class="confirmation-copy">
        <div class="confirmation-eyebrow">DunkHome Kicks / Booking received</div>
        <h1>Thanks for choosing your next pair.</h1>
        <p class="confirmation-lead">Your booking is recorded. Keep the reference below to follow each update from confirmation through delivery.</p>
        <span class="confirmation-state"><?= h((string) $order['status']) ?> · No payment collected</span>
    </div>
    <div class="confirmation-reference">
        <span class="reference-label">Booking reference</span>
        <span class="reference-code"><?= h((string) $order['order_code']) ?></span>
        <span class="reference-date">Placed <?= h((string) $order['created_at']) ?></span>
    </div>
</section>
<div class="confirmation-actions">
    <a class="confirmation-button confirmation-button-primary" href="User/TrackOrder.php?code=<?= rawurlencode((string) $order['order_code']) ?>">Track your booking <span aria-hidden="true">→</span></a>
    <a class="confirmation-button confirmation-button-secondary" href="User/Products.php">Continue shopping</a>
</div>
<?php if ($notificationReport): ?>
<section class="confirmation-panel notification-report" aria-labelledby="notificationTitle">
<h2 class="notification-heading" id="notificationTitle"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 5.75A2.75 2.75 0 0 1 7.75 3h8.5A2.75 2.75 0 0 1 19 5.75v8.5A2.75 2.75 0 0 1 16.25 17H10l-5 4V5.75Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8 8h8M8 12h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>Notification status</h2>
<?php foreach ([
    'Email to you' => $notificationReport['customer_email'] ?? [],
    'Email to store' => $notificationReport['admin_email'] ?? [],
] as $label => $delivery): ?>
<?php $deliveryStatus = (string) ($delivery['status'] ?? 'error'); $deliveryMessage = (string) ($delivery['message'] ?? 'Delivery status is unavailable.'); ?>
<div class="notification-line"><strong><?= h($label) ?></strong><span class="<?= $deliveryStatus === 'success' ? 'delivery-ok' : 'delivery-error' ?>"><?= h($deliveryMessage) ?></span></div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<div class="confirmation-columns">
    <section class="confirmation-panel" aria-labelledby="productsTitle"><h2 id="productsTitle">Your pairs</h2>
        <?php foreach ($items as $item): ?><div class="confirmation-item"><div class="confirmation-item-image"><?php if ($item['product_image'] !== ''): ?><img src="<?= h((string) $item['product_image']) ?>" alt=""><?php endif; ?></div><div><span class="confirmation-item-name"><?= h((string) $item['product_name']) ?></span><span class="confirmation-item-detail">₹<?= number_format((float) $item['unit_price'], 2) ?> × <?= (int) $item['quantity'] ?></span></div><strong class="confirmation-item-price">₹<?= number_format((float) $item['subtotal'], 2) ?></strong></div><?php endforeach; ?>
        <div class="confirmation-total"><span>Booking total</span><strong>₹<?= number_format((float) $order['total'], 2) ?></strong></div>
    </section>
    <section class="confirmation-panel" aria-labelledby="deliveryTitle"><h2 id="deliveryTitle">Delivery details</h2>
        <p class="delivery-details"><?= h((string) $order['customer_name']) ?><br><?= h((string) $order['mobile']) ?><br><?= h((string) $order['email']) ?></p>
        <p class="delivery-address"><?= nl2br(h((string) $order['address'])) ?><br><?= h((string) $order['city']) ?>, <?= h((string) $order['state']) ?> <?= h((string) $order['pincode']) ?></p>
    </section>
</div>
<section class="confirmation-panel" aria-labelledby="timelineTitle"><h2 id="timelineTitle">Booking progress</h2><?php if ($history): ?><div class="confirmation-timeline"><?php foreach ($history as $event): ?><article class="confirmation-event"><strong><?= h((string) $event['new_status']) ?></strong><time datetime="<?= h(date(DATE_ATOM, strtotime((string) $event['created_at']) ?: time())) ?>"><?= h((string) $event['created_at']) ?></time><?php if (!empty($event['note'])): ?><span class="confirmation-muted"><?= h((string) $event['note']) ?></span><?php endif; ?></article><?php endforeach; ?></div><?php else: ?><div class="confirmation-timeline-empty"><p><strong>Your booking is in the queue.</strong><br>The store’s next status update will appear here. You can also follow every step from the booking tracker.</p></div><?php endif; ?></section>
<?php endif; ?>
</main></div><?php require __DIR__ . '/../includes/footer.php'; ?><script src="assets/dunkhome-ui.js?v=20261003-nav14" defer></script><style>@media print{body{background:#fff!important;color:#111!important}.dh-user-header,.confirmation-actions,.notification-report,.dh-theme-trigger,.dk-site-footer{display:none!important}.confirmation-panel,.confirmation-hero{background:#fff!important;color:#111!important;box-shadow:none!important;break-inside:avoid}}</style></body></html>