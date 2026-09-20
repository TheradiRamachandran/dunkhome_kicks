<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

/*
|--------------------------------------------------------------------------
| Session Status
|--------------------------------------------------------------------------
| If a user or admin is already logged in, we can show the appropriate
| dashboard links.
*/

$userLoggedIn  = userLoggedIn();
$adminLoggedIn = adminLoggedIn();

$featuredProducts = [];
$productResult = $conn->query('SELECT id,name,category,price,description,image1 FROM products ORDER BY created_at DESC LIMIT 6');
if ($productResult) { while ($row = $productResult->fetch_assoc()) { $featuredProducts[] = $row; } }

$userName = $_SESSION['username'] ?? $_SESSION['name'] ?? 'Customer';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="DunkHome Kicks — Step into your style. Discover premium sneakers, streetwear and footwear."
    >

    <meta
        name="theme-color"
        content="#07100b"
    >

    <title>DunkHome Kicks | Step Into Your Style</title>
    <link rel="icon" type="image/jpeg" href="image/logo.jpeg">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap"
        rel="stylesheet"
    >

    <style>

        /* =========================================================
           ROOT
        ========================================================= */

        :root {
            --bg: #07100b;
            --bg-secondary: #0b1510;
            --card: rgba(17, 28, 21, 0.72);
            --card-solid: #101b14;

            --text: #f4f8f5;
            --muted: #9aa99f;

            --green: #22c55e;
            --green-dark: #15803d;
            --green-soft: #86efac;

            --border: rgba(134, 239, 172, 0.13);

            --white: #ffffff;
            --black: #000000;

            --shadow:
                0 25px 70px rgba(0, 0, 0, 0.35);

            --radius-lg: 28px;
            --radius-md: 18px;

            --transition: 0.3s ease;
        }

        body.light {
            --bg: #f5f8f6;
            --bg-secondary: #ffffff;
            --card: rgba(255, 255, 255, 0.85);
            --card-solid: #ffffff;

            --text: #102016;
            --muted: #607065;

            --border: rgba(16, 32, 22, 0.10);

            --shadow:
                0 20px 55px rgba(16, 32, 22, 0.10);
        }


        /* =========================================================
           RESET
        ========================================================= */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            min-height: 100vh;

            font-family: "Inter", sans-serif;

            color: var(--text);
            background:
                radial-gradient(
                    circle at 15% 10%,
                    rgba(34, 197, 94, 0.11),
                    transparent 30%
                ),
                radial-gradient(
                    circle at 85% 25%,
                    rgba(34, 197, 94, 0.07),
                    transparent 30%
                ),
                var(--bg);

            overflow-x: hidden;

            transition:
                background var(--transition),
                color var(--transition);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input {
            font: inherit;
        }

        img {
            display: block;
            max-width: 100%;
        }


        /* =========================================================
           SCROLLBAR
        ========================================================= */

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--green-dark);
            border-radius: 20px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--green);
        }


        /* =========================================================
           CONTAINER
        ========================================================= */

        .container {
            width: min(1180px, calc(100% - 36px));
            margin: auto;
        }


        /* =========================================================
           HEADER
        ========================================================= */

        header {
            position: fixed;

            top: 0;
            left: 0;

            width: 100%;

            z-index: 1000;

            border-bottom: 1px solid transparent;

            transition:
                background var(--transition),
                border-color var(--transition),
                backdrop-filter var(--transition);
        }

        header.scrolled {
            background: rgba(7, 16, 11, 0.78);
            backdrop-filter: blur(20px);

            border-bottom-color: var(--border);
        }

        body.light header.scrolled {
            background: rgba(255, 255, 255, 0.82);
        }

        .navbar {
            min-height: 78px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 24px;
        }


        /* =========================================================
           LOGO
        ========================================================= */

        .logo {
            display: flex;
            align-items: center;
            gap: 11px;

            font-family: "Space Grotesk", sans-serif;
            font-size: 21px;
            font-weight: 700;

            white-space: nowrap;
        }

        .logo-image {
            width: 43px;
            height: 43px;

            border-radius: 12px;

            object-fit: cover;

            border: 1px solid var(--border);

            box-shadow:
                0 8px 25px rgba(34, 197, 94, 0.15);
        }

        .logo span {
            color: var(--green);
        }


        /* =========================================================
           NAVIGATION
        ========================================================= */

        .nav-links {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-links a {
            position: relative;

            color: var(--muted);

            font-size: 14px;
            font-weight: 600;

            transition: color var(--transition);
        }

        .nav-links a:hover {
            color: var(--green-soft);
        }

        .nav-links a::after {
            content: "";

            position: absolute;

            left: 0;
            bottom: -7px;

            width: 0;
            height: 2px;

            background: var(--green);

            transition: width var(--transition);
        }

        .nav-links a:hover::after {
            width: 100%;
        }


        /* =========================================================
           HEADER ACTIONS
        ========================================================= */

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .theme-btn,
        .menu-btn {
            width: 42px;
            height: 42px;

            border: 1px solid var(--border);

            border-radius: 12px;

            background: var(--card);

            color: var(--text);

            cursor: pointer;

            display: grid;
            place-items: center;

            transition: var(--transition);
        }

        .theme-btn:hover,
        .menu-btn:hover {
            border-color: rgba(34, 197, 94, 0.4);
            transform: translateY(-2px);
        }

        .menu-btn {
            display: none;
        }


        /* =========================================================
           BUTTONS
        ========================================================= */

        .btn {
            min-height: 46px;

            padding: 0 20px;

            border-radius: 13px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            gap: 8px;

            font-size: 14px;
            font-weight: 700;

            border: 1px solid transparent;

            cursor: pointer;

            transition:
                transform var(--transition),
                box-shadow var(--transition),
                background var(--transition),
                border-color var(--transition);
        }

        .btn:hover {
            transform: translateY(-3px);
        }

        .btn-primary {
            color: #041008;

            background: linear-gradient(
                135deg,
                #4ade80,
                #22c55e
            );

            box-shadow:
                0 12px 35px rgba(34, 197, 94, 0.22);
        }

        .btn-primary:hover {
            box-shadow:
                0 17px 45px rgba(34, 197, 94, 0.35);
        }

        .btn-outline {
            color: var(--text);

            border-color: var(--border);

            background: rgba(255,255,255,0.025);
        }

        .btn-outline:hover {
            border-color: rgba(34, 197, 94, 0.4);
            background: rgba(34, 197, 94, 0.07);
        }

        .btn-small {
            min-height: 40px;
            padding: 0 15px;
            font-size: 13px;
        }


        /* =========================================================
           HERO
        ========================================================= */

        .hero {
            min-height: 100vh;

            display: flex;
            align-items: center;

            padding:
                145px 0
                90px;

            position: relative;
        }

        .hero::before {
            content: "";

            position: absolute;

            width: 450px;
            height: 450px;

            right: -170px;
            top: 100px;

            background: rgba(34, 197, 94, 0.08);

            filter: blur(90px);

            border-radius: 50%;

            pointer-events: none;
        }

        .hero-grid {
            display: grid;

            grid-template-columns:
                1.05fr
                0.95fr;

            gap: 70px;

            align-items: center;
        }

        .hero-content {
            animation: heroText 0.9s ease both;
        }

        @keyframes heroText {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .eyebrow {
            display: inline-flex;

            align-items: center;

            gap: 8px;

            padding: 8px 12px;

            border-radius: 100px;

            border: 1px solid var(--border);

            background: rgba(34, 197, 94, 0.06);

            color: var(--green-soft);

            font-size: 12px;
            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 1.3px;

            margin-bottom: 22px;
        }

        .eyebrow-dot {
            width: 7px;
            height: 7px;

            border-radius: 50%;

            background: var(--green);

            box-shadow:
                0 0 12px var(--green);
        }

        .hero h1 {
            max-width: 720px;

            font-family: "Space Grotesk", sans-serif;

            font-size: clamp(
                48px,
                7vw,
                84px
            );

            line-height: 0.98;

            letter-spacing: -4px;

            margin-bottom: 25px;
        }

        .hero h1 span {
            color: var(--green);
        }

        .hero-description {
            max-width: 570px;

            color: var(--muted);

            font-size: 17px;

            line-height: 1.8;

            margin-bottom: 32px;
        }

        .hero-buttons {
            display: flex;
            flex-wrap: wrap;

            gap: 12px;
        }


        /* =========================================================
           HERO VISUAL
        ========================================================= */

        .hero-visual {
            position: relative;

            min-height: 500px;

            display: grid;
            place-items: center;
        }

        .shoe-stage {
            width: min(100%, 500px);
            aspect-ratio: 1;

            position: relative;

            border-radius: 50%;

            background:
                radial-gradient(
                    circle,
                    rgba(34,197,94,0.16),
                    rgba(34,197,94,0.03) 45%,
                    transparent 68%
                );

            display: grid;
            place-items: center;

            animation:
                floatStage 5s ease-in-out infinite;
        }

        @keyframes floatStage {
            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-12px);
            }
        }

        .shoe-card {
            width: 76%;
            aspect-ratio: 1;

            border-radius: 34px;

            border: 1px solid var(--border);

            background:
                linear-gradient(
                    145deg,
                    rgba(255,255,255,0.09),
                    rgba(255,255,255,0.015)
                );

            backdrop-filter: blur(20px);

            box-shadow:
                var(--shadow);

            display: flex;
            flex-direction: column;

            align-items: center;
            justify-content: center;

            transform: rotate(-7deg);

            overflow: hidden;

            position: relative;
        }

        .shoe-card::before {
            content: "";

            position: absolute;

            width: 230px;
            height: 230px;

            border-radius: 50%;

            background: rgba(34,197,94,0.12);

            filter: blur(45px);
        }

        .shoe-symbol {
            position: relative;

            font-size: 115px;

            filter:
                drop-shadow(
                    0 25px 25px
                    rgba(0,0,0,0.35)
                );

            transform: rotate(7deg);
        }

        .shoe-name {
            position: relative;

            margin-top: 18px;

            font-family: "Space Grotesk", sans-serif;

            font-size: 20px;

            letter-spacing: 1px;
        }

        .floating-tag {
            position: absolute;

            padding: 11px 15px;

            border-radius: 14px;

            background: var(--card);

            border: 1px solid var(--border);

            backdrop-filter: blur(18px);

            box-shadow: var(--shadow);

            font-size: 12px;

            font-weight: 700;
        }

        .tag-one {
            top: 80px;
            left: 0;
        }

        .tag-two {
            right: 0;
            bottom: 90px;
        }


        /* =========================================================
           SECTION
        ========================================================= */

        section {
            padding: 100px 0;
        }

        .section-heading {
            display: flex;
            align-items: end;
            justify-content: space-between;

            gap: 30px;

            margin-bottom: 40px;
        }

        .section-heading h2 {
            font-family: "Space Grotesk", sans-serif;

            font-size: clamp(32px, 5vw, 48px);

            letter-spacing: -1.5px;
        }

        .section-heading p {
            max-width: 480px;

            color: var(--muted);

            line-height: 1.7;
        }


        /* =========================================================
           CATEGORIES
        ========================================================= */

        .category-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 18px;
        }

        .category-card {
            min-height: 230px;

            position: relative;

            overflow: hidden;

            border-radius: var(--radius-lg);

            border: 1px solid var(--border);

            background: var(--card);

            padding: 28px;

            transition:
                transform var(--transition),
                border-color var(--transition),
                background var(--transition);
        }

        .category-card:hover {
            transform: translateY(-8px);

            border-color:
                rgba(34, 197, 94, 0.35);

            background:
                rgba(34, 197, 94, 0.06);
        }

        .category-icon {
            width: 54px;
            height: 54px;

            display: grid;
            place-items: center;

            border-radius: 16px;

            background:
                rgba(34,197,94,0.09);

            font-size: 27px;

            margin-bottom: 45px;
        }

        .category-card h3 {
            font-family: "Space Grotesk", sans-serif;

            font-size: 22px;

            margin-bottom: 8px;
        }

        .category-card p {
            color: var(--muted);

            font-size: 13px;
        }

        .category-number {
            position: absolute;

            right: 22px;
            top: 20px;

            color: rgba(134,239,172,0.35);

            font-size: 12px;

            font-weight: 700;
        }


        /* =========================================================
           FEATURED
        ========================================================= */

        .products-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;
        }

        .product-card {
            overflow: hidden;

            border-radius: var(--radius-lg);

            border: 1px solid var(--border);

            background: var(--card);

            transition:
                transform var(--transition),
                border-color var(--transition);
        }

        .product-card:hover {
            transform: translateY(-7px);

            border-color:
                rgba(34, 197, 94, 0.32);
        }

        .product-image {
            height: 270px;

            display: grid;
            place-items: center;

            background:
                radial-gradient(
                    circle at center,
                    rgba(34,197,94,0.13),
                    transparent 65%
                );

            overflow: hidden;
        }

        .product-shoe {
            font-size: 105px;

            transition:
                transform 0.5s ease;
        }

        .product-card:hover .product-shoe {
            transform:
                scale(1.1)
                rotate(-6deg);
        }

        .product-content {
            padding: 22px;
        }

        .product-label {
            color: var(--green);

            font-size: 11px;

            font-weight: 800;

            text-transform: uppercase;

            letter-spacing: 1px;

            margin-bottom: 8px;
        }

        .product-content h3 {
            font-family: "Space Grotesk", sans-serif;

            font-size: 21px;

            margin-bottom: 8px;
        }

        .product-content p {
            color: var(--muted);

            font-size: 13px;

            line-height: 1.6;

            margin-bottom: 18px;
        }

        .product-bottom {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;
        }

        .price {
            font-weight: 800;

            font-size: 16px;
        }


        /* =========================================================
           PROMO
        ========================================================= */

        .promo {
            position: relative;

            overflow: hidden;

            border-radius: 32px;

            padding: 65px;

            border: 1px solid var(--border);

            background:
                linear-gradient(
                    135deg,
                    rgba(34,197,94,0.14),
                    rgba(255,255,255,0.025)
                );
        }

        .promo::after {
            content: "";

            position: absolute;

            width: 320px;
            height: 320px;

            right: -100px;
            top: -100px;

            border-radius: 50%;

            background:
                rgba(34,197,94,0.12);

            filter: blur(50px);
        }

        .promo-content {
            position: relative;

            z-index: 2;

            max-width: 650px;
        }

        .promo h2 {
            font-family: "Space Grotesk", sans-serif;

            font-size: clamp(32px, 5vw, 52px);

            letter-spacing: -2px;

            margin-bottom: 15px;
        }

        .promo p {
            color: var(--muted);

            line-height: 1.7;

            margin-bottom: 25px;
        }


        /* =========================================================
           ABOUT
        ========================================================= */

        .about-grid {
            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 60px;

            align-items: center;
        }

        .about-visual {
            min-height: 390px;

            border-radius: 30px;

            border: 1px solid var(--border);

            background:
                linear-gradient(
                    145deg,
                    rgba(34,197,94,0.10),
                    rgba(255,255,255,0.02)
                );

            display: grid;

            place-items: center;

            overflow: hidden;

            position: relative;
        }

        .about-circle {
            width: 220px;
            height: 220px;

            border-radius: 50%;

            border:
                1px solid
                rgba(134,239,172,0.25);

            display: grid;

            place-items: center;

            font-size: 90px;

            box-shadow:
                0 0 80px
                rgba(34,197,94,0.12);
        }

        .about-content h2 {
            font-family: "Space Grotesk", sans-serif;

            font-size: clamp(34px, 5vw, 50px);

            letter-spacing: -2px;

            margin-bottom: 20px;
        }

        .about-content p {
            color: var(--muted);

            line-height: 1.85;

            margin-bottom: 20px;
        }

        .about-points {
            display: grid;

            gap: 12px;

            margin-top: 25px;
        }

        .about-point {
            display: flex;

            align-items: center;

            gap: 12px;

            font-size: 14px;

            font-weight: 600;
        }

        .point-icon {
            width: 27px;
            height: 27px;

            display: grid;
            place-items: center;

            border-radius: 50%;

            color: var(--green);

            background:
                rgba(34,197,94,0.10);
        }


        /* =========================================================
           CTA
        ========================================================= */

        .cta {
            text-align: center;
        }

        .cta-box {
            padding: 75px 30px;

            border-radius: 32px;

            border: 1px solid var(--border);

            background:
                radial-gradient(
                    circle at center,
                    rgba(34,197,94,0.11),
                    transparent 60%
                );
        }

        .cta h2 {
            font-family: "Space Grotesk", sans-serif;

            font-size: clamp(36px, 6vw, 60px);

            letter-spacing: -2px;

            margin-bottom: 15px;
        }

        .cta p {
            max-width: 600px;

            margin: 0 auto 28px;

            color: var(--muted);

            line-height: 1.7;
        }

        .cta-buttons {
            display: flex;

            justify-content: center;

            flex-wrap: wrap;

            gap: 12px;
        }


        /* =========================================================
           FOOTER
        ========================================================= */

        footer {
            padding: 55px 0 30px;

            border-top: 1px solid var(--border);
        }

        .footer-grid {
            display: grid;

            grid-template-columns:
                1.4fr
                1fr
                1fr
                1fr;

            gap: 40px;

            padding-bottom: 45px;
        }

        .footer-brand p {
            max-width: 330px;

            color: var(--muted);

            font-size: 13px;

            line-height: 1.8;

            margin-top: 17px;
        }

        .footer-column h4 {
            font-size: 13px;

            text-transform: uppercase;

            letter-spacing: 1px;

            margin-bottom: 18px;
        }

        .footer-column a {
            display: block;

            color: var(--muted);

            font-size: 13px;

            margin-bottom: 12px;

            transition:
                color var(--transition);
        }

        .footer-column a:hover {
            color: var(--green);
        }

        .footer-bottom {
            padding-top: 25px;

            border-top: 1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            color: var(--muted);

            font-size: 12px;
        }


        /* =========================================================
           REVEAL
        ========================================================= */

        .reveal {
            opacity: 0;

            transform: translateY(30px);

            transition:
                opacity 0.7s ease,
                transform 0.7s ease;
        }

        .reveal.visible {
            opacity: 1;

            transform: translateY(0);
        }


        /* =========================================================
           MOBILE NAV
        ========================================================= */

        @media (max-width: 850px) {

            .menu-btn {
                display: grid;
            }

            .nav-links {
                position: fixed;

                top: 78px;

                left: 18px;
                right: 18px;

                padding: 18px;

                display: none;

                flex-direction: column;

                align-items: stretch;

                gap: 5px;

                background:
                    rgba(10, 20, 14, 0.96);

                backdrop-filter: blur(22px);

                border:
                    1px solid var(--border);

                border-radius: 18px;

                box-shadow: var(--shadow);
            }

            body.light .nav-links {
                background:
                    rgba(255,255,255,0.96);
            }

            .nav-links.active {
                display: flex;
            }

            .nav-links a {
                padding: 13px 12px;
            }

            .nav-links a::after {
                display: none;
            }

            .header-actions .btn {
                display: none;
            }

            .hero-grid {
                grid-template-columns: 1fr;

                gap: 45px;
            }

            .hero {
                padding-top: 125px;
            }

            .hero-visual {
                min-height: 420px;
            }

            .category-grid,
            .products-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .about-grid {
                grid-template-columns: 1fr;
            }

            .footer-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }
        }


        /* =========================================================
           SMALL MOBILE
        ========================================================= */

        @media (max-width: 560px) {

            .container {
                width: min(
                    100% - 24px,
                    1180px
                );
            }

            .navbar {
                min-height: 70px;
            }

            .logo-image {
                width: 39px;
                height: 39px;
            }

            .logo {
                font-size: 18px;
            }

            .theme-btn,
            .menu-btn {
                width: 39px;
                height: 39px;
            }

            .hero {
                padding:
                    115px 0
                    65px;
            }

            .hero h1 {
                font-size: 48px;

                letter-spacing: -2.5px;
            }

            .hero-description {
                font-size: 15px;
            }

            .hero-buttons {
                display: grid;

                grid-template-columns: 1fr;
            }

            .btn {
                width: 100%;
            }

            .hero-visual {
                min-height: 330px;
            }

            .shoe-stage {
                width: 100%;
            }

            .shoe-card {
                width: 72%;
            }

            .shoe-symbol {
                font-size: 75px;
            }

            .floating-tag {
                padding: 8px 11px;

                font-size: 10px;
            }

            .tag-one {
                top: 35px;
            }

            .tag-two {
                bottom: 45px;
            }

            section {
                padding: 70px 0;
            }

            .section-heading {
                display: block;
            }

            .section-heading h2 {
                margin-bottom: 15px;
            }

            .category-grid,
            .products-grid {
                grid-template-columns: 1fr;
            }

            .category-card {
                min-height: 200px;
            }

            .product-image {
                height: 240px;
            }

            .promo {
                padding: 40px 25px;
            }

            .about-visual {
                min-height: 300px;
            }

            .about-circle {
                width: 170px;
                height: 170px;

                font-size: 70px;
            }

            .cta-box {
                padding: 55px 20px;
            }

            .cta-buttons {
                display: grid;

                grid-template-columns: 1fr;
            }

            .footer-grid {
                grid-template-columns: 1fr;
            }

            .footer-bottom {
                flex-direction: column;

                align-items: flex-start;
            }
        }

    </style>
    <link rel="stylesheet" href="assets/dunkhome-ui.css">
</head>

<body>

<!-- =========================================================
     HEADER
========================================================= -->

<header id="header">

    <div class="container navbar">

        <a href="index.php" class="logo">

            <img
                src="image/logo.jpeg"
                alt="DunkHome Kicks Logo"
                class="logo-image"
                onerror="this.style.display='none';"
            >

            DunkHome <span>Kicks</span>

        </a>


        <nav class="nav-links" id="navLinks">

            <a href="Home.php">Home</a>

            <a href="Products.php">Categories</a>

            <a href="Products.php">Featured</a>

            <a href="Home.php#about">About</a>

            <?php if ($adminLoggedIn): ?>

                <a href="AdminDashboard.php">
                    Admin Dashboard
                </a>

            <?php endif; ?>

        </nav>


        <div class="header-actions">

            <button
                type="button"
                class="theme-btn"
                id="themeToggle"
                aria-label="Toggle theme"
                title="Toggle theme"
            >
                ☀️
            </button>


            <?php if ($userLoggedIn): ?>

                <a
                    href="Dashboard.php"
                    class="btn btn-primary btn-small"
                >
                    Dashboard
                </a>

            <?php else: ?>

                <a
                    href="SignIn.php"
                    class="btn btn-primary btn-small"
                >
                    Sign In
                </a>

            <?php endif; ?>


            <button
                type="button"
                class="menu-btn"
                id="menuBtn"
                aria-label="Open menu"
            >
                ☰
            </button>

        </div>

    </div>

</header>


<!-- =========================================================
     HERO
========================================================= -->

<main id="home">

    <section class="hero">

        <div class="container hero-grid">

            <div class="hero-content">

                <div class="eyebrow">

                    <span class="eyebrow-dot"></span>

                    Step Into Your Style

                </div>


                <h1>
                    Your next
                    <span>iconic</span>
                    step.
                </h1>


                <p class="hero-description">
                    Discover a fresh collection of sneakers and
                    streetwear built for people who don't follow
                    the crowd. Find your pair. Define your style.
                </p>


                <div class="hero-buttons">

                    <?php if ($userLoggedIn): ?>

                        <a
                            href="Dashboard.php"
                            class="btn btn-primary"
                        >
                            Explore Collection →
                        </a>

                    <?php else: ?>

                        <a
                            href="SignUp.php"
                            class="btn btn-primary"
                        >
                            Start Shopping →
                        </a>

                    <?php endif; ?>


                    <a
                        href="#featured"
                        class="btn btn-outline"
                    >
                        View Featured
                    </a>

                </div>

            </div>


            <div class="hero-visual">

                <div class="shoe-stage">

                    <div class="floating-tag tag-one">
                        PREMIUM QUALITY
                    </div>


                    <div class="shoe-card">

                        <div class="shoe-symbol">
                            👟
                        </div>

                        <div class="shoe-name">
                            DUNKHOME
                        </div>

                    </div>


                    <div class="floating-tag tag-two">
                        STREET • STYLE • CULTURE
                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- =====================================================
         CATEGORIES
    ====================================================== -->

    <section id="categories">

        <div class="container">

            <div class="section-heading reveal">

                <div>

                    <div class="eyebrow">
                        Collections
                    </div>

                    <h2>
                        Find your pair.
                    </h2>

                </div>

                <p>
                    From everyday essentials to statement sneakers,
                    explore styles designed for every mood and moment.
                </p>

            </div>


            <div class="category-grid">

                <article class="category-card reveal">

                    <span class="category-number">
                        01
                    </span>

                    <div class="category-icon">
                        👟
                    </div>

                    <h3>
                        Sneakers
                    </h3>

                    <p>
                        Everyday sneakers with standout style.
                    </p>

                </article>


                <article class="category-card reveal">

                    <span class="category-number">
                        02
                    </span>

                    <div class="category-icon">
                        🏀
                    </div>

                    <h3>
                        Basketball
                    </h3>

                    <p>
                        Court-inspired silhouettes built for impact.
                    </p>

                </article>


                <article class="category-card reveal">

                    <span class="category-number">
                        03
                    </span>

                    <div class="category-icon">
                        🧢
                    </div>

                    <h3>
                        Streetwear
                    </h3>

                    <p>
                        Complete your look with modern street culture.
                    </p>

                </article>

            </div>

        </div>

    </section>


    <!-- =====================================================
         FEATURED
    ====================================================== -->

    <section id="featured">

        <div class="container">

            <div class="section-heading reveal">

                <div>

                    <div class="eyebrow">
                        Featured
                    </div>

                    <h2>
                        Made to stand out.
                    </h2>

                </div>

                <p>
                    A glimpse at the kind of energy we're bringing
                    to the DunkHome Kicks collection.
                </p>

            </div>


            <div class="products-grid">

                <?php if ($featuredProducts): ?>
                    <?php foreach ($featuredProducts as $product): ?>
                        <article class="product-card reveal">
                            <div class="product-image">
                                <img src="<?= htmlspecialchars((string)$product['image1'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8') ?>" style="width:100%;height:100%;object-fit:cover;">
                            </div>
                            <div class="product-content">
                                <div class="product-label"><?= htmlspecialchars((string)$product['category'], ENT_QUOTES, 'UTF-8') ?></div>
                                <h3><?= htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p><?= htmlspecialchars((string)$product['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                <div class="product-bottom">
                                    <span class="price">₹<?= number_format((float)$product['price'], 2) ?></span>
                                    <a href="ProductDetails.php?id=<?= (int)$product['id'] ?>" class="btn btn-outline btn-small">Discover</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <article class="product-card reveal">
                        <div class="product-image"><div class="product-shoe">👟</div></div>
                        <div class="product-content">
                            <div class="product-label">Collection</div>
                            <h3>Products coming soon</h3>
                            <p>Our administrator will add the first DunkHome Kicks products here.</p>
                            <div class="product-bottom"><span class="price">Explore</span><a href="Products.php" class="btn btn-outline btn-small">Browse</a></div>
                        </div>
                    </article>
                <?php endif; ?>

            </div>

        </div>

    </section>


    <!-- =====================================================
         PROMO
    ====================================================== -->

    <section>

        <div class="container">

            <div class="promo reveal">

                <div class="promo-content">

                    <div class="eyebrow">
                        DunkHome Kicks
                    </div>

                    <h2>
                        Style isn't worn.
                        It's owned.
                    </h2>

                    <p>
                        Build your collection around what makes
                        you different. Discover footwear that
                        matches your movement, your energy and
                        your everyday story.
                    </p>


                    <a
                        href="SignUp.php"
                        class="btn btn-primary"
                    >
                        Join DunkHome Kicks →
                    </a>

                </div>

            </div>

        </div>

    </section>


    <!-- =====================================================
         ABOUT
    ====================================================== -->

    <section id="about">

        <div class="container about-grid">

            <div class="about-visual reveal">

                <div class="about-circle">
                    👟
                </div>

            </div>


            <div class="about-content reveal">

                <div class="eyebrow">
                    About Us
                </div>

                <h2>
                    More than a shoe.
                    It's your identity.
                </h2>

                <p>
                    DunkHome Kicks is built around a simple idea:
                    footwear should feel as individual as the person
                    wearing it.
                </p>

                <p>
                    We combine modern streetwear energy with a
                    clean, premium digital experience to make
                    discovering your next pair effortless.
                </p>


                <div class="about-points">

                    <div class="about-point">

                        <span class="point-icon">
                            ✓
                        </span>

                        Modern sneaker collections

                    </div>


                    <div class="about-point">

                        <span class="point-icon">
                            ✓
                        </span>

                        Premium-focused designs

                    </div>


                    <div class="about-point">

                        <span class="point-icon">
                            ✓
                        </span>

                        Built for everyday style

                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- =====================================================
         CTA
    ====================================================== -->

    <section class="cta">

        <div class="container">

            <div class="cta-box reveal">

                <?php if ($userLoggedIn): ?>

                    <div class="eyebrow">
                        Welcome Back
                    </div>

                    <h2>
                        Ready to move,
                        <?php echo htmlspecialchars(
                            $userName,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>?
                    </h2>

                    <p>
                        Continue exploring your DunkHome Kicks
                        experience.
                    </p>

                    <div class="cta-buttons">

                        <a
                            href="Dashboard.php"
                            class="btn btn-primary"
                        >
                            Open Dashboard →
                        </a>

                    </div>

                <?php else: ?>

                    <div class="eyebrow">
                        Your Journey Starts Here
                    </div>

                    <h2>
                        Ready to make
                        your move?
                    </h2>

                    <p>
                        Create your DunkHome Kicks account and
                        step into a new way of discovering style.
                    </p>

                    <div class="cta-buttons">

                        <a
                            href="SignUp.php"
                            class="btn btn-primary"
                        >
                            Create Account →
                        </a>

                        <a
                            href="SignIn.php"
                            class="btn btn-outline"
                        >
                            Sign In
                        </a>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </section>

</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

    <div class="container">

        <div class="footer-grid">

            <div class="footer-brand">

                <a href="index.php" class="logo">

                    <img
                        src="image/logo.jpeg"
                        alt="DunkHome Kicks"
                        class="logo-image"
                        onerror="this.style.display='none';"
                    >

                    DunkHome <span>Kicks</span>

                </a>


                <p>
                    Premium sneaker culture, modern streetwear
                    and a digital experience designed around you.
                </p>

            </div>


            <div class="footer-column">

                <h4>
                    Explore
                </h4>

                <a href="#home">
                    Home
                </a>

                <a href="#categories">
                    Categories
                </a>

                <a href="Products.php">
                    Featured
                </a>

                <a href="Home.php#about">
                    About
                </a>

            </div>


            <div class="footer-column">

                <h4>
                    Account
                </h4>

                <a href="SignIn.php">
                    Sign In
                </a>

                <a href="SignUp.php">
                    Create Account
                </a>

                <?php if ($adminLoggedIn): ?>

                    <a href="AdminDashboard.php">
                        Admin Dashboard
                    </a>

                <?php else: ?>

                    <a href="AdminSignIn.php">
                        Admin Login
                    </a>

                <?php endif; ?>

            </div>


            <div class="footer-column">

                <h4>
                    Connect
                </h4>

                <a href="Home.php#about">
                    Our Story
                </a>

                <a href="Products.php">
                    Collections
                </a>

                <a href="SignUp.php">
                    Join Us
                </a>

            </div>

        </div>


        <div class="footer-bottom">

            <span>
                © <?php echo date('Y'); ?>
                DunkHome Kicks. All rights reserved.
            </span>

            <span>
                Step into your style.
            </span>

        </div>

    </div>

</footer>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

    const body = document.body;

    const header = document.getElementById("header");

    const themeToggle =
        document.getElementById("themeToggle");

    const menuBtn =
        document.getElementById("menuBtn");

    const navLinks =
        document.getElementById("navLinks");


    /* =========================================================
       THEME
    ========================================================= */

    const savedTheme =
        localStorage.getItem("dunkhome-theme");

    if (savedTheme === "light") {

        body.classList.add("light");

        themeToggle.textContent = "🌙";

    } else {

        themeToggle.textContent = "☀️";

    }


    themeToggle.addEventListener(
        "click",
        () => {

            body.classList.toggle("light");

            const isLight =
                body.classList.contains("light");

            localStorage.setItem(
                "dunkhome-theme",
                isLight
                    ? "light"
                    : "dark"
            );

            themeToggle.textContent =
                isLight
                    ? "🌙"
                    : "☀️";

        }
    );


    /* =========================================================
       MOBILE MENU
    ========================================================= */

    menuBtn.addEventListener(
        "click",
        () => {

            navLinks.classList.toggle("active");

            menuBtn.textContent =
                navLinks.classList.contains("active")
                    ? "✕"
                    : "☰";

        }
    );


    /* Close mobile menu after clicking link */

    document
        .querySelectorAll(".nav-links a")
        .forEach(link => {

            link.addEventListener(
                "click",
                () => {

                    navLinks.classList.remove(
                        "active"
                    );

                    menuBtn.textContent = "☰";

                }
            );

        });


    /* =========================================================
       HEADER SCROLL EFFECT
    ========================================================= */

    function handleHeader() {

        if (window.scrollY > 25) {

            header.classList.add("scrolled");

        } else {

            header.classList.remove("scrolled");

        }

    }

    window.addEventListener(
        "scroll",
        handleHeader
    );

    handleHeader();


    /* =========================================================
       SCROLL REVEAL
    ========================================================= */

    const revealElements =
        document.querySelectorAll(".reveal");


    const revealObserver =
        new IntersectionObserver(
            entries => {

                entries.forEach(entry => {

                    if (
                        entry.isIntersecting
                    ) {

                        entry.target.classList.add(
                            "visible"
                        );

                        revealObserver.unobserve(
                            entry.target
                        );

                    }

                });

            },
            {
                threshold: 0.12
            }
        );


    revealElements.forEach(element => {

        revealObserver.observe(element);

    });


    /* =========================================================
       SMOOTH ANCHOR SCROLL
    ========================================================= */

    document
        .querySelectorAll('a[href^="#"]')
        .forEach(anchor => {

            anchor.addEventListener(
                "click",
                function(event) {

                    const target =
                        document.querySelector(
                            this.getAttribute("href")
                        );

                    if (!target) {
                        return;
                    }

                    event.preventDefault();

                    const headerHeight =
                        header.offsetHeight;

                    const targetPosition =
                        target.getBoundingClientRect().top
                        +
                        window.scrollY
                        -
                        headerHeight
                        -
                        15;

                    window.scrollTo({
                        top: targetPosition,
                        behavior: "smooth"
                    });

                }
            );

        });


    /* =========================================================
       BUTTON RIPPLE
    ========================================================= */

    document
        .querySelectorAll(".btn")
        .forEach(button => {

            button.addEventListener(
                "click",
                function() {

                    this.style.transform =
                        "scale(0.97)";

                    setTimeout(() => {

                        this.style.transform = "";

                    }, 120);

                }
            );

        });

</script>

    <script src="assets/dunkhome-ui.js" defer></script>
</body>
</html>