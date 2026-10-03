<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$product = null;
$ratingNotice = (string) ($_SESSION['rating_notice'] ?? '');
$ratingNoticeType = (string) ($_SESSION['rating_notice_type'] ?? 'info');
unset($_SESSION['rating_notice']);
unset($_SESSION['rating_notice_type']);
$ratingError = '';
$userId = userLoggedIn() ? (int) $_SESSION['user_id'] : 0;
$userRating = 0;
$ratingAverage = 0.0;
$ratingCount = 0;
$ratingStorageAvailable = true;
$ratingFormValue = 0;

if ($id !== false && $id !== null) {
    $statement = $conn->prepare(
        'SELECT id, name, category, price, description, image1, image2, image3
         FROM products
         WHERE id = ? AND is_active = 1
         LIMIT 1'
    );

    if ($statement) {
        $statement->bind_param('i', $id);
        if ($statement->execute()) {
            $result = $statement->get_result();
            $product = $result ? $result->fetch_assoc() : null;
        } else {
            error_log('Product details query failed: ' . $statement->error);
        }
        $statement->close();
    } else {
        error_log('Product details query preparation failed: ' . $conn->error);
    }
}

if (!$product) {
    http_response_code(404);
}

if ($product && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rate') {
    $submittedRating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $ratingError = 'Your form expired. Refresh the page and try rating again.';
    } elseif (!$submittedRating) {
        $ratingError = 'Choose a star rating from 1 to 5.';
    } elseif (!userLoggedIn()) {
        $_SESSION['pending_product_rating'] = [
            'product_id' => (int) $product['id'],
            'rating' => $submittedRating,
        ];
        $returnPath = 'User/ProductDetails.php?id=' . (int) $product['id'];
        header('Location: ' . appUrl('User/SignIn.php?' . http_build_query([
            'return_to' => $returnPath,
            'intent' => 'rate',
        ])));
        exit;
    } else {
        $userId = (int) $_SESSION['user_id'];
        $ratingStatement = $conn->prepare(
            'INSERT INTO product_ratings (product_id, user_id, rating)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = CURRENT_TIMESTAMP'
        );
        if (!$ratingStatement) {
            error_log('Product rating query preparation failed: ' . $conn->error);
            http_response_code(503);
            $ratingError = 'Ratings are temporarily unavailable. Please try again later.';
        } else {
            $productId = (int) $product['id'];
            $ratingStatement->bind_param('iii', $productId, $userId, $submittedRating);
            if ($ratingStatement->execute()) {
                $_SESSION['rating_notice'] = 'Thanks — your rating has been saved.';
                $_SESSION['rating_notice_type'] = 'success';
                header('Location: ' . appUrl('User/ProductDetails.php?id=' . $productId));
                exit;
            }
            error_log('Product rating save failed: ' . $ratingStatement->error);
            http_response_code(503);
            $ratingError = 'Your rating could not be saved right now. Please try again.';
        }
        if (isset($ratingStatement) && $ratingStatement instanceof mysqli_stmt) {
            $ratingStatement->close();
        }
    }
    $ratingFormValue = $submittedRating ?: 0;
}

$pendingRating = $_SESSION['pending_product_rating'] ?? null;
if ($product && is_array($pendingRating) && (int) ($pendingRating['product_id'] ?? 0) === (int) $product['id']) {
    $ratingFormValue = (int) ($pendingRating['rating'] ?? 0);
    if (userLoggedIn()) {
        $ratingNotice = 'You’re signed in. Confirm your selected stars to submit your rating.';
        $ratingNoticeType = 'info';
    }
    unset($_SESSION['pending_product_rating']);
}

if ($product) {
    $summaryStatement = $conn->prepare(
        'SELECT AVG(rating) AS average_rating, COUNT(*) AS rating_count
         FROM product_ratings
         WHERE product_id = ?'
    );
    if (!$summaryStatement) {
        error_log('Product rating summary query preparation failed: ' . $conn->error);
        $ratingStorageAvailable = false;
    } else {
        $productId = (int) $product['id'];
        $summaryStatement->bind_param('i', $productId);
        if ($summaryStatement->execute()) {
            $summaryResult = $summaryStatement->get_result();
            $summary = $summaryResult ? $summaryResult->fetch_assoc() : null;
            if ($summary) {
                $ratingAverage = (float) ($summary['average_rating'] ?? 0);
                $ratingCount = (int) ($summary['rating_count'] ?? 0);
            }
        } else {
            error_log('Product rating summary query failed: ' . $summaryStatement->error);
            $ratingStorageAvailable = false;
        }
        $summaryStatement->close();
    }

    if ($ratingStorageAvailable && $userId > 0) {
        $existingRatingStatement = $conn->prepare(
            'SELECT rating FROM product_ratings WHERE product_id = ? AND user_id = ? LIMIT 1'
        );
        if (!$existingRatingStatement) {
            error_log('User product rating query preparation failed: ' . $conn->error);
            $ratingStorageAvailable = false;
        } else {
            $productId = (int) $product['id'];
            $existingRatingStatement->bind_param('ii', $productId, $userId);
            if ($existingRatingStatement->execute()) {
                $ratingResult = $existingRatingStatement->get_result();
                $existingRating = $ratingResult ? $ratingResult->fetch_assoc() : null;
                if ($existingRating && $ratingFormValue === 0) {
                    $userRating = (int) $existingRating['rating'];
                    $ratingFormValue = $userRating;
                }
            } else {
                error_log('User product rating query failed: ' . $existingRatingStatement->error);
                $ratingStorageAvailable = false;
            }
            $existingRatingStatement->close();
        }
    }
}

$productImages = [];
if ($product) {
    foreach (['image1', 'image2', 'image3'] as $imageField) {
        $imagePath = trim((string) ($product[$imageField] ?? ''));
        if ($imagePath !== '') {
            $productImages[] = appUrl($imagePath);
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
    <meta name="theme-color" content="#07130e">
    <meta name="description" content="<?= $product ? h((string) $product['name']) . ' — explore product details and images at DunkHome Kicks.' : 'Product not found at DunkHome Kicks.' ?>">
    <title><?= $product ? h((string) $product['name']) . ' | DunkHome Kicks' : 'Product Not Found | DunkHome Kicks' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-user-nav25">
    <link rel="stylesheet" href="assets/user-premium.css?v=20261003-product-details1">
    <link rel="stylesheet" href="assets/dunkhome-footer.css?v=20261003-footer3">
    <style>
        :root {
            --detail-bg: #07130e;
            --detail-panel: rgba(15, 34, 25, .9);
            --detail-text: #f1f5f0;
            --detail-muted: #a2b5aa;
            --detail-green: #83e6b1;
            --detail-line: rgba(131, 230, 177, .17);
        }

        body {
            min-width: 320px;
            margin: 0;
            color: var(--detail-text);
            font-family: "Manrope", sans-serif;
            background:
                radial-gradient(ellipse at 12% 24%, rgba(38, 145, 91, .17), transparent 42%),
                radial-gradient(ellipse at 88% 72%, rgba(48, 118, 80, .11), transparent 40%),
                var(--detail-bg);
        }

        body.light {
            --detail-bg: #f1f6f2;
            --detail-panel: rgba(255, 255, 255, .92);
            --detail-text: #14251b;
            --detail-muted: #607166;
            --detail-green: #23784f;
            --detail-line: rgba(24, 88, 56, .14);
        }

        .detail-main {
            width: min(1180px, calc(100% - 40px));
            min-height: calc(100vh - 100px);
            margin: 0 auto;
            padding: 130px 0 76px;
        }

        .detail-back {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 18px;
            color: var(--detail-muted);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none !important;
            transition: color .2s ease, transform .2s ease;
        }

        .detail-back:hover {
            transform: translateX(-3px);
            color: var(--detail-green);
        }

        .detail-shell {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(330px, .95fr);
            gap: clamp(26px, 5vw, 66px);
            padding: clamp(18px, 3.2vw, 42px);
            overflow: hidden;
            border: 1px solid var(--detail-line);
            border-radius: 30px;
            background: linear-gradient(145deg, var(--detail-panel), rgba(9, 24, 16, .78));
            box-shadow: 0 28px 80px rgba(0, 0, 0, .2);
        }

        body.light .detail-shell {
            background: linear-gradient(145deg, rgba(255,255,255,.96), rgba(231,242,234,.9));
            box-shadow: 0 26px 70px rgba(21, 55, 34, .1);
        }

        .detail-gallery {
            min-width: 0;
        }

        .detail-hero-image {
            display: grid;
            min-height: 320px;
            aspect-ratio: 1.12 / 1;
            place-items: center;
            overflow: hidden;
            border: 1px solid var(--detail-line);
            border-radius: 22px;
            background:
                radial-gradient(circle at 50% 45%, rgba(131, 230, 177, .16), transparent 67%),
                linear-gradient(145deg, rgba(255, 255, 255, .045), rgba(131, 230, 177, .025));
        }

        .detail-hero-image img {
            width: 100%;
            height: 100%;
            max-height: 560px;
            object-fit: contain;
            transition: opacity .2s ease, transform .4s ease;
        }

        .detail-hero-image img.is-changing {
            opacity: .35;
            transform: scale(.985);
        }

        .detail-thumbnails {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 12px;
        }

        .detail-thumbnail {
            display: grid;
            min-width: 0;
            aspect-ratio: 1.35 / 1;
            place-items: center;
            overflow: hidden;
            padding: 0;
            border: 1px solid var(--detail-line);
            border-radius: 14px;
            background: rgba(131, 230, 177, .045);
            cursor: pointer;
            opacity: .72;
            transition: opacity .2s ease, border-color .2s ease, transform .2s ease;
        }

        .detail-thumbnail:hover,
        .detail-thumbnail.is-active {
            transform: translateY(-2px);
            border-color: var(--detail-green);
            opacity: 1;
        }

        .detail-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .detail-copy {
            display: flex;
            min-width: 0;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            padding: 10px 0;
        }

        .detail-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 11px;
            border: 1px solid var(--detail-line);
            border-radius: 999px;
            color: var(--detail-green);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .13em;
            text-transform: uppercase;
        }

        .detail-eyebrow::before {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--detail-green);
            box-shadow: 0 0 12px var(--detail-green);
            content: "";
        }

        .detail-copy h1 {
            margin: 20px 0 14px;
            color: var(--detail-text);
            font-family: "DM Serif Display", serif;
            font-size: clamp(36px, 4.5vw, 62px);
            font-weight: 400;
            line-height: .98;
            letter-spacing: -.025em;
            overflow-wrap: anywhere;
        }

        .detail-price {
            color: var(--detail-green);
            font-size: clamp(24px, 3vw, 34px);
            font-weight: 800;
            letter-spacing: -.04em;
        }

        .detail-rating {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 13px;
        }

        .detail-rating-stars {
            color: #e5ad54;
            font-size: 18px;
            letter-spacing: .12em;
            line-height: 1;
        }

        .detail-rating-label {
            color: var(--detail-muted);
            font-size: 11px;
            font-weight: 700;
        }

        .detail-rating-form {
            box-sizing: border-box;
            width: 100%;
            margin-top: 22px;
            padding: 16px;
            border: 1px solid var(--detail-line);
            border-radius: 18px;
            background: rgba(131, 230, 177, .035);
        }

        .detail-rating-form fieldset {
            min-width: 0;
            margin: 0;
            padding: 0;
            border: 0;
        }

        .detail-rating-form legend {
            margin-bottom: 10px;
            color: var(--detail-text);
            font-size: 11px;
            font-weight: 800;
        }

        .detail-rating-options {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 4px;
        }

        .detail-rating-options input {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            clip-path: inset(50%);
        }

        .detail-rating-options label {
            color: rgba(229, 173, 84, .42);
            cursor: pointer;
            font-size: 28px;
            line-height: 1;
            transition: color .16s ease, transform .16s ease;
        }

        .detail-rating-options label:hover,
        .detail-rating-options label:hover ~ label,
        .detail-rating-options input:checked ~ label {
            color: #e5ad54;
        }

        .detail-rating-options label:hover {
            transform: scale(1.12);
        }

        .detail-rating-options input:focus-visible + label {
            outline: 2px solid var(--detail-green);
            outline-offset: 3px;
            border-radius: 3px;
        }

        .detail-rating-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 12px;
        }

        .detail-rating-note {
            color: var(--detail-muted);
            font-size: 10px;
            line-height: 1.5;
        }

        .detail-rating-submit {
            min-height: 38px;
            padding: 0 13px;
            border: 0;
            border-radius: 999px;
            background: linear-gradient(135deg, #83e6b1, #4fd69a);
            color: #07140d;
            cursor: pointer;
            font: 800 10px "Manrope", sans-serif;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .detail-rating-submit:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .detail-feedback {
            box-sizing: border-box;
            width: 100%;
            margin-top: 12px;
            padding: 11px 13px;
            border: 1px solid var(--detail-line);
            border-radius: 12px;
            color: var(--detail-green);
            font-size: 11px;
            line-height: 1.55;
        }

        .detail-feedback.success {
            border-color: rgba(79, 214, 154, .38);
            background: rgba(79, 214, 154, .1);
            color: var(--detail-green);
            font-weight: 700;
        }

        .detail-feedback.info {
            background: rgba(131, 230, 177, .06);
        }

        .detail-feedback.error {
            border-color: rgba(255, 123, 123, .3);
            color: #ffaaaa;
        }

        .detail-divider {
            width: 100%;
            height: 1px;
            margin: 23px 0;
            border: 0;
            background: var(--detail-line);
        }

        .detail-description-label {
            margin-bottom: 8px;
            color: var(--detail-text);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .detail-description {
            margin: 0;
            color: var(--detail-muted);
            font-size: 14px;
            line-height: 1.85;
            overflow-wrap: anywhere;
        }

        .detail-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 28px;
        }

        .detail-actions form {
            display: flex;
            margin: 0;
        }

        .detail-button {
            display: inline-flex;
            min-height: 48px;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 0 19px;
            border: 1px solid var(--detail-line);
            border-radius: 999px;
            background: rgba(131, 230, 177, .08);
            color: var(--detail-text);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
            text-decoration: none !important;
            transition: transform .2s ease, border-color .2s ease, background .2s ease;
        }

        .detail-button-primary {
            border-color: transparent;
            background: linear-gradient(135deg, #83e6b1, #4fd69a);
            color: #07140d;
            box-shadow: 0 12px 28px rgba(79, 214, 154, .15);
        }

        .detail-button:hover {
            transform: translateY(-2px);
            border-color: var(--detail-green);
            background-color: rgba(131, 230, 177, .15);
        }

        .detail-button-primary:hover {
            background: linear-gradient(135deg, #a2f4c2, #6ae5a4);
        }

        .detail-not-found {
            grid-template-columns: 1fr;
            min-height: 300px;
            align-items: center;
        }

        .detail-not-found h1 {
            margin: 0 0 12px;
            font: 400 clamp(34px, 5vw, 56px)/1 "DM Serif Display", serif;
        }

        .detail-not-found p {
            max-width: 520px;
            color: var(--detail-muted);
            line-height: 1.75;
        }

        @media (max-width: 780px) {
            .detail-main {
                width: min(100% - 28px, 600px);
                padding: 112px 0 52px;
            }

            .detail-shell {
                grid-template-columns: minmax(0, 1fr);
                gap: 25px;
                border-radius: 24px;
            }

            .detail-hero-image {
                min-height: 0;
                aspect-ratio: 1.1 / 1;
            }

            .detail-copy {
                padding: 0 2px 4px;
            }

            .detail-copy h1 {
                font-size: clamp(36px, 9vw, 52px);
            }
        }

        @media (max-width: 480px) {
            .detail-main {
                width: calc(100% - 24px);
                padding: 100px 0 36px;
            }

            .detail-shell {
                gap: 18px;
                padding: 12px;
                border-radius: 20px;
            }

            .detail-hero-image {
                aspect-ratio: 1.2 / 1;
                border-radius: 16px;
            }

            .detail-thumbnails {
                gap: 8px;
            }

            .detail-thumbnail {
                border-radius: 11px;
            }

            .detail-copy h1 {
                margin-top: 15px;
            }

            .detail-description {
                font-size: 13px;
            }

            .detail-actions,
            .detail-rating-form {
                width: 100%;
            }

            .detail-actions {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 9px;
                margin-top: 22px;
            }

            .detail-button {
                box-sizing: border-box;
                width: 100%;
                min-width: 0;
                min-height: 44px;
                padding: 0 10px;
                font-size: 10px;
                letter-spacing: .03em;
            }

            .detail-actions form {
                grid-column: 1 / -1;
                min-width: 0;
            }

            .detail-actions form .detail-button {
                width: 100%;
            }

            .detail-rating-form {
                padding: 14px;
            }

            .detail-rating-actions {
                align-items: stretch;
                flex-direction: column;
                gap: 10px;
            }

            .detail-rating-submit {
                width: 100%;
            }
        }

        @media (max-width: 350px) {
            .detail-main {
                width: calc(100% - 18px);
                padding-top: 92px;
            }

            .detail-shell {
                padding: 10px;
            }

            .detail-actions {
                grid-template-columns: 1fr;
            }

            .detail-actions form {
                grid-column: auto;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>
<body>
<?php
require __DIR__ . '/../includes/user_nav.php';
?>
<main class="detail-main">
    <a class="detail-back" href="<?= h(appUrl('User/Products.php')) ?>"><span aria-hidden="true">←</span> Back to products</a>
    <?php if (!$product): ?>
        <section class="detail-shell detail-not-found">
            <div>
                <span class="detail-eyebrow">Collection / Product unavailable</span>
                <h1>We couldn't find that pair.</h1>
                <p>This product may have been removed or the link may be invalid. Browse the collection to find another style.</p>
                <a class="detail-button detail-button-primary" href="<?= h(appUrl('User/Products.php')) ?>">Browse products <span aria-hidden="true">↗</span></a>
            </div>
        </section>
    <?php else: ?>
        <section class="detail-shell" aria-labelledby="productTitle">
            <div class="detail-gallery">
                <div class="detail-hero-image">
                    <img
                        id="productHeroImage"
                        src="<?= h($productImages[0] ?? appUrl('image/background.jpg')) ?>"
                        alt="<?= h((string) $product['name']) ?> product image 1"
                        fetchpriority="high"
                    >
                </div>
                <?php if (count($productImages) > 1): ?>
                    <div class="detail-thumbnails" role="group" aria-label="Product images">
                        <?php foreach ($productImages as $imageIndex => $imageUrl): ?>
                            <button
                                class="detail-thumbnail<?= $imageIndex === 0 ? ' is-active' : '' ?>"
                                type="button"
                                data-image="<?= h($imageUrl) ?>"
                                data-alt="<?= h((string) $product['name']) ?> product image <?= $imageIndex + 1 ?>"
                                aria-label="Show product image <?= $imageIndex + 1 ?>"
                                aria-pressed="<?= $imageIndex === 0 ? 'true' : 'false' ?>"
                            >
                                <img src="<?= h($imageUrl) ?>" alt="" loading="<?= $imageIndex === 0 ? 'eager' : 'lazy' ?>">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="detail-copy">
                <span class="detail-eyebrow"><?= h((string) $product['category']) ?> / DunkHome Kicks</span>
                <h1 id="productTitle"><?= h((string) $product['name']) ?></h1>
                <div class="detail-price">₹<?= number_format((float) $product['price'], 2) ?></div>
                <div class="detail-rating" aria-label="<?= $ratingCount > 0 ? 'Rated ' . number_format($ratingAverage, 1) . ' out of 5 from ' . $ratingCount . ' ratings' : 'No ratings yet' ?>">
                    <span class="detail-rating-stars" aria-hidden="true"><?php for ($star = 1; $star <= 5; $star++): ?><?= $star <= (int) round($ratingAverage) ? '★' : '☆' ?><?php endfor; ?></span>
                    <span class="detail-rating-label"><?= $ratingCount > 0 ? number_format($ratingAverage, 1) . ' / 5 · ' . number_format($ratingCount) . ($ratingCount === 1 ? ' rating' : ' ratings') : 'No ratings yet' ?></span>
                </div>
                <hr class="detail-divider">
                <div class="detail-description-label">Product details</div>
                <p class="detail-description"><?= nl2br(h((string) ($product['description'] ?? 'No description available.'))) ?></p>
                <?php if ($ratingStorageAvailable): ?>
                    <form class="detail-rating-form" method="post" action="<?= h(appUrl('User/ProductDetails.php?id=' . (int) $product['id'])) ?>">
                        <input type="hidden" name="action" value="rate">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                        <fieldset>
                            <legend><?= $userRating > 0 ? 'Update your rating' : 'Rate this pair' ?></legend>
                            <div class="detail-rating-options" aria-label="Choose between one and five stars">
                                <?php for ($star = 1; $star <= 5; $star++): ?>
                                    <input
                                        type="radio"
                                        id="productRating<?= $star ?>"
                                        name="rating"
                                        value="<?= $star ?>"
                                        required
                                        <?= $ratingFormValue === $star ? 'checked' : '' ?>
                                    >
                                    <label for="productRating<?= $star ?>" title="<?= $star ?> <?= $star === 1 ? 'star' : 'stars' ?>" aria-label="<?= $star ?> <?= $star === 1 ? 'star' : 'stars' ?>">★</label>
                                <?php endfor; ?>
                            </div>
                            <div class="detail-rating-actions">
                                <span class="detail-rating-note"><?= userLoggedIn() ? 'Your rating helps other shoppers.' : 'Sign in is required to submit a rating.' ?></span>
                                <button class="detail-rating-submit" type="submit" <?= !$ratingStorageAvailable ? 'disabled' : '' ?>><?= userLoggedIn() ? 'Submit rating' : 'Sign in to rate' ?></button>
                            </div>
                        </fieldset>
                        <?php if ($ratingNotice !== ''): ?>
                            <div class="detail-feedback <?= h($ratingNoticeType) ?>" role="status" aria-live="polite"><?= h($ratingNotice) ?></div>
                        <?php endif; ?>
                        <?php if ($ratingError !== ''): ?>
                            <div class="detail-feedback error" role="alert"><?= h($ratingError) ?></div>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <div class="detail-feedback error" role="status">Rating is temporarily unavailable. Please try again later.</div>
                <?php endif; ?>
                <div class="detail-actions">
                    <form method="post" action="<?= h(appUrl('User/Cart.php')) ?>">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                        <input type="hidden" name="quantity" value="1">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                        <button class="detail-button detail-button-primary" type="submit"><?= userLoggedIn() ? 'Add to cart' : 'Sign in to add to cart' ?> <span aria-hidden="true">＋</span></button>
                    </form>
                    <a class="detail-button" href="<?= h(appUrl('User/Products.php')) ?>">Continue shopping <span aria-hidden="true">↗</span></a>
                    <a class="detail-button" href="<?= h(appUrl('User/Cart.php')) ?>">View cart <span aria-hidden="true">→</span></a>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="assets/dunkhome-ui.js?v=20261003-nav14" defer></script>
<script>
document.querySelectorAll('.detail-thumbnail').forEach((thumbnail) => {
    thumbnail.addEventListener('click', () => {
        const heroImage = document.getElementById('productHeroImage');
        if (!heroImage) return;

        heroImage.classList.add('is-changing');
        heroImage.onload = () => heroImage.classList.remove('is-changing');
        heroImage.onerror = () => {
            heroImage.classList.remove('is-changing');
            console.error('Unable to load selected product image:', thumbnail.dataset.image);
        };
        heroImage.src = thumbnail.dataset.image;
        heroImage.alt = thumbnail.dataset.alt || 'Product image';

        document.querySelectorAll('.detail-thumbnail').forEach((item) => {
            const isSelected = item === thumbnail;
            item.classList.toggle('is-active', isSelected);
            item.setAttribute('aria-pressed', String(isSelected));
        });
    });
});
</script>
</body>
</html>
