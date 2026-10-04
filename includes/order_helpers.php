<?php
declare(strict_types=1);

function loadCartItems(mysqli $conn): array
{
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart)) {
        $_SESSION['cart'] = [];
        return ['items' => [], 'total' => 0.0, 'unavailable' => []];
    }

    $items = [];
    $unavailable = [];
    $total = 0.0;
    $stmt = $conn->prepare('SELECT id,name,category,price,image1 FROM products WHERE id=? AND is_active=1 LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Cart products are temporarily unavailable.');
    }

    foreach ($cart as $rawId => $rawQuantity) {
        $productId = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $quantity = filter_var($rawQuantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
        if (!$productId || !$quantity) {
            $unavailable[] = (string) $rawId;
            continue;
        }

        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result ? $result->fetch_assoc() : null;
        if (!$product) {
            $unavailable[] = (string) $productId;
            continue;
        }

        $subtotal = round((float) $product['price'] * $quantity, 2);
        $items[] = ['product' => $product, 'quantity' => $quantity, 'subtotal' => $subtotal];
        $total += $subtotal;
    }

    $stmt->close();
    return ['items' => $items, 'total' => round($total, 2), 'unavailable' => $unavailable];
}

function orderStatuses(): array
{
    global $conn;

    if ($conn instanceof mysqli) {
        $result = $conn->query("SHOW COLUMNS FROM orders LIKE 'status'");
        $column = $result ? $result->fetch_assoc() : null;
        if ($column && preg_match('/^enum\((.*)\)$/i', (string) $column['Type'], $matches)) {
            return str_getcsv($matches[1], ',', "'", '\\');
        }
    }

    return ['Pending', 'Confirmed', 'Processing', 'Ready', 'Out for Delivery', 'Shipped', 'Delivered', 'Cancelled'];
}

function tableHasColumn(mysqli $conn, string $table, string $column): bool
{
    if (!in_array($table, ['orders', 'order_items', 'order_status_history'], true)) {
        return false;
    }

    $stmt = $conn->prepare(
        'SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function loadOrderStatusHistory(mysqli $conn, int $orderId): array
{
    if (tableHasColumn($conn, 'order_status_history', 'new_status')) {
        $sql = 'SELECT new_status,created_at,note FROM order_status_history WHERE order_id=? ORDER BY created_at,id';
    } else {
        $sql = 'SELECT status AS new_status,created_at,note FROM order_status_history WHERE order_id=? ORDER BY created_at,id';
    }

    $statement = $conn->prepare($sql);
    if (!$statement) {
        throw new RuntimeException('Order tracking history is temporarily unavailable.');
    }

    $statement->bind_param('i', $orderId);
    $statement->execute();
    $result = $statement->get_result();
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $statement->close();

    return $history;
}