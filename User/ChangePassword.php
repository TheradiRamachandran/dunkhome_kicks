<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
requireUser();

$message = '';
$messageType = 'error';
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? null;
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!is_string($csrfToken) || !verifyCsrf($csrfToken)) {
        $message = 'Your form session expired. Refresh the page and try again.';
    } elseif ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $message = 'Complete all password fields to continue.';
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $newPassword)) {
        $message = 'Your new password must be at least 8 characters and include an uppercase letter, a number, and a special character.';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'The new password and confirmation do not match.';
    } else {
        $statement = $conn->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');

        if (!$statement) {
            error_log('Change password lookup preparation failed: ' . $conn->error);
            http_response_code(503);
            $message = 'Password changes are temporarily unavailable. Please try again later.';
        } else {
            $statement->bind_param('i', $userId);
            $statement->execute();
            $result = $statement->get_result();
            $user = $result ? $result->fetch_assoc() : null;
            $statement->close();

            if (!$user) {
                http_response_code(404);
                $message = 'Your account could not be found. Please sign in again.';
            } elseif (!password_verify($currentPassword, (string) $user['password'])) {
                $message = 'Your current password is incorrect.';
            } elseif (password_verify($newPassword, (string) $user['password'])) {
                $message = 'Choose a new password that is different from your current password.';
            } else {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');

                if (!$update) {
                    error_log('Change password update preparation failed: ' . $conn->error);
                    http_response_code(503);
                    $message = 'Your password could not be changed right now. Please try again later.';
                } else {
                    $update->bind_param('si', $passwordHash, $userId);
                    if ($update->execute()) {
                        $message = 'Your password has been changed successfully.';
                        $messageType = 'success';
                    } else {
                        error_log('Change password update failed: ' . $update->error);
                        $message = 'Your password could not be changed right now. Please try again later.';
                    }
                    $update->close();
                }
            }
        }
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
    <meta name="description" content="Update your DunkHome Kicks account password securely.">
    <title>Change Password | DunkHome Kicks</title>
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-user-nav25">
    <link rel="stylesheet" href="<?= h(appUrl('assets/user-premium.css?v=20261003-premium1')) ?>">
    <link rel="stylesheet" href="<?= h(appUrl('assets/dunkhome-footer.css?v=20261003-footer3')) ?>">
    <link rel="stylesheet" href="<?= h(appUrl('assets/customer-pages.css?v=20261003-customer6')) ?>">
</head>
<body class="customer-page">
<?php require __DIR__ . '/../includes/user_nav.php'; ?>
<main class="customer-wrap">
    <section class="profile-hero">
        <div class="profile-avatar" aria-hidden="true">✓</div>
        <div>
            <p class="customer-eyebrow">Your account security</p>
            <h1>Change password</h1>
            <p>Choose a strong password to keep your account and order history protected.</p>
        </div>
    </section>
    <section class="password-card">
        <div class="password-card-intro">
            <p class="customer-eyebrow">Password settings</p>
            <h2>Update your sign-in details</h2>
            <p>Enter your current password, then create and confirm your new one.</p>
            <ul>
                <li>At least 8 characters</li>
                <li>One uppercase letter and one number</li>
                <li>One special character</li>
            </ul>
        </div>
        <form class="password-form" method="post" action="<?= h(appUrl('User/ChangePassword.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <?php if ($message !== ''): ?>
                <div class="password-message <?= $messageType === 'success' ? 'is-success' : 'is-error' ?>" role="status">
                    <?= h($message) ?>
                </div>
            <?php endif; ?>
            <label for="currentPassword">Current password</label>
            <input id="currentPassword" name="current_password" type="password" autocomplete="current-password" required>
            <label for="newPassword">New password</label>
            <input id="newPassword" name="new_password" type="password" autocomplete="new-password" minlength="8" required>
            <label for="confirmPassword">Confirm new password</label>
            <input id="confirmPassword" name="confirm_password" type="password" autocomplete="new-password" minlength="8" required>
            <button class="customer-button customer-button-primary password-submit" type="submit">Update password <span aria-hidden="true">→</span></button>
        </form>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="<?= h(appUrl('assets/dunkhome-ui.js?v=20261003-nav14')) ?>" defer></script>
</body>
</html>
