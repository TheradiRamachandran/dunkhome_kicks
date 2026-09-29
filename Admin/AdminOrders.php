<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../includes/order_helpers.php';
requireAdmin();

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
<!DOCTYPE html><html lang="en"><head><base href="<?= h(appBaseUrl()) ?>"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Manage Orders | DunkHome Kicks</title><link rel="stylesheet" href="assets/dunkhome-ui.css"><link rel="stylesheet" href="assets/admin-pages.css"><link rel="stylesheet" href="assets/admin-navigation.css"><style>
:root{--bg:#07100b;--card:rgba(17,28,21,.78);--text:#f4f8f5;--muted:#9aa99f;--green:#22c55e;--border:rgba(134,239,172,.15)}body{margin:0;min-height:100vh;color:var(--text);font-family:"DM Sans",sans-serif;background:radial-gradient(circle at 15% 10%,rgba(34,197,94,.11),transparent 30%),var(--bg)}body.light{--bg:#f5f8f6;--card:#fff;--text:#102016;--muted:#607065;--border:rgba(16,32,22,.1)}a{color:inherit;text-decoration:none}.wrap{width:min(1180px,calc(100% - 36px));margin:auto}.top{height:78px;display:flex;justify-content:space-between;align-items:center}.panel{margin:30px 0;padding:22px;border:1px solid var(--border);border-radius:18px;background:var(--card)}.muted{color:var(--muted)}form.filters{display:flex;gap:9px;flex-wrap:wrap;margin:18px 0}input,select,button{padding:10px;border:1px solid var(--border);border-radius:8px;background:transparent;color:var(--text);font:inherit}button{cursor:pointer}.row{display:grid;grid-template-columns:1.15fr 1.2fr 1fr .8fr 1fr 1fr auto;gap:10px;align-items:center;padding:14px 0;border-top:1px solid var(--border)}.button{padding:8px 10px;border:1px solid var(--border);border-radius:8px}.pager{display:flex;gap:14px;margin-top:18px}@media(max-width:850px){.row{grid-template-columns:1fr 1fr}.row>*:first-child{font-weight:800}.row .mobilehide{display:none}}@media(max-width:560px){.row{grid-template-columns:1fr}.row .mobilehide{display:block}}
</style></head><body><div class="wrap"><header class="top"><a href="Admin/AdminDashboard.php">DunkHome Kicks · Admin</a><nav class="admin-nav-links"><?php require __DIR__ . '/../includes/nav.php'; ?></nav></header><main class="panel"><div class="muted">Order operations</div><h1>Orders</h1><form class="filters" method="get"><input name="q" value="<?= h($query) ?>" placeholder="ID, customer or mobile"><select name="status"><option value="">All statuses</option><?php foreach (orderStatuses() as $option): ?><option value="<?= h($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select><input type="date" name="date" value="<?= h($date) ?>"><button>Apply filters</button></form><?php if (!$orders): ?><p class="muted">No orders match these filters.</p><?php else: ?><div class="row" aria-hidden="true"><strong>Order ID</strong><strong>Customer</strong><strong>Mobile</strong><strong>Total</strong><strong>Status</strong><strong>Date</strong><span></span></div><?php foreach ($orders as $order): ?><article class="row"><strong><?= h((string) $order['order_code']) ?></strong><span><?= h((string) $order['customer_name']) ?></span><span><?= h((string) $order['mobile']) ?></span><span>₹<?= number_format((float) $order['total'], 2) ?></span><span><?= h((string) $order['status']) ?></span><span><?= h((string) $order['created_at']) ?></span><a class="button" href="Admin/AdminOrderDetails.php?code=<?= rawurlencode((string) $order['order_code']) ?>">Open</a></article><?php endforeach; ?><?php endif; ?><nav class="pager"><?php if ($page > 1): ?><a href="?<?= http_build_query(['q'=>$query,'status'=>$status,'date'=>$date,'page'=>$page-1]) ?>">Previous</a><?php endif; ?><?php if ($offset + count($orders) < $totalRows): ?><a href="?<?= http_build_query(['q'=>$query,'status'=>$status,'date'=>$date,'page'=>$page+1]) ?>">Next</a><?php endif; ?></nav></main></div><script src="assets/dunkhome-ui.js" defer></script></body></html>