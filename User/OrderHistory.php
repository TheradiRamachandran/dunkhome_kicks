<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../includes/order_helpers.php';
requireUser();

$userId = (int) $_SESSION['user_id'];
$query = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$where = 'user_id=?';
$types = 'i';
$params = [$userId];
if ($query !== '') {
    $where .= ' AND order_code LIKE ?';
    $types .= 's';
    $params[] = '%' . $query . '%';
}
if (in_array($status, orderStatuses(), true)) {
    $where .= ' AND status=?';
    $types .= 's';
    $params[] = $status;
}

$count = $conn->prepare('SELECT COUNT(*) AS total FROM orders WHERE ' . $where);
$count->bind_param($types, ...$params);
$count->execute();
$totalRows = (int) $count->get_result()->fetch_assoc()['total'];
$count->close();

$offset = ($page - 1) * $perPage;
$sql = 'SELECT order_code,total,status,created_at FROM orders WHERE ' . $where . ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
$types .= 'ii';
$params[] = $perPage;
$params[] = $offset;
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

$statusClass = static function (string $orderStatus): string {
    $normalized = strtolower($orderStatus);
    if ($normalized === 'delivered') {
        return 'status-complete';
    }
    if ($normalized === 'cancelled') {
        return 'status-cancelled';
    }
    if (in_array($normalized, ['out for delivery', 'shipped', 'ready'], true)) {
        return 'status-shipping';
    }
    return 'status-active';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../includes/favicon.php'; ?>
    <base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07100d">
    <title>My Bookings | DunkHome Kicks</title>
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-user-nav25">
    <link rel="stylesheet" href="<?= h(appUrl('assets/user-premium.css?v=20261003-premium1')) ?>">
    <link rel="stylesheet" href="<?= h(appUrl('assets/dunkhome-footer.css?v=20261003-footer3')) ?>">
    <style>
        body { min-height: 100vh; background: radial-gradient(ellipse at 12% 12%, rgba(68, 190, 125, .12), transparent 32%), var(--bg); }
        .orders-wrap { width: min(1040px, calc(100% - 36px)); margin: 0 auto; padding: 132px 0 76px; }
        .orders-heading { display: flex; align-items: end; justify-content: space-between; gap: 24px; margin: 16px 0 28px; }
        .orders-eyebrow { margin: 0 0 9px; color: var(--green); font-size: 11px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; }
        .orders-heading h1 { margin: 0; font-family: "Playfair Display", Georgia, serif; font-size: clamp(36px, 6vw, 58px); letter-spacing: -.04em; line-height: 1.04; }
        .orders-heading p:last-child { max-width: 540px; margin: 12px 0 0; color: var(--muted); line-height: 1.65; }
        .orders-shop-link, .orders-action { display: inline-flex; min-height: 42px; align-items: center; justify-content: center; gap: 9px; padding: 0 16px; border: 1px solid var(--border); border-radius: 999px; color: var(--text); font-size: 13px; font-weight: 800; text-decoration: none; transition: transform 180ms ease, border-color 180ms ease, background 180ms ease; }
        .orders-shop-link { flex: 0 0 auto; border-color: transparent; background: linear-gradient(135deg, #83e6b1, #4fd69a); color: #07140d; }
        .orders-shop-link:hover, .orders-action:hover { transform: translateY(-2px); border-color: var(--green); }
        .orders-panel { overflow: hidden; border: 1px solid var(--border); border-radius: 24px; background: linear-gradient(155deg, var(--user-panel-top), var(--user-panel-bottom)); box-shadow: var(--shadow); }
        .orders-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 23px 26px; border-bottom: 1px solid var(--border); }
        .orders-count { margin: 0; color: var(--muted); font-size: 13px; }
        .orders-count strong { color: var(--text); font-size: 16px; }
        .orders-filter { display: flex; width: min(100%, 650px); gap: 10px; }
        .orders-filter input, .orders-filter select { min-height: 44px; margin: 0; }
        .orders-filter input { flex: 1 1 220px; min-width: 0; }
        .orders-filter select { flex: 0 1 190px; }
        .orders-filter button { min-height: 44px; padding: 0 18px; border: 0; border-radius: 999px; background: linear-gradient(135deg, #83e6b1, #4fd69a); color: #07140d; font: inherit; font-size: 13px; font-weight: 800; cursor: pointer; }
        .orders-filter select option { color: #102016; }
        .orders-list { padding: 0 26px; }
        .booking-card { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(120px, .7fr) minmax(145px, .8fr) auto; align-items: center; gap: 20px; padding: 23px 0; border-bottom: 1px solid var(--border); }
        .booking-card:last-child { border-bottom: 0; }
        .booking-code { display: block; overflow-wrap: anywhere; color: var(--text); font-size: 15px; font-weight: 800; letter-spacing: .015em; }
        .booking-label { display: block; margin-bottom: 7px; color: var(--muted); font-size: 10px; font-weight: 800; letter-spacing: .13em; text-transform: uppercase; }
        .booking-meta { color: var(--muted); font-size: 13px; line-height: 1.5; }
        .booking-total { color: var(--text); font-size: 15px; font-weight: 800; white-space: nowrap; }
        .booking-status { display: inline-flex; align-items: center; gap: 7px; padding: 7px 11px; border: 1px solid var(--border); border-radius: 999px; font-size: 12px; font-weight: 800; white-space: nowrap; }
        .booking-status::before { width: 7px; height: 7px; border-radius: 50%; background: currentColor; content: ""; }
        .status-active { color: #eacb7b; background: rgba(234, 203, 123, .1); }
        .status-shipping, .status-complete { color: #79dca8; background: rgba(121, 220, 168, .09); }
        .status-cancelled { color: #f19a9a; background: rgba(241, 154, 154, .09); }
        body.light .status-active { color: #805a09; background: rgba(234, 203, 123, .2); }
        body.light .status-shipping, body.light .status-complete { color: #26764b; background: rgba(38, 118, 75, .1); }
        body.light .status-cancelled { color: #a43c3c; background: rgba(164, 60, 60, .1); }
        .booking-actions { display: flex; gap: 8px; }
        .orders-action { min-height: 38px; padding: 0 13px; font-size: 12px; }
        .orders-action-primary { border-color: transparent; background: rgba(131, 230, 177, .12); color: var(--green-soft); }
        .orders-empty { padding: 64px 22px; text-align: center; }
        .orders-empty-icon { display: grid; width: 60px; height: 60px; margin: 0 auto 18px; place-items: center; border: 1px solid var(--border); border-radius: 20px; background: rgba(131, 230, 177, .08); color: var(--green); font-size: 25px; }
        .orders-empty h2 { margin: 0; font-family: "Playfair Display", Georgia, serif; font-size: 26px; }
        .orders-empty p { max-width: 430px; margin: 10px auto 22px; color: var(--muted); line-height: 1.65; }
        .orders-empty .orders-shop-link { min-height: 44px; }
        .orders-pager { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 26px; border-top: 1px solid var(--border); }
        .orders-pager:empty { display: none; }
        .orders-page-number { color: var(--muted); font-size: 12px; }
        .visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; clip-path: inset(50%); }
        @media (max-width: 760px) {
            .orders-wrap { padding-top: 112px; }
            .orders-heading { align-items: start; flex-direction: column; }
            .orders-toolbar { align-items: stretch; flex-direction: column; }
            .orders-filter { width: 100%; }
            .booking-card { grid-template-columns: minmax(0, 1fr) auto; gap: 17px 12px; }
            .booking-reference { grid-column: 1 / -1; }
            .booking-status-cell { justify-self: end; }
            .booking-total-cell { grid-column: 1; }
            .booking-actions { grid-column: 2; grid-row: 3; }
        }
        @media (max-width: 460px) {
            .orders-wrap { width: min(100% - 24px, 1040px); padding-top: 104px; }
            .orders-panel { border-radius: 19px; }
            .orders-toolbar { padding: 19px 17px; }
            .orders-list { padding: 0 17px; }
            .orders-filter { flex-wrap: wrap; }
            .orders-filter input, .orders-filter select { flex-basis: 100%; }
            .orders-filter button { flex: 1; }
            .booking-card { gap: 15px 8px; }
            .booking-actions { gap: 6px; }
            .orders-action { padding: 0 11px; }
            .orders-pager { padding: 16px 17px; }
        }
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/user_nav.php'; ?>
<main class="orders-wrap">
    <section class="orders-heading" aria-labelledby="ordersTitle">
        <div>
            <p class="orders-eyebrow">Your account · Order history</p>
            <h1 id="ordersTitle">My bookings</h1>
            <p>Every pair you’ve ordered, all in one place. Check a booking’s latest status or follow its delivery journey.</p>
        </div>
        <a class="orders-shop-link" href="<?= h(appUrl('User/Products.php')) ?>">Explore sneakers <span aria-hidden="true">↗</span></a>
    </section>

    <section class="orders-panel" aria-label="Your bookings">
        <div class="orders-toolbar">
            <p class="orders-count"><strong><?= number_format($totalRows) ?></strong> <?= $totalRows === 1 ? 'booking' : 'bookings' ?><?= ($query !== '' || $status !== '') ? ' found' : '' ?></p>
            <form class="orders-filter" method="get" action="<?= h(appUrl('User/OrderHistory.php')) ?>" role="search">
                <label class="visually-hidden" for="bookingSearch">Search by booking reference</label>
                <input id="bookingSearch" name="q" value="<?= h($query) ?>" placeholder="Search booking reference">
                <label class="visually-hidden" for="bookingStatus">Filter by status</label>
                <select id="bookingStatus" name="status">
                    <option value="">All statuses</option>
                    <?php foreach (orderStatuses() as $option): ?>
                        <option value="<?= h($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Find bookings</button>
            </form>
        </div>

        <?php if (!$orders): ?>
            <div class="orders-empty">
                <div class="orders-empty-icon" aria-hidden="true">✦</div>
                <h2><?= $query !== '' || $status !== '' ? 'No matching bookings' : 'Your next pair starts here' ?></h2>
                <p><?= $query !== '' || $status !== '' ? 'Try another reference or clear the filters to see your bookings.' : 'When you place a booking, you’ll find its status and tracking details here.' ?></p>
                <?php if ($query !== '' || $status !== ''): ?>
                    <a class="orders-action" href="<?= h(appUrl('User/OrderHistory.php')) ?>">Clear filters</a>
                <?php else: ?>
                    <a class="orders-shop-link" href="<?= h(appUrl('User/Products.php')) ?>">Browse the collection <span aria-hidden="true">→</span></a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($orders as $order): ?>
                    <?php $orderCode = (string) $order['order_code']; $orderStatus = (string) $order['status']; ?>
                    <article class="booking-card">
                        <div class="booking-reference">
                            <span class="booking-label">Booking reference</span>
                            <strong class="booking-code"><?= h($orderCode) ?></strong>
                            <span class="booking-meta"><?= h((string) $order['created_at']) ?></span>
                        </div>
                        <div class="booking-total-cell">
                            <span class="booking-label">Booking total</span>
                            <span class="booking-total">₹<?= number_format((float) $order['total'], 2) ?></span>
                        </div>
                        <div class="booking-status-cell">
                            <span class="booking-label">Current status</span>
                            <span class="booking-status <?= h($statusClass($orderStatus)) ?>"><?= h($orderStatus) ?></span>
                        </div>
                        <div class="booking-actions">
                            <a class="orders-action orders-action-primary" href="<?= h(appUrl('User/BookingSuccess.php?code=' . rawurlencode($orderCode))) ?>">Details</a>
                            <a class="orders-action" href="<?= h(appUrl('User/TrackOrder.php?code=' . rawurlencode($orderCode))) ?>">Track <span aria-hidden="true">→</span></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($page > 1 || $offset + count($orders) < $totalRows): ?>
                <nav class="orders-pager" aria-label="Booking pages">
                    <?php if ($page > 1): ?>
                        <a class="orders-action" href="<?= h(appUrl('User/OrderHistory.php?' . http_build_query(['q' => $query, 'status' => $status, 'page' => $page - 1]))) ?>">← Previous</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <span class="orders-page-number">Page <?= number_format($page) ?></span>
                    <?php if ($offset + count($orders) < $totalRows): ?>
                        <a class="orders-action" href="<?= h(appUrl('User/OrderHistory.php?' . http_build_query(['q' => $query, 'status' => $status, 'page' => $page + 1]))) ?>">Next →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="<?= h(appUrl('assets/dunkhome-ui.js?v=20261003-nav14')) ?>" defer></script>
</body>
</html>
