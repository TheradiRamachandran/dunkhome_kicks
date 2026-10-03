<?php
declare(strict_types=1);
require_once __DIR__ . '/../session.php';
requireUser();

$bookingCode = trim((string) ($_GET['code'] ?? ''));
$error = '';
if ($bookingCode !== '') {
    if (!preg_match('/^DHK-[0-9]{8}-[A-Fa-f0-9]{6}$/', $bookingCode)) {
        $error = 'Enter the booking reference in the format DHK-YYYYMMDD-XXXXXX.';
    } else {
        header('Location: ' . appUrl('User/TrackOrder.php?code=' . rawurlencode(strtoupper($bookingCode))));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../includes/favicon.php'; ?>
    <base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07100d">
    <title>Track Order | DunkHome Kicks</title>
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-user-nav25">
    <link rel="stylesheet" href="<?= h(appUrl('assets/user-premium.css?v=20261003-premium1')) ?>">
    <link rel="stylesheet" href="<?= h(appUrl('assets/dunkhome-footer.css?v=20261003-footer3')) ?>">
    <link rel="stylesheet" href="<?= h(appUrl('assets/customer-pages.css?v=20261003-customer6')) ?>">
</head>
<body class="customer-page">
<?php require __DIR__ . '/../includes/user_nav.php'; ?>
<main class="customer-wrap customer-track-lookup">
    <section class="customer-lookup-card">
        <div class="customer-lookup-icon" aria-hidden="true">↗</div>
        <p class="customer-eyebrow">Your order, one step away</p>
        <h1>Track your booking</h1>
        <p>Enter the booking reference from your confirmation to see the latest status and delivery timeline.</p>
        <?php if ($error !== ''): ?>
            <p class="customer-form-error" role="alert"><?= h($error) ?></p>
        <?php endif; ?>
        <form class="customer-lookup-form" method="get" action="<?= h(appUrl('User/TrackOrderLookup.php')) ?>">
            <label for="bookingCode">Booking reference</label>
            <input id="bookingCode" name="code" value="<?= h($bookingCode) ?>" placeholder="DHK-20261003-9B1E3B" pattern="DHK-[0-9]{8}-[A-Fa-f0-9]{6}" maxlength="21" autocomplete="off" required>
            <button class="customer-button customer-button-primary" type="submit">View tracking <span aria-hidden="true">→</span></button>
        </form>
        <a class="customer-text-link" href="<?= h(appUrl('User/OrderHistory.php')) ?>">Find the reference in order history <span aria-hidden="true">↗</span></a>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="<?= h(appUrl('assets/dunkhome-ui.js?v=20261003-nav14')) ?>" defer></script>
</body>
</html>
