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

    if ($action === 'add' && $productId && !userLoggedIn()) {
        $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
        $_SESSION['pending_cart_add'] = [
            'product_id' => (int) $productId,
            'quantity' => $quantity ?: 1,
        ];
        $returnPath = 'User/Cart.php';
        header('Location: ' . appUrl('User/SignIn.php?' . http_build_query([
            'return_to' => $returnPath,
            'intent' => 'cart',
        ])));
        exit;
    }

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

$pendingCartAdd = $_SESSION['pending_cart_add'] ?? null;
if (userLoggedIn() && is_array($pendingCartAdd)) {
    unset($_SESSION['pending_cart_add']);
    $pendingProductId = filter_var($pendingCartAdd['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $pendingQuantity = filter_var($pendingCartAdd['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);

    if ($pendingProductId && $pendingQuantity) {
        $statement = $conn->prepare('SELECT id FROM products WHERE id = ? AND is_active = 1 LIMIT 1');
        if (!$statement) {
            error_log('Pending cart product query preparation failed: ' . $conn->error);
            http_response_code(503);
            $_SESSION['cart_message'] = 'Unable to add this product right now. Please try again.';
        } else {
            $statement->bind_param('i', $pendingProductId);
            if (!$statement->execute()) {
                error_log('Pending cart product query failed: ' . $statement->error);
                http_response_code(503);
                $_SESSION['cart_message'] = 'Unable to add this product right now. Please try again.';
            } else {
                $result = $statement->get_result();
                if ($result && $result->fetch_assoc()) {
                    $cart = is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : [];
                    $cart[$pendingProductId] = min(99, (int) ($cart[$pendingProductId] ?? 0) + $pendingQuantity);
                    $_SESSION['cart'] = $cart;
                    $_SESSION['cart_message'] = 'Product added to your cart.';
                } else {
                    $_SESSION['cart_message'] = 'This product is no longer available.';
                }
            }
            $statement->close();
        }
    }
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
$cartUnitCount = array_sum(array_map(
    static fn (array $item): int => (int) ($item['quantity'] ?? 0),
    $cartData['items']
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../includes/favicon.php'; ?><base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07100d">
    <title>Your Cart | DunkHome Kicks</title>
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-user-nav25">
    <style>
        :root {
            --bg: #07100b;
            --card: rgba(17, 28, 21, 0.88);
            --card-strong: #0f1d18;
            --text: #f4f8f5;
            --muted: #9aa99f;
            --green: #8fe9b5;
            --green-strong: #58d594;
            --border: rgba(143, 233, 181, 0.18);
            --shadow: 0 30px 80px rgba(0, 0, 0, 0.22);
            --glow: rgba(143, 233, 181, 0.14);
        }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            font-family: "Manrope", sans-serif;
            background:
                radial-gradient(circle at 15% 10%, rgba(143, 233, 181, 0.12), transparent 30%),
                radial-gradient(circle at 85% 20%, rgba(255, 201, 139, 0.08), transparent 28%),
                var(--bg);
        }

        body.light {
            --bg: #f4f8f6;
            --card: rgba(255, 255, 255, 0.94);
            --card-strong: #ffffff;
            --text: #102016;
            --muted: #607065;
            --border: rgba(16, 32, 22, 0.1);
            --shadow: 0 20px 50px rgba(16, 32, 22, 0.09);
            --glow: rgba(45, 122, 87, 0.08);
        }

        * { box-sizing: border-box; }
        a { color: inherit; text-decoration: none; }
        .wrap { width: min(1160px, calc(100% - 40px)); margin: auto; }
        .top {
            min-height: 86px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 16px 0;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: var(--text);
            font-size: 17px;
            font-weight: 800;
            letter-spacing: -0.04em;
        }
        .brand img {
            width: 42px;
            height: 42px;
            border: 1px solid var(--border);
            border-radius: 13px;
            object-fit: cover;
            box-shadow: 0 8px 22px rgba(0, 0, 0, .16);
        }
        .brand span { color: var(--green); }
        .top a:last-child {
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: rgba(255, 255, 255, .035);
            color: var(--text);
            font-size: 12px;
            font-weight: 700;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .top a:last-child:hover {
            transform: translateY(-2px);
            border-color: rgba(143, 233, 181, 0.5);
            color: var(--text);
        }

        main {
            min-height: calc(100svh - 100px);
            padding: 132px 0 88px;
        }

        .cart-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 28px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
        }

        .cart-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            color: var(--green);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .16em;
            text-transform: uppercase;
        }

        .cart-kicker::before {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 14px var(--glow);
            content: "";
        }

        .cart-heading h1 {
            margin: 0;
            color: var(--text);
            font-size: clamp(2.5rem, 5vw, 4rem);
            line-height: .98;
            letter-spacing: -.055em;
            font-family: "DM Serif Display", serif;
        }

        .cart-heading p {
            max-width: 480px;
            margin: 12px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
        }

        .cart-count {
            flex: 0 0 auto;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 999px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
        }

        .cart-message {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 18px;
            padding: 13px 16px;
            border: 1px solid rgba(143, 233, 181, .22);
            border-radius: 14px;
            background: rgba(143, 233, 181, .08);
            color: var(--green);
            font-size: 12px;
            font-weight: 700;
        }

        .cart-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(260px, 330px);
            align-items: start;
            gap: 20px;
        }

        .cart-items {
            display: grid;
            gap: 13px;
        }

        .cart-item {
            min-width: 0;
            display: grid;
            grid-template-columns: 112px minmax(0, 1fr) auto;
            align-items: center;
            gap: 18px;
            padding: 15px;
            border: 1px solid var(--border);
            border-radius: 21px;
            background: linear-gradient(155deg, var(--user-panel-top, rgba(19,42,31,.94)), var(--user-panel-bottom, rgba(10,23,17,.96)));
            box-shadow: 0 14px 38px rgba(0, 0, 0, .11);
            transition: transform .22s ease, border-color .22s ease, box-shadow .22s ease;
        }

        body.light .cart-item {
            background: linear-gradient(155deg, #fff, #f0f6f2);
        }

        .cart-item:hover {
            transform: translateY(-2px);
            border-color: rgba(143, 233, 181, .38);
            box-shadow: 0 19px 42px rgba(0, 0, 0, .15);
        }

        .cart-item-image {
            width: 112px;
            aspect-ratio: 1;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: radial-gradient(circle at 50% 45%, var(--glow), transparent 72%), var(--card-strong);
        }

        .cart-item-image img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cart-item-info { min-width: 0; }

        .cart-category {
            margin-bottom: 7px;
            color: var(--green);
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .cart-item h2 {
            margin: 0 0 7px;
            color: var(--text);
            font-family: "DM Serif Display", serif;
            font-size: clamp(19px, 2vw, 25px);
            font-weight: 400;
            line-height: 1.12;
            overflow-wrap: anywhere;
        }

        .cart-unit-price {
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
        }

        .cart-item-total {
            align-self: start;
            padding-top: 3px;
            color: var(--text);
            font-size: 14px;
            font-weight: 800;
            white-space: nowrap;
        }

        .cart-controls {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 0;
            padding-top: 13px;
            border-top: 1px solid var(--border);
        }

        .cart-controls label {
            margin-right: 2px;
            color: var(--muted);
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .cart-quantity {
            width: 68px;
            min-height: 40px;
            padding: 0 10px;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: rgba(255, 255, 255, .035);
            color: var(--text);
            font-size: 12px;
            font-weight: 800;
        }

        body.light .cart-quantity { background: #fff; }

        .cart-control-button,
        .cart-clear,
        .cart-checkout,
        .cart-browse {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 14px;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: rgba(255, 255, 255, .025);
            color: var(--text);
            font: 800 10px "Manrope", sans-serif;
            letter-spacing: .04em;
            cursor: pointer;
            transition: transform .2s ease, border-color .2s ease, background .2s ease;
        }

        .cart-control-button:hover,
        .cart-clear:hover,
        .cart-browse:hover {
            transform: translateY(-1px);
            border-color: rgba(143, 233, 181, .48);
            background: rgba(143, 233, 181, .07);
        }

        .cart-remove {
            margin-left: auto;
            color: #e6a4a4;
        }

        .cart-remove:hover {
            border-color: rgba(255, 123, 123, .42);
            background: rgba(255, 123, 123, .07);
        }

        .cart-summary {
            position: sticky;
            top: 24px;
            padding: 22px;
            border: 1px solid var(--border);
            border-radius: 22px;
            background:
                radial-gradient(circle at 100% 0, var(--glow), transparent 42%),
                linear-gradient(155deg, var(--user-panel-top, rgba(19,42,31,.96)), var(--user-panel-bottom, rgba(10,23,17,.98)));
            box-shadow: var(--shadow);
        }

        body.light .cart-summary {
            background: radial-gradient(circle at 100% 0, rgba(45, 122, 87, .08), transparent 42%), linear-gradient(155deg, #fff, #f0f6f2);
        }

        .cart-summary h2 {
            margin: 0 0 18px;
            color: var(--text);
            font-family: "DM Serif Display", serif;
            font-size: 25px;
            font-weight: 400;
        }

        .cart-summary-line {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            padding: 11px 0;
            border-bottom: 1px solid var(--border);
            color: var(--muted);
            font-size: 11px;
        }

        .cart-summary-line strong {
            color: var(--text);
            font-weight: 800;
            text-align: right;
        }

        .cart-grand-total {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            padding: 18px 0 8px;
        }

        .cart-grand-total span {
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
        }

        .cart-grand-total strong {
            color: var(--green);
            font-size: 23px;
            font-weight: 800;
            letter-spacing: -.04em;
        }

        .cart-summary-note {
            margin: 4px 0 17px;
            color: var(--muted);
            font-size: 10px;
            line-height: 1.6;
        }

        .cart-summary-actions {
            display: grid;
            gap: 9px;
        }

        .cart-checkout {
            width: 100%;
            min-height: 48px;
            border-color: transparent;
            background: linear-gradient(135deg, #8fe9b5, #58d594);
            color: #041008;
            box-shadow: 0 12px 25px rgba(88, 213, 148, .16);
        }

        .cart-checkout:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 30px rgba(88, 213, 148, .23);
        }

        .cart-clear {
            width: 100%;
            color: var(--muted);
        }

        .cart-empty {
            position: relative;
            overflow: hidden;
            display: grid;
            min-height: 350px;
            place-items: center;
            padding: 48px 24px;
            border: 1px solid var(--border);
            border-radius: 28px;
            background:
                radial-gradient(circle at 50% 30%, var(--glow), transparent 38%),
                linear-gradient(155deg, var(--user-panel-top, rgba(19,42,31,.94)), var(--user-panel-bottom, rgba(10,23,17,.96)));
            box-shadow: var(--shadow);
            text-align: center;
        }

        body.light .cart-empty {
            background: radial-gradient(circle at 50% 30%, rgba(45, 122, 87, .09), transparent 38%), linear-gradient(155deg, #fff, #eef6f0);
        }

        .cart-empty-inner {
            position: relative;
            z-index: 1;
            max-width: 420px;
        }

        .cart-empty-icon {
            width: 78px;
            height: 78px;
            display: grid;
            place-items: center;
            margin: 0 auto 20px;
            border: 1px solid var(--border);
            border-radius: 24px;
            background: rgba(143, 233, 181, .08);
            color: var(--green);
            box-shadow: 0 15px 36px rgba(0, 0, 0, .12);
        }

        .cart-empty-icon svg {
            width: 36px;
            height: 36px;
        }

        .cart-empty h2 {
            margin: 0 0 10px;
            color: var(--text);
            font-family: "DM Serif Display", serif;
            font-size: clamp(30px, 5vw, 40px);
            font-weight: 400;
            letter-spacing: -.03em;
        }

        .cart-empty p {
            margin: 0 auto 22px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .cart-browse {
            min-height: 46px;
            padding: 0 20px;
            border-color: transparent;
            background: linear-gradient(135deg, #8fe9b5, #58d594);
            color: #041008;
            box-shadow: 0 12px 25px rgba(88, 213, 148, .16);
        }

        .cart-browse:hover { transform: translateY(-2px); }

        .cart-empty::before,
        .cart-empty::after {
            position: absolute;
            width: 220px;
            height: 220px;
            border: 1px solid var(--border);
            border-radius: 50%;
            content: "";
            pointer-events: none;
        }

        .cart-empty::before { top: -150px; right: -70px; }
        .cart-empty::after { bottom: -170px; left: -70px; }

        .cart-empty .cart-kicker { margin-bottom: 10px; }

        .cart-empty .cart-kicker::before { display: none; }

        .cart-empty .cart-kicker {
            display: block;
            margin: 0 0 9px;
            letter-spacing: .14em;
        }

        @media (max-width: 820px) {
            .cart-layout { grid-template-columns: minmax(0, 1fr); }
            .cart-summary { position: static; }
            .cart-summary-actions { grid-template-columns: 1fr 1fr; }
            .cart-checkout { grid-row: 1; grid-column: 1 / -1; }
        }

        @media (max-width: 600px) {
            .wrap { width: calc(100% - 28px); }
            main { min-height: calc(100svh - 76px); padding: 104px 0 58px; }
            .cart-heading { align-items: flex-start; gap: 12px; margin-bottom: 20px; padding-bottom: 19px; }
            .cart-heading h1 { font-size: clamp(38px, 11vw, 52px); }
            .cart-heading p { max-width: 280px; font-size: 11px; }
            .cart-count { margin-top: 2px; padding: 8px 10px; font-size: 9px; white-space: nowrap; }
            .cart-item { grid-template-columns: 78px minmax(0, 1fr); gap: 11px; padding: 11px; border-radius: 17px; }
            .cart-item-image { width: 78px; border-radius: 13px; }
            .cart-item h2 { margin-bottom: 5px; font-size: 18px; }
            .cart-category { margin-bottom: 5px; font-size: 8px; }
            .cart-unit-price { font-size: 10px; }
            .cart-item-total { grid-column: 2; grid-row: 2; align-self: center; padding: 0; font-size: 13px; }
            .cart-controls { grid-column: 1 / -1; display: grid; grid-template-columns: auto 68px minmax(0, 1fr) minmax(0, 1fr); gap: 7px; align-items: center; padding-top: 10px; }
            .cart-controls label { font-size: 8px; }
            .cart-quantity { width: 68px; min-height: 38px; }
            .cart-control-button { min-width: 0; min-height: 38px; padding: 0 8px; font-size: 9px; }
            .cart-remove { margin-left: 0; }
            .cart-empty { min-height: 320px; padding: 35px 20px; border-radius: 22px; }
            .cart-empty-icon { width: 68px; height: 68px; margin-bottom: 17px; border-radius: 21px; }
            .cart-empty h2 { font-size: 32px; }
            .cart-summary { padding: 18px; border-radius: 19px; }
        }

        @media (max-width: 360px) {
            .cart-heading { flex-direction: column; }
            .cart-count { margin: 0; }
            .cart-controls { grid-template-columns: auto 62px 1fr; }
            .cart-remove { grid-column: 1 / -1; width: 100%; }
            .cart-summary-actions { grid-template-columns: 1fr; }
            .cart-checkout { grid-column: auto; }
        }

        @media (prefers-reduced-motion: reduce) {
            .cart-item,
            .cart-control-button,
            .cart-clear,
            .cart-checkout,
            .cart-browse { transition: none; }
        }
    </style>
    <link rel="stylesheet" href="<?= h(appUrl('assets/user-premium.css?v=20261003-premium1')) ?>">
    <link rel="stylesheet" href="<?= h(appUrl('assets/dunkhome-footer.css?v=20261003-footer3')) ?>">
</head>
<body>
<?php require __DIR__ . '/../includes/user_nav.php'; ?>
<div class="wrap">
    <main>
        <header class="cart-heading">
            <div>
                <div class="cart-kicker">Your selection / DunkHome Kicks</div>
                <h1>Your cart.</h1>
                <p>Good choice. Review your pairs, adjust quantities and get your next step ready.</p>
            </div>
            <span class="cart-count"><?= number_format($cartUnitCount) ?> <?= $cartUnitCount === 1 ? 'pair' : 'pairs' ?></span>
        </header>
        <?php if ($cartMessage !== ''): ?><p class="cart-message" role="status"><span aria-hidden="true">✦</span><?= h($cartMessage) ?></p><?php endif; ?>
        <?php if (!$cartData['items']): ?>
            <section class="cart-empty" aria-labelledby="emptyCartTitle">
                <div class="cart-empty-inner">
                    <div class="cart-empty-icon" aria-hidden="true">
                        <svg viewBox="0 0 40 40" fill="none">
                            <path d="M8 13.5h24l2 20H6l2-20Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                            <path d="M14 15v-3a6 6 0 0 1 12 0v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M14 24h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="cart-kicker">A fresh rotation starts here</div>
                    <h2 id="emptyCartTitle">Your cart is waiting.</h2>
                    <p>Find a pair that feels like you. Explore the collection and save your next favorite here.</p>
                    <a class="cart-browse" href="User/Products.php">Explore the collection <span aria-hidden="true">↗</span></a>
                </div>
            </section>
        <?php else: ?>
            <div class="cart-layout">
                <section class="cart-items" aria-label="Items in your cart">
                    <?php foreach ($cartData['items'] as $item): $product = $item['product']; ?>
                        <article class="cart-item">
                            <a class="cart-item-image" href="User/ProductDetails.php?id=<?= (int) $product['id'] ?>" aria-label="View <?= h((string) $product['name']) ?>">
                                <img src="<?= h((string) $product['image1']) ?>" alt="<?= h((string) $product['name']) ?>">
                            </a>
                            <div class="cart-item-info">
                                <div class="cart-category"><?= h((string) $product['category']) ?></div>
                                <h2><?= h((string) $product['name']) ?></h2>
                                <div class="cart-unit-price">₹<?= number_format((float) $product['price'], 2) ?> each</div>
                            </div>
                            <div class="cart-item-total">₹<?= number_format((float) $item['subtotal'], 2) ?></div>
                            <form class="cart-controls" method="post">
                                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                <label for="quantity-<?= (int) $product['id'] ?>">Qty</label>
                                <input class="cart-quantity" id="quantity-<?= (int) $product['id'] ?>" type="number" name="quantity" min="1" max="99" value="<?= (int) $item['quantity'] ?>" aria-label="Quantity of <?= h((string) $product['name']) ?>">
                                <button class="cart-control-button" name="action" value="update">Update</button>
                                <button class="cart-control-button cart-remove" name="action" value="remove">Remove</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </section>
                <aside class="cart-summary" aria-labelledby="cartSummaryTitle">
                    <h2 id="cartSummaryTitle">Order summary</h2>
                    <div class="cart-summary-line"><span>Pairs in your cart</span><strong><?= number_format($cartUnitCount) ?></strong></div>
                    <div class="cart-summary-line"><span>Delivery</span><strong>Calculated at checkout</strong></div>
                    <div class="cart-grand-total"><span>Estimated total</span><strong>₹<?= number_format((float) $cartData['total'], 2) ?></strong></div>
                    <p class="cart-summary-note">No payment is required now. Your booking request is confirmed at the next step.</p>
                    <div class="cart-summary-actions">
                        <a class="cart-checkout" href="User/Checkout.php">Continue to booking <span aria-hidden="true">→</span></a>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                            <button class="cart-clear" name="action" value="clear">Clear cart</button>
                        </form>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="assets/dunkhome-ui.js?v=20261003-nav14" defer></script>
</body>
</html>