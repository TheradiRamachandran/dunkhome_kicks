<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
requireUser();

$userId = (int) $_SESSION['user_id'];
$statement = $conn->prepare('SELECT email,created_at FROM users WHERE id=? LIMIT 1');
if (!$statement) {
    error_log('My profile lookup preparation failed: ' . $conn->error);
    http_response_code(503);
    exit('Your profile is temporarily unavailable. Please try again later.');
}
$statement->bind_param('i', $userId);
$statement->execute();
$profile = $statement->get_result()->fetch_assoc();
$statement->close();
if (!$profile) {
    http_response_code(404);
    exit('This account could not be found. Please sign in again.');
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
    <title>My Profile | DunkHome Kicks</title>
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-user-nav25">
    <link rel="stylesheet" href="<?= h(appUrl('assets/user-premium.css?v=20261003-premium1')) ?>">
    <link rel="stylesheet" href="<?= h(appUrl('assets/dunkhome-footer.css?v=20261003-footer3')) ?>">
    <link rel="stylesheet" href="<?= h(appUrl('assets/customer-pages.css?v=20261003-customer6')) ?>">
</head>
<body class="customer-page">
<?php require __DIR__ . '/../includes/user_nav.php'; ?>
<main class="customer-wrap">
    <section class="profile-hero">
        <div class="profile-avatar" aria-hidden="true"><?= h(strtoupper(substr((string) $profile['email'], 0, 1))) ?></div>
        <div>
            <p class="customer-eyebrow">Your DunkHome account</p>
            <h1>My Profile</h1>
            <p>Manage your account access and keep your bookings close by.</p>
        </div>
    </section>
    <section class="profile-grid">
        <article class="profile-card">
            <p class="customer-eyebrow">Account details</p>
            <h2>Your information</h2>
            <div class="profile-detail">
                <span>Email address</span>
                <strong><?= h((string) $profile['email']) ?></strong>
            </div>
            <div class="profile-detail">
                <span>Member since</span>
                <strong><?= h(date('F Y', strtotime((string) $profile['created_at']) ?: time())) ?></strong>
            </div>
            <p class="profile-note">Your account email is used for booking updates and account verification.</p>
        </article>
        <article class="profile-card profile-shortcuts">
            <p class="customer-eyebrow">Your activity</p>
            <h2>Quick links</h2>
            <a class="profile-shortcut" href="<?= h(appUrl('User/OrderHistory.php')) ?>"><span><strong>Order history</strong><small>Review your bookings and their latest status.</small></span><b aria-hidden="true">→</b></a>
            <a class="profile-shortcut" href="<?= h(appUrl('User/TrackOrderLookup.php')) ?>"><span><strong>Track an order</strong><small>Follow a booking from placement to delivery.</small></span><b aria-hidden="true">→</b></a>
            <a class="profile-shortcut" href="<?= h(appUrl('User/Cart.php')) ?>"><span><strong>Your cart</strong><small>Continue with the pairs you picked.</small></span><b aria-hidden="true">→</b></a>
            <a class="profile-shortcut" href="<?= h(appUrl('User/ChangePassword.php')) ?>"><span><strong>Change password</strong><small>Update your password to keep your account secure.</small></span><b aria-hidden="true">→</b></a>
            <a class="profile-shortcut profile-signout" href="<?= h(appUrl('Logout.php?scope=user')) ?>"><span><strong>Log out</strong><small>Securely end your account session.</small></span><b aria-hidden="true">↗</b></a>
        </article>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="<?= h(appUrl('assets/dunkhome-ui.js?v=20261003-nav14')) ?>" defer></script>
</body>
</html>
