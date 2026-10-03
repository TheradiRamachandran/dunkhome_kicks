<?php
declare(strict_types=1);
require_once __DIR__ . '/../session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../includes/favicon.php'; ?>
    <base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07100d">
    <meta name="description" content="Learn about the DunkHome Kicks shopping experience.">
    <title>About | DunkHome Kicks</title>
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-user-nav25">
    <link rel="stylesheet" href="<?= h(appUrl('assets/user-premium.css?v=20261003-premium1')) ?>">
    <link rel="stylesheet" href="<?= h(appUrl('assets/dunkhome-footer.css?v=20261003-footer3')) ?>">
    <link rel="stylesheet" href="<?= h(appUrl('assets/customer-pages.css?v=20261003-customer6')) ?>">
</head>
<body class="customer-page">
<?php require __DIR__ . '/../includes/user_nav.php'; ?>
<main class="customer-wrap">
    <section class="customer-hero customer-about-hero">
        <div class="customer-copy">
            <p class="customer-eyebrow">About DunkHome Kicks</p>
            <h1>Good shoes.<br><span>Your own direction.</span></h1>
            <p>We make it simple to explore sneakers, place a booking, and follow its progress. Find a pair that feels like you and keep every order update close at hand.</p>
            <div class="customer-actions">
                <a class="customer-button customer-button-primary" href="<?= h(appUrl('User/Products.php')) ?>">Explore products <span aria-hidden="true">↗</span></a>
                <a class="customer-button" href="<?= h(appUrl('User/Contact.php')) ?>">Get in touch</a>
            </div>
        </div>
        <div class="customer-hero-art" aria-hidden="true">
            <span class="customer-art-orbit"></span>
            <span class="customer-art-mark">DH<br><strong>K</strong></span>
            <span class="customer-art-caption">MADE FOR EVERY MOVE</span>
        </div>
    </section>
    <section class="customer-feature-grid" aria-label="Shopping with DunkHome Kicks">
        <article class="customer-feature-card">
            <span class="customer-feature-number">01</span>
            <h2>Find your pair</h2>
            <p>Browse the collection, explore product details, and save the pairs that fit your everyday style.</p>
        </article>
        <article class="customer-feature-card">
            <span class="customer-feature-number">02</span>
            <h2>Book with clarity</h2>
            <p>Review your cart and delivery details before placing your booking. Your order starts as pending while the store reviews it.</p>
        </article>
        <article class="customer-feature-card">
            <span class="customer-feature-number">03</span>
            <h2>Follow every update</h2>
            <p>Use your booking reference to see the latest status and delivery progress from your account.</p>
        </article>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="<?= h(appUrl('assets/dunkhome-ui.js?v=20261003-nav14')) ?>" defer></script>
</body>
</html>
