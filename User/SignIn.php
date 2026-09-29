<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Already authenticated
|--------------------------------------------------------------------------
*/
if (isset($_SESSION['user_id'])) {
    header('Location: ' . appUrl('index.php'));
    exit;
}

$message = '';
$messageType = 'error';

/*
|--------------------------------------------------------------------------
| Login helpers
|--------------------------------------------------------------------------
*/
function normalizeEmail(string $email): string
{
    return strtolower(trim((string) filter_var($email, FILTER_SANITIZE_EMAIL)));
}

/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $email = normalizeEmail($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $message = 'Your sign-in form expired. Refresh the page and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
    } elseif ($password === '') {
        $message = 'Please enter your password.';
    } else {
        $stmt = $conn->prepare(
            'SELECT id, email, password FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1'
        );

        if (!$stmt) {
            $message = 'Unable to process your sign in right now. Please try again.';
        } else {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if (!$user || !password_verify($password, (string) $user['password'])) {
                $message = 'The email or password you entered is incorrect.';
            } else {
                /*
                 * Prevent session fixation after successful authentication.
                 */
                session_regenerate_id(true);
                unset($_SESSION['csrf']);

                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['user_email'] = (string) $user['email'];

                /*
                 * Optional compatibility values for existing pages.
                 */
                $_SESSION['email'] = (string) $user['email'];

                header('Location: ' . appUrl('index.php'));
                exit;
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Successful signup message
|--------------------------------------------------------------------------
*/
if ($message === '' && !empty($_SESSION['signup_success'])) {
    $message = (string) $_SESSION['signup_success'];
    $messageType = 'success';
    unset($_SESSION['signup_success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head><base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta name="theme-color" content="#07100d">
    <meta
        name="description"
        content="Sign in to your DunkHome Kicks account."
    >

    <title>Sign In | DunkHome Kicks</title>

    <link rel="icon" type="image/jpeg" href="image/logo.jpeg">

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
            --bg: #07100d;
            --bg-soft: #0b1713;
            --panel: rgba(13, 27, 23, .86);
            --white: #f8faf8;
            --muted: #9baba3;
            --muted-2: #697870;
            --line: rgba(255,255,255,.10);
            --line-strong: rgba(255,255,255,.17);
            --green: #79e6aa;
            --green-light: #92efbb;
            --green-2: #47cc86;
            --orange: #ff9a62;
            --danger: #ff7b7b;
            --danger-bg: rgba(255,123,123,.08);
            --success: #8ee9b0;
            --success-bg: rgba(121,230,170,.08);
            --shadow: 0 35px 100px rgba(0,0,0,.44);
            --radius-xl: 30px;
            --radius-lg: 20px;
            --radius-md: 15px;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
            font-family: "DM Sans", sans-serif;
            color: var(--white);
            background:
                radial-gradient(circle at 8% 7%, rgba(121,230,170,.13), transparent 27%),
                radial-gradient(circle at 92% 82%, rgba(255,154,98,.09), transparent 23%),
                linear-gradient(135deg, #06100d 0%, #0a1713 49%, #06100d 100%);
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: .22;
            background-image:
                linear-gradient(rgba(255,255,255,.018) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.018) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(to bottom, black, transparent 88%);
        }

        body::after {
            content: "";
            position: fixed;
            width: 340px;
            height: 340px;
            left: -180px;
            bottom: -180px;
            border-radius: 50%;
            background: rgba(121,230,170,.07);
            filter: blur(55px);
            pointer-events: none;
        }

        a {
            color: inherit;
        }

        button,
        input {
            font: inherit;
        }

        button {
            border: 0;
        }

        .page {
            width: min(1260px, calc(100% - 34px));
            min-height: 100vh;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
        }

        /* ------------------------------------------------------------------
           Header
        ------------------------------------------------------------------ */
        .topbar {
            height: 88px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            animation: fadeDown .55s ease both;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .brand-logo {
            width: 43px;
            height: 43px;
            border-radius: 13px;
            object-fit: cover;
            border: 1px solid var(--line-strong);
            box-shadow: 0 12px 28px rgba(0,0,0,.25);
        }

        .brand-name {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .16em;
            text-transform: uppercase;
        }

        .brand-name span {
            color: var(--green);
        }

        .secure-label {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #66766f;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .secure-label i {
            color: var(--green);
            font-size: 10px;
        }

        /* ------------------------------------------------------------------
           Main shell
        ------------------------------------------------------------------ */
        main {
            flex: 1;
            display: grid;
            place-items: center;
            padding: 18px 0 42px;
        }

        .auth-shell {
            width: 100%;
            min-height: 680px;
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(420px, .95fr);
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: var(--radius-xl);
            background: rgba(255,255,255,.025);
            box-shadow: var(--shadow);
            backdrop-filter: blur(22px);
            animation: shellIn .72s cubic-bezier(.2,.75,.2,1) both;
        }

        /* ------------------------------------------------------------------
           Image / brand panel
        ------------------------------------------------------------------ */
        .visual {
            position: relative;
            min-height: 680px;
            padding: 45px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            isolation: isolate;
            background:
                linear-gradient(
                    180deg,
                    rgba(4,13,11,.03) 0%,
                    rgba(4,13,11,.20) 33%,
                    rgba(4,13,11,.95) 100%
                ),
                url("image/background.jpg") center/cover no-repeat;
        }

        .visual::before {
            content: "";
            position: absolute;
            inset: 20px;
            border: 1px solid rgba(255,255,255,.17);
            border-radius: 24px;
            pointer-events: none;
        }

        .visual::after {
            content: "";
            position: absolute;
            width: 330px;
            height: 330px;
            right: -125px;
            top: -120px;
            border-radius: 50%;
            background: rgba(121,230,170,.13);
            filter: blur(65px);
            z-index: -1;
        }

        .visual-tag {
            position: relative;
            z-index: 2;
            width: fit-content;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border: 1px solid rgba(255,255,255,.14);
            border-radius: 999px;
            background: rgba(0,0,0,.25);
            backdrop-filter: blur(12px);
            color: #dfeae5;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .18em;
            text-transform: uppercase;
            animation: fadeUp .65s .14s ease both;
        }

        .visual-tag i {
            color: var(--green);
        }

        .visual-copy {
            position: relative;
            z-index: 2;
            max-width: 570px;
            animation: copyIn .8s .18s ease both;
        }

        .eyebrow {
            margin-bottom: 15px;
            color: var(--orange);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .23em;
            text-transform: uppercase;
        }

        .visual-copy h1 {
            max-width: 520px;
            margin: 0;
            font-family: "Playfair Display", serif;
            font-size: clamp(47px, 5vw, 77px);
            line-height: .92;
            letter-spacing: -.045em;
        }

        .visual-copy h1 em {
            color: var(--green);
            font-style: normal;
        }

        .visual-copy p {
            max-width: 455px;
            margin: 22px 0 0;
            color: rgba(248,250,248,.70);
            font-size: 13px;
            line-height: 1.75;
        }

        .visual-bottom {
            position: relative;
            z-index: 2;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            animation: fadeUp .8s .32s ease both;
        }

        .mini-card {
            min-width: 150px;
            padding: 13px 14px;
            border: 1px solid rgba(255,255,255,.13);
            border-radius: 15px;
            background: rgba(0,0,0,.22);
            backdrop-filter: blur(14px);
            transition: transform .25s ease, background .25s ease, border-color .25s ease;
        }

        .mini-card:hover {
            transform: translateY(-3px);
            background: rgba(0,0,0,.31);
            border-color: rgba(121,230,170,.24);
        }

        .mini-card strong {
            display: block;
            font-size: 12px;
        }

        .mini-card span {
            display: block;
            margin-top: 4px;
            color: rgba(255,255,255,.48);
            font-size: 9px;
        }

        /* ------------------------------------------------------------------
           Login panel
        ------------------------------------------------------------------ */
        .form-side {
            min-width: 0;
            display: grid;
            place-items: center;
            padding: 42px 44px 32px;
            background:
                radial-gradient(circle at 100% 0%, rgba(121,230,170,.10), transparent 34%),
                linear-gradient(145deg, rgba(14,29,24,.98), rgba(8,18,15,.99));
        }

        .form-card {
            width: 100%;
            max-width: 465px;
            animation: fadeUp .8s .12s ease both;
        }

        .card-head {
            margin-bottom: 24px;
        }

        .kicker {
            margin: 0 0 8px;
            color: var(--green);
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .20em;
            text-transform: uppercase;
        }

        .title {
            margin: 0;
            font-family: "Playfair Display", serif;
            font-size: 35px;
            line-height: 1;
            letter-spacing: -.025em;
        }

        .subtitle {
            max-width: 360px;
            margin: 10px 0 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.6;
        }

        .server-message {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            margin-bottom: 17px;
            padding: 11px 12px;
            border-radius: 13px;
            font-size: 10px;
            line-height: 1.5;
            animation: messageIn .32s ease both;
        }

        .server-message.error {
            color: #ffc7c7;
            background: var(--danger-bg);
            border: 1px solid rgba(255,123,123,.18);
        }

        .server-message.success {
            color: #baf3ce;
            background: var(--success-bg);
            border: 1px solid rgba(121,230,170,.18);
        }

        .server-message i {
            margin-top: 1px;
        }

        .server-message.error i {
            color: var(--danger);
        }

        .server-message.success i {
            color: var(--success);
        }

        .field {
            margin-bottom: 17px;
        }

        .field-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
            color: #dce5e1;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .09em;
            text-transform: uppercase;
        }

        .field-label small {
            color: #64756d;
            font-size: 8px;
            letter-spacing: .03em;
            text-transform: none;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            z-index: 1;
            transform: translateY(-50%);
            color: #5d6e67;
            pointer-events: none;
            transition: color .2s ease;
        }

        .input {
            width: 100%;
            height: 53px;
            padding: 0 50px 0 42px;
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            outline: none;
            color: white;
            background: rgba(255,255,255,.038);
            font-size: 12px;
            transition:
                border-color .22s ease,
                box-shadow .22s ease,
                background .22s ease,
                transform .22s ease;
        }

        .input::placeholder {
            color: #5e6c66;
        }

        .input:hover {
            border-color: rgba(255,255,255,.17);
        }

        .input:focus {
            background: rgba(121,230,170,.04);
            border-color: rgba(121,230,170,.62);
            box-shadow: 0 0 0 4px rgba(121,230,170,.065);
            transform: translateY(-1px);
        }

        .input:focus ~ .input-icon,
        .input-wrap:focus-within .input-icon {
            color: var(--green);
        }

        .input.invalid {
            border-color: rgba(255,123,123,.68);
            box-shadow: 0 0 0 4px rgba(255,123,123,.055);
        }

        .icon-button {
            position: absolute;
            right: 8px;
            top: 50%;
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            transform: translateY(-50%);
            border-radius: 10px;
            color: #6f8078;
            background: transparent;
            cursor: pointer;
            transition: .22s ease;
        }

        .icon-button:hover {
            color: var(--green);
            background: rgba(255,255,255,.06);
        }

        .message {
            display: none;
            margin-top: 7px;
            padding: 8px 10px;
            border-radius: 10px;
            font-size: 10px;
            line-height: 1.45;
        }

        .message.visible {
            display: block;
        }

        .message.error {
            color: #ffb4b4;
            background: rgba(255,123,123,.065);
            border: 1px solid rgba(255,123,123,.16);
        }

        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin: 5px 0 20px;
        }

        .remember {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #697970;
            font-size: 10px;
            cursor: pointer;
        }

        .remember input {
            appearance: none;
            width: 17px;
            height: 17px;
            margin: 0;
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 5px;
            background: rgba(255,255,255,.035);
            cursor: pointer;
            transition: .2s ease;
        }

        .remember input:checked {
            border-color: var(--green);
            background: var(--green);
            box-shadow: inset 0 0 0 4px #10251e;
        }

        .forgot {
            color: #8ebaa0;
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
            text-underline-offset: 3px;
            transition: color .2s ease;
        }

        .forgot:hover {
            color: var(--green);
        }

        .primary {
            width: 100%;
            height: 53px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 15px;
            color: #06100c;
            background: linear-gradient(135deg, #90efbb, #56d993);
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 12px 28px rgba(121,230,170,.13);
            transition: transform .25s ease, box-shadow .25s ease, filter .25s ease;
        }

        .primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 17px 34px rgba(121,230,170,.19);
            filter: brightness(1.03);
        }

        .primary:active:not(:disabled) {
            transform: scale(.99);
        }

        .primary:disabled {
            opacity: .58;
            cursor: not-allowed;
        }

        .trust-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 7px;
            margin-top: 18px;
        }

        .trust-item {
            padding: 9px 7px;
            text-align: center;
            border: 1px solid rgba(255,255,255,.065);
            border-radius: 11px;
            background: rgba(255,255,255,.022);
        }

        .trust-item i {
            display: block;
            margin-bottom: 5px;
            color: var(--green);
            font-size: 11px;
        }

        .trust-item span {
            color: #5d6d66;
            font-size: 8px;
        }

        /* ------------------------------------------------------------------
           Sign-up footer link
        ------------------------------------------------------------------ */
        .card-footer {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid rgba(255,255,255,.08);
            text-align: center;
        }

        .card-footer p {
            margin: 0;
            color: #64736d;
            font-size: 10px;
        }

        .signup-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 7px;
            color: var(--green);
            font-size: 11px;
            font-weight: 800;
            text-decoration: none;
            transition: transform .2s ease, color .2s ease;
        }

        .signup-link:hover {
            color: #a5f2c6;
            transform: translateY(-1px);
        }

        .secure-note {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            margin-top: 16px;
            color: #4e5e57;
            font-size: 8px;
        }

        .secure-note i {
            color: var(--green);
        }

        footer {
            padding: 0 0 19px;
            text-align: center;
            color: #4f5d57;
            font-size: 9px;
            animation: fadeUp .7s .35s ease both;
        }

        /* ------------------------------------------------------------------
           Animations
        ------------------------------------------------------------------ */
        @keyframes shellIn {
            from {
                opacity: 0;
                transform: translateY(20px) scale(.985);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes fadeDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(16px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes copyIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes messageIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .shake {
            animation: shake .32s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* ------------------------------------------------------------------
           Responsive
        ------------------------------------------------------------------ */
        @media (max-width: 960px) {
            .auth-shell {
                max-width: 650px;
                grid-template-columns: 1fr;
                min-height: 0;
            }

            .visual {
                min-height: 290px;
                padding: 30px;
            }

            .visual-bottom {
                display: none;
            }

            .visual-copy h1 {
                font-size: clamp(45px, 9vw, 68px);
            }

            .form-side {
                padding: 35px 28px 37px;
            }
        }

        @media (max-width: 600px) {
            .page {
                width: calc(100% - 14px);
            }

            .topbar {
                height: 72px;
            }

            .brand-logo {
                width: 37px;
                height: 37px;
                border-radius: 11px;
            }

            .brand-name {
                font-size: 10px;
            }

            .secure-label {
                display: none;
            }

            main {
                padding: 7px 0 24px;
            }

            .auth-shell {
                border-radius: 23px;
            }

            .visual {
                min-height: 235px;
                padding: 23px;
            }

            .visual::before {
                inset: 12px;
                border-radius: 17px;
            }

            .visual-copy h1 {
                max-width: 315px;
                font-size: 42px;
            }

            .visual-copy p {
                max-width: 315px;
                margin-top: 12px;
                font-size: 10px;
                line-height: 1.55;
            }

            .form-side {
                padding: 28px 16px 29px;
            }

            .title {
                font-size: 29px;
            }

            .subtitle {
                font-size: 10px;
            }

            .input {
                height: 52px;
            }

            .remember-row {
                align-items: flex-start;
                flex-direction: column;
                gap: 9px;
            }

            .trust-row {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }

        @media (max-width: 360px) {
            .page {
                width: calc(100% - 10px);
            }

            .visual {
                min-height: 214px;
                padding: 20px;
            }

            .visual-copy h1 {
                font-size: 36px;
            }

            .form-side {
                padding-left: 12px;
                padding-right: 12px;
            }

            .title {
                font-size: 27px;
            }

            .trust-item span {
                font-size: 7px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
                scroll-behavior: auto !important;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/dunkhome-ui.css">
</head>

<body>
<div class="page">

    <header class="topbar">
        <a class="brand" href="index.php" aria-label="DunkHome Kicks home">
            <img
                class="brand-logo"
                src="image/logo.jpeg"
                alt="DunkHome Kicks"
            >
            <span class="brand-name">
                dunkhome_<span>kicks</span>
            </span>
        </a>

        <div class="secure-label">
            <i class="fa-solid fa-shield-halved"></i>
            Secure sign in
        </div>
    </header>

    <main>
        <section class="auth-shell" aria-label="Sign in to DunkHome Kicks">

            <aside class="visual" aria-label="DunkHome Kicks">
                <div class="visual-tag">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    Welcome back
                </div>

                <div class="visual-copy">
                    <div class="eyebrow">Your style, your space</div>

                    <h1>
                        Step back into your
                        <em>own</em> style.
                    </h1>

                    <p>
                        Your collection, your preferences, your next pair.
                        Sign in and continue where you left off.
                    </p>
                </div>

                <div class="visual-bottom">
                    <div class="mini-card">
                        <strong>Secure access</strong>
                        <span>Password protected account</span>
                    </div>

                    <div class="mini-card">
                        <strong>Ready when you are</strong>
                        <span>Pick up right where you stopped</span>
                    </div>
                </div>
            </aside>

            <section class="form-side">
                <div class="form-card">

                    <div class="card-head">
                        <p class="kicker">Good to see you again</p>

                        <h2 class="title">Sign in</h2>

                        <p class="subtitle">
                            Enter your account details to continue to DunkHome Kicks.
                        </p>
                    </div>

                    <?php if ($message !== ''): ?>
                        <div
                            class="server-message <?= $messageType === 'success' ? 'success' : 'error' ?>"
                            role="alert"
                        >
                            <i class="fa-solid <?= $messageType === 'success'
                                ? 'fa-circle-check'
                                : 'fa-circle-exclamation' ?>"></i>

                            <span>
                                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <form
                        id="signinForm"
                        action="User/SignIn.php"
                        method="POST"
                        novalidate
                    >
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

                        <div class="field">
                            <label class="field-label" for="emailField">
                                Email address
                                <small>Required</small>
                            </label>

                            <div class="input-wrap">
                                <i class="fa-regular fa-envelope input-icon"></i>

                                <input
                                    class="input"
                                    type="email"
                                    id="emailField"
                                    name="email"
                                    placeholder="you@example.com"
                                    autocomplete="email"
                                    inputmode="email"
                                    required
                                >
                            </div>

                            <div
                                id="emailMessage"
                                class="message error"
                                aria-live="polite"
                            ></div>
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
                                    placeholder="Enter your password"
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

                            <div
                                id="passwordMessage"
                                class="message error"
                                aria-live="polite"
                            ></div>
                        </div>

                        <div class="remember-row">
                            <label class="remember" for="rememberMe">
                                <input
                                    type="checkbox"
                                    id="rememberMe"
                                    name="remember"
                                    value="1"
                                >
                                <span>Remember me</span>
                            </label>

                            <a class="forgot" href="User/ForgotPassword.php">
                                Forgot password?
                            </a>
                        </div>

                        <div
                            id="formMessage"
                            class="message error"
                            aria-live="polite"
                        ></div>

                        <button
                            type="submit"
                            class="primary"
                            id="signinButton"
                        >
                            <i class="fa-solid fa-arrow-right-to-bracket"></i>
                            Sign in securely
                        </button>
                    </form>

                    <div class="trust-row" aria-label="Security features">
                        <div class="trust-item">
                            <i class="fa-solid fa-lock"></i>
                            <span>Encrypted</span>
                        </div>

                        <div class="trust-item">
                            <i class="fa-solid fa-shield-halved"></i>
                            <span>Protected</span>
                        </div>

                        <div class="trust-item">
                            <i class="fa-solid fa-user-check"></i>
                            <span>Private</span>
                        </div>
                    </div>

                    <!-- Sign-up moved to the bottom of the card -->
                    <div class="card-footer">
                        <p>Don't have a DunkHome Kicks account?</p>

                        <a
                            class="signup-link"
                            href="User/SignUp.php"
                        >
                            Create an account
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    <div class="secure-note">
                        <i class="fa-solid fa-lock"></i>
                        Your credentials are securely verified.
                    </div>

                </div>
            </section>

        </section>
    </main>

    <footer>
        &copy; <?= date('Y') ?> DunkHome Kicks. All rights reserved.
    </footer>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('signinForm');
    const emailField = document.getElementById('emailField');
    const passwordField = document.getElementById('passwordField');
    const togglePassword = document.getElementById('togglePassword');
    const signinButton = document.getElementById('signinButton');

    const emailMessage = document.getElementById('emailMessage');
    const passwordMessage = document.getElementById('passwordMessage');
    const formMessage = document.getElementById('formMessage');

    function isValidEmail(email) {
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

    function markInvalid(input, value = true) {
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
        markInvalid(emailField, false);

        const email = emailField.value.trim();

        if (email && !isValidEmail(email)) {
            showMessage(emailMessage, 'Enter a valid email address.');
            markInvalid(emailField);
        }
    });

    passwordField.addEventListener('input', () => {
        hideMessage(passwordMessage);
        hideMessage(formMessage);
        markInvalid(passwordField, false);
    });

    togglePassword.addEventListener('click', () => {
        const isHidden = passwordField.type === 'password';
        const icon = togglePassword.querySelector('i');

        passwordField.type = isHidden ? 'text' : 'password';

        icon.className = isHidden
            ? 'fa-regular fa-eye-slash'
            : 'fa-regular fa-eye';

        togglePassword.setAttribute(
            'aria-label',
            isHidden ? 'Hide password' : 'Show password'
        );

        passwordField.focus();
    });

    form.addEventListener('submit', (event) => {
        hideMessage(formMessage);
        hideMessage(emailMessage);
        hideMessage(passwordMessage);

        markInvalid(emailField, false);
        markInvalid(passwordField, false);

        const email = emailField.value.trim();
        const password = passwordField.value;

        let valid = true;

        if (!email) {
            showMessage(emailMessage, 'Please enter your email address.');
            markInvalid(emailField);
            valid = false;
        } else if (!isValidEmail(email)) {
            showMessage(emailMessage, 'Enter a valid email address.');
            markInvalid(emailField);
            valid = false;
        }

        if (!password) {
            showMessage(passwordMessage, 'Please enter your password.');
            markInvalid(passwordField);
            valid = false;
        }

        if (!valid) {
            event.preventDefault();

            shake(form);

            if (email && isValidEmail(email) === false) {
                emailField.focus();
            } else if (!email) {
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

    /*
     * Enter on the email field moves naturally through the form.
     * Enter on password submits through the normal form event.
     */
    emailField.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();

            if (isValidEmail(emailField.value.trim())) {
                passwordField.focus();
            } else {
                showMessage(emailMessage, 'Enter a valid email address.');
                markInvalid(emailField);
            }
        }
    });
});
</script>
    <script src="assets/dunkhome-ui.js" defer></script>
</body>
</html>
