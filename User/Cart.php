<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../includes/order_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid request token. Refresh the page and try again.');
    }

    $action = (string) ($_POST['action'] ?? '');
    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $cart = is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : [];

    if ($action === 'add' && $productId) {
        $stmt = $conn->prepare('SELECT id FROM products WHERE id=? AND is_active=1 LIMIT 1');
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->fetch_assoc()) {
            $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
            $cart[$productId] = min(99, (int) ($cart[$productId] ?? 0) + (int) ($quantity ?: 1));
            $_SESSION['cart_message'] = 'Product added to your cart.';
        } else {
            $_SESSION['cart_message'] = 'This product is no longer available.';
        }
        $stmt->close();
    } elseif ($action === 'update' && $productId) {
        $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
        if ($quantity) {
            $cart[$productId] = $quantity;
            $_SESSION['cart_message'] = 'Cart updated.';
        } else {
            unset($cart[$productId]);
            $_SESSION['cart_message'] = 'Item removed from your cart.';
        }
    } elseif ($action === 'remove' && $productId) {
        unset($cart[$productId]);
        $_SESSION['cart_message'] = 'Item removed from your cart.';
    } elseif ($action === 'clear') {
        $cart = [];
        $_SESSION['cart_message'] = 'Your cart is empty.';
    }

    $_SESSION['cart'] = $cart;
    header('Location: ' . appUrl('User/Cart.php'));
    exit;
}

$cartMessage = (string) ($_SESSION['cart_message'] ?? '');
unset($_SESSION['cart_message']);
try {
    $cartData = loadCartItems($conn);
} catch (Throwable $error) {
    error_log('Cart load failed: ' . $error->getMessage());
    http_response_code(503);
    $cartData = ['items' => [], 'total' => 0.0, 'unavailable' => []];
    $cartMessage = 'Cart is temporarily unavailable. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head><base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07100d">
    <title>Your Cart | DunkHome Kicks</title>
    <link rel="icon" type="image/jpeg" href="image/logo.jpeg">
    <link rel="stylesheet" href="assets/dunkhome-ui.css">
    <style>
        :root{--bg:#07100b;--card:rgba(17,28,21,.78);--text:#f4f8f5;--muted:#9aa99f;--green:#22c55e;--border:rgba(134,239,172,.15)}
        body{margin:0;min-height:100vh;color:var(--text);font-family:"DM Sans",sans-serif;background:radial-gradient(circle at 15% 10%,rgba(34,197,94,.11),transparent 30%),var(--bg)}body.light{--bg:#f5f8f6;--card:#fff;--text:#102016;--muted:#607065;--border:rgba(16,32,22,.1)}a{color:inherit;text-decoration:none}.wrap{width:min(1000px,calc(100% - 36px));margin:auto}.top{min-height:78px;display:flex;align-items:center;justify-content:space-between}.brand{font-weight:800}.brand span{color:var(--green)}main{padding:48px 0 80px}h1{font-size:42px;margin:8px 0 25px}.panel{border:1px solid var(--border);border-radius:20px;background:var(--card);padding:22px;margin-bottom:14px}.line{display:grid;grid-template-columns:90px 1fr auto;align-items:center;gap:18px}.line img{width:90px;aspect-ratio:1;object-fit:cover;border-radius:12px}.muted{color:var(--muted)}.price{font-weight:800}.controls{display:flex;align-items:center;gap:8px}.controls input{width:68px;padding:9px;border:1px solid var(--border);border-radius:9px;background:transparent;color:var(--text)}button,.button{padding:10px 13px;border:1px solid var(--border);border-radius:10px;background:transparent;color:var(--text);font:inherit;font-weight:700;cursor:pointer}.primary{background:var(--green);color:#041008;border-color:var(--green)}.summary{display:flex;justify-content:space-between;align-items:center;gap:16px}.message{margin:0 0 15px;color:#9ff0bd}.empty{text-align:center;padding:46px 18px}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}@media(max-width:640px){h1{font-size:34px}.line{grid-template-columns:68px 1fr;gap:12px}.line img{width:68px}.line>.price{grid-column:2}.controls{grid-column:1/-1}.summary{align-items:flex-start;flex-direction:column}.panel{padding:16px}}
    </style>
</head>
<body>
<div class="wrap">
    <header class="top"><a class="brand" href="index.php">DunkHome <span>Kicks</span></a><a href="User/Products.php">Continue shopping</a></header>
    <main>
        <div class="muted">Your selection</div><h1>Shopping cart</h1>
        <?php if ($cartMessage !== ''): ?><p class="message" role="status"><?= h($cartMessage) ?></p><?php endif; ?>
        <?php if (!$cartData['items']): ?>
            <section class="panel empty"><h2>Your cart is empty</h2><p class="muted">Find a pair in the current collection.</p><a class="button primary" href="User/Products.php">Browse products</a></section>
        <?php else: ?>
            <?php foreach ($cartData['items'] as $item): $product = $item['product']; ?>
                <article class="panel line">
                    <img src="<?= h((string) $product['image1']) ?>" alt="<?= h((string) $product['name']) ?>">
                    <div><div class="muted"><?= h((string) $product['category']) ?></div><h2><?= h((string) $product['name']) ?></h2><div class="muted">₹<?= number_format((float) $product['price'], 2) ?> each</div></div>
                    <div class="price">₹<?= number_format((float) $item['subtotal'], 2) ?></div>
                    <form class="controls" method="post">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                        <label class="muted" for="quantity-<?= (int) $product['id'] ?>">Qty</label><input id="quantity-<?= (int) $product['id'] ?>" type="number" name="quantity" min="1" max="99" value="<?= (int) $item['quantity'] ?>">
                        <button name="action" value="update">Update</button><button name="action" value="remove">Remove</button>
                    </form>
                </article>
            <?php endforeach; ?>
            <section class="panel summary"><div><div class="muted">Grand total</div><strong class="price">₹<?= number_format((float) $cartData['total'], 2) ?></strong></div><div class="actions"><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><button name="action" value="clear">Clear cart</button></form><a class="button primary" href="User/Checkout.php">Continue to booking</a></div></section>
        <?php endif; ?>
    </main>
</div>
<script src="assets/dunkhome-ui.js" defer></script>
</body>
</html>