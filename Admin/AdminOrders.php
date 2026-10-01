<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../includes/order_helpers.php';
requireAdmin();

$adminEmail = (string) ($_SESSION['admin_email'] ?? 'Administrator');
$emailName = explode('@', $adminEmail)[0] ?? '';
$adminInitials = strtoupper(substr((string) (preg_replace('/[^a-zA-Z0-9]/', '', $emailName) ?: 'AD'), 0, 2));

$query = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$date = (string) ($_GET['date'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$orderTotalColumn = tableHasColumn($conn, 'orders', 'total') ? 'total' : 'total_amount';
$orderCodeExpression = tableHasColumn($conn, 'orders', 'order_code') ? 'order_code' : 'CAST(id AS CHAR)';
$mobileColumn = tableHasColumn($conn, 'orders', 'mobile') ? 'mobile' : 'customer_phone';
$where = '1=1';
$types = '';
$params = [];
if ($query !== '') { $where .= " AND ({$orderCodeExpression} LIKE ? OR customer_name LIKE ? OR {$mobileColumn} LIKE ?)"; $wild = '%' . $query . '%'; $types .= 'sss'; array_push($params, $wild, $wild, $wild); }
if (in_array($status, orderStatuses(), true)) { $where .= ' AND status=?'; $types .= 's'; $params[] = $status; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $where .= ' AND DATE(created_at)=?'; $types .= 's'; $params[] = $date; }
$count = $conn->prepare('SELECT COUNT(*) AS total FROM orders WHERE ' . $where);
if ($types !== '') $count->bind_param($types, ...$params);
$count->execute();
$totalRows = (int) $count->get_result()->fetch_assoc()['total'];
$count->close();
$offset = ($page - 1) * $perPage;
$listTypes = $types . 'ii';
$listParams = $params;
$listParams[] = $perPage;
$listParams[] = $offset;
$stmt = $conn->prepare('SELECT id,' . $orderCodeExpression . ' AS order_code,customer_name,' . $mobileColumn . ' AS mobile,' . $orderTotalColumn . ' AS total,status,created_at FROM orders WHERE ' . $where . ' ORDER BY created_at DESC LIMIT ? OFFSET ?');
$stmt->bind_param($listTypes, ...$listParams);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) $orders[] = $row;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<base href="<?= h(appBaseUrl()) ?>">
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Manage orders | DunkHome Kicks</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
	<link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261001-loader4">
	<link rel="stylesheet" href="assets/admin-pages.css">
	<link rel="stylesheet" href="assets/admin-navigation.css">
	<style>
		.orders-heading { margin-bottom: 24px; }
		.orders-heading .admin-subtitle { max-width: 620px; margin-bottom: 0; }
		.orders-panel { overflow: hidden; padding: 0; }
		.orders-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 20px 22px; border-bottom: 1px solid var(--admin-line); }
		.orders-count strong,.orders-count span { display: block; }
		.orders-count strong { color: var(--admin-text); font-size: 13px; }
		.orders-count span { margin-top: 4px; color: var(--admin-muted); font-size: 10px; }
		.order-filters { display: grid; grid-template-columns: minmax(190px,1.3fr) minmax(145px,.9fr) minmax(145px,.9fr) auto; gap: 8px; }
		.order-filters input,.order-filters select { min-width: 0; min-height: 38px; padding: 9px 10px; border: 1px solid var(--admin-line); border-radius: 8px; outline: none; color: var(--admin-text); background: rgba(255,255,255,.035); font: 11px "DM Sans",sans-serif; }
		.order-filters input:focus,.order-filters select:focus { border-color: rgba(121,230,170,.62); box-shadow: 0 0 0 3px rgba(121,230,170,.09); }
		.order-filters select option { color: #18251d; }
		.orders-table-wrap { overflow-x: auto; }
		.orders-table { width: 100%; min-width: 820px; border-collapse: collapse; }
		.orders-table caption { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
		.orders-table th,.orders-table td { padding: 13px 15px; text-align: left; vertical-align: middle; }
		.orders-table th { color: var(--admin-muted); font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
		.orders-table td { border-top: 1px solid var(--admin-line); color: var(--admin-text); font-size: 11px; }
		.order-code { color: var(--admin-green); font-size: 11px; font-weight: 800; }
		.order-customer { font-weight: 700; }
		.order-mobile,.order-date { color: var(--admin-muted); }
		.order-total { white-space: nowrap; font-variant-numeric: tabular-nums; }
		.order-status { display: inline-flex; align-items: center; max-width: 100%; padding: 5px 8px; border: 1px solid var(--admin-line); border-radius: 999px; color: var(--admin-muted); background: rgba(255,255,255,.025); font-size: 9px; font-weight: 700; white-space: nowrap; }
		.order-status.status-delivered,.order-status.status-confirmed { border-color: rgba(121,230,170,.22); color: var(--admin-green); background: rgba(121,230,170,.06); }
		.order-status.status-cancelled { border-color: rgba(255,123,123,.22); color: #ff9c9c; background: rgba(255,123,123,.06); }
		.order-open { min-height: 34px; padding: 7px 10px; font-size: 10px; white-space: nowrap; }
		.orders-empty { padding: 36px 22px; color: var(--admin-muted); font-size: 12px; text-align: center; }
		.orders-pager { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 15px 20px; border-top: 1px solid var(--admin-line); }
		.orders-pager span { color: var(--admin-muted); font-size: 10px; }
		.orders-pager .admin-button { min-height: 34px; padding: 7px 10px; font-size: 10px; }
		@media(max-width:1050px) { .orders-toolbar { align-items: stretch; flex-direction: column; } .order-filters { grid-template-columns: minmax(0,1.2fr) minmax(130px,.9fr) minmax(130px,.9fr) auto; } }
		@media(max-width:640px) {
			.orders-heading { margin-bottom: 18px; }
			.orders-toolbar { padding: 17px; }
			.order-filters { grid-template-columns: minmax(0,1fr) minmax(0,1fr); }
			.order-filters input[name="q"] { grid-column: 1 / -1; }
			.order-filters button { justify-content: center; }
			.orders-table-wrap { overflow: visible; }
			.orders-table { display: block; min-width: 0; }
			.orders-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; }
			.orders-table tbody { display: block; padding: 0 12px; }
			.orders-table tr { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); border-bottom: 1px solid var(--admin-line); }
			.orders-table td { display: block; min-width: 0; padding: 10px; border: 0; overflow-wrap: anywhere; }
			.orders-table td::before { display: block; margin-bottom: 5px; color: var(--admin-muted); content: attr(data-label); font-size: 8px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
			.orders-table td:first-child,.orders-table td:last-child { grid-column: 1 / -1; }
			.orders-pager { padding: 13px 16px; }
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
		<button class="admin-mobile-action" id="ordersRefreshButton" type="button" title="Reload page" aria-label="Reload page"><i class="fa-solid fa-rotate-right"></i></button>
	</div>
	<header class="admin-topbar" id="adminSidebar">
		<a class="admin-brand" href="<?= h(appUrl('Admin/AdminDashboard.php')) ?>"><img src="image/logo.jpeg" alt=""><div class="brand-name">dunkhome_<span>kicks</span></div></a>
		<div class="admin-nav-title">Administration</div>
		<nav class="admin-nav-links"><?php $adminNavigationShowThemeToggle = false; require __DIR__ . '/../includes/nav.php'; ?></nav>
		<div class="admin-sidebar-identity"><div class="admin-sidebar-avatar"><?= h($adminInitials) ?></div><div class="admin-sidebar-user"><strong><?= h($adminEmail) ?></strong><span>Administrator</span></div></div>
	</header>
	<main class="admin-content">
		<div class="orders-heading"><div><div class="admin-eyebrow">Order operations</div><h1 class="admin-title">Manage orders</h1><p class="admin-subtitle">Find customer orders, review payment totals, and open order details.</p></div></div>
		<section class="admin-panel orders-panel" aria-label="Orders">
			<div class="orders-toolbar">
				<div class="orders-count"><strong><?= $totalRows ?> orders</strong><span><?= $query !== '' || $status !== '' || $date !== '' ? 'Filtered results' : 'Most recent customer orders' ?></span></div>
				<form class="order-filters" method="get" role="search">
					<input type="search" name="q" value="<?= h($query) ?>" placeholder="Order ID, customer or mobile" aria-label="Search orders">
					<select name="status" aria-label="Filter by order status"><option value="">All statuses</option><?php foreach (orderStatuses() as $option): ?><option value="<?= h($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select>
					<input type="date" name="date" value="<?= h($date) ?>" aria-label="Filter by order date">
					<button class="admin-button" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
				</form>
			</div>
			<?php if (!$orders): ?>
				<div class="orders-empty">No orders match these filters.</div>
			<?php else: ?>
				<div class="orders-table-wrap">
					<table class="orders-table">
						<caption class="sr-only">Customer orders and status</caption>
						<thead><tr><th>Order</th><th>Customer</th><th>Mobile</th><th>Total</th><th>Status</th><th>Date</th><th>Details</th></tr></thead>
						<tbody>
						<?php foreach ($orders as $order): ?>
							<?php $statusClass = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', (string) $order['status']), '-')); ?>
							<tr>
								<td data-label="Order"><strong class="order-code"><?= h((string) $order['order_code']) ?></strong></td>
								<td data-label="Customer"><span class="order-customer"><?= h((string) $order['customer_name']) ?></span></td>
								<td data-label="Mobile"><span class="order-mobile"><?= h((string) $order['mobile']) ?></span></td>
								<td data-label="Total"><span class="order-total">₹<?= number_format((float) $order['total'], 2) ?></span></td>
								<td data-label="Status"><span class="order-status status-<?= h($statusClass) ?>"><?= h((string) $order['status']) ?></span></td>
								<td data-label="Date"><time class="order-date" datetime="<?= h((string) $order['created_at']) ?>"><?= h((string) $order['created_at']) ?></time></td>
								<td data-label="Details"><a class="admin-button order-open" href="<?= h(appUrl('Admin/AdminOrderDetails.php?code=' . rawurlencode((string) $order['order_code']))) ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open</a></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
			<nav class="orders-pager" aria-label="Order pages">
				<span>Page <?= $page ?> · <?= $totalRows ?> total orders</span>
				<div>
					<?php if ($page > 1): ?><a class="admin-button" href="<?= h(appUrl('Admin/AdminOrders.php?' . http_build_query(['q' => $query, 'status' => $status, 'date' => $date, 'page' => $page - 1]))) ?>">Previous</a><?php endif; ?>
					<?php if ($offset + count($orders) < $totalRows): ?><a class="admin-button" href="<?= h(appUrl('Admin/AdminOrders.php?' . http_build_query(['q' => $query, 'status' => $status, 'date' => $date, 'page' => $page + 1]))) ?>">Next</a><?php endif; ?>
				</div>
			</nav>
		</section>
	</main>
	<footer class="admin-footer">DunkHome Kicks administration</footer>
</div>
<script src="assets/dunkhome-ui.js?v=20261001-loader4" defer></script>
<script src="assets/admin-sidebar-drawer.js?v=20261001-1" defer></script>
<script>document.getElementById('ordersRefreshButton')?.addEventListener('click', () => window.location.reload());</script>
</body>
</html>