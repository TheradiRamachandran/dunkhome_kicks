<?php
declare(strict_types=1);
require_once __DIR__ . '/../session.php';
$supportEmail = 'theradiramachandran@gmail.com';
$supportPhone = '9566589111';
$messageSent = '';
$messageError = '';
$contactName = '';
$contactEmail = (string) ($_SESSION['user_email'] ?? '');
$contactBody = '';
$flash = $_SESSION['contact_message_flash'] ?? null;
unset($_SESSION['contact_message_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedName = $_POST['name'] ?? '';
    $postedEmail = $_POST['email'] ?? '';
    $postedMessage = $_POST['message'] ?? '';
    $postedCsrfToken = $_POST['csrf_token'] ?? null;
    $contactName = is_string($postedName) ? trim($postedName) : '';
    $contactEmail = is_string($postedEmail) ? trim($postedEmail) : '';
    $contactBody = is_string($postedMessage) ? trim($postedMessage) : '';
    $nameLength = function_exists('mb_strlen') ? mb_strlen($contactName) : strlen($contactName);
    $messageLength = function_exists('mb_strlen') ? mb_strlen($contactBody) : strlen($contactBody);

    if (!is_string($postedCsrfToken) || !verifyCsrf($postedCsrfToken)) {
        $messageError = 'Your form session expired. Refresh the page and submit your message again.';
    } elseif ($nameLength < 2 || $nameLength > 120) {
        $messageError = 'Enter your name using 2 to 120 characters.';
    } elseif (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL) || strlen($contactEmail) > 254) {
        $messageError = 'Enter a valid email address so we can reply.';
    } elseif ($messageLength < 10 || $messageLength > 3000) {
        $messageError = 'Your message must be between 10 and 3000 characters.';
    } else {
        require_once __DIR__ . '/../Mailer.php';
        $result = sendDunkHomeContactEmail($contactName, $contactEmail, $contactBody);
        if (($result['status'] ?? '') === 'success') {
            $_SESSION['contact_message_flash'] = ['type' => 'success', 'text' => (string) $result['message']];
            header('Location: ' . appUrl('User/Contact.php#contactMessage'));
            exit;
        }
        $messageError = (string) ($result['message'] ?? 'We could not send your message. Please call us instead.');
    }
}

if (is_array($flash) && ($flash['type'] ?? '') === 'success') {
    $messageSent = (string) ($flash['text'] ?? '');
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
    <meta name="description" content="Find help with DunkHome Kicks bookings, accounts, and products.">
    <title>Contact Us | DunkHome Kicks</title>
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-user-nav25">
    <link rel="stylesheet" href="<?= h(appUrl('assets/user-premium.css?v=20261003-premium1')) ?>">
    <link rel="stylesheet" href="<?= h(appUrl('assets/dunkhome-footer.css?v=20261003-footer3')) ?>">
    <link rel="stylesheet" href="<?= h(appUrl('assets/customer-pages.css?v=20261003-customer6')) ?>">
</head>
<body class="customer-page">
<?php require __DIR__ . '/../includes/user_nav.php'; ?>
<main class="customer-wrap">
    <section class="customer-simple-hero">
        <p class="customer-eyebrow">Here when you need us</p>
        <h1>Let’s get you<br><span>the right help.</span></h1>
        <p>Call, email, or send us a message. For booking questions, include your booking reference so we can help faster.</p>
    </section>
    <section class="customer-contact-grid" aria-label="Customer support options">
        <article class="customer-contact-card">
            <span class="customer-contact-icon" aria-hidden="true">↗</span>
            <p class="customer-eyebrow">Booking updates</p>
            <h2>Check your order</h2>
            <p>Open your booking history or enter a booking reference to see the current status and delivery timeline.</p>
            <a class="customer-text-link" href="<?= h(appUrl('User/OrderHistory.php')) ?>">Go to order history <span aria-hidden="true">→</span></a>
        </article>
        <article class="customer-contact-card">
            <span class="customer-contact-icon" aria-hidden="true">✉</span>
            <p class="customer-eyebrow">Customer support</p>
            <h2>Call or email us</h2>
            <p>Prefer to speak with us directly? Reach our team using the contact details below.</p>
            <a class="customer-text-link" href="tel:+91<?= h($supportPhone) ?>">+91 <?= h($supportPhone) ?> <span aria-hidden="true">↗</span></a>
            <a class="customer-text-link customer-contact-email" href="mailto:<?= h($supportEmail) ?>"><?= h($supportEmail) ?> <span aria-hidden="true">↗</span></a>
        </article>
        <article class="customer-contact-card">
            <span class="customer-contact-icon" aria-hidden="true">◎</span>
            <p class="customer-eyebrow">Account access</p>
            <h2>Need account help?</h2>
            <p>Sign in to view your profile, booking history, and private tracking details. You can also recover access with an email code.</p>
            <a class="customer-text-link" href="<?= h(appUrl('User/SignIn.php')) ?>">Account help <span aria-hidden="true">→</span></a>
        </article>
    </section>
    <section class="customer-message-card" id="contactMessage" aria-labelledby="contactMessageTitle">
        <div class="customer-message-intro">
            <p class="customer-eyebrow">Send us a note</p>
            <h2 id="contactMessageTitle">What can we help with?</h2>
            <p>Share a few details and our team will get back to you at the email address you provide.</p>
            <div class="customer-message-contact"><span>Call us</span><a href="tel:+91<?= h($supportPhone) ?>">+91 <?= h($supportPhone) ?></a></div>
            <div class="customer-message-contact"><span>Email</span><a href="mailto:<?= h($supportEmail) ?>"><?= h($supportEmail) ?></a></div>
        </div>
        <form class="customer-contact-form" method="post" action="<?= h(appUrl('User/Contact.php#contactMessage')) ?>">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <label for="contactName">Your name</label>
            <input id="contactName" name="name" type="text" minlength="2" maxlength="120" autocomplete="name" value="<?= h($contactName) ?>" placeholder="Name" required>
            <label for="contactEmail">Email address</label>
            <input id="contactEmail" name="email" type="email" maxlength="254" autocomplete="email" value="<?= h($contactEmail) ?>" placeholder="you@example.com" required>
            <label for="contactBody">Message</label>
            <textarea id="contactBody" name="message" minlength="10" maxlength="3000" rows="6" placeholder="Tell us how we can help..." required><?= h($contactBody) ?></textarea>
            <?php if ($messageError !== ''): ?>
                <p class="customer-form-error" role="alert"><?= h($messageError) ?></p>
            <?php elseif ($messageSent !== ''): ?>
                <p class="customer-form-success" role="status"><?= h($messageSent) ?></p>
            <?php endif; ?>
            <button class="customer-button customer-button-primary" type="submit">Send message <span aria-hidden="true">→</span></button>
            <p class="customer-form-footnote">Your message will be emailed to our support team.</p>
        </form>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="<?= h(appUrl('assets/dunkhome-ui.js?v=20261003-nav14')) ?>" defer></script>
</body>
</html>
