<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../includes/order_helpers.php';
requireUser();

$userId = (int) $_SESSION['user_id'];
$message = '';
$fields = [
    'customer_name' => trim((string) ($_POST['customer_name'] ?? '')),
    'mobile' => trim((string) ($_POST['mobile'] ?? '')),
    'email' => trim((string) ($_POST['email'] ?? ($_SESSION['user_email'] ?? ''))),
    'address' => trim((string) ($_POST['address'] ?? '')),
    'city' => trim((string) ($_POST['city'] ?? '')),
    'state' => trim((string) ($_POST['state'] ?? '')),
    'pincode' => trim((string) ($_POST['pincode'] ?? '')),
];

try {
    $cartData = loadCartItems($conn);
} catch (Throwable $error) {
    error_log('Checkout cart load failed: ' . $error->getMessage());
    http_response_code(503);
    exit('Checkout is temporarily unavailable. Please try again later.');
}

if (!$cartData['items']) {
    header('Location: ' . appUrl('User/Cart.php'));
    exit;
}

if (empty($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid request token. Refresh the page and try again.');
    }

    $digits = preg_replace('/\D+/', '', $fields['mobile']);
    $valid = strlen($fields['customer_name']) >= 2
        && strlen($fields['customer_name']) <= 160
        && strlen((string) $digits) >= 10
        && strlen((string) $digits) <= 15
        && filter_var($fields['email'], FILTER_VALIDATE_EMAIL)
        && $fields['address'] !== '' && strlen($fields['address']) <= 500
        && $fields['city'] !== '' && strlen($fields['city']) <= 120
        && $fields['state'] !== '' && strlen($fields['state']) <= 120
        && preg_match('/^[0-9]{4,10}$/', $fields['pincode']);

    if (!$valid) {
        $message = 'Check your name, mobile, email and delivery address fields.';
    } elseif (!hash_equals((string) $_SESSION['checkout_token'], (string) ($_POST['checkout_token'] ?? ''))) {
        $message = 'This booking form has expired. Refresh the page and try again.';
    } else {
        $requestToken = (string) $_SESSION['checkout_token'];
        try {
            $conn->begin_transaction();

            $existing = $conn->prepare('SELECT order_code FROM orders WHERE request_token=? AND user_id=? LIMIT 1');
            $existing->bind_param('si', $requestToken, $userId);
            $existing->execute();
            $existingOrder = $existing->get_result()->fetch_assoc();
            $existing->close();
            if ($existingOrder) {
                $conn->commit();
                header('Location: ' . appUrl('User/BookingSuccess.php?code=' . rawurlencode((string) $existingOrder['order_code'])));
                exit;
            }

            $lockedProducts = [];
            $currentTotal = 0.0;
            $productQuery = $conn->prepare('SELECT id,name,price,image1,is_active FROM products WHERE id=? FOR UPDATE');
            foreach ($_SESSION['cart'] as $rawId => $rawQuantity) {
                $productId = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $quantity = filter_var($rawQuantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
                if (!$productId || !$quantity) {
                    throw new RuntimeException('Invalid cart item.');
                }
                $productQuery->bind_param('i', $productId);
                $productQuery->execute();
                $product = $productQuery->get_result()->fetch_assoc();
                if (!$product || (int) $product['is_active'] !== 1) {
                    throw new RuntimeException('A cart product is unavailable.');
                }
                $subtotal = round((float) $product['price'] * $quantity, 2);
                $currentTotal += $subtotal;
                $lockedProducts[] = ['product' => $product, 'quantity' => $quantity, 'subtotal' => $subtotal];
            }
            $productQuery->close();
            if (!$lockedProducts) {
                throw new RuntimeException('Cart is empty.');
            }

            $orderCode = 'DHK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $status = 'Pending';
            $insertOrder = $conn->prepare('INSERT INTO orders (order_code,user_id,request_token,customer_name,mobile,email,address,city,state,pincode,total,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $insertOrder->bind_param('sissssssssds', $orderCode, $userId, $requestToken, $fields['customer_name'], $fields['mobile'], $fields['email'], $fields['address'], $fields['city'], $fields['state'], $fields['pincode'], $currentTotal, $status);
            $insertOrder->execute();
            $orderId = (int) $conn->insert_id;
            $insertOrder->close();

            $insertItem = $conn->prepare('INSERT INTO order_items (order_id,product_id,product_name,product_image,unit_price,quantity,subtotal) VALUES (?,?,?,?,?,?,?)');
            foreach ($lockedProducts as $item) {
                $product = $item['product'];
                $productId = (int) $product['id'];
                $name = (string) $product['name'];
                $image = (string) $product['image1'];
                $price = (float) $product['price'];
                $quantity = (int) $item['quantity'];
                $subtotal = (float) $item['subtotal'];
                $insertItem->bind_param('iissdid', $orderId, $productId, $name, $image, $price, $quantity, $subtotal);
                $insertItem->execute();
            }
            $insertItem->close();

            $history = $conn->prepare('INSERT INTO order_status_history (order_id,previous_status,new_status,changed_by_type,changed_by_id,note) VALUES (?,NULL,\'Pending\',\'user\',?,\'Booking placed\')');
            $history->bind_param('ii', $orderId, $userId);
            $history->execute();
            $history->close();

            $conn->commit();
            $_SESSION['cart'] = [];
            unset($_SESSION['checkout_token']);
            header('Location: ' . appUrl('User/BookingSuccess.php?code=' . rawurlencode($orderCode)));
            exit;
        } catch (Throwable $error) {
            try { $conn->rollback(); } catch (Throwable $rollbackError) { error_log('Checkout rollback failed: ' . $rollbackError->getMessage()); }
            error_log('Booking creation failed: ' . $error->getMessage());
            $message = 'We could not place this booking. Please check the cart and try again.';
            try { $cartData = loadCartItems($conn); } catch (Throwable $cartError) { error_log('Checkout refresh failed: ' . $cartError->getMessage()); }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#07100d">
    <title>Booking Checkout | DunkHome Kicks</title><link rel="icon" type="image/jpeg" href="image/logo.jpeg"><link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261001-loader4">
    <style>:root{--bg:#07100b;--card:rgba(17,28,21,.78);--text:#f4f8f5;--muted:#9aa99f;--green:#22c55e;--border:rgba(134,239,172,.15)}body{margin:0;color:var(--text);font-family:"DM Sans",sans-serif;background:radial-gradient(circle at 15% 10%,rgba(34,197,94,.11),transparent 30%),var(--bg)}body.light{--bg:#f5f8f6;--card:#fff;--text:#102016;--muted:#607065;--border:rgba(16,32,22,.1)}a{color:inherit;text-decoration:none}.wrap{width:min(1050px,calc(100% - 36px));margin:auto}.top{min-height:78px;display:flex;justify-content:space-between;align-items:center}.brand{font-weight:800}.brand span{color:var(--green)}main{padding:42px 0 80px}h1{font-size:40px}.layout{display:grid;grid-template-columns:1.15fr .85fr;gap:18px}.panel{padding:22px;border:1px solid var(--border);border-radius:18px;background:var(--card)}.fields{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field{display:grid;gap:7px}.field.full{grid-column:1/-1}.field label{font-size:13px;font-weight:700}.field input,.field textarea{width:100%;box-sizing:border-box;padding:12px;border:1px solid var(--border);border-radius:10px;background:transparent;color:var(--text);font:inherit}.field textarea{min-height:90px;resize:vertical}.muted{color:var(--muted);line-height:1.6}.item{display:flex;gap:12px;justify-content:space-between;padding:13px 0;border-bottom:1px solid var(--border)}.message{padding:12px;color:#ffb0b0;border:1px solid rgba(255,123,123,.25);border-radius:10px}.primary{width:100%;margin-top:18px;padding:14px;border:0;border-radius:10px;background:var(--green);color:#041008;font:inherit;font-weight:800;cursor:pointer}@media(max-width:760px){.layout{grid-template-columns:1fr}.fields{grid-template-columns:1fr}.field.full{grid-column:auto}}
    </style>
</head>
<body><div class="wrap"><header class="top"><a class="brand" href="index.php">DunkHome <span>Kicks</span></a><a href="User/Cart.php">Back to cart</a></header><main>
    <div class="muted">No payment required. This places a booking request.</div><h1>Delivery details</h1>
    <?php if ($message !== ''): ?><p class="message" role="alert"><?= h($message) ?></p><?php endif; ?>
    <div class="layout"><form class="panel" method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="checkout_token" value="<?= h((string) $_SESSION['checkout_token']) ?>">
        <div class="fields">
            <div class="field"><label for="customer_name">Full name</label><input id="customer_name" name="customer_name" maxlength="160" required value="<?= h($fields['customer_name']) ?>"></div>
            <div class="field"><label for="mobile">Mobile</label><input id="mobile" name="mobile" type="tel" maxlength="25" required value="<?= h($fields['mobile']) ?>"></div>
            <div class="field full"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="255" required value="<?= h($fields['email']) ?>"></div>
            <div class="field full"><label for="address">Address</label><textarea id="address" name="address" maxlength="500" required><?= h($fields['address']) ?></textarea></div>
            <div class="field"><label for="city">City</label><input id="city" name="city" maxlength="120" required value="<?= h($fields['city']) ?>"></div>
            <div class="field"><label for="state">State</label><input id="state" name="state" maxlength="120" required value="<?= h($fields['state']) ?>"></div>
            <div class="field"><label for="pincode">Pincode</label><input id="pincode" name="pincode" inputmode="numeric" pattern="[0-9]{4,10}" maxlength="10" required value="<?= h($fields['pincode']) ?>"></div>
        </div>
        <button class="primary" type="submit">Confirm booking</button>
    </form><aside class="panel"><h2>Order review</h2>
        <?php foreach ($cartData['items'] as $item): ?><div class="item"><span><?= h((string) $item['product']['name']) ?><br><small class="muted">₹<?= number_format((float) $item['product']['price'], 2) ?> × <?= (int) $item['quantity'] ?></small></span><strong>₹<?= number_format((float) $item['subtotal'], 2) ?></strong></div><?php endforeach; ?>
        <div class="item"><strong>Total</strong><strong>₹<?= number_format((float) $cartData['total'], 2) ?></strong></div>
        <p class="muted">Prices are checked again when you confirm the booking.</p>
    </aside></div>
</main></div><script src="assets/dunkhome-ui.js?v=20261001-loader4" defer></script></body></html>