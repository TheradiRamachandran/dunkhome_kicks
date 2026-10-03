<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

if (($_GET['ajax'] ?? '') === 'search') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $queryValue = $_GET['q'] ?? '';
    $query = is_string($queryValue) ? trim($queryValue) : '';
    $queryLength = function_exists('mb_strlen') ? mb_strlen($query) : strlen($query);
    if ($query === '' || $queryLength > 100) {
        echo json_encode(['categories' => [], 'products' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $searchPattern = '%' . $query . '%';
    $categoryStatement = $conn->prepare(
        'SELECT id, name FROM categories WHERE is_active = 1 AND name LIKE ? ORDER BY name LIMIT 5'
    );
    $productStatement = $conn->prepare(
        'SELECT id, name, category, price, image1 FROM products
         WHERE is_active = 1 AND (name LIKE ? OR category LIKE ? OR COALESCE(description, \'\') LIKE ?)
         ORDER BY created_at DESC, id DESC LIMIT 6'
    );
    if (!$categoryStatement || !$productStatement) {
        error_log('Homepage live search query preparation failed: ' . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Search is temporarily unavailable.']);
        exit;
    }

    $categoryStatement->bind_param('s', $searchPattern);
    if (!$categoryStatement->execute()) {
        error_log('Homepage live category search failed: ' . $categoryStatement->error);
        $categoryStatement->close();
        $productStatement->close();
        http_response_code(500);
        echo json_encode(['error' => 'Search is temporarily unavailable.']);
        exit;
    }
    $categoryResult = $categoryStatement->get_result();
    $searchCategories = $categoryResult ? $categoryResult->fetch_all(MYSQLI_ASSOC) : [];
    $categoryStatement->close();

    $productStatement->bind_param('sss', $searchPattern, $searchPattern, $searchPattern);
    if (!$productStatement->execute()) {
        error_log('Homepage live product search failed: ' . $productStatement->error);
        $productStatement->close();
        http_response_code(500);
        echo json_encode(['error' => 'Search is temporarily unavailable.']);
        exit;
    }
    $productResult = $productStatement->get_result();
    $searchProducts = $productResult ? $productResult->fetch_all(MYSQLI_ASSOC) : [];
    $productStatement->close();

    echo json_encode(
        ['categories' => $searchCategories, 'products' => $searchProducts],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Session Status
|--------------------------------------------------------------------------
| If a user or admin is already logged in, we can show the appropriate
| dashboard links.
*/

$userLoggedIn  = userLoggedIn();
$adminLoggedIn = adminLoggedIn();

$categories = [];
$categoryResult = $conn->query(
     'SELECT id, name, image
      FROM categories
      WHERE is_active = 1
      ORDER BY name'
);
if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

$products = [];
$productsByCategory = [];
$productsError = false;
$productResult = $conn->query(
    'SELECT id, name, category, price, description, image1, image2, image3
     FROM products
     WHERE is_active = 1
     ORDER BY category ASC, created_at DESC, id DESC'
);
if ($productResult) {
    while ($row = $productResult->fetch_assoc()) {
        $products[] = $row;
        $categoryName = trim((string) ($row['category'] ?? ''));
        if ($categoryName === '') {
            $categoryName = 'Other';
        }
        $categoryKey = function_exists('mb_strtolower') ? mb_strtolower($categoryName) : strtolower($categoryName);
        if (!isset($productsByCategory[$categoryKey])) {
            $productsByCategory[$categoryKey] = [
                'name' => $categoryName,
                'products' => [],
            ];
        }
        $productsByCategory[$categoryKey]['products'][] = $row;
    }
} else {
    error_log('Homepage product collection query failed: ' . $conn->error);
    $productsError = true;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/includes/favicon.php'; ?><base href="<?= h(appBaseUrl()) ?>">
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
        content="#0b1d16"
    >

    <title>DunkHome Kicks | Step Into Your Style</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=DM+Serif+Display&display=swap"
        rel="stylesheet"
    >

    <style>

        /* =========================================================
           ROOT
        ========================================================= */

        :root {
            --bg: #0b1d16;
            --bg-secondary: #10271e;
            --card: rgba(23, 51, 41, 0.9);
            --card-solid: #173329;

            --text: #f1f5f0;
            --muted: #a2b5aa;

            --green: #83e6b1;
            --green-dark: #347c58;
            --green-soft: #c7f1d9;
            --green-secondary: #4fd69a;
            --accent-warm: #cdb27e;

            --border: rgba(131, 230, 177, 0.16);

            --white: #ffffff;
            --black: #000000;

            --shadow:
                0 28px 90px rgba(0, 0, 0, 0.32);

            --radius-lg: 30px;
            --radius-md: 18px;

            --transition: 0.3s ease;
        }

        body.light {
            --bg: #f3f6f1;
            --bg-secondary: #ffffff;
            --card: rgba(255, 255, 255, 0.92);
            --card-solid: #ffffff;

            --text: #19291f;
            --muted: #5f7265;
            --green: #2d8054;
            --green-dark: #236847;
            --green-soft: #2d6b48;
            --green-secondary: #347e57;

            --border: rgba(25, 43, 32, 0.12);

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

            font-family: "Manrope", sans-serif;

            color: var(--text);
            background:
                radial-gradient(
                    circle at 15% 10%,
                    rgba(131, 230, 177, 0.09),
                    transparent 34%
                ),
                radial-gradient(
                    circle at 85% 25%,
                    rgba(79, 214, 154, 0.035),
                    transparent 28%
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

        .dh-user-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            padding: 18px 0 10px;
            background: transparent;
            transition: background var(--transition), border-color var(--transition), backdrop-filter var(--transition);
        }

        .dh-user-header.scrolled {
            background: rgba(11, 29, 22, 0.84);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
        }

        body.light .dh-user-header.scrolled {
            background: rgba(243, 246, 241, 0.9);
        }

        .navbar {
            position: relative;
            min-height: 78px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 12px 18px;
            border-radius: 26px;
            background: rgba(16, 39, 30, 0.9);
            border: 1px solid rgba(131, 230, 177, 0.2);
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.18);
            backdrop-filter: blur(18px);
        }

        body.light .navbar {
            background: rgba(255, 255, 255, 0.75);
            border-color: rgba(16, 32, 22, 0.08);
            box-shadow: 0 18px 45px rgba(16, 32, 22, 0.08);
        }


        /* =========================================================
           LOGO
        ========================================================= */

        .logo {
            display: flex;
            align-items: center;
            gap: 11px;
            font-family: "Manrope", sans-serif;
            font-size: 21px;
            font-weight: 800;
            letter-spacing: 0;
            white-space: nowrap;
            transition: transform var(--transition);
        }

        .logo:hover {
            transform: translateY(-1px);
        }

        .logo-image {
            width: 43px;
            height: 43px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid var(--border);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.24);
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
            justify-content: center;
            gap: 4px;
            padding: 5px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.035);
            border: 1px solid rgba(131, 230, 177, 0.14);
            margin: 0 auto;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.035);
        }

        .nav-links a {
            position: relative;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            padding: 10px 13px;
            border-radius: 999px;
            white-space: nowrap;
            transition: color var(--transition), background var(--transition), transform var(--transition), box-shadow var(--transition);
        }

        .nav-links a:hover {
            color: var(--green-soft);
            background: rgba(131, 230, 177, 0.11);
            transform: translateY(-1px);
            box-shadow: inset 0 0 0 1px rgba(131, 230, 177, 0.08);
        }

        body.light .nav-links a:hover {
            color: #174d34;
        }

        .nav-links a::after {
            content: "";
            position: absolute;
            left: 14px;
            right: 14px;
            bottom: 7px;
            height: 2px;
            border-radius: 999px;
            background: var(--green);
            transform: scaleX(0);
            transform-origin: center;
            transition: transform var(--transition);
        }

        .nav-links a:hover::after {
            transform: scaleX(1);
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
            width: 46px;
            height: 46px;
            border: 1px solid rgba(131, 230, 177, 0.24);
            border-radius: 50%;
            background: linear-gradient(
                135deg,
                rgba(255, 255, 255, 0.12),
                rgba(131, 230, 177, 0.08)
            );
            color: var(--text);
            cursor: pointer;
            display: grid;
            place-items: center;
            font-size: 18px;
            backdrop-filter: blur(16px);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.12),
                0 12px 30px rgba(0, 0, 0, 0.16);
            transition: transform var(--transition), border-color var(--transition), background var(--transition), box-shadow var(--transition), filter var(--transition);
        }

        .theme-btn:hover,
        .menu-btn:hover {
            border-color: rgba(131, 230, 177, 0.55);
            transform: translateY(-2px) scale(1.04);
            background: linear-gradient(
                135deg,
                rgba(131, 230, 177, 0.18),
                rgba(255, 255, 255, 0.08)
            );
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.18),
                0 16px 35px rgba(79, 214, 154, 0.16);
        }

        .theme-btn:hover {
            rotate: -5deg;
        }

        .theme-btn:focus-visible,
        .menu-btn:focus-visible,
        .nav-links a:focus-visible,
        .logo:focus-visible {
            outline: 2px solid var(--green);
            outline-offset: 3px;
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
            color: #07140d;

            background: linear-gradient(
                135deg,
                #83e6b1,
                #4fd69a
            );

            box-shadow:
                0 12px 28px rgba(79, 214, 154, 0.16);
        }

        .btn-primary:hover {
            box-shadow:
                0 17px 38px rgba(79, 214, 154, 0.22);
        }

        .hero-buttons .btn-primary span {
            display: inline-block;
            transition: transform var(--transition);
        }

        .hero-buttons .btn-primary:hover span {
            transform: translateX(4px);
        }

        .btn-outline {
            color: var(--text);

            border-color: var(--border);

            background: rgba(255,255,255,0.025);
        }

        .btn-outline:hover {
            border-color: rgba(131, 230, 177, 0.42);
            background: rgba(131, 230, 177, 0.07);
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
            flex-direction: column;
            justify-content: center;
            align-items: stretch;

            overflow: clip;

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

            background: rgba(131, 230, 177, 0.075);

            filter: blur(90px);

            border-radius: 50%;

            pointer-events: none;
        }

        .category-shortcuts {
            width: min(1180px, calc(100% - 36px));
            margin: 0 auto 32px;
            padding: 16px 0 13px;
            border-top: 1px solid rgba(131, 230, 177, 0.12);
            border-bottom: 1px solid rgba(131, 230, 177, 0.12);
        }

        .category-shortcuts-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 14px;
        }

        .category-shortcuts-tools {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .category-shortcuts-heading strong {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .category-shortcut-count {
            color: var(--green);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0;
        }

        .category-shortcuts-heading strong::before {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 12px rgba(131, 230, 177, 0.48);
            content: "";
        }

        .category-clear-filter {
            color: var(--green);
            font-size: 11px;
            font-weight: 800;
        }

        .category-clear-filter:hover {
            color: var(--text);
        }

        .category-search {
            min-height: 36px;
            width: 220px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 11px;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.045);
            color: var(--muted);
            transition: border-color var(--transition), background var(--transition), box-shadow var(--transition);
        }

        .category-search:focus-within {
            border-color: rgba(131, 230, 177, 0.58);
            background: rgba(131, 230, 177, 0.08);
            box-shadow: 0 0 0 3px rgba(131, 230, 177, 0.09);
        }

        .category-search-label {
            flex: 0 0 auto;
            color: var(--green);
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .category-search input {
            width: 100%;
            min-width: 0;
            border: 0;
            outline: 0;
            background: transparent;
            color: var(--text);
            font: 600 11px "Manrope", sans-serif;
        }

        .category-search input::placeholder {
            color: var(--muted);
            opacity: 0.8;
        }

        .category-search-clear {
            width: 30px;
            height: 30px;
            display: grid;
            flex: 0 0 30px;
            place-items: center;
            border: 1px solid var(--border);
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.04);
            color: var(--muted);
            cursor: pointer;
            font-size: 16px;
            transition: color var(--transition), background var(--transition), transform var(--transition);
        }

        .category-search-clear:hover {
            transform: rotate(90deg);
            background: rgba(131, 230, 177, 0.12);
            color: var(--green);
        }

        .category-search:focus-within,
        .category-search-clear:focus-visible {
            outline: 2px solid rgba(114, 228, 167, 0.72);
            outline-offset: 3px;
        }

        .category-shortcut-list {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            padding: 5px 4px 9px;
            scrollbar-width: none;
            scroll-behavior: smooth;
            scroll-snap-type: x proximity;
        }

        .category-shortcut-list::-webkit-scrollbar {
            display: none;
        }

        .category-shortcut {
            display: flex;
            flex: 0 0 80px;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            text-align: center;
            scroll-snap-align: start;
            animation: categoryShortcutEnter 0.5s ease both;
            animation-delay: calc(var(--category-index, 0) * 35ms);
            transition: color var(--transition), transform var(--transition);
        }

        @keyframes categoryShortcutEnter {
            from { opacity: 0; transform: translateY(9px) scale(0.94); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .category-shortcut-image {
            position: relative;
            width: 66px;
            height: 66px;
            display: grid;
            place-items: center;
            overflow: hidden;
            border: 2px solid rgba(131, 230, 177, 0.2);
            border-radius: 50%;
            background: linear-gradient(145deg, rgba(131, 230, 177, 0.16), rgba(255, 255, 255, 0.04));
            color: var(--green);
            font-size: 20px;
            font-weight: 800;
            box-shadow: inset 0 0 0 3px rgba(7, 16, 11, 0.18);
            transition: transform var(--transition), border-color var(--transition), box-shadow var(--transition);
        }

        .category-shortcut:nth-child(3n + 2) .category-shortcut-image {
            border-color: rgba(255, 201, 139, 0.42);
        }

        .category-shortcut:nth-child(3n) .category-shortcut-image {
            border-color: rgba(131, 230, 177, 0.5);
        }

        .category-shortcut-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .category-shortcut-initial {
            position: absolute;
            z-index: 1;
            top: 50%;
            left: 50%;
            width: 25px;
            height: 25px;
            display: grid;
            place-items: center;
            transform: translate(-50%, -50%);
            border: 1px solid rgba(255, 255, 255, 0.38);
            border-radius: 50%;
            background: rgba(7, 16, 11, 0.64);
            color: #f5faf7;
            font-size: 10px;
            font-weight: 800;
            backdrop-filter: blur(8px);
        }

        body.light .category-shortcut-initial {
            border-color: rgba(255, 255, 255, 0.82);
            background: rgba(16, 54, 36, 0.72);
            color: #fff;
        }

        .category-shortcut:hover,
        .category-shortcut[aria-current="page"] {
            color: var(--green-soft);
            transform: translateY(-2px);
        }

        .category-shortcut:hover .category-shortcut-image,
        .category-shortcut[aria-current="page"] .category-shortcut-image {
            transform: scale(1.06);
            border-color: var(--accent-warm);
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.1), rgba(131, 230, 177, 0.24));
            box-shadow: 0 0 0 4px rgba(131, 230, 177, 0.14), 0 12px 28px rgba(11, 29, 22, 0.2);
        }

        .category-shortcut[aria-current="page"] .category-shortcut-image {
            border-color: var(--green);
            box-shadow: 0 0 0 4px rgba(131, 230, 177, 0.2), 0 0 28px rgba(131, 230, 177, 0.22);
        }

        body.light .category-shortcut {
            color: #53665b;
        }

        body.light .category-shortcut:hover,
        body.light .category-shortcut[aria-current="page"] {
            color: #174d34;
        }

        body.light .category-shortcut-image {
            border-color: rgba(45, 122, 87, 0.3);
            background: linear-gradient(145deg, rgba(45, 122, 87, 0.14), rgba(255, 201, 139, 0.12));
            color: #236844;
            box-shadow: inset 0 0 0 3px rgba(255, 255, 255, 0.42);
        }

        body.light .category-shortcut:hover .category-shortcut-image,
        body.light .category-shortcut[aria-current="page"] .category-shortcut-image {
            border-color: #2d7a57;
            background: linear-gradient(145deg, rgba(45, 122, 87, 0.22), rgba(255, 201, 139, 0.24));
            box-shadow: 0 0 0 4px rgba(45, 122, 87, 0.12), 0 10px 24px rgba(16, 32, 22, 0.14);
        }

        .category-shortcut[hidden],
        .category-search-clear[hidden],
        .category-search-empty[hidden] {
            display: none;
        }

        .category-search-empty {
            padding: 10px 4px 4px;
            color: var(--muted);
            font-size: 11px;
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            .hero-content,
            .shoe-stage,
            .category-shortcut,
            .category-shortcut-image,
            .product-card,
            .product-image img,
            .hero-buttons .btn-primary span {
                animation: none !important;
                transition: none !important;
            }
        }

        .category-shortcut-name {
            width: 100%;
            overflow: hidden;
            font-size: 10px;
            font-weight: 700;
            line-height: 1.3;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .category-shortcut:focus-visible {
            outline: 2px solid var(--green);
            outline-offset: 5px;
            border-radius: 12px;
        }

        .hero {
            position: relative;
            padding: 160px 0 70px;
            overflow: hidden;
            background:
                radial-gradient(circle at 16% 18%, rgba(121, 230, 170, 0.12), transparent 20%),
                radial-gradient(circle at 82% 22%, rgba(121, 230, 170, 0.10), transparent 18%),
                linear-gradient(180deg, rgba(4, 19, 13, 1) 0%, rgba(7, 21, 16, 1) 45%, rgba(8, 24, 18, 1) 100%);
        }

        .hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(255,255,255,0.02) 0, rgba(255,255,255,0) 18%, rgba(255,255,255,0.02) 32%, rgba(255,255,255,0) 48%),
                radial-gradient(circle at 50% 80%, rgba(11, 38, 31, 0.85), transparent 28%);
            pointer-events: none;
        }

        .hero-grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1.02fr 0.98fr;
            gap: 18px;
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
            border: 1px solid rgba(131, 230, 177, 0.26);
            background: rgba(131, 230, 177, 0.06);
            color: var(--green-soft);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            margin-bottom: 28px;
        }

        .eyebrow-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 12px rgba(131, 230, 177, 0.8);
        }

        body.light .eyebrow {
            color: #286b49;
            border-color: rgba(45, 122, 87, 0.22);
            background: rgba(45, 122, 87, 0.08);
        }

        .hero h1 {
            max-width: 620px;
            font-family: "DM Serif Display", serif;
            font-size: clamp(3.5rem, 6vw, 6rem);
            line-height: 0.88;
            letter-spacing: -0.06em;
            margin-bottom: 28px;
            color: #f1f5f0;
        }

        .hero h1 span {
            display: block;
            color: var(--green);
        }

        body.light .hero {
            background:
                radial-gradient(circle at 16% 18%, rgba(82, 162, 110, 0.18), transparent 20%),
                radial-gradient(circle at 82% 22%, rgba(82, 162, 110, 0.12), transparent 18%),
                linear-gradient(180deg, rgba(243, 248, 244, 1) 0%, rgba(236, 243, 238, 1) 45%, rgba(229, 238, 232, 1) 100%);
        }

        body.light .hero::before {
            background:
                linear-gradient(90deg, rgba(20, 44, 31, 0.02) 0, rgba(20, 44, 31, 0) 18%, rgba(20, 44, 31, 0.02) 32%, rgba(20, 44, 31, 0) 48%),
                radial-gradient(circle at 50% 80%, rgba(119, 161, 132, 0.18), transparent 28%);
        }

        body.light .hero h1,
        body.light .hero-description,
        body.light .hero-feature,
        body.light .hero-bottomline {
            color: #102016;
        }

        body.light .hero h1 span {
            color: #1f7c52;
        }

        body.light .hero .eyebrow {
            color: #1f7c52;
            border-color: rgba(31, 124, 82, 0.18);
            background: rgba(31, 124, 82, 0.08);
        }

        body.light .hero-feature-icon {
            border-color: rgba(31, 124, 82, 0.25);
            background: rgba(31, 124, 82, 0.08);
            color: #1f7c52;
        }

        .hero-description {
            max-width: 580px;
            color: rgba(241, 245, 240, 0.85);
            font-size: 1.05rem;
            line-height: 1.7;
            margin-bottom: 34px;
        }

        .hero-features {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            max-width: 560px;
            margin-bottom: 30px;
        }

        .hero-feature {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            color: rgba(241, 245, 240, 0.82);
            font-size: 10px;
            font-weight: 800;
            line-height: 1.25;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            text-align: center;
        }

        .hero-feature-icon {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 1px solid rgba(131, 230, 177, 0.34);
            background: rgba(131, 230, 177, 0.06);
            color: var(--green);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.03);
        }

        .hero-feature-icon svg {
            width: 18px;
            height: 18px;
        }

        .hero-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }

        .hero-buttons .btn-primary {
            min-width: 260px;
            min-height: 60px;
            padding: 0 28px;
            border-radius: 999px;
            font-size: 1rem;
            box-shadow: 0 18px 36px rgba(79, 214, 154, 0.2);
        }

        .hero-highlights {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .stat-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.02);
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
            backdrop-filter: blur(12px);
        }

        .stat-pill strong {
            color: var(--text);
            font-size: 13px;
        }

        .stat-pill .dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--green), var(--accent-warm));
            box-shadow: 0 0 18px rgba(131, 230, 177, 0.5);
        }

        .hero-bottomline {
            display: flex;
            align-items: center;
            gap: 18px;
            width: min(100%, 500px);
            padding-top: 18px;
            margin-top: 10px;
            border-top: 1px solid rgba(131, 230, 177, 0.18);
            color: rgba(241, 245, 240, 0.72);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .hero-bottomline i {
            flex: 1;
            height: 1px;
            background: rgba(131, 230, 177, 0.18);
        }


        /* =========================================================
           HERO VISUAL
        ========================================================= */

        .hero-visual {
            position: relative;
            width: 100%;
            min-height: 0;
            display: grid;
            place-items: center;
            justify-self: end;
            isolation: isolate;
        }

        .hero-visual::before {
            content: "";
            position: absolute;
            inset: 18% 18% 4% 16%;
            background: radial-gradient(circle, rgba(131, 230, 177, 0.24), transparent 44%);
            filter: blur(12px);
            border-radius: 50%;
        }

        .hero-visual-ring {
            position: absolute;
            border: 2px solid rgba(132, 230, 176, 0.9);
            border-radius: 50%;
            box-shadow: 0 0 24px rgba(121, 230, 170, 0.1), inset 0 0 18px rgba(121, 230, 170, 0.08);
            pointer-events: none;
        }

        .hero-visual-ring-one {
            width: 72%;
            height: 72%;
            left: 16%;
            top: 16%;
            transform: rotate(-10deg);
        }

        .hero-visual-ring-two {
            width: 88%;
            height: 88%;
            left: 7%;
            top: 7%;
            transform: rotate(12deg);
            border-color: rgba(132, 230, 176, 0.62);
        }

        .shoe-stage {
            position: relative;
            width: min(100%, 660px);
            aspect-ratio: 1;
            margin-left: auto;
            display: grid;
            place-items: center;
            padding: 28px;
            border-radius: 36px;
            border: 0;
            background: transparent;
            box-shadow: none;
            animation: stageReveal 0.85s 0.12s ease both;
        }

        @keyframes stageReveal {
            from { opacity: 0; transform: translateY(14px) scale(0.985); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .hero-shoe-art {
            display: block;
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            border-radius: 14px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.24);
        }

        .hero-visual .hero-shoe-art {
            height: auto;
            aspect-ratio: 3 / 2;
            border: 1px solid rgba(131, 230, 177, 0.18);
            border-radius: 24px;
            box-shadow: 0 30px 75px rgba(0, 0, 0, 0.32), 0 0 48px rgba(79, 214, 154, 0.12);
        }

        .hero-art-note {
            position: absolute;
            right: 4%;
            bottom: 15%;
            z-index: 2;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: rgba(241, 245, 240, 0.82);
            text-align: left;
            line-height: 1.2;
            transform: rotate(-6deg);
        }

        .hero-art-note em {
            font-style: normal;
            color: var(--green);
        }

        .hero-slide-indicators {
            position: absolute;
            right: 0;
            bottom: 4%;
            display: flex;
            gap: 10px;
        }

        .hero-slide-indicators span {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(241, 245, 240, 0.2);
            display: block;
        }

        .hero-slide-indicators .active {
            background: var(--green);
            box-shadow: 0 0 12px rgba(121, 230, 170, 0.8);
        }

        .dh-user-search {
            position: relative;
            display: flex;
            flex: 0 1 190px;
            align-items: center;
            gap: 8px;
            min-width: 150px;
            min-height: 42px;
            padding: 0 8px 0 12px;
            border: 1px solid rgba(114, 228, 167, 0.22);
            border-radius: 999px;
            background: rgba(7, 22, 15, 0.36);
        }

        .dh-user-search-label {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            clip-path: inset(50%);
        }

        .dh-user-search input {
            width: 100%;
            min-width: 0;
            padding: 0;
            border: 0;
            outline: 0;
            background: transparent;
            color: var(--text);
            font: 600 11px "Manrope", sans-serif;
        }

        .dh-user-search input::placeholder {
            color: var(--muted);
        }

        .dh-user-search button {
            flex: 0 0 28px;
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            padding: 0;
            border: 0;
            border-radius: 50%;
            background: transparent;
            color: var(--green);
            cursor: pointer;
        }

        .dh-user-search button svg {
            width: 18px;
            height: 18px;
        }

        .dh-user-search:focus-within {
            border-color: rgba(131, 230, 177, 0.65);
            box-shadow: 0 0 0 3px rgba(131, 230, 177, 0.1);
        }

        body.light .dh-user-search {
            background: rgba(45, 122, 87, 0.05);
            border-color: rgba(45, 122, 87, 0.2);
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
            padding-bottom: 18px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .section-heading h2 {
            font-family: "DM Serif Display", serif;

            font-size: clamp(32px, 5vw, 48px);

            letter-spacing: -1.5px;
        }

        .section-heading p {
            max-width: 480px;

            color: var(--muted);

            line-height: 1.7;
        }

        body.light .section-heading {
            border-bottom-color: rgba(25, 43, 32, 0.12);
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

            background: linear-gradient(180deg, rgba(24, 39, 32, 0.86), rgba(12, 21, 18, 0.88));

            padding: 28px;
            box-shadow: 0 18px 38px rgba(0,0,0,0.12);

            transition:
                transform var(--transition),
                border-color var(--transition),
                background var(--transition),
                box-shadow var(--transition);
        }

        .category-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 28px 50px rgba(0,0,0,0.2);

            border-color:
                rgba(143, 233, 181, 0.42);

            background:
                linear-gradient(180deg, rgba(32, 52, 44, 0.95), rgba(16, 26, 21, 0.95));
        }

        .category-icon {
            width: 54px;
            height: 54px;

            display: grid;
            place-items: center;

            border-radius: 16px;

            background:
                rgba(121,230,170,0.09);

            font-size: 27px;

            margin-bottom: 45px;
        }

        .category-card h3 {
            font-family: "DM Serif Display", serif;

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

            background: linear-gradient(180deg, rgba(19, 32, 28, 0.78), rgba(8, 16, 13, 0.87));
            box-shadow: 0 18px 38px rgba(0,0,0,0.12);

            transition:
                transform var(--transition),
                border-color var(--transition),
                box-shadow var(--transition);
        }

        .product-card:hover {
            transform: translateY(-7px);
            box-shadow: 0 28px 50px rgba(0,0,0,0.18);

            border-color:
                rgba(131, 230, 177, 0.35);
        }

        .product-image {
            height: 270px;

            display: grid;
            place-items: center;

            background:
                radial-gradient(
                    circle at center,
                    rgba(121,230,170,0.13),
                    transparent 65%
                );

            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .product-card:hover .product-image img {
            transform: scale(1.06);
        }

        body.light .category-card,
        body.light .product-card {
            background: linear-gradient(180deg, #ffffff, #f0f6f2);
        }

        body.light .category-card:hover,
        body.light .product-card:hover {
            background: linear-gradient(180deg, #ffffff, #eaf5ee);
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

        .product-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 9px;
        }

        .product-meta .product-label {
            min-width: 0;
            overflow: hidden;
            margin-bottom: 0;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .product-sample-badge {
            flex: 0 0 auto;
            padding: 4px 7px;
            border: 1px solid rgba(255, 201, 139, 0.34);
            border-radius: 999px;
            background: rgba(255, 201, 139, 0.1);
            color: var(--accent-warm);
            font-size: 9px;
            font-weight: 800;
            line-height: 1;
            text-transform: uppercase;
        }

        body.light .product-sample-badge {
            border-color: rgba(137, 83, 29, 0.22);
            background: rgba(255, 201, 139, 0.25);
            color: #80501e;
        }

        .product-label {
            color: var(--green);

            font-size: 11px;

            font-weight: 800;

            text-transform: uppercase;

            letter-spacing: 1px;

            margin-bottom: 8px;
        }

        body.light .product-label {
            color: #2d7a57;
        }

        .product-content h3 {
            font-family: "DM Serif Display", serif;

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
                    rgba(121,230,170,0.14),
                    rgba(255,255,255,0.025)
                );
        }

        .promo::after {
            content: "";

            position: absolute;

            width: 320px;
            height: 320px;

            right: 0;
            top: -100px;

            border-radius: 50%;

            background:
                rgba(121,230,170,0.12);

            filter: blur(50px);
        }

        .promo-content {
            position: relative;

            z-index: 2;

            max-width: 650px;
        }

        .promo h2 {
            font-family: "DM Serif Display", serif;

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
                    rgba(121,230,170,0.10),
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
                rgba(121,230,170,0.12);
        }

        .about-content h2 {
            font-family: "DM Serif Display", serif;

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
                rgba(121,230,170,0.10);
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
                    rgba(121,230,170,0.11),
                    transparent 60%
                );
        }

        .cta h2 {
            font-family: "DM Serif Display", serif;

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

        @media (max-width: 1020px) {

            .menu-btn {
                display: grid;
            }

            .nav-links {
                position: fixed;

                top: 96px;

                left: 18px;
                right: 18px;
                z-index: 1001;

                padding: 18px;

                display: none;

                flex-direction: column;

                align-items: stretch;

                gap: 5px;

                background: var(--bg-secondary);

                backdrop-filter: blur(22px);
                -webkit-backdrop-filter: blur(22px);

                border:
                    1px solid var(--border);

                border-radius: 18px;

                box-shadow: var(--shadow);
                margin: 0;
            }

            .nav-links.active {
                display: flex;
            }

            .nav-links a {
                padding: 14px 15px;
                border-radius: 12px;
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
                width: min(100%, 760px);
                margin: auto;
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
                gap: 10px;
                padding: 10px 12px;
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

            .header-actions {
                gap: 8px;
            }

            .nav-links {
                top: 86px;
                left: 12px;
                right: 12px;
            }

            .hero {
                padding:
                    115px 0
                    65px;
            }

            .category-shortcuts {
                width: calc(100% - 24px);
                margin-bottom: 22px;
            }

            .category-shortcuts-heading {
                align-items: flex-start;
                flex-direction: column;
                gap: 10px;
            }

            .category-shortcuts-tools {
                width: 100%;
            }

            .category-search {
                flex: 1;
                width: auto;
            }

            .category-shortcut-list {
                gap: 14px;
            }

            .category-shortcut {
                flex-basis: 72px;
            }

            .category-shortcut-image {
                width: 60px;
                height: 60px;
            }

            .hero h1 {
                font-size: clamp(3rem, 10vw, 3.5rem);
                letter-spacing: -0.04em;
            }

            .hero-description {
                font-size: 15px;
            }

            .hero-buttons {
                display: grid;

                grid-template-columns: 1fr;
            }

            .hero-buttons .btn {
                min-height: 56px;
            }

            .btn {
                width: 100%;
            }

            .hero-visual {
                display: grid;
                width: 100%;
                margin-top: 4px;
            }

            .shoe-stage {
                width: 100%;
            }

            .hero-visual-ring {
                display: none;
            }

            .hero-visual .hero-shoe-art {
                border-radius: 18px;
            }

            .hero-slide-indicators {
                right: 12px;
                bottom: 12px;
            }

            .dh-user-search {
                flex: 0 0 auto;
                width: 100%;
                min-width: 0;
                margin: 4px 0;
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

            .category-grid {
                grid-template-columns: 1fr;
            }

            .products-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .category-card {
                min-height: 200px;
            }

            .product-image {
                height: auto;
                aspect-ratio: 1 / 1;
            }

            .product-content {
                padding: 13px;
            }

            .product-content h3 {
                min-height: 40px;
                font-size: 16px;
            }

            .product-content p {
                display: -webkit-box;
                min-height: 34px;
                margin-bottom: 12px;
                overflow: hidden;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 2;
            }

            .product-bottom {
                align-items: stretch;
                flex-direction: column;
                gap: 8px;
            }

            .product-bottom .btn {
                min-height: 36px;
                padding: 0 9px;
                font-size: 11px;
            }

            .product-bottom .price {
                font-size: 14px;
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

<style>
/* ================================================================
   DUNKHOME KICKS — CINEMATIC FOREST EDITION
   Drop-in visual layer: no database, auth, routes or existing assets changed.
================================================================ */
:root{
    --dk-bg:#06140f;
    --dk-bg-2:#0a2118;
    --dk-panel:rgba(15,39,29,.68);
    --dk-panel-strong:#102c21;
    --dk-line:rgba(117,247,199,.14);
    --dk-line-strong:rgba(117,247,199,.30);
    --dk-mint:#75f7c7;
    --dk-emerald:#18d995;
    --dk-sage:#89a99b;
    --dk-cream:#f3f6ef;
    --dk-muted:#a5b8ae;
    --dk-serif:"DM Serif Display",Georgia,serif;
    --dk-radius:28px;
    --dk-shadow:0 30px 100px rgba(0,0,0,.38);
}
body{
    background:
      radial-gradient(900px 520px at 82% 8%,rgba(24,217,149,.12),transparent 62%),
      radial-gradient(700px 480px at 8% 35%,rgba(117,247,199,.055),transparent 64%),
      linear-gradient(180deg,#06140f 0%,#071a13 42%,#06130f 100%);
}
body.light{
    --dk-bg:#eef4ef;--dk-bg-2:#e6efe9;--dk-panel:rgba(255,255,255,.74);
    --dk-panel-strong:#fff;--dk-line:rgba(16,74,51,.12);--dk-line-strong:rgba(16,115,77,.25);
    --dk-mint:#197b59;--dk-emerald:#1c9569;--dk-sage:#567166;--dk-cream:#10241b;--dk-muted:#5b7066;
    background:linear-gradient(180deg,#f2f6f3,#eaf2ed 55%,#f3f6f4);
}
body:before{
    content:"";position:fixed;inset:0;pointer-events:none;z-index:-1;opacity:.34;
    background-image:radial-gradient(rgba(255,255,255,.08) 1px,transparent 1px);
    background-size:38px 38px;mask-image:linear-gradient(to bottom,black,transparent 72%);
}
.dk-container{width:min(1240px,calc(100% - 36px));margin-inline:auto}
.dk-main{position:relative;overflow:hidden}
.dk-hero{position:relative;min-height:calc(100svh - 10px);display:grid;align-items:center;padding:148px 0 82px}
.dk-hero:before{content:"";position:absolute;width:58vw;height:58vw;right:-25vw;top:8%;border:1px solid rgba(117,247,199,.11);border-radius:50%;box-shadow:0 0 100px rgba(24,217,149,.06);pointer-events:none}
.dk-hero:after{content:"";position:absolute;width:34vw;height:34vw;right:-12vw;top:22%;border:1px solid rgba(117,247,199,.08);border-radius:50%;pointer-events:none}
.dk-hero-grid{display:grid;grid-template-columns:minmax(0,1.03fr) minmax(420px,.97fr);gap:46px;align-items:center;position:relative;z-index:1}
.dk-kicker{display:inline-flex;align-items:center;gap:10px;padding:8px 12px;border:1px solid var(--dk-line-strong);border-radius:999px;background:rgba(9,31,23,.55);color:var(--dk-mint);font-size:11px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;backdrop-filter:blur(14px)}
body.light .dk-kicker{background:rgba(255,255,255,.65)}
.dk-kicker i{width:7px;height:7px;border-radius:50%;background:var(--dk-mint);box-shadow:0 0 16px var(--dk-mint);animation:dkPulse 2.4s ease-in-out infinite}
.dk-hero h1{margin:24px 0 22px;font-family:var(--dk-serif);font-weight:400;font-size:clamp(58px,7.5vw,112px);line-height:.88;letter-spacing:-.045em;max-width:760px}
.dk-hero h1 em{display:block;color:var(--dk-mint);font-style:normal}
.dk-hero-copy{max-width:590px;color:var(--dk-muted);font-size:16px;line-height:1.8}
.dk-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:30px}
.dk-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:48px;padding:0 19px;border-radius:999px;border:1px solid var(--dk-line-strong);font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;transition:transform .28s ease,background .28s ease,border-color .28s ease,box-shadow .28s ease}
.dk-btn:hover{transform:translateY(-3px);border-color:var(--dk-mint);box-shadow:0 12px 30px rgba(24,217,149,.12)}
.dk-btn-primary{background:var(--dk-mint);color:#062017;border-color:var(--dk-mint)}
.dk-btn-primary:hover{background:#a2ffdc;color:#062017}
.dk-btn-ghost{background:rgba(255,255,255,.025);color:var(--dk-cream);backdrop-filter:blur(14px)}
body.light .dk-btn-ghost{color:var(--dk-cream);background:rgba(255,255,255,.6)}
.dk-benefits{display:grid;grid-template-columns:repeat(4,1fr);gap:9px;margin-top:42px;max-width:700px}
.dk-benefit{padding:13px 12px;border-top:1px solid var(--dk-line);color:var(--dk-muted);font-size:10px;line-height:1.35;text-transform:uppercase;letter-spacing:.08em}
.dk-benefit strong{display:block;color:var(--dk-cream);font-size:10px;margin-bottom:4px}
.dk-hero-visual{min-height:620px;position:relative;display:grid;place-items:center}
.dk-hero-art{position:relative;width:min(570px,100%);aspect-ratio:1.26/1;overflow:hidden;border:1px solid rgba(117,247,199,.22);border-radius:30px;background:#071b13 url("image/homepage-hero-art.svg") center/cover no-repeat;box-shadow:0 32px 90px rgba(0,0,0,.3),0 0 70px rgba(24,217,149,.08);animation:dkFloat 8s ease-in-out infinite}
.dk-hero-art:after{position:absolute;inset:0;border:1px solid rgba(255,255,255,.08);border-radius:inherit;content:"";pointer-events:none}
.dk-hero-art-label{position:absolute;right:16px;top:16px;z-index:1;padding:9px 12px;border:1px solid rgba(202,255,224,.2);border-radius:999px;background:rgba(4,19,13,.58);color:#dbf9e5;font-size:9px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;backdrop-filter:blur(12px)}
.dk-visual-label{position:absolute;right:0;bottom:4%;writing-mode:vertical-rl;transform:rotate(180deg);color:var(--dk-sage);font-size:10px;letter-spacing:.35em;text-transform:uppercase;z-index:3}
.dk-all-products{padding:36px 0 90px}
.dk-all-products-head{display:flex;align-items:end;justify-content:space-between;gap:18px;margin-bottom:22px;padding-bottom:16px;border-bottom:1px solid var(--dk-line)}
.dk-all-products-head h2{font-family:var(--dk-serif);font-size:clamp(30px,4vw,48px);font-weight:400;line-height:1}
.dk-all-products-head p{margin-top:8px;color:var(--dk-muted);font-size:12px}
.dk-all-products-count{flex:0 0 auto;color:var(--dk-muted);font-size:11px;font-weight:700}
.dk-product-category-section+.dk-product-category-section{margin-top:32px}
.dk-product-category-head{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:16px;padding:14px 17px;border:1px solid var(--dk-line);border-radius:15px;background:linear-gradient(110deg,rgba(24,217,149,.09),rgba(255,255,255,.025) 62%)}
body.light .dk-product-category-head{background:linear-gradient(110deg,rgba(25,123,89,.08),rgba(255,255,255,.78) 70%)}
.dk-product-category-title{display:flex;align-items:center;gap:11px;min-width:0}
.dk-product-category-index{display:grid;flex:0 0 32px;width:32px;height:32px;place-items:center;border:1px solid var(--dk-line-strong);border-radius:50%;color:var(--dk-mint);font-size:10px;font-weight:800}
.dk-product-category-head h3{overflow:hidden;margin:0;color:var(--dk-cream);font-family:var(--dk-serif);font-size:clamp(19px,2.5vw,27px);font-weight:400;text-overflow:ellipsis;white-space:nowrap}
.dk-product-category-count{flex:0 0 auto;padding:7px 10px;border:1px solid var(--dk-line);border-radius:999px;color:var(--dk-muted);font-size:9px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
.dk-all-products-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.dk-all-product{min-width:0;overflow:hidden;border:1px solid var(--dk-line);border-radius:15px;background:linear-gradient(180deg,rgba(17,31,24,.94),rgba(9,19,14,.96));box-shadow:0 18px 46px rgba(0,0,0,.16);transition:transform .25s ease,border-color .25s ease,box-shadow .25s ease}
body.light .dk-all-product{background:linear-gradient(180deg,#fff,#edf5ef);box-shadow:0 16px 38px rgba(16,32,22,.08)}
.dk-all-product:hover{transform:translateY(-5px);border-color:var(--dk-line-strong);box-shadow:0 25px 55px rgba(0,0,0,.23)}
.dk-all-product-image{position:relative;display:block;overflow:hidden;aspect-ratio:4/3;background:radial-gradient(circle at 50% 45%,rgba(117,247,199,.2),transparent 68%),var(--dk-bg-2)}
.dk-all-product-image img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .45s ease,opacity .3s ease}
.dk-all-product:hover .dk-all-product-image img{transform:scale(1.045)}
.dk-all-product-image img.is-changing{opacity:.35}
.dk-all-product-category{position:absolute;top:12px;left:12px;max-width:calc(100% - 24px);overflow:hidden;padding:7px 10px;border:1px solid rgba(255,255,255,.16);border-radius:999px;background:rgba(5,15,10,.72);color:#d7f5e3;font-size:9px;font-weight:800;text-overflow:ellipsis;white-space:nowrap;backdrop-filter:blur(10px)}
@media(prefers-reduced-motion:reduce){.dk-all-product-image img{transition:none}}
.dk-all-product-content{padding:16px}
.dk-all-product-content h3{min-height:42px;margin:8px 0 5px;overflow:hidden;color:var(--dk-cream);font-family:var(--dk-serif);font-size:21px;font-weight:400;line-height:1.2}
.dk-all-product-description{display:-webkit-box;min-height:36px;margin:0;overflow:hidden;color:var(--dk-muted);font-size:11px;line-height:1.6;overflow-wrap:anywhere;-webkit-box-orient:vertical;-webkit-line-clamp:2}
.dk-all-product-bottom{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:14px}
.dk-all-product-price{color:var(--dk-cream);font-size:13px;font-weight:800;white-space:nowrap}
.dk-all-product-link{min-height:36px;display:inline-flex;align-items:center;gap:7px;padding:0 11px;border:1px solid var(--dk-line);border-radius:9px;color:var(--dk-cream);font-size:10px;font-weight:800;white-space:nowrap;transition:background .2s ease,border-color .2s ease}
.dk-all-product-link:hover{border-color:var(--dk-mint);background:rgba(117,247,199,.1)}
.dk-all-product-link:hover{color:var(--dk-mint)}
.dk-all-products-message{grid-column:1/-1;padding:20px;border:1px solid var(--dk-line);border-radius:14px;color:var(--dk-muted);font-size:13px}
@keyframes dkFloat{50%{transform:translateY(-12px) rotate(3deg)}to{transform:translateY(0) rotate(0)}}
@keyframes dkPulse{50%{opacity:.35;box-shadow:0 0 5px var(--dk-mint)}}
@media (max-width:980px){.dk-hero-grid{grid-template-columns:1fr}.dk-hero{padding-top:135px}.dk-hero-visual{min-height:480px;margin-top:-12px}.dk-benefits{max-width:none}.dk-all-products-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (max-width:640px){.dk-container{width:min(100% - 24px,1240px)}.dk-hero{min-height:auto;padding:122px 0 58px}.dk-hero h1{font-size:clamp(55px,16vw,82px);margin-top:20px}.dk-hero-copy{font-size:14px}.dk-benefits{grid-template-columns:repeat(2,1fr);margin-top:30px}.dk-benefit{padding:12px 8px}.dk-hero-visual{min-height:350px;margin-top:12px}.dk-visual-label{right:3px}.dk-btn{width:100%}.dk-actions{width:100%}.dk-all-products{padding:28px 0 64px}.dk-all-products-head{align-items:flex-start}.dk-all-products-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.dk-all-product{border-radius:24px}.dk-all-product-content{padding:13px}.dk-all-product-content h3{min-height:42px;font-size:clamp(16px,4.5vw,19px)}.dk-all-product-description{min-height:34px;font-size:11px}.dk-all-product-bottom{align-items:stretch;flex-direction:column;gap:9px;margin-top:12px}.dk-all-product-price{font-size:14px}.dk-all-product-link{width:100%;min-height:44px;justify-content:center;font-size:11px}.dk-product-category-section+.dk-product-category-section{margin-top:24px}.dk-product-category-head{padding:11px 12px}.dk-product-category-index{flex-basis:28px;width:28px;height:28px}.dk-product-category-count{padding:6px 8px;font-size:8px}}
@media (prefers-reduced-motion:reduce){.dk-hero-art{animation:none}}
</style>

    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-user-nav25">
    <link rel="stylesheet" href="assets/dunkhome-footer.css?v=20261003-footer3">
    <style>
        .dh-user-header {
            z-index: 10000;
            -webkit-backdrop-filter: blur(14px);
        }

        .dh-user-header-inner {
            width: min(1320px, calc(100% - 36px));
        }

        .dh-user-search {
            position: relative;
            display: flex;
            align-items: center;
            gap: 6px;
            width: 190px;
            min-width: 150px;
            padding: 4px 5px 4px 11px;
            border: 1px solid rgba(143, 233, 181, 0.18);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.045);
        }

        .dh-user-search-label {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
        }

        .dh-user-search input {
            width: 100%;
            min-width: 0;
            height: 32px;
            padding: 0 5px;
            border: 0;
            outline: 0;
            background: transparent;
            color: var(--text);
            font: 600 11px "Manrope", sans-serif;
        }

        .dh-user-search input::placeholder {
            color: var(--muted);
            opacity: 0.9;
        }

        .dh-user-search button {
            display: grid;
            flex: 0 0 30px;
            width: 30px;
            height: 30px;
            place-items: center;
            border: 0;
            border-radius: 50%;
            background: rgba(131, 230, 177, 0.14);
            color: var(--green);
            cursor: pointer;
        }

        .dh-user-search button svg {
            width: 16px;
            height: 16px;
        }

        .dh-user-search:focus-within {
            border-color: rgba(131, 230, 177, 0.62);
            box-shadow: 0 0 0 3px rgba(131, 230, 177, 0.09);
        }

        .dh-live-search-results {
            position: absolute;
            top: calc(100% + 10px);
            left: 0;
            z-index: 10001;
            width: min(380px, calc(100vw - 40px));
            max-height: min(420px, 70vh);
            overflow: auto;
            padding: 8px;
            border: 1px solid var(--dk-line-strong);
            border-radius: 18px;
            background: #0c1e16;
            box-shadow: 0 24px 65px rgba(0, 0, 0, 0.42);
        }

        body.light .dh-live-search-results {
            background: #fff;
            box-shadow: 0 24px 65px rgba(16, 32, 22, 0.2);
        }

        .dh-live-search-heading {
            padding: 9px 10px 6px;
            color: var(--dk-mint);
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .dh-live-search-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 11px 10px;
            border-radius: 11px;
            color: var(--dk-cream);
            font-size: 12px;
            text-decoration: none;
        }

        .dh-live-search-item:hover,
        .dh-live-search-item:focus-visible {
            outline: 0;
            background: rgba(117, 247, 199, 0.1);
        }

        .dh-live-search-item small {
            flex: 0 0 auto;
            color: var(--dk-muted);
            font-size: 10px;
        }

        .dh-live-search-empty {
            padding: 12px 10px;
            color: var(--dk-muted);
            font-size: 12px;
        }

        .dk-category-pills {
            display: flex;
            gap: 19px;
            overflow-x: auto;
            margin: 0;
            padding: 10px 4px 16px;
            scrollbar-width: none;
        }

        .dk-category-pills::-webkit-scrollbar {
            display: none;
        }

        .dk-top-categories {
            position: relative;
            z-index: 2;
            padding: 108px 0 0;
        }

        .dk-page-search {
            width: min(100%, 470px);
            margin: 0 auto 10px;
            padding: 6px 7px 6px 15px;
            border-color: var(--dk-line-strong);
            background: rgba(15, 39, 29, 0.78);
            box-shadow: 0 14px 35px rgba(0, 0, 0, 0.16);
        }

        .dk-page-search input {
            height: 38px;
            color: var(--dk-cream);
            font-size: 12px;
        }

        .dk-page-search input::placeholder {
            color: var(--dk-muted);
        }

        .dk-page-search button {
            width: 36px;
            height: 36px;
            flex-basis: 36px;
            background: var(--dk-mint);
            color: #062017;
        }

        body.light .dk-page-search {
            background: rgba(255, 255, 255, 0.9);
        }

        body.light .dk-page-search input {
            color: #102016;
        }

        .dk-hero {
            padding-top: 48px;
        }

        .dk-category-pill {
            display: flex;
            flex: 0 0 82px;
            flex-direction: column;
            align-items: center;
            gap: 9px;
            color: var(--dk-muted);
            font-size: 10px;
            font-weight: 700;
            line-height: 1.3;
            text-align: center;
            text-decoration: none;
            transition: color 0.25s ease, transform 0.25s ease;
        }

        .dk-category-pill:hover,
        .dk-category-pill.is-active {
            color: var(--dk-cream);
            transform: translateY(-3px);
        }

        .dk-category-pill-icon {
            display: grid;
            width: 66px;
            height: 66px;
            place-items: center;
            overflow: hidden;
            border: 2px solid var(--dk-line-strong);
            border-radius: 50%;
            background: linear-gradient(145deg, rgba(117, 247, 199, 0.17), rgba(255, 255, 255, 0.04));
            color: var(--dk-mint);
            font-size: 21px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.17);
            transition: border-color 0.25s ease, box-shadow 0.25s ease, transform 0.25s ease;
        }

        .dk-category-pill-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .dk-category-pill:hover .dk-category-pill-icon,
        .dk-category-pill.is-active .dk-category-pill-icon {
            transform: scale(1.06);
            border-color: var(--dk-mint);
            box-shadow: 0 0 0 4px rgba(117, 247, 199, 0.12), 0 10px 28px rgba(0, 0, 0, 0.22);
        }

        .dk-category-pill:focus-visible {
            outline: 2px solid var(--dk-mint);
            outline-offset: 4px;
            border-radius: 8px;
        }

        body.light .dh-user-search input {
            color: #102016;
        }

        @media (max-width: 1180px) {
            .dh-user-header-inner {
                width: min(100% - 28px, 1180px);
            }

            .dh-user-menu {
                display: grid;
            }

            .dh-user-account {
                display: none;
            }

            .dh-user-links {
                position: absolute;
                top: calc(100% + 10px);
                left: 0;
                right: 0;
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 4px;
                padding: 12px;
                border: 1px solid rgba(143, 233, 181, 0.18);
                border-radius: 18px;
                background: #0d1b16;
                box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28);
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transform: translateY(-7px);
                transition: opacity 0.22s ease, transform 0.22s ease, visibility 0.22s ease;
            }

            .dh-user-links.active {
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
                transform: translateY(0);
            }

            .dh-user-links a {
                padding: 13px;
                border-radius: 11px;
                font-size: 13px;
            }

            .dh-user-search {
                width: 100%;
                min-width: 0;
                order: -1;
                margin-bottom: 4px;
            }

            .dh-user-search input {
                height: 38px;
            }

            .dh-user-search button {
                width: 34px;
                height: 34px;
                flex-basis: 34px;
            }

            body.light .dh-user-links {
                background: #fff;
                border-color: rgba(16, 32, 22, 0.12);
            }
        }

        @media (max-width: 640px) {
            .dk-top-categories {
                padding-top: 91px;
            }

            .dk-hero {
                padding-top: 38px;
            }

            .dk-category-pills {
                gap: 14px;
            }

            .dk-category-pill {
                flex-basis: 72px;
                font-size: 9px;
            }

            .dk-category-pill-icon {
                width: 58px;
                height: 58px;
            }

            .dk-products {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 11px;
            }

            .dk-product {
                border-radius: 18px;
            }

            .dk-product-media {
                aspect-ratio: 1.32 / 1;
            }

            .dk-product-tag {
                top: 9px;
                left: 9px;
                max-width: calc(100% - 52px);
                padding: 6px 8px;
                font-size: 8px;
                letter-spacing: 0.08em;
            }

            .dk-product-open {
                top: 8px;
                right: 8px;
                width: 32px;
                height: 32px;
                font-size: 14px;
            }

            .dk-product-body {
                padding: 10px;
            }

            .dk-product-meta {
                gap: 5px;
                font-size: 7px;
                letter-spacing: 0.07em;
            }

            .dk-product-meta span:last-child {
                display: none;
            }

            .dk-product h3 {
                min-height: 34px;
                margin: 7px 0 5px;
                font-size: 16px;
            }

            .dk-product p {
                display: none;
            }

            .dk-product-foot {
                align-items: stretch;
                flex-direction: column;
                gap: 9px;
                padding-top: 10px;
            }

            .dk-price {
                font-size: 14px;
            }

            .dk-view {
                width: 100%;
                min-height: 31px;
                padding: 0 8px;
                font-size: 8px;
                letter-spacing: 0.08em;
            }
        }

        .dk-hero {
            min-height: 0;
            padding-top: 128px;
            padding-bottom: 64px;
            background-image: radial-gradient(ellipse at 78% 48%, rgba(24, 217, 149, 0.08), transparent 42%);
        }

        body.light .dk-hero {
            background-image: radial-gradient(ellipse at 78% 48%, rgba(25, 123, 89, 0.08), transparent 42%);
        }

        .dk-hero:before,
        .dk-hero:after {
            display: none;
        }

        .dk-hero-grid {
            grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
            gap: 34px;
        }

        .dk-hero-visual {
            min-height: 0;
            padding: 24px 0;
            perspective: none;
        }

        .dk-hero-art {
            width: min(100%, 540px);
            aspect-ratio: 1.26 / 1;
            background-image: url("image/homepage-hero-art.svg");
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            transition: transform 0.35s ease, box-shadow 0.35s ease;
        }

        .dk-hero-art:hover {
            transform: translateY(-5px) rotate(-0.5deg);
            box-shadow: 0 38px 100px rgba(0, 0, 0, 0.34), 0 0 80px rgba(24, 217, 149, 0.14);
        }

        body.light .dk-hero-art {
            border-color: rgba(25, 123, 89, 0.28);
            box-shadow: 0 28px 78px rgba(22, 72, 49, 0.16), 0 0 0 7px rgba(255, 255, 255, 0.76);
        }

        .dk-visual-label {
            right: -2px;
            bottom: 5%;
            color: var(--dk-muted);
            font-size: 8px;
            letter-spacing: 0.23em;
        }

        .dk-products {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .dk-product {
            border-radius: 18px;
        }

        .dk-product-media {
            aspect-ratio: 1.45 / 1;
        }

        .dk-product-body {
            padding: 13px;
        }

        .dk-product h3 {
            min-height: 38px;
            margin: 7px 0 4px;
            font-size: 19px;
        }

        .dk-product p {
            min-height: 18px;
            font-size: 10px;
            line-height: 1.5;
            -webkit-line-clamp: 1;
        }

        .dk-product-foot {
            padding-top: 10px;
        }

        .dk-price {
            font-size: 16px;
        }

        .dk-view {
            min-height: 34px;
            padding: 0 10px;
            font-size: 9px;
        }

        @media (max-width: 980px) {
            .dk-hero {
                padding-top: 120px;
            }

            .dk-hero-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .dk-hero-visual {
                width: min(100%, 650px);
                min-height: 0;
                margin: 0 auto;
            }

            .dk-hero-art {
                width: min(100%, 560px);
            }

            .dk-products {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 13px;
            }
        }

        @media (max-width: 640px) {
            .dk-hero {
                padding-top: 38px;
                padding-bottom: 46px;
            }

            .dk-hero-art {
                width: 100%;
                border-radius: 21px;
            }

            .dk-hero-visual {
                padding: 8px 0 18px;
            }

            .dk-visual-label {
                right: 3px;
                bottom: 0;
            }

            .dk-products {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .dk-product {
                border-radius: 16px;
            }

            .dk-product-media {
                aspect-ratio: 1.35 / 1;
            }

            .dk-product-body {
                padding: 9px;
            }

            .dk-product-meta {
                font-size: 7px;
            }

            .dk-product h3 {
                min-height: 34px;
                margin: 7px 0 4px;
                font-size: 16px;
            }

            .dk-product p {
                display: none;
            }

            .dk-product-foot {
                align-items: stretch;
                flex-direction: column;
                gap: 7px;
                padding-top: 9px;
            }

            .dk-price {
                font-size: 14px;
            }

            .dk-view {
                min-height: 30px;
                font-size: 8px;
            }
        }

        @media (max-width: 360px) {
            .dk-product-body {
                padding: 7px;
            }

            .dk-product h3 {
                font-size: 14px;
            }
        }

        @media (max-width: 360px) {
            .dk-product-body {
                padding: 8px;
            }

            .dk-product h3 {
                font-size: 15px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .dk-category-pill,
            .dk-category-pill-icon {
                transition: none;
            }
        }
    </style>
</head>


<body>
<?php
require __DIR__ . '/includes/user_nav.php';
?>

<main class="dk-main">
    <div class="dk-top-categories" id="categories">
        <div class="dk-container">
            <form class="dh-user-search dk-page-search" method="get" action="<?= h(appUrl('User/Products.php')) ?>" role="search">
                <label class="dh-user-search-label" for="dkPageSearch">Search categories and products</label>
                <input id="dkPageSearch" type="search" name="q" placeholder="Search sneakers or categories..." autocomplete="off">
                <button type="submit" aria-label="Search categories and products">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.6" stroke="currentColor" stroke-width="1.8"></circle><path d="m16 16 4.2 4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>
                </button>
            </form>
            <nav class="dk-category-pills" aria-label="Shop all categories">
                <a class="dk-category-pill is-active" href="User/Products.php" aria-current="page">
                    <span class="dk-category-pill-icon" aria-hidden="true">✦</span>
                    <span>All pairs</span>
                </a>
                <?php foreach ($categories as $category): ?>
                    <?php
                    $categoryName = (string) $category['name'];
                    $categoryInitial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($categoryName, 0, 1)) : strtoupper(substr($categoryName, 0, 1));
                    ?>
                    <a class="dk-category-pill" href="User/Products.php?category=<?= (int) $category['id'] ?>">
                        <span class="dk-category-pill-icon">
                            <?php if (!empty($category['image'])): ?>
                                <img src="<?= h(appUrl((string) $category['image'])) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <?= h($categoryInitial) ?>
                            <?php endif; ?>
                        </span>
                        <span><?= h($categoryName) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>
    <section class="dk-hero" aria-labelledby="dkHeroTitle">
        <div class="dk-container dk-hero-grid">
            <div class="dk-hero-copy-block">
                <div class="dk-kicker"><i></i> Premium sneakers / DunkHome Kicks</div>
                <h1 id="dkHeroTitle">Step Into<br><em>Something</em><br> Bolder.</h1>
                <p class="dk-hero-copy">A curated rotation of sneakers built for everyday movement, street-ready style and the moments that deserve a pair with presence.</p>
                <div class="dk-actions">
                    <a class="dk-btn dk-btn-primary" href="User/Products.php">Explore collection <span>↗</span></a>
                    <a class="dk-btn dk-btn-ghost" href="#categories">View categories <span>↓</span></a>
                </div>
                <div class="dk-benefits" aria-label="DunkHome Kicks benefits">
                    <div class="dk-benefit"><strong>Authentic</strong>Curated pairs</div>
                    <div class="dk-benefit"><strong>Fast</strong>Easy delivery</div>
                    <div class="dk-benefit"><strong>Simple</strong>Easy returns</div>
                    <div class="dk-benefit"><strong>Premium</strong>Made to move</div>
                </div>
            </div>
            <div class="dk-hero-visual">
                <div class="dk-hero-art" role="img" aria-label="Original DunkHome Kicks sneaker illustration">
                    <span class="dk-hero-art-label">DunkHome / Rotation 01</span>
                </div>
                <div class="dk-visual-label">More than just shoes / Est. DunkHome</div>
            </div>
        </div>
    </section>

    <section class="dk-all-products" id="products" aria-labelledby="dkProductsTitle">
        <div class="dk-container">
            <div class="dk-all-products-head">
                <div>
                    <h2 id="dkProductsTitle">Shop all products</h2>
                    <p>Find your next pair from the full collection.</p>
                </div>
                <?php if (!$productsError): ?>
                    <span class="dk-all-products-count"><?= number_format(count($products)) ?> pairs</span>
                <?php endif; ?>
            </div>
            <?php if ($productsError): ?>
                <div class="dk-all-products-grid">
                    <p class="dk-all-products-message">The product collection is temporarily unavailable. Please try again later.</p>
                </div>
            <?php elseif (!$products): ?>
                <div class="dk-all-products-grid">
                    <p class="dk-all-products-message">No products are available right now. Please check back soon.</p>
                </div>
            <?php else: ?>
                <?php $categoryNumber = 0; ?>
                <?php foreach ($productsByCategory as $categoryGroup): ?>
                    <?php $categoryNumber++; ?>
                    <section class="dk-product-category-section" aria-labelledby="dkProductCategory<?= $categoryNumber ?>">
                        <div class="dk-product-category-head">
                            <div class="dk-product-category-title">
                                <span class="dk-product-category-index"><?= str_pad((string) $categoryNumber, 2, '0', STR_PAD_LEFT) ?></span>
                                <h3 id="dkProductCategory<?= $categoryNumber ?>"><?= h($categoryGroup['name']) ?></h3>
                            </div>
                            <span class="dk-product-category-count"><?= number_format(count($categoryGroup['products'])) ?> pairs</span>
                        </div>
                        <div class="dk-all-products-grid">
                            <?php foreach ($categoryGroup['products'] as $product): ?>
                                <article class="dk-all-product">
                                    <a
                                        class="dk-all-product-image"
                                        href="User/ProductDetails.php?id=<?= (int) $product['id'] ?>"
                                        aria-label="View <?= h((string) $product['name']) ?>"
                                        data-images="<?= h(json_encode([
                                            appUrl((string) $product['image1']),
                                            appUrl((string) $product['image2']),
                                            appUrl((string) $product['image3']),
                                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]') ?>"
                                    >
                                        <img src="<?= h(appUrl((string) $product['image1'])) ?>" alt="<?= h((string) $product['name']) ?>" loading="lazy">
                                        <span class="dk-all-product-category"><?= h((string) $product['category']) ?></span>
                                    </a>
                                    <div class="dk-all-product-content">
                                        <h3><?= h((string) $product['name']) ?></h3>
                                        <p class="dk-all-product-description"><?= h((string) ($product['description'] ?? 'No description available.')) ?></p>
                                        <div class="dk-all-product-bottom">
                                            <span class="dk-all-product-price">₹<?= number_format((float) $product['price'], 2) ?></span>
                                            <a class="dk-all-product-link" href="User/ProductDetails.php?id=<?= (int) $product['id'] ?>">See details <span aria-hidden="true">&#8594;</span></a>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
<script src="assets/dunkhome-ui.js?v=20261003-nav14"></script>
<script>
(() => {
    const slideshows = [...document.querySelectorAll('.dk-all-product-image[data-images]')]
        .map((card) => {
            let images;
            try {
                images = JSON.parse(card.dataset.images || '[]');
            } catch (error) {
                console.error('Product image slideshow data is invalid.', error);
                return null;
            }

            if (!Array.isArray(images) || images.some((image) => typeof image !== 'string')) {
                console.error('Product image slideshow data must be an array of image URLs.');
                return null;
            }

            const distinctImages = [...new Set(images.map((image) => image.trim()).filter(Boolean))];
            const image = card.querySelector('img');
            if (distinctImages.length < 2 || !image) return null;

            card.dataset.imageIndex = '0';
            return { card, image, images: distinctImages, index: 0, pending: false };
        })
        .filter(Boolean);
    const visibleSlideshows = new Set();

    if ('IntersectionObserver' in window) {
        const slideshowsByCard = new Map(slideshows.map((slideshow) => [slideshow.card, slideshow]));
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                const slideshow = slideshowsByCard.get(entry.target);
                if (!slideshow) return;

                if (entry.isIntersecting) {
                    visibleSlideshows.add(slideshow);
                } else {
                    visibleSlideshows.delete(slideshow);
                }
            });
        }, { rootMargin: '100px' });
        slideshows.forEach(({ card }) => observer.observe(card));
    } else {
        slideshows.forEach((slideshow) => visibleSlideshows.add(slideshow));
    }

    window.setInterval(() => {
        visibleSlideshows.forEach((slideshow) => {
            if (slideshow.pending) return;

            const nextIndex = (slideshow.index + 1) % slideshow.images.length;
            const nextImage = slideshow.images[nextIndex];
            const preload = new Image();
            slideshow.pending = true;
            preload.onload = () => {
                slideshow.index = nextIndex;
                slideshow.card.dataset.imageIndex = String(nextIndex);
                slideshow.image.classList.add('is-changing');
                slideshow.image.onload = () => slideshow.image.classList.remove('is-changing');
                slideshow.image.onerror = () => {
                    slideshow.image.classList.remove('is-changing');
                    console.error('Unable to load product gallery image:', nextImage);
                };
                slideshow.image.src = nextImage;
                slideshow.pending = false;
            };
            preload.onerror = () => {
                slideshow.pending = false;
                console.error('Unable to preload product gallery image:', nextImage);
            };
            preload.src = nextImage;
        });
    }, 2000);

    document.querySelectorAll('.dh-user-search').forEach((searchForm, index) => {
        const searchInput = searchForm.querySelector('input[type="search"]');
        if (!searchInput) return;

        const results = document.createElement('div');
        results.className = 'dh-live-search-results';
        results.id = `dhLiveSearchResults${index + 1}`;
        results.hidden = true;
        results.setAttribute('role', 'listbox');
        searchForm.appendChild(results);
        searchInput.setAttribute('aria-controls', results.id);
        searchInput.setAttribute('aria-autocomplete', 'list');

        let debounceTimer;
        let activeRequest;
        const hideResults = () => {
            results.hidden = true;
            results.replaceChildren();
            searchInput.setAttribute('aria-expanded', 'false');
        };
        const appendGroup = (heading, items) => {
            if (!items.length) return;
            const title = document.createElement('div');
            title.className = 'dh-live-search-heading';
            title.textContent = heading;
            results.appendChild(title);
            items.forEach((item) => {
                const link = document.createElement('a');
                link.className = 'dh-live-search-item';
                link.href = item.href;
                const label = document.createElement('span');
                label.textContent = item.label;
                const detail = document.createElement('small');
                detail.textContent = item.detail;
                link.append(label, detail);
                results.appendChild(link);
            });
        };

        searchInput.addEventListener('input', () => {
            window.clearTimeout(debounceTimer);
            if (activeRequest) activeRequest.abort();
            const query = searchInput.value.trim();
            if (query.length < 2) {
                hideResults();
                return;
            }

            debounceTimer = window.setTimeout(async () => {
                activeRequest = new AbortController();
                results.replaceChildren();
                const loading = document.createElement('div');
                loading.className = 'dh-live-search-empty';
                loading.textContent = 'Searching the collection…';
                results.appendChild(loading);
                results.hidden = false;
                searchInput.setAttribute('aria-expanded', 'true');
                try {
                    const url = new URL('index.php', document.baseURI);
                    url.searchParams.set('ajax', 'search');
                    url.searchParams.set('q', query);
                    const response = await fetch(url, {
                        headers: { Accept: 'application/json' },
                        signal: activeRequest.signal
                    });
                    if (!response.ok) throw new Error(`Search request failed (${response.status}).`);
                    const data = await response.json();
                    results.replaceChildren();
                    const categories = (data.categories || []).map((category) => ({
                        href: `User/Products.php?category=${encodeURIComponent(category.id)}`,
                        label: category.name,
                        detail: 'Category'
                    }));
                    const products = (data.products || []).map((product) => ({
                        href: `User/ProductDetails.php?id=${encodeURIComponent(product.id)}`,
                        label: product.name,
                        detail: `₹${Number(product.price).toFixed(2)} · ${product.category}`
                    }));
                    appendGroup('Categories', categories);
                    appendGroup('Products', products);
                    if (!categories.length && !products.length) {
                        const empty = document.createElement('div');
                        empty.className = 'dh-live-search-empty';
                        empty.textContent = 'No matching categories or products.';
                        results.appendChild(empty);
                    }
                    results.hidden = false;
                } catch (error) {
                    if (error.name === 'AbortError') return;
                    results.replaceChildren();
                    const message = document.createElement('div');
                    message.className = 'dh-live-search-empty';
                    message.textContent = 'Live search is unavailable. Press Enter to search the full collection.';
                    results.appendChild(message);
                    results.hidden = false;
                }
            }, 180);
        });

        searchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') hideResults();
        });
        document.addEventListener('click', (event) => {
            if (!searchForm.contains(event.target)) hideResults();
        });
        searchInput.addEventListener('focus', () => {
            if (results.childElementCount && searchInput.value.trim().length >= 2) {
                results.hidden = false;
                searchInput.setAttribute('aria-expanded', 'true');
            }
        });
    });

})();
</script>
</body>
</html>
