<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../Mailer.php';
require_once __DIR__ . '/../session.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Admin authentication
|--------------------------------------------------------------------------
|
| Admin accounts are stored separately in an `admins` table.
| This file creates that table automatically when it does not exist.
|
*/
$conn->query("
    CREATE TABLE IF NOT EXISTS admins (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        email VARCHAR(255) NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_admin_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

if (isset($_SESSION['admin_id'])) {
    header('Location: ' . appUrl('Admin/AdminDashboard.php'));
    exit;
}

$message = '';
$messageType = 'error';

function normalizeAdminEmail(string $email): string
{
    return strtolower(trim((string) filter_var($email, FILTER_SANITIZE_EMAIL)));
}

function adminEmailExists(mysqli $conn, string $email): bool
{
    $stmt = $conn->prepare(
        'SELECT id FROM admins WHERE LOWER(email) = LOWER(?) LIMIT 1'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();

    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function adminBootstrapAllowed(mysqli $conn): bool
{
    $result = $conn->query('SELECT COUNT(*) AS total FROM admins');
    if (!$result) {
        return false;
    }

    $adminCount = (int) (($result->fetch_assoc()['total'] ?? 0));
    if ($adminCount !== 0) {
        return false;
    }

    return in_array(
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ['127.0.0.1', '::1', '::ffff:127.0.0.1'],
        true
    );
}

/*
|--------------------------------------------------------------------------
| AJAX: Send admin OTP
|--------------------------------------------------------------------------
*/
if (($_POST['action'] ?? '') === 'send_admin_otp') {
    header('Content-Type: application/json; charset=utf-8');

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        echo json_encode(['status' => 'error', 'message' => 'Refresh the page and try again.']);
        exit;
    }

    $email = normalizeAdminEmail($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Please enter a valid admin email address.'
        ]);
        exit;
    }

    if (adminEmailExists($conn, $email)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'An admin account with this email already exists. Please sign in.'
        ]);
        exit;
    }

    if (!adminBootstrapAllowed($conn)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'First-admin setup is only available from localhost and only before an admin account exists.'
        ]);
        exit;
    }

    $otp = random_int(100000, 999999);

    $_SESSION['admin_signup_otp'] = (string) $otp;
    $_SESSION['admin_signup_otp_email'] = $email;
    $_SESSION['admin_signup_otp_expires_at'] = time() + 600;
    $_SESSION['admin_signup_otp_verified'] = false;

    $result = sendOtpEmail($email, $otp);

    if (($result['status'] ?? 'error') !== 'success') {
        unset(
            $_SESSION['admin_signup_otp'],
            $_SESSION['admin_signup_otp_email'],
            $_SESSION['admin_signup_otp_expires_at'],
            $_SESSION['admin_signup_otp_verified']
        );

        echo json_encode($result);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Admin verification code sent successfully.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| AJAX: Verify admin OTP
|--------------------------------------------------------------------------
*/
if (($_POST['action'] ?? '') === 'verify_admin_otp') {
    header('Content-Type: application/json; charset=utf-8');

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        echo json_encode(['status' => 'error', 'message' => 'Refresh the page and try again.']);
        exit;
    }

    $email = normalizeAdminEmail($_POST['email'] ?? '');
    $enteredOtp = trim((string) ($_POST['otp'] ?? ''));

    $valid =
        isset(
            $_SESSION['admin_signup_otp'],
            $_SESSION['admin_signup_otp_email'],
            $_SESSION['admin_signup_otp_expires_at']
        )
        && hash_equals(
            (string) $_SESSION['admin_signup_otp'],
            $enteredOtp
        )
        && hash_equals(
            (string) $_SESSION['admin_signup_otp_email'],
            $email
        )
        && time() <= (int) $_SESSION['admin_signup_otp_expires_at'];

    if ($valid) {
        $_SESSION['admin_signup_otp_verified'] = true;

        echo json_encode([
            'status' => 'success',
            'message' => 'Admin email verified successfully.'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid or expired verification code.'
        ]);
    }

    exit;
}

/*
|--------------------------------------------------------------------------
| Final admin registration
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'register_admin'
) {
    $email = normalizeAdminEmail($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    $strongPassword =
        '/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/';

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $message = 'Your registration form expired. Refresh the page and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email address.';
    } elseif (adminEmailExists($conn, $email)) {
        $message = 'An admin account with this email already exists.';
    } elseif (!adminBootstrapAllowed($conn)) {
        $message = 'First-admin setup is only available from localhost and only before an admin account exists.';
    } elseif (
        !isset(
            $_SESSION['admin_signup_otp_verified'],
            $_SESSION['admin_signup_otp_email']
        )
        || $_SESSION['admin_signup_otp_verified'] !== true
        || !hash_equals(
            (string) $_SESSION['admin_signup_otp_email'],
            $email
        )
    ) {
        $message = 'Please verify the admin email before creating the account.';
    } elseif (!preg_match($strongPassword, $password)) {
        $message =
            'Password must be at least 8 characters and include an uppercase letter, a number, and a special character.';
    } elseif ($password !== $confirmPassword) {
        $message = 'Password and confirm password must match.';
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            'INSERT INTO admins (email, password, created_at) VALUES (?, ?, NOW())'
        );

        if (!$stmt) {
            $message = 'Unable to prepare admin registration. Please try again.';
        } else {
            $stmt->bind_param('ss', $email, $passwordHash);

            if ($stmt->execute()) {
                $stmt->close();

                unset(
                    $_SESSION['admin_signup_otp'],
                    $_SESSION['admin_signup_otp_email'],
                    $_SESSION['admin_signup_otp_expires_at'],
                    $_SESSION['admin_signup_otp_verified']
                );

                $_SESSION['admin_signup_success'] =
                    'Admin account created successfully. Please sign in.';

                header('Location: ' . appUrl('Admin/AdminSignIn.php'));
                exit;
            }

            $message = 'Admin registration failed. Please try again.';
            $stmt->close();
        }
    }
}

if ($message === '' && !empty($_SESSION['admin_signup_success'])) {
    $message = (string) $_SESSION['admin_signup_success'];
    $messageType = 'success';
    unset($_SESSION['admin_signup_success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../includes/favicon.php'; ?><base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="theme-color" content="#07100d">
    <meta name="description" content="Create a DunkHome Kicks administrator account.">

    <title>Admin Sign Up | DunkHome Kicks</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>
        :root {
            --bg:#07100d;
            --bg2:#0b1713;
            --white:#f8faf8;
            --muted:#9baba3;
            --line:rgba(255,255,255,.10);
            --line-strong:rgba(255,255,255,.17);
            --green:#79e6aa;
            --green2:#49cc86;
            --orange:#ff9a62;
            --danger:#ff7b7b;
            --success:#8ee9b0;
            --shadow:0 35px 100px rgba(0,0,0,.44);
            --radius:30px;
        }

        * { box-sizing:border-box; }

        html { scroll-behavior:smooth; }

        body {
            min-height:100vh;
            margin:0;
            overflow-x:hidden;
            font-family:"DM Sans",sans-serif;
            color:var(--white);
            background:
                radial-gradient(circle at 8% 7%,rgba(121,230,170,.14),transparent 27%),
                radial-gradient(circle at 92% 82%,rgba(255,154,98,.08),transparent 23%),
                linear-gradient(135deg,#06100d,#0a1713 50%,#06100d);
        }

        body::before {
            content:"";
            position:fixed;
            inset:0;
            pointer-events:none;
            opacity:.21;
            background-image:
                linear-gradient(rgba(255,255,255,.018) 1px,transparent 1px),
                linear-gradient(90deg,rgba(255,255,255,.018) 1px,transparent 1px);
            background-size:42px 42px;
            mask-image:linear-gradient(to bottom,black,transparent 88%);
        }

        a { color:inherit; }
        button,input { font:inherit; }
        button { border:0; }

        .page {
            width:min(1260px,calc(100% - 34px));
            min-height:100vh;
            margin:auto;
            display:flex;
            flex-direction:column;
        }

        .topbar {
            height:88px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            animation:fadeDown .55s ease both;
        }

        .brand {
            display:inline-flex;
            align-items:center;
            gap:12px;
            text-decoration:none;
        }

        .brand-logo {
            width:43px;
            height:43px;
            object-fit:cover;
            border-radius:13px;
            border:1px solid var(--line-strong);
            box-shadow:0 12px 28px rgba(0,0,0,.25);
        }

        .brand-name {
            font-size:13px;
            font-weight:800;
            letter-spacing:.16em;
            text-transform:uppercase;
        }

        .brand-name span { color:var(--green); }

        .security-label {
            display:flex;
            align-items:center;
            gap:7px;
            color:#64756d;
            font-size:9px;
            font-weight:700;
            letter-spacing:.12em;
            text-transform:uppercase;
        }

        .security-label i { color:var(--green); }

        main {
            flex:1;
            display:grid;
            place-items:center;
            padding:18px 0 42px;
        }

        .auth-shell {
            width:100%;
            min-height:700px;
            display:grid;
            grid-template-columns:minmax(0,1.04fr) minmax(430px,.96fr);
            overflow:hidden;
            border:1px solid var(--line);
            border-radius:var(--radius);
            background:rgba(255,255,255,.025);
            box-shadow:var(--shadow);
            backdrop-filter:blur(22px);
            animation:shellIn .72s cubic-bezier(.2,.75,.2,1) both;
        }

        .visual {
            position:relative;
            min-height:700px;
            padding:45px;
            display:flex;
            flex-direction:column;
            justify-content:space-between;
            isolation:isolate;
            background:
                linear-gradient(180deg,rgba(4,13,11,.03),rgba(4,13,11,.22) 35%,rgba(4,13,11,.96)),
                url("image/background.jpg") center/cover no-repeat;
        }

        .visual::before {
            content:"";
            position:absolute;
            inset:20px;
            border:1px solid rgba(255,255,255,.17);
            border-radius:24px;
            pointer-events:none;
        }

        .visual::after {
            content:"";
            position:absolute;
            width:330px;
            height:330px;
            top:-120px;
            right:-120px;
            border-radius:50%;
            background:rgba(121,230,170,.13);
            filter:blur(65px);
            z-index:-1;
        }

        .visual-tag {
            position:relative;
            z-index:2;
            width:max-content;
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:9px 12px;
            border:1px solid rgba(255,255,255,.14);
            border-radius:999px;
            background:rgba(0,0,0,.25);
            backdrop-filter:blur(12px);
            color:#dfeae5;
            font-size:9px;
            font-weight:800;
            letter-spacing:.18em;
            text-transform:uppercase;
            animation:fadeUp .65s .14s ease both;
        }

        .visual-tag i { color:var(--green); }

        .visual-copy {
            position:relative;
            z-index:2;
            max-width:570px;
            animation:copyIn .8s .18s ease both;
        }

        .eyebrow {
            margin-bottom:15px;
            color:var(--orange);
            font-size:10px;
            font-weight:800;
            letter-spacing:.23em;
            text-transform:uppercase;
        }

        .visual-copy h1 {
            max-width:520px;
            margin:0;
            font-family:"Playfair Display",serif;
            font-size:clamp(46px,5vw,76px);
            line-height:.92;
            letter-spacing:-.045em;
        }

        .visual-copy h1 em {
            color:var(--green);
            font-style:normal;
        }

        .visual-copy p {
            max-width:455px;
            margin:22px 0 0;
            color:rgba(248,250,248,.70);
            font-size:13px;
            line-height:1.75;
        }

        .form-side {
            min-width:0;
            display:grid;
            place-items:center;
            padding:40px 42px 32px;
            background:
                radial-gradient(circle at 100% 0%,rgba(121,230,170,.10),transparent 34%),
                linear-gradient(145deg,rgba(14,29,24,.98),rgba(8,18,15,.99));
        }

        .form-card {
            width:100%;
            max-width:465px;
            animation:fadeUp .8s .12s ease both;
        }

        .card-head {
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:18px;
            margin-bottom:24px;
        }

        .kicker {
            margin:0 0 8px;
            color:var(--green);
            font-size:9px;
            font-weight:800;
            letter-spacing:.2em;
            text-transform:uppercase;
        }

        .title {
            margin:0;
            font-family:"Playfair Display",serif;
            font-size:34px;
            line-height:1;
        }

        .subtitle {
            margin:10px 0 0;
            color:var(--muted);
            font-size:11px;
            line-height:1.6;
        }

        .progress {
            display:flex;
            gap:6px;
            padding-top:4px;
        }

        .progress span {
            width:8px;
            height:8px;
            border-radius:50%;
            background:rgba(255,255,255,.15);
            transition:.3s ease;
        }

        .progress span.active {
            width:25px;
            border-radius:999px;
            background:var(--green);
            box-shadow:0 0 18px rgba(121,230,170,.4);
        }

        .server-message,
        .message {
            border-radius:13px;
            font-size:10px;
            line-height:1.5;
        }

        .server-message {
            display:flex;
            gap:9px;
            padding:11px 12px;
            margin-bottom:16px;
        }

        .server-message.error {
            color:#ffc7c7;
            background:rgba(255,123,123,.08);
            border:1px solid rgba(255,123,123,.18);
        }

        .server-message.success {
            color:#baf3ce;
            background:rgba(121,230,170,.08);
            border:1px solid rgba(121,230,170,.18);
        }

        .field { margin-bottom:17px; }

        .field-label {
            display:flex;
            justify-content:space-between;
            gap:10px;
            margin-bottom:8px;
            color:#dce5e1;
            font-size:10px;
            font-weight:800;
            letter-spacing:.09em;
            text-transform:uppercase;
        }

        .field-label small {
            color:#64756d;
            font-size:8px;
            letter-spacing:.03em;
            text-transform:none;
        }

        .input-wrap { position:relative; }

        .input-icon {
            position:absolute;
            left:15px;
            top:50%;
            z-index:1;
            transform:translateY(-50%);
            color:#5d6e67;
            pointer-events:none;
            transition:.2s ease;
        }

        .input {
            width:100%;
            height:53px;
            padding:0 50px 0 42px;
            border:1px solid var(--line);
            border-radius:15px;
            outline:none;
            color:white;
            background:rgba(255,255,255,.038);
            font-size:12px;
            transition:.22s ease;
        }

        .input::placeholder { color:#5e6c66; }

        .input:hover { border-color:rgba(255,255,255,.18); }

        .input:focus {
            background:rgba(121,230,170,.04);
            border-color:rgba(121,230,170,.62);
            box-shadow:0 0 0 4px rgba(121,230,170,.065);
            transform:translateY(-1px);
        }

        .input-wrap:focus-within .input-icon { color:var(--green); }

        .icon-button,
        .send-button {
            position:absolute;
            top:50%;
            transform:translateY(-50%);
            display:grid;
            place-items:center;
            cursor:pointer;
            transition:.22s ease;
        }

        .icon-button {
            right:8px;
            width:36px;
            height:36px;
            border-radius:10px;
            color:#6f8078;
            background:transparent;
        }

        .icon-button:hover {
            color:var(--green);
            background:rgba(255,255,255,.06);
        }

        .send-button {
            right:7px;
            width:39px;
            height:39px;
            border-radius:11px;
            color:#05100c;
            background:var(--green);
        }

        .send-button:hover:not(:disabled) {
            background:#93efbd;
            transform:translateY(-50%) translateY(-1px);
        }

        .send-button:disabled {
            opacity:.55;
            cursor:not-allowed;
        }

        .message {
            display:none;
            margin-top:7px;
            padding:8px 10px;
        }

        .message.visible { display:block; }

        .message.error {
            color:#ffb4b4;
            background:rgba(255,123,123,.065);
            border:1px solid rgba(255,123,123,.16);
        }

        .message.success {
            color:#baf3ce;
            background:rgba(121,230,170,.065);
            border:1px solid rgba(121,230,170,.15);
        }

        .invalid {
            border-color:rgba(255,123,123,.68) !important;
            box-shadow:0 0 0 4px rgba(255,123,123,.05) !important;
        }

        .divider {
            display:flex;
            align-items:center;
            gap:12px;
            margin:19px 0;
            color:#586760;
            font-size:8px;
            letter-spacing:.18em;
            text-transform:uppercase;
        }

        .divider::before,
        .divider::after {
            content:"";
            flex:1;
            height:1px;
            background:rgba(255,255,255,.08);
        }

        .otp-info {
            text-align:center;
            margin-bottom:18px;
        }

        .otp-icon {
            width:53px;
            height:53px;
            margin:0 auto 13px;
            display:grid;
            place-items:center;
            border-radius:17px;
            color:var(--green);
            background:rgba(121,230,170,.08);
            border:1px solid rgba(121,230,170,.15);
            font-size:19px;
        }

        .otp-info h3 {
            margin:0;
            font-family:"Playfair Display",serif;
            font-size:24px;
        }

        .otp-info p {
            max-width:330px;
            margin:7px auto 0;
            color:var(--muted);
            font-size:10px;
            line-height:1.55;
        }

        .email-pill {
            width:max-content;
            max-width:100%;
            display:inline-flex;
            align-items:center;
            gap:7px;
            margin-top:10px;
            padding:7px 10px;
            color:#d8e6df;
            background:rgba(255,255,255,.04);
            border:1px solid rgba(255,255,255,.08);
            border-radius:999px;
            font-size:9px;
        }

        .otp-grid {
            display:grid;
            grid-template-columns:repeat(6,1fr);
            gap:7px;
            margin-bottom:14px;
        }

        .otp {
            width:100%;
            height:57px;
            text-align:center;
            border:1px solid var(--line);
            border-radius:13px;
            outline:none;
            color:white;
            background:rgba(255,255,255,.038);
            font-size:20px;
            font-weight:800;
            transition:.2s ease;
        }

        .otp:focus {
            border-color:rgba(121,230,170,.62);
            box-shadow:0 0 0 4px rgba(121,230,170,.065);
        }

        .otp.valid {
            color:var(--green);
            border-color:rgba(121,230,170,.48);
        }

        .primary {
            width:100%;
            height:53px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            border-radius:15px;
            color:#06100c;
            background:linear-gradient(135deg,#90efbb,#56d993);
            font-size:12px;
            font-weight:800;
            cursor:pointer;
            box-shadow:0 12px 28px rgba(121,230,170,.13);
            transition:.25s ease;
        }

        .primary:hover:not(:disabled) {
            transform:translateY(-2px);
            box-shadow:0 17px 34px rgba(121,230,170,.18);
        }

        .primary:disabled {
            opacity:.55;
            cursor:not-allowed;
        }

        .password-meter {
            display:grid;
            grid-template-columns:repeat(4,1fr);
            gap:5px;
            margin-top:9px;
        }

        .meter {
            height:4px;
            border-radius:999px;
            background:rgba(255,255,255,.095);
            transition:background .2s ease;
        }

        .password-rules {
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:7px;
            margin-top:10px;
        }

        .rule {
            display:flex;
            align-items:center;
            gap:7px;
            color:#607068;
            font-size:9px;
            transition:.2s ease;
        }

        .rule i { color:#53625b; }
        .rule.ok { color:#abebc2; }
        .rule.ok i { color:var(--green); }

        .terms {
            display:flex;
            align-items:flex-start;
            gap:9px;
            margin:14px 0 16px;
            color:#687870;
            font-size:9px;
            line-height:1.5;
        }

        .terms input {
            appearance:none;
            width:17px;
            height:17px;
            flex:0 0 17px;
            margin:0;
            border:1px solid rgba(255,255,255,.16);
            border-radius:5px;
            background:rgba(255,255,255,.035);
            cursor:pointer;
            transition:.2s ease;
        }

        .terms input:checked {
            border-color:var(--green);
            background:var(--green);
            box-shadow:inset 0 0 0 4px #10251e;
        }

        .terms a {
            color:#a9d7ba;
            font-weight:700;
        }

        .resend {
            margin-top:14px;
            text-align:center;
            color:#67766f;
            font-size:9px;
        }

        .resend button {
            padding:0;
            color:var(--green);
            background:transparent;
            font-weight:800;
            cursor:pointer;
        }

        .resend button:disabled {
            color:#53635b;
            cursor:not-allowed;
        }

        .card-footer {
            margin-top:22px;
            padding-top:17px;
            border-top:1px solid rgba(255,255,255,.08);
            text-align:center;
        }

        .card-footer p {
            margin:0;
            color:#64736d;
            font-size:10px;
        }

        .signin-link {
            display:inline-flex;
            align-items:center;
            gap:6px;
            margin-top:7px;
            color:var(--green);
            font-size:11px;
            font-weight:800;
            text-decoration:none;
            transition:.2s ease;
        }

        .signin-link:hover {
            color:#a2f4c5;
            transform:translateY(-1px);
        }

        .secure-note {
            display:flex;
            justify-content:center;
            align-items:center;
            gap:6px;
            margin-top:16px;
            color:#4e5e57;
            font-size:8px;
        }

        .secure-note i { color:var(--green); }

        footer {
            padding:0 0 19px;
            text-align:center;
            color:#4f5d57;
            font-size:9px;
        }

        .step {
            animation:stepIn .42s ease both;
        }

        .hidden { display:none !important; }

        .shake { animation:shake .32s ease; }

        @keyframes shellIn {
            from { opacity:0; transform:translateY(20px) scale(.985); }
            to { opacity:1; transform:translateY(0) scale(1); }
        }

        @keyframes fadeDown {
            from { opacity:0; transform:translateY(-10px); }
            to { opacity:1; transform:translateY(0); }
        }

        @keyframes fadeUp {
            from { opacity:0; transform:translateY(16px); }
            to { opacity:1; transform:translateY(0); }
        }

        @keyframes copyIn {
            from { opacity:0; transform:translateX(-20px); }
            to { opacity:1; transform:translateX(0); }
        }

        @keyframes stepIn {
            from { opacity:0; transform:translateX(16px); }
            to { opacity:1; transform:translateX(0); }
        }

        @keyframes shake {
            0%,100% { transform:translateX(0); }
            25% { transform:translateX(-5px); }
            75% { transform:translateX(5px); }
        }

        @media (max-width:960px) {
            .auth-shell {
                max-width:650px;
                grid-template-columns:1fr;
                min-height:0;
            }

            .visual {
                min-height:290px;
                padding:30px;
            }

            .visual-copy h1 {
                font-size:clamp(45px,9vw,68px);
            }

            .form-side {
                padding:35px 28px 37px;
            }
        }

        @media (max-width:600px) {
            .page { width:calc(100% - 14px); }

            .topbar { height:72px; }

            .brand-logo {
                width:37px;
                height:37px;
                border-radius:11px;
            }

            .brand-name { font-size:10px; }
            .security-label { display:none; }

            main { padding:7px 0 24px; }

            .auth-shell { border-radius:23px; }

            .visual {
                min-height:235px;
                padding:23px;
            }

            .visual::before {
                inset:12px;
                border-radius:17px;
            }

            .visual-copy h1 {
                max-width:315px;
                font-size:42px;
            }

            .visual-copy p {
                max-width:315px;
                margin-top:12px;
                font-size:10px;
                line-height:1.55;
            }

            .form-side {
                padding:28px 16px 29px;
            }

            .title { font-size:29px; }
            .subtitle { font-size:10px; }

            .input { height:52px; }

            .otp-grid { gap:5px; }

            .otp {
                height:51px;
                font-size:18px;
            }

            .password-rules {
                grid-template-columns:1fr;
            }
        }

        @media (max-width:360px) {
            .page { width:calc(100% - 10px); }

            .visual {
                min-height:214px;
                padding:20px;
            }

            .visual-copy h1 { font-size:36px; }

            .form-side {
                padding-left:12px;
                padding-right:12px;
            }

            .otp { height:47px; }
        }

        @media (prefers-reduced-motion:reduce) {
            *,
            *::before,
            *::after {
                animation-duration:.01ms !important;
                animation-iteration-count:1 !important;
                transition-duration:.01ms !important;
                scroll-behavior:auto !important;
            }
        }

        body.light {
            --white:#18251d;
            --muted:#5f7066;
            --line:rgba(20,48,31,.14);
            --line-strong:rgba(20,48,31,.22);
            --shadow:0 28px 70px rgba(28,55,38,.14);
            color:#18251d;
            background:
                radial-gradient(circle at 8% 7%,rgba(73,204,134,.16),transparent 27%),
                radial-gradient(circle at 92% 82%,rgba(255,154,98,.10),transparent 23%),
                linear-gradient(135deg,#f2f8f3,#e8f2eb 50%,#f7faf7);
        }

        body.light::before { opacity:.08; }
        body.light .auth-shell { border-color:rgba(20,48,31,.13); background:rgba(255,255,255,.56); }
        body.light .form-side { background:radial-gradient(circle at 100% 0%,rgba(73,204,134,.10),transparent 34%),linear-gradient(145deg,rgba(255,255,255,.98),rgba(242,248,243,.99)); }
        body.light .title,
        body.light .otp-info h3 { color:#18251d; }
        body.light .subtitle,
        body.light .field-label { color:#43544a; }
        body.light .field-label small { color:#74847a; }
        body.light .input,
        body.light .otp { color:#18251d; background:rgba(255,255,255,.88); border-color:rgba(20,48,31,.16); }
        body.light .input::placeholder { color:#87958c; }
        body.light .input:hover { border-color:rgba(20,48,31,.28); }
        body.light .input:focus { background:#fff; }
        body.light .divider { color:#75847b; }
        body.light .divider::before,
        body.light .divider::after { background:rgba(20,48,31,.12); }
        body.light .email-pill { color:#33473a; background:rgba(255,255,255,.8); border-color:rgba(20,48,31,.12); }
        body.light .secure-note,
        body.light .terms { color:#586960; }
        body.light .card-footer p { color:#66766c; }
        body.light .signin-link { color:#166b43; }
        body.light .progress span { background:rgba(20,48,31,.15); }
    </style>
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-theme2">
</head>

<body>
<div class="page">

    <header class="topbar">
        <a class="brand" href="index.php" aria-label="DunkHome Kicks home">
            <img class="brand-logo" src="image/logo.jpg" alt="DunkHome Kicks">
            <span class="brand-name">dunkhome_<span>kicks</span></span>
        </a>

        <div class="security-label">
            <i class="fa-solid fa-user-shield"></i>
            Administrator access
        </div>
    </header>

    <main>
        <section class="auth-shell" aria-label="Create administrator account">

            <aside class="visual">
                <div class="visual-tag">
                    <i class="fa-solid fa-crown"></i>
                    Admin portal
                </div>

                <div class="visual-copy">
                    <div class="eyebrow">Control. Protect. Manage.</div>

                    <h1>
                        Your store,
                        <em>your command.</em>
                    </h1>

                    <p>
                        Create a secure administrator account for managing
                        DunkHome Kicks operations, users, products and content.
                    </p>
                </div>

            </aside>

            <section class="form-side">
                <div class="form-card">

                    <div class="card-head">
                        <div>
                            <p class="kicker">Restricted access</p>
                            <h2 class="title">Create admin</h2>
                            <p class="subtitle">
                                Verify the administrator email and create a strong password.
                            </p>
                        </div>

                        <div class="progress" aria-label="Registration progress">
                            <span id="progress1" class="active"></span>
                            <span id="progress2"></span>
                            <span id="progress3"></span>
                        </div>
                    </div>

                    <?php if ($message !== ''): ?>
                        <div class="server-message <?= $messageType === 'success' ? 'success' : 'error' ?>" role="alert">
                            <i class="fa-solid <?= $messageType === 'success'
                                ? 'fa-circle-check'
                                : 'fa-circle-exclamation' ?>"></i>

                            <span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>

                    <form id="adminSignupForm" action="Admin/AdminSignUp.php" method="POST" novalidate>
                        <input type="hidden" name="action" value="register_admin">
                        <input type="hidden" name="email" id="verifiedAdminEmail">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

                        <!-- STEP 1 -->
                        <div id="step1" class="step">
                            <div class="field">
                                <label class="field-label" for="emailField">
                                    Admin email
                                    <small>Required</small>
                                </label>

                                <div class="input-wrap">
                                    <i class="fa-regular fa-envelope input-icon"></i>

                                    <input
                                        class="input"
                                        type="email"
                                        id="emailField"
                                        name="email_input"
                                        placeholder="admin@example.com"
                                        autocomplete="email"
                                        inputmode="email"
                                        required
                                    >

                                    <button
                                        class="send-button"
                                        id="sendOtpBtn"
                                        type="button"
                                        aria-label="Send admin OTP"
                                    >
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </button>
                                </div>

                                <div id="emailMessage" class="message error"></div>
                            </div>

                            <div class="divider">administrator verification</div>

                            <div class="secure-note">
                                <i class="fa-solid fa-shield-halved"></i>
                                A one-time verification code will be sent to this address.
                            </div>
                        </div>

                        <!-- STEP 2 -->
                        <div id="step2" class="step hidden">
                            <div class="otp-info">
                                <div class="otp-icon">
                                    <i class="fa-solid fa-user-shield"></i>
                                </div>

                                <h3>Verify admin email</h3>

                                <p>
                                    Enter the 6-digit code sent to your administrator email.
                                    The code expires in 10 minutes.
                                </p>

                                <div class="email-pill">
                                    <i class="fa-regular fa-envelope"></i>
                                    <span id="emailPreview">admin@example.com</span>
                                </div>
                            </div>

                            <div class="otp-grid" id="otpGrid">
                                <?php for ($i = 0; $i < 6; $i++): ?>
                                    <input
                                        class="otp"
                                        type="text"
                                        maxlength="1"
                                        inputmode="numeric"
                                        autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
                                        aria-label="Admin OTP digit <?= $i + 1 ?>"
                                    >
                                <?php endfor; ?>
                            </div>

                            <div id="otpMessage" class="message error"></div>

                            <button type="button" id="verifyOtpBtn" class="primary">
                                <i class="fa-solid fa-check"></i>
                                Verify &amp; continue
                            </button>

                            <div class="resend">
                                Didn't receive the code?
                                <button type="button" id="resendOtpBtn" disabled>
                                    Resend <span id="resendTimer">(60s)</span>
                                </button>
                            </div>
                        </div>

                        <!-- STEP 3 -->
                        <div id="step3" class="step hidden">
                            <div class="field">
                                <label class="field-label" for="passwordField">
                                    Admin password
                                    <small>Strong is required</small>
                                </label>

                                <div class="input-wrap">
                                    <i class="fa-solid fa-lock input-icon"></i>

                                    <input
                                        class="input"
                                        type="password"
                                        id="passwordField"
                                        name="password"
                                        placeholder="Create a strong password"
                                        autocomplete="new-password"
                                        required
                                    >

                                    <button
                                        type="button"
                                        class="icon-button"
                                        data-password-target="passwordField"
                                        aria-label="Show password"
                                    >
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>

                                <div class="password-meter" aria-hidden="true">
                                    <span class="meter"></span>
                                    <span class="meter"></span>
                                    <span class="meter"></span>
                                    <span class="meter"></span>
                                </div>

                                <div class="password-rules">
                                    <div class="rule" data-rule="length">
                                        <i class="fa-regular fa-circle"></i> 8+ characters
                                    </div>

                                    <div class="rule" data-rule="upper">
                                        <i class="fa-regular fa-circle"></i> Uppercase letter
                                    </div>

                                    <div class="rule" data-rule="number">
                                        <i class="fa-regular fa-circle"></i> One number
                                    </div>

                                    <div class="rule" data-rule="special">
                                        <i class="fa-regular fa-circle"></i> Special character
                                    </div>
                                </div>

                                <div id="passwordMessage" class="message error"></div>
                            </div>

                            <div class="field">
                                <label class="field-label" for="confirmPasswordField">
                                    Confirm password
                                    <small>Must match</small>
                                </label>

                                <div class="input-wrap">
                                    <i class="fa-solid fa-lock input-icon"></i>

                                    <input
                                        class="input"
                                        type="password"
                                        id="confirmPasswordField"
                                        name="confirm_password"
                                        placeholder="Repeat your password"
                                        autocomplete="new-password"
                                        required
                                    >

                                    <button
                                        type="button"
                                        class="icon-button"
                                        data-password-target="confirmPasswordField"
                                        aria-label="Show password"
                                    >
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>

                                <div id="confirmMessage" class="message error"></div>
                            </div>

                            <label class="terms" for="termsCheck">
                                <input type="checkbox" id="termsCheck" required>

                                <span>
                                    I confirm that I am authorized to create and manage
                                    an administrator account for this application.
                                </span>
                            </label>

                            <div id="formMessage" class="message error"></div>

                            <button type="submit" id="createAdminBtn" class="primary">
                                <i class="fa-solid fa-user-shield"></i>
                                Create admin account
                            </button>

                            <div class="secure-note">
                                <i class="fa-solid fa-lock"></i>
                                Admin credentials are securely hashed before storage.
                            </div>
                        </div>
                    </form>

                    <div class="card-footer">
                        <p>Already have an administrator account?</p>

                        <a class="signin-link" href="Admin/AdminSignIn.php">
                            Admin sign in
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                </div>
            </section>

        </section>
    </main>

    <footer>
        &copy; <?= date('Y') ?> DunkHome Kicks. Administrator access.
    </footer>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const step3 = document.getElementById('step3');

    const progress = [
        document.getElementById('progress1'),
        document.getElementById('progress2'),
        document.getElementById('progress3')
    ];

    const form = document.getElementById('adminSignupForm');
    const emailField = document.getElementById('emailField');
    const verifiedEmail = document.getElementById('verifiedAdminEmail');
    const emailPreview = document.getElementById('emailPreview');

    const sendOtpBtn = document.getElementById('sendOtpBtn');
    const verifyOtpBtn = document.getElementById('verifyOtpBtn');
    const resendOtpBtn = document.getElementById('resendOtpBtn');
    const resendTimer = document.getElementById('resendTimer');

    const emailMessage = document.getElementById('emailMessage');
    const otpMessage = document.getElementById('otpMessage');
    const passwordMessage = document.getElementById('passwordMessage');
    const confirmMessage = document.getElementById('confirmMessage');
    const formMessage = document.getElementById('formMessage');

    const passwordField = document.getElementById('passwordField');
    const confirmPasswordField = document.getElementById('confirmPasswordField');
    const termsCheck = document.getElementById('termsCheck');

    const otpInputs = [...document.querySelectorAll('.otp')];
    const meterBars = [...document.querySelectorAll('.meter')];

    let resendInterval = null;

    const passwordRules = {
        length: value => value.length >= 8,
        upper: value => /[A-Z]/.test(value),
        number: value => /\d/.test(value),
        special: value => /[^A-Za-z0-9]/.test(value)
    };

    function validEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function showMessage(element, text, type = 'error') {
        element.textContent = text;
        element.classList.remove('error', 'success');
        element.classList.add(type, 'visible');
    }

    function hideMessage(element) {
        element.textContent = '';
        element.classList.remove('visible', 'error', 'success');
    }

    function invalid(input, value = true) {
        input.classList.toggle('invalid', value);
    }

    function setProgress(step) {
        progress.forEach((dot, index) => {
            dot.classList.toggle('active', index === step - 1);
        });
    }

    function switchStep(current, next, stepNumber) {
        current.classList.add('hidden');
        next.classList.remove('hidden');
        setProgress(stepNumber);

        next.style.animation = 'none';
        void next.offsetWidth;
        next.style.animation = '';

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function buttonLoading(button, loading, iconClass, label) {
        button.disabled = loading;

        if (loading) {
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        } else {
            button.innerHTML = `<i class="${iconClass}"></i> ${label}`;
        }
    }

    async function postAction(action, payload) {
        const body = new FormData();
        body.append('action', action);
        body.append('csrf_token', document.querySelector('#adminSignupForm [name="csrf_token"]').value);

        Object.entries(payload).forEach(([key, value]) => {
            body.append(key, value);
        });

        const response = await fetch('Admin/AdminSignUp.php', {
            method: 'POST',
            body,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
    }

    function startResendCountdown() {
        clearInterval(resendInterval);

        let seconds = 60;
        resendOtpBtn.disabled = true;
        resendTimer.textContent = `(${seconds}s)`;

        resendInterval = setInterval(() => {
            seconds--;
            resendTimer.textContent = seconds > 0 ? `(${seconds}s)` : '';

            if (seconds <= 0) {
                clearInterval(resendInterval);
                resendOtpBtn.disabled = false;
            }
        }, 1000);
    }

    async function sendOtp() {
        const email = emailField.value.trim();

        hideMessage(emailMessage);
        invalid(emailField, false);

        if (!validEmail(email)) {
            showMessage(emailMessage, 'Please enter a valid administrator email address.');
            invalid(emailField);
            emailField.focus();
            return;
        }

        buttonLoading(sendOtpBtn, true);

        try {
            const data = await postAction('send_admin_otp', {
                email
            });

            if (data.status !== 'success') {
                throw new Error(data.message || 'Unable to send admin verification code.');
            }

            verifiedEmail.value = email;
            emailPreview.textContent = email;

            otpInputs.forEach(input => {
                input.value = '';
                input.classList.remove('valid', 'invalid');
            });

            switchStep(step1, step2, 2);
            startResendCountdown();

            setTimeout(() => otpInputs[0].focus(), 350);
        } catch (error) {
            showMessage(
                emailMessage,
                error.message || 'Unable to send verification code.'
            );
            invalid(emailField);
        } finally {
            buttonLoading(
                sendOtpBtn,
                false,
                'fa-solid fa-arrow-right',
                ''
            );
        }
    }

    sendOtpBtn.addEventListener('click', sendOtp);

    emailField.addEventListener('input', () => {
        hideMessage(emailMessage);
        invalid(emailField, false);

        const email = emailField.value.trim();

        if (email && !validEmail(email)) {
            showMessage(emailMessage, 'Enter a valid email address.');
            invalid(emailField);
        }
    });

    otpInputs.forEach((input, index) => {
        input.addEventListener('input', event => {
            const value = event.target.value.replace(/\D/g, '').slice(-1);

            event.target.value = value;
            input.classList.toggle('valid', Boolean(value));
            input.classList.remove('invalid');

            hideMessage(otpMessage);

            if (value && index < otpInputs.length - 1) {
                otpInputs[index + 1].focus();
            }
        });

        input.addEventListener('keydown', event => {
            if (
                event.key === 'Backspace'
                && !input.value
                && index > 0
            ) {
                otpInputs[index - 1].focus();
            }

            if (
                event.key === 'ArrowLeft'
                && index > 0
            ) {
                otpInputs[index - 1].focus();
            }

            if (
                event.key === 'ArrowRight'
                && index < otpInputs.length - 1
            ) {
                otpInputs[index + 1].focus();
            }
        });

        input.addEventListener('paste', event => {
            const code = (event.clipboardData || window.clipboardData)
                .getData('text')
                .replace(/\D/g, '')
                .slice(0, 6);

            if (!code) return;

            event.preventDefault();

            code.split('').forEach((digit, digitIndex) => {
                if (!otpInputs[digitIndex]) return;

                otpInputs[digitIndex].value = digit;
                otpInputs[digitIndex].classList.add('valid');
                otpInputs[digitIndex].classList.remove('invalid');
            });

            otpInputs[Math.min(code.length, 6) - 1].focus();
            hideMessage(otpMessage);
        });
    });

    async function verifyOtp() {
        const otp = otpInputs.map(input => input.value).join('');
        const email = verifiedEmail.value;

        hideMessage(otpMessage);

        if (otp.length !== 6) {
            otpInputs.forEach(input => input.classList.add('invalid'));
            showMessage(otpMessage, 'Please enter the complete 6-digit code.');

            const grid = document.getElementById('otpGrid');
            grid.classList.remove('shake');
            void grid.offsetWidth;
            grid.classList.add('shake');

            setTimeout(() => grid.classList.remove('shake'), 350);
            return;
        }

        buttonLoading(
            verifyOtpBtn,
            true
        );

        try {
            const data = await postAction('verify_admin_otp', {
                email,
                otp
            });

            if (data.status !== 'success') {
                throw new Error(data.message || 'Verification failed.');
            }

            otpInputs.forEach(input => input.classList.remove('invalid'));

            switchStep(step2, step3, 3);
            setTimeout(() => passwordField.focus(), 350);
        } catch (error) {
            otpInputs.forEach(input => input.classList.add('invalid'));

            showMessage(
                otpMessage,
                error.message || 'Verification failed. Please try again.'
            );
        } finally {
            buttonLoading(
                verifyOtpBtn,
                false,
                'fa-solid fa-check',
                'Verify & continue'
            );
        }
    }

    verifyOtpBtn.addEventListener('click', verifyOtp);

    resendOtpBtn.addEventListener('click', async () => {
        const email = verifiedEmail.value;

        if (!validEmail(email)) return;

        resendOtpBtn.disabled = true;

        const previousText = resendOtpBtn.innerHTML;
        resendOtpBtn.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i>';

        try {
            const data = await postAction('send_admin_otp', {
                email
            });

            if (data.status !== 'success') {
                throw new Error(data.message || 'Could not resend the code.');
            }

            otpInputs.forEach(input => {
                input.value = '';
                input.classList.remove('valid', 'invalid');
            });

            hideMessage(otpMessage);
            startResendCountdown();
            otpInputs[0].focus();
        } catch (error) {
            showMessage(
                otpMessage,
                error.message || 'Could not resend the code.'
            );
            resendOtpBtn.disabled = false;
        } finally {
            resendOtpBtn.innerHTML = previousText;
        }
    });

    document.querySelectorAll('[data-password-target]').forEach(button => {
        button.addEventListener('click', () => {
            const field = document.getElementById(
                button.dataset.passwordTarget
            );

            const icon = button.querySelector('i');
            const visible = field.type === 'text';

            field.type = visible ? 'password' : 'text';

            icon.className = visible
                ? 'fa-regular fa-eye'
                : 'fa-regular fa-eye-slash';

            button.setAttribute(
                'aria-label',
                visible ? 'Show password' : 'Hide password'
            );

            field.focus();
        });
    });

    function updatePasswordUI() {
        const password = passwordField.value;

        const results = Object.fromEntries(
            Object.entries(passwordRules).map(
                ([name, check]) => [name, check(password)]
            )
        );

        Object.entries(results).forEach(([name, valid]) => {
            const rule = document.querySelector(
                `[data-rule="${name}"]`
            );

            const icon = rule.querySelector('i');

            rule.classList.toggle('ok', valid);

            icon.className = valid
                ? 'fa-solid fa-circle-check'
                : 'fa-regular fa-circle';
        });

        const score = Object.values(results).filter(Boolean).length;

        meterBars.forEach((bar, index) => {
            if (index >= score) {
                bar.style.background = 'rgba(255,255,255,.095)';
            } else {
                bar.style.background =
                    score <= 1
                        ? '#ff7b7b'
                        : score === 2
                            ? '#ffbd69'
                            : '#79e6aa';
            }
        });

        if (!password) {
            hideMessage(passwordMessage);
            invalid(passwordField, false);
        } else if (score < 4) {
            showMessage(
                passwordMessage,
                'Use 8+ characters, uppercase, number and special character.'
            );
            invalid(passwordField);
        } else {
            hideMessage(passwordMessage);
            invalid(passwordField, false);
        }

        updateConfirmPassword();
    }

    function updateConfirmPassword() {
        const password = passwordField.value;
        const confirm = confirmPasswordField.value;

        hideMessage(confirmMessage);
        invalid(confirmPasswordField, false);

        if (!confirm) return;

        if (password !== confirm) {
            showMessage(confirmMessage, 'Passwords do not match.');
            invalid(confirmPasswordField);
        } else {
            showMessage(confirmMessage, 'Passwords match.', 'success');
        }
    }

    passwordField.addEventListener('input', updatePasswordUI);
    confirmPasswordField.addEventListener(
        'input',
        updateConfirmPassword
    );

    form.addEventListener('submit', event => {
        const password = passwordField.value;
        const confirm = confirmPasswordField.value;

        const strong = Object.values(passwordRules)
            .every(check => check(password));

        hideMessage(formMessage);
        updatePasswordUI();
        updateConfirmPassword();

        if (!strong) {
            event.preventDefault();

            showMessage(
                passwordMessage,
                'Password must meet all four requirements.'
            );

            invalid(passwordField);
            passwordField.focus();
            return;
        }

        if (password !== confirm) {
            event.preventDefault();

            showMessage(
                confirmMessage,
                'Passwords do not match.'
            );

            invalid(confirmPasswordField);
            confirmPasswordField.focus();
            return;
        }

        if (!termsCheck.checked) {
            event.preventDefault();

            showMessage(
                formMessage,
                'Please confirm that you are authorized to create this admin account.'
            );

            termsCheck.focus();
            return;
        }

        const button = document.getElementById('createAdminBtn');

        button.disabled = true;
        button.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> Creating...';
    });

    emailField.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            sendOtp();
        }
    });
});
</script>
    <script src="assets/dunkhome-ui.js?v=20261001-loader4" defer></script>
</body>
</html>

