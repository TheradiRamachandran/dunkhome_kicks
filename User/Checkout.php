<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../includes/order_helpers.php';
require_once __DIR__ . '/../Mailer.php';
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
            if (!$existing) {
                throw new RuntimeException('Checkout schema migration is required: ' . $conn->error);
            }
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
            if (!$productQuery) {
                throw new RuntimeException('Unable to verify cart products: ' . $conn->error);
            }
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
            if (tableHasColumn($conn, 'orders', 'total_amount')) {
                $legacyAddress = implode(', ', [$fields['address'], $fields['city'], $fields['state'], $fields['pincode']]);
                $insertOrder = $conn->prepare('INSERT INTO orders (order_code,request_token,user_id,customer_name,mobile,email,address,city,state,pincode,total,status,total_amount,customer_email,customer_phone,shipping_address) VALUES (?,?, ?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                if (!$insertOrder) {
                    throw new RuntimeException('Unable to prepare booking details: ' . $conn->error);
                }
                $orderTypes = 'ssi' . str_repeat('s', 7) . 'dsdsss';
                $insertOrder->bind_param($orderTypes, $orderCode, $requestToken, $userId, $fields['customer_name'], $fields['mobile'], $fields['email'], $fields['address'], $fields['city'], $fields['state'], $fields['pincode'], $currentTotal, $status, $currentTotal, $fields['email'], $fields['mobile'], $legacyAddress);
            } else {
                $insertOrder = $conn->prepare('INSERT INTO orders (order_code,user_id,request_token,customer_name,mobile,email,address,city,state,pincode,total,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                if (!$insertOrder) {
                    throw new RuntimeException('Unable to prepare booking details: ' . $conn->error);
                }
                $insertOrder->bind_param('sissssssssds', $orderCode, $userId, $requestToken, $fields['customer_name'], $fields['mobile'], $fields['email'], $fields['address'], $fields['city'], $fields['state'], $fields['pincode'], $currentTotal, $status);
            }
            $insertOrder->execute();
            $orderId = (int) $conn->insert_id;
            $insertOrder->close();

            $legacyOrderItems = tableHasColumn($conn, 'order_items', 'price');
            $insertItem = $legacyOrderItems
                ? $conn->prepare('INSERT INTO order_items (order_id,product_id,product_name,product_image,price,unit_price,quantity,subtotal) VALUES (?,?,?,?,?,?,?,?)')
                : $conn->prepare('INSERT INTO order_items (order_id,product_id,product_name,product_image,unit_price,quantity,subtotal) VALUES (?,?,?,?,?,?,?)');
            if (!$insertItem) {
                throw new RuntimeException('Unable to prepare booking items: ' . $conn->error);
            }
            foreach ($lockedProducts as $item) {
                $product = $item['product'];
                $productId = (int) $product['id'];
                $name = (string) $product['name'];
                $image = (string) $product['image1'];
                $price = (float) $product['price'];
                $quantity = (int) $item['quantity'];
                $subtotal = (float) $item['subtotal'];
                if ($legacyOrderItems) {
                    $insertItem->bind_param('iissddid', $orderId, $productId, $name, $image, $price, $price, $quantity, $subtotal);
                } else {
                    $insertItem->bind_param('iissdid', $orderId, $productId, $name, $image, $price, $quantity, $subtotal);
                }
                $insertItem->execute();
            }
            $insertItem->close();

            if (tableHasColumn($conn, 'order_status_history', 'new_status')) {
                $history = $conn->prepare('INSERT INTO order_status_history (order_id,previous_status,new_status,changed_by_type,changed_by_id,note) VALUES (?,NULL,\'Pending\',\'user\',?,\'Booking placed\')');
                if (!$history) {
                    throw new RuntimeException('Unable to prepare booking status history: ' . $conn->error);
                }
                $history->bind_param('ii', $orderId, $userId);
            } else {
                $history = $conn->prepare('INSERT INTO order_status_history (order_id,status,note) VALUES (?,\'Pending\',\'Booking placed\')');
                if (!$history) {
                    throw new RuntimeException('Unable to prepare booking status history: ' . $conn->error);
                }
            }
            $history->execute();
            $history->close();

            $conn->commit();
            $_SESSION['cart'] = [];
            unset($_SESSION['checkout_token']);

            $trackingUrl = dunkhomeAbsoluteUrl('User/TrackOrder.php?code=' . rawurlencode($orderCode));
            $itemLines = [];
            $htmlItems = '';
            foreach ($lockedProducts as $item) {
                $productName = (string) $item['product']['name'];
                $quantity = (int) $item['quantity'];
                $subtotal = (float) $item['subtotal'];
                $itemLines[] = $productName . ' — ' . $quantity . ' × INR ' . number_format((float) $item['product']['price'], 2) . ' = INR ' . number_format($subtotal, 2);
                $htmlItems .= '<li style="padding:8px 0;color:#33483b;border-bottom:1px solid #e3ece6">'
                    . htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') . ' &mdash; ' . $quantity
                    . ' &times; INR ' . number_format((float) $item['product']['price'], 2)
                    . ' = <strong>INR ' . number_format($subtotal, 2) . '</strong></li>';
            }

            $plainDetails = implode("\n", [
                'Booking reference: ' . $orderCode,
                'Status: Pending (booking received; payment is not collected on this site)',
                'Customer: ' . $fields['customer_name'],
                'Mobile: ' . $fields['mobile'],
                'Email: ' . $fields['email'],
                'Delivery address: ' . $fields['address'] . ', ' . $fields['city'] . ', ' . $fields['state'] . ' ' . $fields['pincode'],
                '',
                'Items:',
                implode("\n", $itemLines),
                'Total: INR ' . number_format($currentTotal, 2),
                '',
                'Track your booking: ' . $trackingUrl,
            ]);
            $safeCustomerName = htmlspecialchars($fields['customer_name'], ENT_QUOTES, 'UTF-8');
            $safeOrderCode = htmlspecialchars($orderCode, ENT_QUOTES, 'UTF-8');
            $safeTrackUrl = htmlspecialchars($trackingUrl, ENT_QUOTES, 'UTF-8');
            $safeEmail = htmlspecialchars($fields['email'], ENT_QUOTES, 'UTF-8');
            $safeMobile = htmlspecialchars($fields['mobile'], ENT_QUOTES, 'UTF-8');
            $safeAddress = htmlspecialchars($fields['address'] . ', ' . $fields['city'] . ', ' . $fields['state'] . ' ' . $fields['pincode'], ENT_QUOTES, 'UTF-8');
            $htmlDetails = '<!doctype html><html lang="en"><body style="margin:0;padding:28px;background:#f2f6f3;font-family:Arial,sans-serif;color:#14251b">'
                . '<main style="max-width:620px;margin:auto;padding:30px;border:1px solid #dfe9e2;border-radius:18px;background:#fff">'
                . '<p style="margin:0;color:#267b4d;font-size:12px;font-weight:bold;letter-spacing:2px">DUNKHOME KICKS / BOOKING RECEIVED</p>'
                . '<h1 style="margin:16px 0 8px;font-family:Georgia,serif;font-size:30px;font-weight:normal">Thank you, ' . $safeCustomerName . '.</h1>'
                . '<p style="color:#5d6d62;line-height:1.6">Your booking is in our system. Its current status is <strong>Pending</strong>; no payment is collected on this site.</p>'
                . '<p style="padding:14px;border-radius:12px;background:#f2f8f4"><strong>Booking reference</strong><br>' . $safeOrderCode . '</p>'
                . '<h2 style="font-size:17px">Order details</h2><ul style="padding-left:20px">' . $htmlItems . '</ul>'
                . '<p style="font-size:17px"><strong>Total: INR ' . number_format($currentTotal, 2) . '</strong></p>'
                . '<p style="color:#5d6d62;line-height:1.7"><strong>Delivery address:</strong><br>' . nl2br($safeAddress) . '<br>' . $safeMobile . '<br>' . $safeEmail . '</p>'
                . '<p style="margin:24px 0"><a href="' . $safeTrackUrl . '" style="display:inline-block;padding:13px 18px;border-radius:999px;background:#70d99a;color:#082013;font-weight:bold;text-decoration:none">Track your booking</a></p>'
                . '<p style="color:#718176;font-size:12px">DunkHome Kicks</p></main></body></html>';
            $htmlAdminDetails = '<!doctype html><html lang="en"><body style="margin:0;padding:28px;background:#f2f6f3;font-family:Arial,sans-serif;color:#14251b">'
                . '<main style="max-width:620px;margin:auto;padding:30px;border:1px solid #dfe9e2;border-radius:18px;background:#fff">'
                . '<p style="margin:0;color:#267b4d;font-size:12px;font-weight:bold;letter-spacing:2px">DUNKHOME KICKS / NEW BOOKING</p>'
                . '<h1 style="margin:16px 0 8px;font-family:Georgia,serif;font-size:30px;font-weight:normal">Booking ' . $safeOrderCode . '</h1>'
                . '<p style="color:#5d6d62;line-height:1.7"><strong>Customer:</strong> ' . $safeCustomerName . '<br><strong>Mobile:</strong> ' . $safeMobile . '<br><strong>Email:</strong> ' . $safeEmail . '<br><strong>Delivery address:</strong><br>' . nl2br($safeAddress) . '</p>'
                . '<h2 style="font-size:17px">Items</h2><ul style="padding-left:20px">' . $htmlItems . '</ul>'
                . '<p style="font-size:17px"><strong>Total: INR ' . number_format($currentTotal, 2) . '</strong></p>'
                . '<p style="color:#5d6d62">Current status: Pending</p>'
                . '<p style="margin:24px 0"><a href="' . $safeTrackUrl . '" style="display:inline-block;padding:13px 18px;border-radius:999px;background:#70d99a;color:#082013;font-weight:bold;text-decoration:none">Track this booking</a></p>'
                . '</main></body></html>';

            $adminEmail = trim((string) (getenv('DUNKHOME_ORDER_ADMIN_EMAIL') ?: 'theradimuthu.r@gmail.com'));
            $customerEmailResult = ['status' => 'error', 'message' => 'Customer email notification could not be attempted.'];
            $adminEmailResult = ['status' => 'error', 'message' => 'Admin email notification could not be attempted.'];
            $whatsAppResult = ['status' => 'error', 'message' => 'WhatsApp notification could not be attempted.'];
            try {
                $customerEmailResult = sendDunkHomeOrderEmail(
                    $fields['email'],
                    'DunkHome Kicks booking received — ' . $orderCode,
                    "Hello {$fields['customer_name']},\n\nYour booking has been received. Its current status is Pending; no payment is collected on this site.\n\n{$plainDetails}\n\nDunkHome Kicks",
                    $htmlDetails
                );
            } catch (Throwable $notificationError) {
                error_log('Customer booking email failed: ' . $notificationError->getMessage());
            }
            try {
                $adminEmailResult = sendDunkHomeOrderEmail(
                    $adminEmail,
                    'New DunkHome Kicks booking — ' . $orderCode,
                    "A new booking was placed.\n\n{$plainDetails}\n",
                    $htmlAdminDetails
                );
            } catch (Throwable $notificationError) {
                error_log('Admin booking email failed: ' . $notificationError->getMessage());
            }
            try {
                $whatsAppResult = sendDunkHomeOrderWhatsApp([
                    'order_code' => $orderCode,
                    'customer_name' => $fields['customer_name'],
                    'mobile' => $fields['mobile'],
                    'total' => 'INR ' . number_format($currentTotal, 2),
                    'tracking_url' => $trackingUrl,
                ]);
            } catch (Throwable $notificationError) {
                error_log('Admin booking WhatsApp notification failed: ' . $notificationError->getMessage());
            }
            $_SESSION['order_notifications'] = [
                'code' => $orderCode,
                'customer_email' => $customerEmailResult,
                'admin_email' => $adminEmailResult,
                'whatsapp' => $whatsAppResult,
            ];
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
<head>
<?php require __DIR__ . '/../includes/favicon.php'; ?><base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#07100d">
    <title>Booking Checkout | DunkHome Kicks</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-user-nav25"><link rel="stylesheet" href="<?= h(appUrl('assets/dunkhome-footer.css?v=20261003-footer3')) ?>">
    <style>
        :root {
            --checkout-bg: #07100b;
            --checkout-panel: rgba(17, 28, 21, .88);
            --checkout-text: #f4f8f5;
            --checkout-muted: #9aa99f;
            --checkout-green: #8fe9b5;
            --checkout-line: rgba(143, 233, 181, .17);
            --checkout-shadow: 0 25px 70px rgba(0, 0, 0, .2);
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            color: var(--checkout-text);
            font-family: "DM Sans", sans-serif;
            background:
                radial-gradient(ellipse at 8% 10%, rgba(143, 233, 181, .12), transparent 32%),
                radial-gradient(ellipse at 92% 27%, rgba(255, 201, 139, .07), transparent 30%),
                var(--checkout-bg);
        }
        body.light {
            --checkout-bg: #f3f7f3;
            --checkout-panel: rgba(255, 255, 255, .94);
            --checkout-text: #13231a;
            --checkout-muted: #627267;
            --checkout-line: rgba(20, 54, 34, .12);
            --checkout-shadow: 0 22px 60px rgba(22, 45, 31, .08);
        }
        a { color: inherit; text-decoration: none; }
        button, input, textarea { font: inherit; }
        .checkout-main { padding: 138px 0 82px; }
        .checkout-wrap { width: min(1120px, calc(100% - 40px)); margin: 0 auto; }
        .checkout-intro {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 28px;
            padding-bottom: 23px;
            border-bottom: 1px solid var(--checkout-line);
        }
        .checkout-kicker {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 12px;
            color: var(--checkout-green);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .16em;
            text-transform: uppercase;
        }
        .checkout-kicker::before { width: 7px; height: 7px; border-radius: 50%; background: var(--checkout-green); content: ""; }
        .checkout-intro h1 {
            margin: 0;
            font: clamp(40px, 6vw, 62px)/.98 "DM Serif Display", serif;
            letter-spacing: -.045em;
        }
        .checkout-intro p { max-width: 560px; margin: 12px 0 0; color: var(--checkout-muted); font-size: 13px; line-height: 1.7; }
        .checkout-step {
            flex: 0 0 auto;
            padding: 10px 14px;
            border: 1px solid var(--checkout-line);
            border-radius: 999px;
            color: var(--checkout-muted);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .checkout-alert {
            margin: 0 0 18px;
            padding: 14px 16px;
            border: 1px solid rgba(255, 123, 123, .26);
            border-radius: 14px;
            background: rgba(255, 123, 123, .08);
            color: #ffb4b4;
            font-size: 12px;
            line-height: 1.6;
        }
        .checkout-layout { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(290px, .75fr); align-items: start; gap: 20px; }
        .checkout-panel {
            min-width: 0;
            padding: clamp(19px, 3vw, 28px);
            border: 1px solid var(--checkout-line);
            border-radius: 22px;
            background: linear-gradient(150deg, var(--checkout-panel), rgba(12, 26, 18, .74));
            box-shadow: var(--checkout-shadow);
        }
        body.light .checkout-panel { background: linear-gradient(150deg, #fff, #f1f6f2); }
        .checkout-panel-heading { margin: 0 0 5px; font: 27px "DM Serif Display", serif; }
        .checkout-panel-subtitle { margin: 0 0 22px; color: var(--checkout-muted); font-size: 11px; line-height: 1.7; }
        .checkout-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .checkout-field { display: grid; align-content: start; gap: 7px; min-width: 0; }
        .checkout-field.full { grid-column: 1 / -1; }
        .checkout-field label { color: var(--checkout-muted); font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .checkout-field input, .checkout-field textarea {
            width: 100%;
            min-height: 47px;
            padding: 12px 14px;
            border: 1px solid var(--checkout-line);
            border-radius: 12px;
            outline: 0;
            background: rgba(255, 255, 255, .035);
            color: var(--checkout-text);
            font-size: 12px;
            transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
        }
        body.light .checkout-field input, body.light .checkout-field textarea { background: rgba(255, 255, 255, .9); }
        .checkout-field input:focus, .checkout-field textarea:focus { border-color: var(--checkout-green); box-shadow: 0 0 0 3px rgba(143, 233, 181, .12); }
        .checkout-field textarea { min-height: 104px; resize: vertical; line-height: 1.6; }
        .checkout-field input::placeholder, .checkout-field textarea::placeholder { color: var(--checkout-muted); opacity: .7; }
        .checkout-submit {
            width: 100%;
            min-height: 52px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 22px;
            padding: 0 18px;
            border: 0;
            border-radius: 13px;
            background: linear-gradient(135deg, #9bf0bd, #59d692);
            color: #07160d;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 13px 27px rgba(88, 213, 148, .16);
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .checkout-submit:hover { transform: translateY(-2px); box-shadow: 0 17px 32px rgba(88, 213, 148, .24); }
        .checkout-submit span:last-child { font-size: 18px; }
        .checkout-summary { position: sticky; top: 112px; }
        .checkout-summary-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 15px; }
        .checkout-summary-heading h2 { margin: 0; font: 25px "DM Serif Display", serif; }
        .checkout-summary-count { color: var(--checkout-muted); font-size: 10px; font-weight: 700; }
        .checkout-item {
            display: grid;
            grid-template-columns: 58px minmax(0, 1fr) auto;
            align-items: center;
            gap: 11px;
            padding: 13px 0;
            border-top: 1px solid var(--checkout-line);
        }
        .checkout-item-image { width: 58px; height: 58px; overflow: hidden; border: 1px solid var(--checkout-line); border-radius: 13px; background: rgba(143, 233, 181, .07); }
        .checkout-item-image img { display: block; width: 100%; height: 100%; object-fit: cover; }
        .checkout-item-name { display: block; margin-bottom: 5px; font-size: 11px; font-weight: 800; line-height: 1.4; overflow-wrap: anywhere; }
        .checkout-item-detail { color: var(--checkout-muted); font-size: 10px; }
        .checkout-item-price { color: var(--checkout-text); font-size: 11px; font-weight: 800; white-space: nowrap; }
        .checkout-total { display: flex; align-items: baseline; justify-content: space-between; gap: 14px; margin-top: 6px; padding-top: 17px; border-top: 1px solid var(--checkout-line); }
        .checkout-total span { color: var(--checkout-muted); font-size: 11px; font-weight: 700; }
        .checkout-total strong { color: var(--checkout-green); font-size: 22px; letter-spacing: -.04em; }
        .checkout-note { margin: 15px 0 0; padding: 12px; border: 1px solid var(--checkout-line); border-radius: 12px; color: var(--checkout-muted); font-size: 10px; line-height: 1.65; }
        .checkout-note strong { color: var(--checkout-text); }
        .checkout-back { display: inline-flex; gap: 7px; margin-top: 16px; color: var(--checkout-muted); font-size: 10px; font-weight: 800; }
        .checkout-back:hover { color: var(--checkout-green); }
        @media (max-width: 820px) {
            .checkout-layout { grid-template-columns: minmax(0, 1fr); }
            .checkout-summary { position: static; grid-row: 1; }
        }
        @media (max-width: 600px) {
            .checkout-main { padding: 105px 0 56px; }
            .checkout-wrap { width: calc(100% - 28px); }
            .checkout-intro { align-items: flex-start; flex-direction: column; gap: 14px; margin-bottom: 19px; padding-bottom: 18px; }
            .checkout-intro h1 { font-size: 44px; }
            .checkout-intro p { font-size: 11px; }
            .checkout-layout { gap: 13px; }
            .checkout-panel { padding: 18px; border-radius: 18px; }
            .checkout-summary-heading h2 { font-size: 23px; }
        }
        @media (max-width: 440px) {
            .checkout-fields { grid-template-columns: minmax(0, 1fr); gap: 13px; }
            .checkout-field.full { grid-column: auto; }
            .checkout-item { grid-template-columns: 50px minmax(0, 1fr) auto; gap: 9px; }
            .checkout-item-image { width: 50px; height: 50px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .checkout-field input, .checkout-field textarea, .checkout-submit { transition: none; }
        }
    </style>
    <link rel="stylesheet" href="<?= h(appUrl('assets/user-premium.css?v=20261003-premium1')) ?>">
</head>
<body>
<?php require __DIR__ . '/../includes/user_nav.php'; ?>
<main class="checkout-main">
    <div class="checkout-wrap">
        <header class="checkout-intro">
            <div>
                <div class="checkout-kicker">Your rotation / Final step</div>
                <h1>Delivery details.</h1>
                <p>Confirm where your pair should go. We’ll create a booking and send the reference and tracking link to your email.</p>
            </div>
            <div class="checkout-step">Secure booking · Step 2 of 2</div>
        </header>
        <?php if ($message !== ''): ?><p class="checkout-alert" role="alert"><?= h($message) ?></p><?php endif; ?>
        <div class="checkout-layout">
            <form class="checkout-panel" method="post">
                <h2 class="checkout-panel-heading">Where should we deliver?</h2>
                <p class="checkout-panel-subtitle">Add your contact details and delivery address. Fields marked required must be completed.</p>
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="checkout_token" value="<?= h((string) $_SESSION['checkout_token']) ?>">
                <div class="checkout-fields">
                    <div class="checkout-field">
                        <label for="customer_name">Full name</label>
                        <input id="customer_name" name="customer_name" autocomplete="name" maxlength="160" required value="<?= h($fields['customer_name']) ?>" placeholder="Name for delivery">
                    </div>
                    <div class="checkout-field">
                        <label for="mobile">Mobile number</label>
                        <input id="mobile" name="mobile" type="tel" autocomplete="tel" maxlength="25" required value="<?= h($fields['mobile']) ?>" placeholder="+91">
                    </div>
                    <div class="checkout-field full">
                        <label for="email">Email address</label>
                        <input id="email" name="email" type="email" autocomplete="email" maxlength="255" required value="<?= h($fields['email']) ?>" placeholder="you@example.com">
                    </div>
                    <div class="checkout-field full">
                        <label for="address">Street address</label>
                        <textarea id="address" name="address" autocomplete="street-address" maxlength="500" required placeholder="House number, street and area"><?= h($fields['address']) ?></textarea>
                    </div>
                    <div class="checkout-field">
                        <label for="city">City</label>
                        <input id="city" name="city" autocomplete="address-level2" maxlength="120" required value="<?= h($fields['city']) ?>">
                    </div>
                    <div class="checkout-field">
                        <label for="state">State</label>
                        <input id="state" name="state" autocomplete="address-level1" maxlength="120" required value="<?= h($fields['state']) ?>">
                    </div>
                    <div class="checkout-field">
                        <label for="pincode">PIN code</label>
                        <input id="pincode" name="pincode" autocomplete="postal-code" inputmode="numeric" pattern="[0-9]{4,10}" maxlength="10" required value="<?= h($fields['pincode']) ?>" placeholder="Postal code">
                    </div>
                </div>
                <button class="checkout-submit" type="submit"><span>Confirm booking</span><span aria-hidden="true">→</span></button>
            </form>
            <aside class="checkout-panel checkout-summary" aria-labelledby="checkoutSummaryTitle">
                <div class="checkout-summary-heading">
                    <h2 id="checkoutSummaryTitle">Order review</h2>
                    <span class="checkout-summary-count"><?= count($cartData['items']) ?> <?= count($cartData['items']) === 1 ? 'style' : 'styles' ?></span>
                </div>
                <?php foreach ($cartData['items'] as $item): ?>
                    <div class="checkout-item">
                        <div class="checkout-item-image"><img src="<?= h((string) $item['product']['image1']) ?>" alt=""></div>
                        <div>
                            <span class="checkout-item-name"><?= h((string) $item['product']['name']) ?></span>
                            <span class="checkout-item-detail">₹<?= number_format((float) $item['product']['price'], 2) ?> × <?= (int) $item['quantity'] ?></span>
                        </div>
                        <strong class="checkout-item-price">₹<?= number_format((float) $item['subtotal'], 2) ?></strong>
                    </div>
                <?php endforeach; ?>
                <div class="checkout-total"><span>Estimated total</span><strong>₹<?= number_format((float) $cartData['total'], 2) ?></strong></div>
                <p class="checkout-note"><strong>Booking, not payment.</strong> No payment is collected here. Your booking status begins as pending, and prices are verified again when you confirm.</p>
                <a class="checkout-back" href="User/Cart.php"><span aria-hidden="true">←</span> Back to your cart</a>
            </aside>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="assets/dunkhome-ui.js?v=20261003-nav14" defer></script>
</body></html>