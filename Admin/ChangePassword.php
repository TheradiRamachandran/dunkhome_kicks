<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../Mailer.php';
requireAdmin();

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$adminEmail = (string) ($_SESSION['admin_email'] ?? 'Administrator');
$emailName = explode('@', $adminEmail)[0] ?? '';
$adminInitials = strtoupper(substr((string) (preg_replace('/[^a-zA-Z0-9]/', '', $emailName) ?: 'AD'), 0, 2));
$message = '';
$messageType = 'error';

$clearOtp = static function (): void {
    unset(
        $_SESSION['change_password_otp_hash'],
        $_SESSION['change_password_otp_admin_id'],
        $_SESSION['change_password_otp_expires_at'],
        $_SESSION['change_password_otp_attempts']
    );
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $message = 'This request expired. Refresh the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'send_otp') {
            $lastSentAt = (int) ($_SESSION['change_password_otp_sent_at'] ?? 0);
            if (time() - $lastSentAt < 60) {
                $message = 'Please wait before requesting another code.';
            } else {
                $account = $conn->prepare('SELECT email FROM admins WHERE id = ? LIMIT 1');
                if (!$account) {
                    error_log('Change password account lookup preparation failed: ' . $conn->error);
                    $message = 'Unable to load the admin account. Please try again.';
                } else {
                    $account->bind_param('i', $adminId);
                    $loaded = $account->execute();
                    $admin = $loaded ? $account->get_result()->fetch_assoc() : null;
                    $account->close();

                    if (!$admin) {
                        $message = 'Unable to load the admin account. Please sign in again.';
                    } else {
                        $otp = (string) random_int(100000, 999999);
                        $delivery = sendOtpEmail((string) $admin['email'], (int) $otp, 'change_password');
                        $_SESSION['change_password_otp_sent_at'] = time();
                        unset($_SESSION['change_password_verified_admin_id'], $_SESSION['change_password_verified_at']);

                        if (($delivery['status'] ?? 'error') === 'success') {
                            $_SESSION['change_password_otp_hash'] = password_hash($otp, PASSWORD_DEFAULT);
                            $_SESSION['change_password_otp_admin_id'] = $adminId;
                            $_SESSION['change_password_otp_expires_at'] = time() + 600;
                            $_SESSION['change_password_otp_attempts'] = 0;
                            $message = 'A verification code was sent to ' . (string) $admin['email'] . '. It expires in 10 minutes.';
                            $messageType = 'success';
                        } else {
                            $clearOtp();
                            $message = (string) ($delivery['message'] ?? 'Unable to send the verification code.');
                        }
                    }
                }
            }
        } elseif ($action === 'verify_otp') {
            $otp = trim((string) ($_POST['otp'] ?? ''));
            $otpMatchesAdmin = (int) ($_SESSION['change_password_otp_admin_id'] ?? 0) === $adminId;
            $otpIsValid = $otpMatchesAdmin
                && isset($_SESSION['change_password_otp_hash'], $_SESSION['change_password_otp_expires_at'])
                && time() <= (int) $_SESSION['change_password_otp_expires_at']
                && preg_match('/^\d{6}$/', $otp)
                && password_verify($otp, (string) $_SESSION['change_password_otp_hash']);

            if (!$otpIsValid) {
                $_SESSION['change_password_otp_attempts'] = (int) ($_SESSION['change_password_otp_attempts'] ?? 0) + 1;
                if ((int) $_SESSION['change_password_otp_attempts'] >= 5) {
                    $clearOtp();
                    $message = 'Too many incorrect attempts. Request a new verification code.';
                } else {
                    $message = 'The verification code is invalid or expired.';
                }
            } else {
                $clearOtp();
                $_SESSION['change_password_verified_admin_id'] = $adminId;
                $_SESSION['change_password_verified_at'] = time();
                $message = 'Email verified. Enter your current password and choose a new password.';
                $messageType = 'success';
            }
        } elseif ($action === 'change_password') {
            $verifiedAdminId = (int) ($_SESSION['change_password_verified_admin_id'] ?? 0);
            $verifiedAt = (int) ($_SESSION['change_password_verified_at'] ?? 0);
            $otpWasVerified = $verifiedAdminId === $adminId && $verifiedAt > 0 && time() - $verifiedAt <= 300;

            if (!$otpWasVerified) {
                unset($_SESSION['change_password_verified_admin_id'], $_SESSION['change_password_verified_at']);
                $message = 'Verify your email with a new code before changing your password.';
            } else {
                $currentPassword = (string) ($_POST['current_password'] ?? '');
                $newPassword = (string) ($_POST['new_password'] ?? '');
                $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
                $account = $conn->prepare('SELECT password FROM admins WHERE id = ? LIMIT 1');

                if (!$account) {
                    error_log('Change password verification preparation failed: ' . $conn->error);
                    $message = 'Unable to verify the admin account. Please try again.';
                } else {
                    $account->bind_param('i', $adminId);
                    $loaded = $account->execute();
                    $admin = $loaded ? $account->get_result()->fetch_assoc() : null;
                    $account->close();

                    if (!$admin || !password_verify($currentPassword, (string) $admin['password'])) {
                        $message = 'The current password is incorrect.';
                    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $newPassword) || strlen($newPassword) > 128) {
                        $message = 'Use at least 8 characters, including an uppercase letter, a number, and a special character.';
                    } elseif ($newPassword !== $confirmPassword) {
                        $message = 'The new passwords do not match.';
                    } elseif (password_verify($newPassword, (string) $admin['password'])) {
                        $message = 'Choose a password different from your current password.';
                    } else {
                        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $update = $conn->prepare('UPDATE admins SET password = ? WHERE id = ?');
                        if (!$update) {
                            error_log('Admin password update preparation failed: ' . $conn->error);
                            $message = 'Unable to update the password. Please try again.';
                        } else {
                            $update->bind_param('si', $passwordHash, $adminId);
                            $updated = $update->execute();
                            $update->close();

                            if ($updated) {
                                unset($_SESSION['change_password_verified_admin_id'], $_SESSION['change_password_verified_at'], $_SESSION['change_password_otp_sent_at']);
                                session_regenerate_id(true);
                                $message = 'Password changed successfully.';
                                $messageType = 'success';
                            } else {
                                $message = 'Unable to update the password. Please try again.';
                            }
                        }
                    }
                }
            }
        } else {
            $message = 'Select a valid password action.';
        }
    }
}

$otpVerifiedAt = (int) ($_SESSION['change_password_verified_at'] ?? 0);
$otpVerified = (int) ($_SESSION['change_password_verified_admin_id'] ?? 0) === $adminId
    && $otpVerifiedAt > 0
    && time() - $otpVerifiedAt <= 300;
if (!$otpVerified && isset($_SESSION['change_password_verified_admin_id'])) {
    unset($_SESSION['change_password_verified_admin_id'], $_SESSION['change_password_verified_at']);
}
$otpPending = (int) ($_SESSION['change_password_otp_admin_id'] ?? 0) === $adminId
    && isset($_SESSION['change_password_otp_hash'], $_SESSION['change_password_otp_expires_at'])
    && time() <= (int) $_SESSION['change_password_otp_expires_at'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change password | DunkHome Kicks</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261001-loader4">
    <link rel="stylesheet" href="assets/admin-pages.css">
    <link rel="stylesheet" href="assets/admin-navigation.css">
    <style>
        .password-heading { margin-bottom: 24px; }
        .password-heading .admin-subtitle { max-width: 620px; margin-bottom: 0; }
        .password-panel { max-width: 760px; overflow: hidden; padding: 0; }
        .password-panel-head { display: flex; align-items: center; gap: 14px; padding: 22px 24px; border-bottom: 1px solid var(--admin-line); }
        .password-panel-icon { width: 44px; height: 44px; flex: 0 0 44px; display: grid; place-items: center; border: 1px solid rgba(121,230,170,.22); border-radius: 12px; color: var(--admin-green); background: rgba(121,230,170,.08); }
        .password-panel-head h2 { margin: 0; color: var(--admin-text); font-size: 15px; }
        .password-panel-head p { margin: 5px 0 0; color: var(--admin-muted); font-size: 10px; line-height: 1.5; }
        .password-step { padding: 22px 24px; }
        .password-step-label { display: block; margin-bottom: 7px; color: var(--admin-green); font-size: 8px; font-weight: 800; text-transform: uppercase; }
        .password-step h3 { margin: 0; color: var(--admin-text); font-size: 14px; }
        .password-step p { margin: 7px 0 17px; color: var(--admin-muted); font-size: 10px; line-height: 1.6; }
        .password-step label { display: block; margin: 14px 0 6px; color: var(--admin-text); font-size: 10px; font-weight: 700; }
        .password-step input { width: 100%; min-height: 43px; padding: 10px 11px; border: 1px solid var(--admin-line); border-radius: 8px; outline: none; color: var(--admin-text); background: rgba(255,255,255,.035); font: 12px "DM Sans",sans-serif; }
        .password-step input:focus { border-color: rgba(121,230,170,.62); box-shadow: 0 0 0 3px rgba(121,230,170,.09); }
        .password-step input.otp-input { max-width: 240px; font-size: 18px; font-weight: 800; letter-spacing: 4px; }
        .password-step-actions { display: flex; flex-wrap: wrap; gap: 9px; margin-top: 18px; }
        .password-step-actions .admin-button { min-height: 38px; }
        .password-checklist { display: grid; gap: 6px; margin: 10px 0 0; padding: 0; color: var(--admin-muted); font-size: 9px; list-style: none; }
        .password-checklist li::before { margin-right: 7px; color: var(--admin-green); content: "•"; }
        .password-email { color: var(--admin-text); font-weight: 700; }
        body.light .password-step input { background: rgba(255,255,255,.88); }
        @media(max-width:640px) { .password-heading { margin-bottom: 18px; } .password-panel-head,.password-step { padding: 18px; } .password-step-actions { display: grid; grid-template-columns: 1fr; } .password-step-actions .admin-button { width: 100%; justify-content: center; } }
    </style>
</head>
<body>
<div class="admin-page admin-sidebar-layout">
    <div class="admin-menu-overlay" id="adminMenuOverlay"></div>
    <button class="admin-mobile-menu" id="adminMobileMenu" type="button" aria-label="Open administration menu" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
    <div class="admin-mobile-actions" aria-label="Admin controls">
        <button class="admin-mobile-action" id="themeToggle" type="button" title="Toggle theme" aria-label="Switch to light theme">☀️</button>
        <a class="admin-mobile-action" href="<?= h(appUrl('Admin/Logout.php?scope=admin')) ?>" title="Sign out" aria-label="Sign out"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
        <button class="admin-mobile-action" id="passwordRefreshButton" type="button" title="Reload page" aria-label="Reload page"><i class="fa-solid fa-rotate-right"></i></button>
    </div>
    <header class="admin-topbar" id="adminSidebar">
        <a class="admin-brand" href="<?= h(appUrl('Admin/AdminDashboard.php')) ?>"><img src="image/logo.jpeg" alt=""><div class="brand-name">dunkhome_<span>kicks</span></div></a>
        <div class="admin-nav-title">Administration</div>
        <nav class="admin-nav-links"><?php $adminNavigationShowThemeToggle = false; require __DIR__ . '/../includes/nav.php'; ?></nav>
        <div class="admin-sidebar-identity"><div class="admin-sidebar-avatar"><?= h($adminInitials) ?></div><div class="admin-sidebar-user"><strong><?= h($adminEmail) ?></strong><span>Administrator</span></div></div>
    </header>
    <main class="admin-content">
        <div class="password-heading"><div><div class="admin-eyebrow">Account security</div><h1 class="admin-title">Change password</h1><p class="admin-subtitle">Verify your admin email before confirming your current password and setting a new one.</p></div></div>
        <?php if ($message !== ''): ?><div class="admin-message <?= $messageType === 'success' ? 'success' : '' ?>" role="status"><?= h($message) ?></div><?php endif; ?>
        <section class="admin-panel password-panel" aria-label="Change admin password">
            <div class="password-panel-head"><span class="password-panel-icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span><div><h2>Secure password change</h2><p>Verification codes expire after 10 minutes. After verification, finish within 5 minutes.</p></div></div>
            <?php if (!$otpVerified && !$otpPending): ?>
                <form class="password-step" method="post">
                    <span class="password-step-label">Step 1 of 3</span><h3>Send a verification code</h3>
                    <p>A one-time code will be sent to <span class="password-email"><?= h($adminEmail) ?></span>.</p>
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="send_otp">
                    <div class="password-step-actions"><button class="admin-button primary" type="submit"><i class="fa-regular fa-paper-plane"></i> Send code</button></div>
                </form>
            <?php elseif (!$otpVerified): ?>
                <form class="password-step" method="post">
                    <span class="password-step-label">Step 2 of 3</span><h3>Verify your email</h3>
                    <p>Enter the six-digit code sent to <span class="password-email"><?= h($adminEmail) ?></span>.</p>
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="verify_otp">
                    <label for="otp">Verification code</label><input class="otp-input" id="otp" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required>
                    <div class="password-step-actions"><button class="admin-button primary" type="submit"><i class="fa-solid fa-check"></i> Verify code</button></div>
                </form>
                <form class="password-step" method="post">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="send_otp">
                    <div class="password-step-actions"><button class="admin-button" type="submit"><i class="fa-solid fa-rotate"></i> Resend code</button></div>
                </form>
            <?php else: ?>
                <form class="password-step" method="post">
                    <span class="password-step-label">Step 3 of 3</span><h3>Set a new password</h3>
                    <p>Your email is verified. Confirm your current password to finish the change.</p>
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="change_password">
                    <label for="currentPassword">Current password</label><input id="currentPassword" name="current_password" type="password" autocomplete="current-password" required>
                    <label for="newPassword">New password</label><input id="newPassword" name="new_password" type="password" autocomplete="new-password" minlength="8" maxlength="128" required>
                    <ul class="password-checklist"><li>At least 8 characters</li><li>One uppercase letter, one number, and one special character</li></ul>
                    <label for="confirmPassword">Confirm new password</label><input id="confirmPassword" name="confirm_password" type="password" autocomplete="new-password" minlength="8" maxlength="128" required>
                    <div class="password-step-actions"><button class="admin-button primary" type="submit"><i class="fa-solid fa-lock"></i> Change password</button></div>
                </form>
            <?php endif; ?>
        </section>
    </main>
    <footer class="admin-footer">DunkHome Kicks administration</footer>
</div>
<script src="assets/dunkhome-ui.js?v=20261001-loader4" defer></script>
<script src="assets/admin-sidebar-drawer.js?v=20261001-1" defer></script>
<script>document.getElementById('passwordRefreshButton')?.addEventListener('click', () => window.location.reload());</script>
</body>
</html>