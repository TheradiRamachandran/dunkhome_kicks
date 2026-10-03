<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Admin table
|--------------------------------------------------------------------------
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

/*
|--------------------------------------------------------------------------
| Admin sign in
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'admin_login'
) {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $message = 'Your sign-in form expired. Refresh the page and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid administrator email address.';
    } elseif ($password === '') {
        $message = 'Please enter your administrator password.';
    } else {
        $stmt = $conn->prepare(
            'SELECT id, email, password FROM admins
             WHERE LOWER(email) = LOWER(?)
             LIMIT 1'
        );

        if (!$stmt) {
            $message =
                'Unable to process administrator sign in right now. Please try again.';
        } else {
            $stmt->bind_param('s', $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $admin = $result ? $result->fetch_assoc() : null;

            $stmt->close();

            if (
                !$admin
                || !password_verify(
                    $password,
                    (string) $admin['password']
                )
            ) {
                $message =
                    'The administrator email or password is incorrect.';
            } else {
                session_regenerate_id(true);
                unset($_SESSION['csrf']);

                $_SESSION['admin_id'] = (int) $admin['id'];
                $_SESSION['admin_email'] = (string) $admin['email'];

                header('Location: ' . appUrl('Admin/AdminDashboard.php'));
                exit;
            }
        }
    }
}

if (
    $message === ''
    && !empty($_SESSION['admin_signup_success'])
) {
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
    <meta name="description" content="Administrator sign in for DunkHome Kicks.">

    <title>Admin Sign In | DunkHome Kicks</title>

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
            --white:#f8faf8;
            --muted:#9baba3;
            --line:rgba(255,255,255,.10);
            --line-strong:rgba(255,255,255,.17);
            --green:#79e6aa;
            --orange:#ff9a62;
            --danger:#ff7b7b;
            --shadow:0 35px 100px rgba(0,0,0,.44);
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
                radial-gradient(circle at 8% 7%,rgba(121,230,170,.13),transparent 27%),
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

        a,button,input { font:inherit; }
        a { color:inherit; }
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

        .secure-label {
            display:flex;
            align-items:center;
            gap:7px;
            color:#64756d;
            font-size:9px;
            font-weight:700;
            letter-spacing:.12em;
            text-transform:uppercase;
        }

        .secure-label i { color:var(--green); }

        main {
            flex:1;
            display:grid;
            place-items:center;
            padding:18px 0 42px;
        }

        .auth-shell {
            width:100%;
            min-height:680px;
            display:grid;
            grid-template-columns:minmax(0,1.05fr) minmax(420px,.95fr);
            overflow:hidden;
            border:1px solid var(--line);
            border-radius:30px;
            background:rgba(255,255,255,.025);
            box-shadow:var(--shadow);
            backdrop-filter:blur(22px);
            animation:shellIn .72s cubic-bezier(.2,.75,.2,1) both;
        }

        .visual {
            position:relative;
            min-height:680px;
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
            right:-120px;
            top:-120px;
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
            font-size:clamp(47px,5vw,77px);
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
            padding:42px 44px 32px;
            background:
                radial-gradient(circle at 100% 0%,rgba(121,230,170,.10),transparent 34%),
                linear-gradient(145deg,rgba(14,29,24,.98),rgba(8,18,15,.99));
        }

        .form-card {
            width:100%;
            max-width:465px;
            animation:fadeUp .8s .12s ease both;
        }

        .kicker {
            margin:0 0 8px;
            color:var(--green);
            font-size:9px;
            font-weight:800;
            letter-spacing:.20em;
            text-transform:uppercase;
        }

        .title {
            margin:0;
            font-family:"Playfair Display",serif;
            font-size:35px;
            line-height:1;
        }

        .subtitle {
            max-width:360px;
            margin:10px 0 24px;
            color:var(--muted);
            font-size:11px;
            line-height:1.6;
        }

        .server-message {
            display:flex;
            align-items:flex-start;
            gap:9px;
            margin-bottom:17px;
            padding:11px 12px;
            border-radius:13px;
            font-size:10px;
            line-height:1.5;
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
            text-transform:none;
        }

        .input-wrap { position:relative; }

        .input-icon {
            position:absolute;
            left:15px;
            top:50%;
            transform:translateY(-50%);
            color:#5d6e67;
            transition:.2s ease;
            z-index:1;
            pointer-events:none;
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

        .input-wrap:focus-within .input-icon {
            color:var(--green);
        }

        .input.invalid {
            border-color:rgba(255,123,123,.68);
            box-shadow:0 0 0 4px rgba(255,123,123,.05);
        }

        .icon-button {
            position:absolute;
            right:8px;
            top:50%;
            width:36px;
            height:36px;
            display:grid;
            place-items:center;
            transform:translateY(-50%);
            border-radius:10px;
            color:#6f8078;
            background:transparent;
            cursor:pointer;
            transition:.22s ease;
        }

        .icon-button:hover {
            color:var(--green);
            background:rgba(255,255,255,.06);
        }

        .message {
            display:none;
            margin-top:7px;
            padding:8px 10px;
            border-radius:10px;
            font-size:10px;
            line-height:1.45;
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

        .login-options {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:15px;
            margin:5px 0 20px;
        }

        .remember {
            display:inline-flex;
            align-items:center;
            gap:8px;
            color:#697970;
            font-size:10px;
            cursor:pointer;
        }

        .remember input {
            appearance:none;
            width:17px;
            height:17px;
            margin:0;
            border:1px solid rgba(255,255,255,.16);
            border-radius:5px;
            background:rgba(255,255,255,.035);
            cursor:pointer;
        }

        .remember input:checked {
            border-color:var(--green);
            background:var(--green);
            box-shadow:inset 0 0 0 4px #10251e;
        }

        .forgot {
            color:#8ebaa0;
            font-size:10px;
            font-weight:700;
            text-decoration:none;
            transition:.2s ease;
        }

        .forgot:hover {
            color:var(--green);
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
            box-shadow:0 17px 34px rgba(121,230,170,.19);
        }

        .primary:disabled {
            opacity:.58;
            cursor:not-allowed;
        }

        .trust-row {
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:7px;
            margin-top:18px;
        }

        .trust {
            padding:9px 7px;
            text-align:center;
            border:1px solid rgba(255,255,255,.065);
            border-radius:11px;
            background:rgba(255,255,255,.022);
        }

        .trust i {
            display:block;
            margin-bottom:5px;
            color:var(--green);
            font-size:11px;
        }

        .trust span {
            color:#5d6d66;
            font-size:8px;
        }

        .card-footer {
            margin-top:24px;
            padding-top:18px;
            border-top:1px solid rgba(255,255,255,.08);
            text-align:center;
        }

        .card-footer p {
            margin:0;
            color:#64736d;
            font-size:10px;
        }

        .signup-link {
            display:inline-flex;
            align-items:center;
            gap:7px;
            margin-top:7px;
            color:var(--green);
            font-size:11px;
            font-weight:800;
            text-decoration:none;
            transition:.2s ease;
        }

        .signup-link:hover {
            color:#a5f2c6;
            transform:translateY(-1px);
        }

        .secure-note {
            display:flex;
            align-items:center;
            justify-content:center;
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
            .secure-label { display:none; }

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

            .login-options {
                align-items:flex-start;
                flex-direction:column;
                gap:9px;
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
        }

        @media (prefers-reduced-motion:reduce) {
            *,
            *::before,
            *::after {
                animation-duration:.01ms !important;
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
        body.light .title { color:#18251d; }
        body.light .subtitle,
        body.light .field-label { color:#43544a; }
        body.light .field-label small { color:#74847a; }
        body.light .input { color:#18251d; background:rgba(255,255,255,.88); border-color:rgba(20,48,31,.16); }
        body.light .input::placeholder { color:#87958c; }
        body.light .input:hover { border-color:rgba(20,48,31,.28); }
        body.light .input:focus { background:#fff; }
        body.light .remember { color:#586960; }
        body.light .remember input { border-color:rgba(20,48,31,.2); background:rgba(255,255,255,.8); }
        body.light .remember input:checked { box-shadow:inset 0 0 0 4px #e1f4e8; }
        body.light .trust { border-color:rgba(20,48,31,.1); background:rgba(255,255,255,.55); }
        body.light .trust span,
        body.light .card-footer p,
        body.light footer { color:#66766c; }
        body.light .card-footer { border-color:rgba(20,48,31,.12); }
        body.light .secure-note { color:#65756b; }
        body.light .signup-link { color:#166b43; }
        body.light .forgot { color:#276c48; }
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

        <div class="secure-label">
            <i class="fa-solid fa-user-shield"></i>
            Administrator sign in
        </div>
    </header>

    <main>
        <section class="auth-shell" aria-label="Administrator sign in">

            <aside class="visual">
                <div class="visual-tag">
                    <i class="fa-solid fa-shield-halved"></i>
                    Protected portal
                </div>

                <div class="visual-copy">
                    <div class="eyebrow">Control the experience</div>

                    <h1>
                        Welcome to your
                        <em>command center.</em>
                    </h1>

                    <p>
                        Sign in to manage your DunkHome Kicks administration
                        securely and continue where you left off.
                    </p>
                </div>

            </aside>

            <section class="form-side">
                <div class="form-card">

                    <div class="kicker">Restricted access</div>

                    <h2 class="title">Admin sign in</h2>

                    <p class="subtitle">
                        Use your administrator credentials to enter the control panel.
                    </p>

                    <?php if ($message !== ''): ?>
                        <div
                            class="server-message <?= $messageType === 'success' ? 'success' : 'error' ?>"
                            role="alert"
                        >
                            <i class="fa-solid <?= $messageType === 'success'
                                ? 'fa-circle-check'
                                : 'fa-circle-exclamation' ?>"></i>

                            <span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>

                    <form id="adminSigninForm" action="Admin/AdminSignIn.php" method="POST" novalidate>
                        <input type="hidden" name="action" value="admin_login">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

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
                                    name="email"
                                    placeholder="admin@example.com"
                                    autocomplete="username"
                                    inputmode="email"
                                    required
                                >
                            </div>

                            <div id="emailMessage" class="message error" aria-live="polite"></div>
                        </div>

                        <div class="field">
                            <label class="field-label" for="passwordField">
                                Password
                                <small>Required</small>
                            </label>

                            <div class="input-wrap">
                                <i class="fa-solid fa-lock input-icon"></i>

                                <input
                                    class="input"
                                    type="password"
                                    id="passwordField"
                                    name="password"
                                    placeholder="Enter your admin password"
                                    autocomplete="current-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="icon-button"
                                    id="togglePassword"
                                    aria-label="Show password"
                                >
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>

                            <div id="passwordMessage" class="message error" aria-live="polite"></div>
                        </div>

                        <div class="login-options">
                            <label class="remember" for="rememberMe">
                                <input
                                    type="checkbox"
                                    id="rememberMe"
                                    name="remember"
                                    value="1"
                                >
                                <span>Remember this device</span>
                            </label>

                            <a class="forgot" href="<?= h(appUrl('Admin/AdminForgotPassword.php')) ?>">
                                Forgot password?
                            </a>
                        </div>

                        <div id="formMessage" class="message error" aria-live="polite"></div>

                        <button type="submit" id="signinButton" class="primary">
                            <i class="fa-solid fa-arrow-right-to-bracket"></i>
                            Admin sign in
                        </button>
                    </form>

                    <div class="trust-row">
                        <div class="trust">
                            <i class="fa-solid fa-lock"></i>
                            <span>Encrypted</span>
                        </div>

                        <div class="trust">
                            <i class="fa-solid fa-user-shield"></i>
                            <span>Restricted</span>
                        </div>

                        <div class="trust">
                            <i class="fa-solid fa-shield-halved"></i>
                            <span>Protected</span>
                        </div>
                    </div>

                    <div class="card-footer">
                        <p>Need to create an administrator account?</p>

                        <a class="signup-link" href="Admin/AdminSignUp.php">
                            Admin sign up
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    <div class="secure-note">
                        <i class="fa-solid fa-lock"></i>
                        Admin credentials are securely verified.
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
    const form = document.getElementById('adminSigninForm');
    const emailField = document.getElementById('emailField');
    const passwordField = document.getElementById('passwordField');
    const togglePassword = document.getElementById('togglePassword');
    const signinButton = document.getElementById('signinButton');

    const emailMessage = document.getElementById('emailMessage');
    const passwordMessage = document.getElementById('passwordMessage');
    const formMessage = document.getElementById('formMessage');

    function validEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function showMessage(element, text) {
        element.textContent = text;
        element.classList.add('visible');
    }

    function hideMessage(element) {
        element.textContent = '';
        element.classList.remove('visible');
    }

    function invalid(input, value = true) {
        input.classList.toggle('invalid', value);
    }

    function shake(element) {
        element.classList.remove('shake');
        void element.offsetWidth;
        element.classList.add('shake');

        setTimeout(() => {
            element.classList.remove('shake');
        }, 350);
    }

    emailField.addEventListener('input', () => {
        hideMessage(emailMessage);
        hideMessage(formMessage);
        invalid(emailField, false);

        const email = emailField.value.trim();

        if (email && !validEmail(email)) {
            showMessage(emailMessage, 'Enter a valid administrator email address.');
            invalid(emailField);
        }
    });

    passwordField.addEventListener('input', () => {
        hideMessage(passwordMessage);
        hideMessage(formMessage);
        invalid(passwordField, false);
    });

    togglePassword.addEventListener('click', () => {
        const hidden = passwordField.type === 'password';
        const icon = togglePassword.querySelector('i');

        passwordField.type = hidden ? 'text' : 'password';

        icon.className = hidden
            ? 'fa-regular fa-eye-slash'
            : 'fa-regular fa-eye';

        togglePassword.setAttribute(
            'aria-label',
            hidden ? 'Hide password' : 'Show password'
        );

        passwordField.focus();
    });

    form.addEventListener('submit', event => {
        hideMessage(formMessage);
        hideMessage(emailMessage);
        hideMessage(passwordMessage);

        invalid(emailField, false);
        invalid(passwordField, false);

        const email = emailField.value.trim();
        const password = passwordField.value;

        let valid = true;

        if (!email) {
            showMessage(emailMessage, 'Please enter your administrator email.');
            invalid(emailField);
            valid = false;
        } else if (!validEmail(email)) {
            showMessage(emailMessage, 'Enter a valid administrator email address.');
            invalid(emailField);
            valid = false;
        }

        if (!password) {
            showMessage(passwordMessage, 'Please enter your administrator password.');
            invalid(passwordField);
            valid = false;
        }

        if (!valid) {
            event.preventDefault();
            shake(form);

            if (!email || !validEmail(email)) {
                emailField.focus();
            } else {
                passwordField.focus();
            }

            return;
        }

        signinButton.disabled = true;
        signinButton.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> Signing in...';
    });

    emailField.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();

            if (validEmail(emailField.value.trim())) {
                passwordField.focus();
            } else {
                showMessage(emailMessage, 'Enter a valid administrator email address.');
                invalid(emailField);
            }
        }
    });
});
</script>
    <script src="assets/dunkhome-ui.js?v=20261001-loader4" defer></script>
</body>
</html>

