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
if ($query !== '') { $where .= ' AND order_code LIKE ?'; $types .= 's'; $params[] = '%' . $query . '%'; }
if (in_array($status, orderStatuses(), true)) { $where .= ' AND status=?'; $types .= 's'; $params[] = $status; }
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
while ($row = $result->fetch_assoc()) $orders[] = $row;
$stmt->close();
?>
<!DOCTYPE html><html lang="en"><head><base href="<?= h(appBaseUrl()) ?>"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My Bookings | DunkHome Kicks</title><link rel="stylesheet" href="assets/dunkhome-ui.css"><style>
:root{--bg:#07100b;--card:rgba(17,28,21,.78);--text:#f4f8f5;--muted:#9aa99f;--green:#22c55e;--border:rgba(134,239,172,.15)}body{margin:0;min-height:100vh;color:var(--text);font-family:"DM Sans",sans-serif;background:radial-gradient(circle at 15% 10%,rgba(34,197,94,.11),transparent 30%),var(--bg)}body.light{--bg:#f5f8f6;--card:#fff;--text:#102016;--muted:#607065;--border:rgba(16,32,22,.1)}a{color:inherit;text-decoration:none}.wrap{width:min(1000px,calc(100% - 36px));margin:auto}.top{height:78px;display:flex;align-items:center;justify-content:space-between}.panel{margin:35px 0;padding:22px;border:1px solid var(--border);border-radius:18px;background:var(--card)}.muted{color:var(--muted)}form{display:flex;gap:10px;margin:16px 0}input,select,button{padding:10px;border:1px solid var(--border);border-radius:9px;background:transparent;color:var(--text);font:inherit}button{cursor:pointer}.row{display:grid;grid-template-columns:1.4fr 1fr 1fr auto;gap:14px;align-items:center;padding:16px 0;border-bottom:1px solid var(--border)}.button{display:inline-block;padding:9px 11px;border:1px solid var(--border);border-radius:8px}.pager{display:flex;gap:12px;margin-top:18px}@media(max-width:680px){.row{grid-template-columns:1fr 1fr}.row>*:first-child{grid-column:1/-1}form{flex-wrap:wrap}}
</style></head><body><div class="wrap"><header class="top"><a href="index.php">DunkHome Kicks</a><a href="User/Products.php">Continue shopping</a></header><main class="panel"><div class="muted">Account</div><h1>My bookings</h1><form method="get"><input name="q" value="<?= h($query) ?>" placeholder="Search order ID"><select name="status"><option value="">All statuses</option><?php foreach (orderStatuses() as $option): ?><option value="<?= h($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select><button>Filter</button></form><?php if (!$orders): ?><p class="muted">No bookings found.</p><?php else: ?><?php foreach ($orders as $order): ?><article class="row"><strong><?= h((string) $order['order_code']) ?></strong><span>₹<?= number_format((float) $order['total'], 2) ?></span><span><?= h((string) $order['status']) ?><br><small class="muted"><?= h((string) $order['created_at']) ?></small></span><span><a class="button" href="User/BookingSuccess.php?code=<?= rawurlencode((string) $order['order_code']) ?>">View</a> <a class="button" href="User/TrackOrder.php?code=<?= rawurlencode((string) $order['order_code']) ?>">Track</a></span></article><?php endforeach; ?><?php endif; ?><nav class="pager"><?php if ($page > 1): ?><a href="?<?= http_build_query(['q' => $query, 'status' => $status, 'page' => $page - 1]) ?>">Previous</a><?php endif; ?><?php if ($offset + count($orders) < $totalRows): ?><a href="?<?= http_build_query(['q' => $query, 'status' => $status, 'page' => $page + 1]) ?>">Next</a><?php endif; ?></nav></main></div><script src="assets/dunkhome-ui.js" defer></script></body></html>