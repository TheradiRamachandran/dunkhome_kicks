<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../Mailer.php';
require_once __DIR__ . '/../session.php';

$message = '';
$messageType = 'error';
$emailInput = trim((string) ($_POST['email'] ?? $_SESSION['admin_reset_email'] ?? ''));
$resetSucceeded = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $message = 'This request expired. Refresh the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'send_reset_otp') {
            $emailInput = strtolower(trim((string) ($_POST['email'] ?? '')));
            $lastSentAt = (int) ($_SESSION['admin_reset_sent_at'] ?? 0);
            if (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
                $message = 'Enter a valid admin email address.';
            } elseif (time() - $lastSentAt < 60) {
                $message = 'Please wait before requesting another code.';
            } else {
                $account = $conn->prepare('SELECT id, email FROM admins WHERE LOWER(email) = LOWER(?) LIMIT 1');
                if (!$account) {
                    error_log('Admin reset account lookup preparation failed: ' . $conn->error);
                    $message = 'Unable to process the request. Please try again.';
                } else {
                    $account->bind_param('s', $emailInput);
                    $account->execute();
                    $admin = $account->get_result()->fetch_assoc();
                    $account->close();

                    if (!$admin) {
                        $message = 'No admin account was found for that email address.';
                    } else {
                        $otp = (string) random_int(100000, 999999);
                        $delivery = sendOtpEmail((string) $admin['email'], (int) $otp, 'reset');
                        $_SESSION['admin_reset_sent_at'] = time();
                        unset($_SESSION['admin_reset_verified_at']);

                        if (($delivery['status'] ?? 'error') === 'success') {
                            $_SESSION['admin_reset_email'] = (string) $admin['email'];
                            $_SESSION['admin_reset_otp_hash'] = password_hash($otp, PASSWORD_DEFAULT);
                            $_SESSION['admin_reset_otp_expires_at'] = time() + 600;
                            $_SESSION['admin_reset_otp_attempts'] = 0;
                            $_SESSION['admin_reset_account_id'] = (int) $admin['id'];
                            $emailInput = (string) $admin['email'];
                            $message = 'A six-digit code was sent. It will expire in 10 minutes.';
                            $messageType = 'success';
                        } else {
                            $message = (string) ($delivery['message'] ?? 'Unable to send the verification code.');
                        }
                    }
                }
            }
        } elseif ($action === 'verify_reset_otp') {
            $otp = trim((string) ($_POST['otp'] ?? ''));
            $otpValid = isset($_SESSION['admin_reset_otp_hash'], $_SESSION['admin_reset_otp_expires_at'], $_SESSION['admin_reset_email'], $_SESSION['admin_reset_account_id'])
                && time() <= (int) $_SESSION['admin_reset_otp_expires_at']
                && preg_match('/^\d{6}$/', $otp)
                && password_verify($otp, (string) $_SESSION['admin_reset_otp_hash']);

            if (!$otpValid) {
                $_SESSION['admin_reset_otp_attempts'] = (int) ($_SESSION['admin_reset_otp_attempts'] ?? 0) + 1;
                if ((int) $_SESSION['admin_reset_otp_attempts'] >= 5) {
                    unset($_SESSION['admin_reset_otp_hash'], $_SESSION['admin_reset_otp_expires_at'], $_SESSION['admin_reset_otp_attempts']);
                    $message = 'Too many incorrect attempts. Request a new code.';
                } else {
                    $message = 'The code is invalid or expired.';
                }
            } else {
                $_SESSION['admin_reset_verified_at'] = time();
                unset($_SESSION['admin_reset_otp_hash'], $_SESSION['admin_reset_otp_expires_at'], $_SESSION['admin_reset_otp_attempts']);
                $message = 'Email verified. Set a new password below.';
                $messageType = 'success';
            }
        } elseif ($action === 'reset_password') {
            $verifiedAt = (int) ($_SESSION['admin_reset_verified_at'] ?? 0);
            $verifiedEmail = (string) ($_SESSION['admin_reset_email'] ?? '');
            $verifiedAdminId = (int) ($_SESSION['admin_reset_account_id'] ?? 0);
            $resetIsVerified = $verifiedAt > 0 && time() - $verifiedAt <= 300 && $verifiedEmail !== '' && $verifiedAdminId > 0;
            $password = (string) ($_POST['password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if (!$resetIsVerified) {
                unset($_SESSION['admin_reset_verified_at']);
                $message = 'Verify your email with a new code before resetting your password.';
            } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password) || strlen($password) > 128) {
                $message = 'Use at least 8 characters, including an uppercase letter, a number, and a special character.';
            } elseif ($password !== $confirmPassword) {
                $message = 'The passwords do not match.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $update = $conn->prepare('UPDATE admins SET password = ? WHERE id = ?');
                if (!$update) {
                    error_log('Admin password reset preparation failed: ' . $conn->error);
                    $message = 'Unable to reset the password. Please try again.';
                } else {
                    $update->bind_param('si', $passwordHash, $verifiedAdminId);
                    $updated = $update->execute();
                    $update->close();

                    if ($updated) {
                        unset($_SESSION['admin_reset_email'], $_SESSION['admin_reset_account_id'], $_SESSION['admin_reset_verified_at'], $_SESSION['admin_reset_sent_at']);
                        $message = 'Password reset successfully. You can sign in now.';
                        $messageType = 'success';
                        $resetSucceeded = true;
                    } else {
                        $message = 'Unable to reset the password. Please try again.';
                    }
                }
            }
        } else {
            $message = 'Select a valid recovery action.';
        }
    }
}

$otpPending = isset($_SESSION['admin_reset_otp_hash'], $_SESSION['admin_reset_otp_expires_at'], $_SESSION['admin_reset_email'])
    && time() <= (int) $_SESSION['admin_reset_otp_expires_at'];
$resetVerifiedAt = (int) ($_SESSION['admin_reset_verified_at'] ?? 0);
$resetVerified = !$resetSucceeded && $resetVerifiedAt > 0 && time() - $resetVerifiedAt <= 300 && !empty($_SESSION['admin_reset_email']);
if (!$resetVerified && isset($_SESSION['admin_reset_verified_at'])) {
    unset($_SESSION['admin_reset_verified_at']);
}
$emailInput = (string) ($_SESSION['admin_reset_email'] ?? $emailInput);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../includes/favicon.php'; ?>
    <base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07100d">
    <meta name="description" content="Reset your DunkHome Kicks administrator password securely.">
    <title>Admin password recovery | DunkHome Kicks</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-theme2">
    <style>
        :root { --bg:#07100d; --white:#f8faf8; --muted:#9baba3; --line:rgba(255,255,255,.10); --line-strong:rgba(255,255,255,.17); --green:#79e6aa; --orange:#ff9a62; --danger:#ff7b7b; --shadow:0 35px 100px rgba(0,0,0,.44); }
        * { box-sizing: border-box; }
        body { min-height:100vh; margin:0; overflow-x:hidden; color:var(--white); font-family:"DM Sans",sans-serif; background:radial-gradient(circle at 8% 7%,rgba(121,230,170,.13),transparent 27%),radial-gradient(circle at 92% 82%,rgba(255,154,98,.08),transparent 23%),linear-gradient(135deg,#06100d,#0a1713 50%,#06100d); }
        body::before { position:fixed; inset:0; z-index:-1; opacity:.21; background-image:linear-gradient(rgba(255,255,255,.018) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.018) 1px,transparent 1px); background-size:42px 42px; content:""; mask-image:linear-gradient(to bottom,black,transparent 88%); pointer-events:none; }
        a,button,input { font:inherit; }
        a { color:inherit; }
        button { border:0; }
        .page { width:min(1260px,calc(100% - 34px)); min-height:100vh; margin:auto; display:flex; flex-direction:column; }
        .topbar { height:88px; display:flex; align-items:center; justify-content:space-between; gap:16px; }
        .brand { display:inline-flex; align-items:center; gap:12px; text-decoration:none; }
        .brand-logo { width:43px; height:43px; object-fit:cover; border:1px solid var(--line-strong); border-radius:13px; box-shadow:0 12px 28px rgba(0,0,0,.25); }
        .brand-name { font-size:13px; font-weight:800; letter-spacing:.16em; text-transform:uppercase; }
        .brand-name span { color:var(--green); }
        .secure-label { display:flex; align-items:center; gap:7px; color:#74857c; font-size:9px; font-weight:700; text-transform:uppercase; }
        .secure-label i { color:var(--green); }
        main { flex:1; display:grid; place-items:center; padding:18px 0 38px; }
        .auth-shell { width:100%; min-height:620px; display:grid; grid-template-columns:minmax(0,1.05fr) minmax(390px,.95fr); overflow:hidden; border:1px solid var(--line); border-radius:28px; background:rgba(255,255,255,.025); box-shadow:var(--shadow); backdrop-filter:blur(22px); }
        .visual { position:relative; min-height:620px; padding:38px; display:flex; flex-direction:column; justify-content:space-between; isolation:isolate; background:linear-gradient(180deg,rgba(4,13,11,.04),rgba(4,13,11,.28) 40%,rgba(4,13,11,.96)),url("image/background.jpg") center/cover no-repeat; }
        .visual::before { position:absolute; inset:16px; z-index:-1; border:1px solid rgba(255,255,255,.16); border-radius:20px; content:""; }
        .visual-tag { display:inline-flex; align-items:center; gap:8px; padding:9px 11px; border:1px solid rgba(255,255,255,.14); border-radius:999px; color:#e3ece7; background:rgba(0,0,0,.25); font-size:9px; font-weight:800; text-transform:uppercase; }
        .visual-tag i { color:var(--green); }
        .visual-copy { max-width:480px; }
        .eyebrow { margin-bottom:13px; color:var(--orange); font-size:10px; font-weight:800; text-transform:uppercase; }
        .visual-copy h1 { margin:0; font:600 clamp(40px,4vw,64px)/.98 "Playfair Display",serif; }
        .visual-copy h1 em { color:var(--green); font-style:normal; }
        .visual-copy p { max-width:400px; margin:18px 0 0; color:rgba(248,250,248,.72); font-size:12px; line-height:1.75; }
        .form-side { min-width:0; display:grid; place-items:center; padding:36px; background:radial-gradient(circle at 100% 0%,rgba(121,230,170,.08),transparent 34%),linear-gradient(145deg,rgba(14,29,24,.98),rgba(8,18,15,.99)); }
        .form-card { width:100%; max-width:440px; }
        .kicker { margin:0 0 8px; color:var(--green); font-size:9px; font-weight:800; letter-spacing:.16em; text-transform:uppercase; }
        .title { margin:0; font:600 34px/1.05 "Playfair Display",serif; }
        .subtitle { margin:10px 0 22px; color:var(--muted); font-size:11px; line-height:1.65; }
        .server-message { display:flex; align-items:flex-start; gap:9px; margin:0 0 17px; padding:11px 12px; border:1px solid rgba(255,123,123,.18); border-radius:10px; color:#ffc7c7; background:rgba(255,123,123,.08); font-size:10px; line-height:1.5; }
        .server-message.success { border-color:rgba(121,230,170,.18); color:#baf3ce; background:rgba(121,230,170,.08); }
        .server-message i { margin-top:1px; }
        .field { margin-bottom:14px; }
        .field label { display:flex; justify-content:space-between; gap:10px; margin:0 0 7px; color:#dce5e1; font-size:10px; font-weight:800; text-transform:uppercase; }
        .field label small { color:#718178; font-size:8px; font-weight:600; text-transform:none; }
        .input-wrap { position:relative; }
        .input-icon { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:#65776d; pointer-events:none; }
        .input { width:100%; min-height:48px; padding:0 13px 0 39px; border:1px solid var(--line); border-radius:11px; outline:none; color:var(--white); background:rgba(255,255,255,.035); font-size:12px; }
        .input::placeholder { color:#718078; }
        .input:focus { border-color:rgba(121,230,170,.62); box-shadow:0 0 0 4px rgba(121,230,170,.07); }
        .otp-input { max-width:220px; letter-spacing:5px; font-size:18px; font-weight:800; }
        .primary { width:100%; min-height:48px; display:inline-flex; align-items:center; justify-content:center; gap:8px; margin-top:8px; border:0; border-radius:11px; color:#06170f; background:linear-gradient(135deg,#90efbb,#56d993); font-size:11px; font-weight:800; cursor:pointer; box-shadow:0 12px 26px rgba(121,230,170,.12); }
        .primary:hover { filter:brightness(1.04); }
        .secondary { width:100%; min-height:44px; display:inline-flex; align-items:center; justify-content:center; gap:8px; margin-top:9px; border:1px solid var(--line); border-radius:10px; color:#dce5e1; background:rgba(255,255,255,.025); font-size:10px; font-weight:700; text-decoration:none; }
        .step-note { margin:11px 0 0; color:#78887f; font-size:9px; line-height:1.5; }
        .card-footer { margin-top:20px; padding-top:15px; border-top:1px solid rgba(255,255,255,.08); text-align:center; }
        .card-footer p { margin:0; color:#839188; font-size:10px; }
        .card-footer a { display:inline-flex; align-items:center; gap:7px; margin-top:8px; color:var(--green); font-size:10px; font-weight:800; text-decoration:none; }
        footer { padding:0 0 16px; color:#64736b; font-size:9px; text-align:center; }
        body.light { --white:#18251d; --muted:#5f7066; --line:rgba(20,48,31,.14); --line-strong:rgba(20,48,31,.22); color:#18251d; background:radial-gradient(circle at 8% 7%,rgba(73,204,134,.15),transparent 27%),linear-gradient(135deg,#f2f8f3,#e8f2eb 50%,#f7faf7); }
        body.light::before { opacity:.08; }
        body.light .auth-shell { border-color:rgba(20,48,31,.13); background:rgba(255,255,255,.56); }
        body.light .form-side { background:linear-gradient(145deg,rgba(255,255,255,.98),rgba(242,248,243,.99)); }
        body.light .title,body.light .field label { color:#18251d; }
        body.light .input { color:#18251d; background:rgba(255,255,255,.88); }
        body.light .input::placeholder { color:#87958c; }
        body.light .secondary { color:#304239; }
        @media(max-width:900px) { .auth-shell { max-width:620px; grid-template-columns:1fr; min-height:0; } .visual { min-height:230px; padding:26px; } .visual::before { inset:12px; } .visual-copy { max-width:500px; } .visual-copy h1 { font-size:42px; } .visual-copy p { margin-top:10px; font-size:10px; } .form-side { padding:28px; } }
        @media(max-width:600px) { .page { width:calc(100% - 14px); } .topbar { height:70px; } .brand-logo { width:37px; height:37px; border-radius:11px; } .brand-name { font-size:10px; } .secure-label { display:none; } main { padding:7px 0 22px; } .auth-shell { border-radius:20px; } .visual { min-height:190px; padding:22px; } .visual-copy h1 { max-width:310px; font-size:36px; } .visual-copy p { max-width:310px; } .form-side { padding:24px 16px; } .title { font-size:29px; } }
    </style>
</head>
<body>
<div class="page">
    <header class="topbar">
        <a class="brand" href="<?= h(appUrl('index.php')) ?>" aria-label="DunkHome Kicks home"><img class="brand-logo" src="image/logo.jpg" alt=""><span class="brand-name">dunkhome_<span>kicks</span></span></a>
    </header>
    <main>
        <section class="auth-shell" aria-label="Admin password recovery">
            <aside class="visual">
                <div class="visual-tag"><i class="fa-solid fa-shield-halved"></i> Account security</div>
                <div class="visual-copy"><div class="eyebrow">Protected admin access</div><h1>Secure your <em>account.</em></h1><p>Verify your registered admin email with a one-time code before choosing a new password.</p></div>
            </aside>
            <section class="form-side">
                <div class="form-card">
                    <div class="kicker">Account recovery</div>
                    <?php if ($resetSucceeded): ?>
                        <h1 class="title">Password updated</h1>
                        <p class="subtitle">Your administrator password was reset successfully. You can sign in with the new password.</p>
                    <?php elseif ($resetVerified): ?>
                        <h1 class="title">Choose a new password</h1>
                        <p class="subtitle">Email verified for <?= h((string) $_SESSION['admin_reset_email']) ?>. Set a new password to finish recovery.</p>
                    <?php elseif ($otpPending): ?>
                        <h1 class="title">Check your email</h1>
                        <p class="subtitle">Enter the six-digit code sent to <?= h((string) $_SESSION['admin_reset_email']) ?>.</p>
                    <?php else: ?>
                        <h1 class="title">Admin password recovery</h1>
                        <p class="subtitle">Enter your registered admin email. We’ll send a six-digit code to verify your account.</p>
                    <?php endif; ?>

                    <?php if ($message !== ''): ?>
                        <div class="server-message <?= $messageType === 'success' ? 'success' : '' ?>" role="status"><i class="fa-solid <?= $messageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i><span><?= h($message) ?></span></div>
                    <?php endif; ?>

                    <?php if (!$resetSucceeded && !$resetVerified && !$otpPending): ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                            <input type="hidden" name="action" value="send_reset_otp">
                            <div class="field"><label for="recoveryEmail">Admin email <small>Registered address</small></label><div class="input-wrap"><i class="fa-regular fa-envelope input-icon"></i><input class="input" id="recoveryEmail" type="email" name="email" value="<?= h($emailInput) ?>" placeholder="admin@example.com" autocomplete="username" required></div></div>
                            <button class="primary" type="submit"><i class="fa-regular fa-paper-plane"></i> Send verification code</button>
                            <p class="step-note">The code expires in 10 minutes. You can request another after 60 seconds.</p>
                        </form>
                    <?php elseif (!$resetSucceeded && !$resetVerified): ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                            <input type="hidden" name="action" value="verify_reset_otp">
                            <div class="field"><label for="recoveryOtp">Verification code <small>6 digits</small></label><div class="input-wrap"><i class="fa-solid fa-key input-icon"></i><input class="input otp-input" id="recoveryOtp" type="text" name="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required></div></div>
                            <button class="primary" type="submit"><i class="fa-solid fa-check"></i> Verify code</button>
                        </form>
                        <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="send_reset_otp"><input type="hidden" name="email" value="<?= h((string) $_SESSION['admin_reset_email']) ?>"><button class="secondary" type="submit"><i class="fa-solid fa-rotate"></i> Resend code</button></form>
                        <p class="step-note">For security, you have up to five attempts. Codes expire after 10 minutes.</p>
                    <?php elseif (!$resetSucceeded): ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                            <input type="hidden" name="action" value="reset_password">
                            <div class="field"><label for="newPassword">New password</label><div class="input-wrap"><i class="fa-solid fa-lock input-icon"></i><input class="input" id="newPassword" type="password" name="password" autocomplete="new-password" minlength="8" maxlength="128" required></div><p class="step-note">At least 8 characters, with an uppercase letter, a number, and a special character.</p></div>
                            <div class="field"><label for="confirmPassword">Confirm new password</label><div class="input-wrap"><i class="fa-solid fa-lock input-icon"></i><input class="input" id="confirmPassword" type="password" name="confirm_password" autocomplete="new-password" minlength="8" maxlength="128" required></div></div>
                            <button class="primary" type="submit"><i class="fa-solid fa-key"></i> Reset password</button>
                        </form>
                    <?php endif; ?>

                    <div class="card-footer"><p>Remembered your password?</p><a href="<?= h(appUrl('Admin/AdminSignIn.php')) ?>"><i class="fa-solid fa-arrow-left"></i> Return to admin sign in</a></div>
                </div>
            </section>
        </section>
    </main>
    <footer>&copy; <?= date('Y') ?> DunkHome Kicks. Administrator access.</footer>
</div>
<script src="assets/dunkhome-ui.js?v=20261001-loader4" defer></script>
</body>
</html>

