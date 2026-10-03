<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../includes/order_helpers.php';
requireUser();

$code = trim((string) ($_GET['code'] ?? ''));
$userId = (int) $_SESSION['user_id'];
$order = null;
$history = [];

if (preg_match('/^DHK-[0-9]{8}-[A-F0-9]{6}$/', $code)) {
    $statement = $conn->prepare('SELECT id,order_code,status,created_at FROM orders WHERE order_code=? AND user_id=? LIMIT 1');
    if (!$statement) {
        error_log('Track booking lookup preparation failed: ' . $conn->error);
        http_response_code(503);
        exit('Booking tracking is temporarily unavailable. Please try again later.');
    }

    $statement->bind_param('si', $code, $userId);
    $statement->execute();
    $order = $statement->get_result()->fetch_assoc();
    $statement->close();

    if ($order) {
        $history = loadOrderStatusHistory($conn, (int) $order['id']);
    }
}

if (!$order) {
    http_response_code(404);
}

$status = (string) ($order['status'] ?? '');
$normalizedStatus = strtolower(trim($status));
$statusCopy = [
    'pending' => 'Your booking has been received and is waiting for the store to review it.',
    'confirmed' => 'The store confirmed your booking. We’ll keep you updated as it moves forward.',
    'processing' => 'Your pair is being prepared. Check back for the next tracking update.',
    'ready' => 'Your order is ready for the next delivery step.',
    'out for delivery' => 'Your order is on its way to the delivery address.',
    'shipped' => 'Your order has left the store and is on its way.',
    'delivered' => 'Your order has been delivered. We hope you enjoy your new pair.',
    'cancelled' => 'This booking has been cancelled. Contact the store if you need help.',
];
$statusDescription = $statusCopy[$normalizedStatus] ?? 'Your latest booking status is shown below.';
$milestones = [
    ['label' => 'Booking placed', 'description' => 'Your order request is in.'],
    ['label' => 'Confirmed', 'description' => 'The store has reviewed your booking.'],
    ['label' => 'Preparing', 'description' => 'Your pair is being prepared.'],
    ['label' => 'On its way', 'description' => 'Your order is with the delivery service.'],
    ['label' => 'Delivered', 'description' => 'Your pair has arrived.'],
];
$milestoneIndex = match ($normalizedStatus) {
    'pending' => 0,
    'confirmed' => 1,
    'processing', 'ready' => 2,
    'shipped', 'out for delivery' => 3,
    'delivered' => 4,
    default => -1,
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../includes/favicon.php'; ?>
    <base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07100d">
    <meta name="description" content="View the latest status and updates for your DunkHome Kicks booking.">
    <title>Track Booking | DunkHome Kicks</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-user-nav25">
    <link rel="stylesheet" href="<?= h(appUrl('assets/user-premium.css?v=20261003-premium1')) ?>">
    <link rel="stylesheet" href="<?= h(appUrl('assets/dunkhome-footer.css?v=20261003-footer3')) ?>">
    <style>
        :root {
            --track-bg: #07100b;
            --track-panel: rgba(17, 29, 22, .88);
            --track-text: #f4f8f5;
            --track-muted: #9aa99f;
            --track-green: #8fe9b5;
            --track-line: rgba(143, 233, 181, .17);
            --track-shadow: 0 24px 70px rgba(0, 0, 0, .2);
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            color: var(--track-text);
            font-family: "DM Sans", sans-serif;
            background:
                radial-gradient(ellipse at 8% 10%, rgba(143, 233, 181, .12), transparent 32%),
                radial-gradient(ellipse at 90% 26%, rgba(255, 201, 139, .07), transparent 30%),
                var(--track-bg);
        }
        body.light {
            --track-bg: #f3f7f3;
            --track-panel: rgba(255, 255, 255, .95);
            --track-text: #13231a;
            --track-muted: #627267;
            --track-green: #26764b;
            --track-line: rgba(20, 54, 34, .12);
            --track-shadow: 0 22px 60px rgba(22, 45, 31, .08);
        }
        a { color: inherit; text-decoration: none; }
        .track-wrap { width: min(1000px, calc(100% - 40px)); margin: 0 auto; }
        .track-main { padding: 136px 0 78px; }
        .track-hero, .track-panel, .track-empty {
            border: 1px solid var(--track-line);
            border-radius: 23px;
            background: linear-gradient(150deg, var(--track-panel), rgba(12, 26, 18, .74));
            box-shadow: var(--track-shadow);
        }
        body.light .track-hero, body.light .track-panel, body.light .track-empty { background: linear-gradient(150deg, #fff, #f1f6f2); }
        .track-hero {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 22px;
            margin-bottom: 16px;
            padding: clamp(24px, 5vw, 40px);
        }
        .track-hero::after {
            position: absolute;
            top: -135px;
            right: 115px;
            width: 260px;
            height: 260px;
            border: 1px solid var(--track-line);
            border-radius: 50%;
            content: "";
            pointer-events: none;
        }
        .track-hero-copy, .track-code-card { position: relative; z-index: 1; }
        .track-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            color: var(--track-green);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .15em;
            text-transform: uppercase;
        }
        .track-kicker::before { width: 7px; height: 7px; border-radius: 50%; background: var(--track-green); content: ""; }
        .track-hero h1 { margin: 0; font: clamp(36px, 5vw, 50px)/1.02 "DM Serif Display", serif; letter-spacing: -.04em; }
        .track-description { max-width: 520px; margin: 12px 0 0; color: var(--track-muted); font-size: 12px; line-height: 1.75; }
        .track-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 18px;
            padding: 8px 11px;
            border: 1px solid var(--track-line);
            border-radius: 999px;
            color: var(--track-green);
            font-size: 10px;
            font-weight: 800;
        }
        .track-status::before { width: 6px; height: 6px; border-radius: 50%; background: currentColor; content: ""; }
        .track-code-card {
            flex: 0 0 auto;
            min-width: 190px;
            padding: 16px 18px;
            border: 1px solid var(--track-line);
            border-radius: 16px;
            background: rgba(143, 233, 181, .06);
        }
        .track-code-label { display: block; margin-bottom: 7px; color: var(--track-muted); font-size: 9px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        .track-code { font-size: 15px; font-weight: 800; letter-spacing: .02em; overflow-wrap: anywhere; }
        .track-date { display: block; margin-top: 8px; color: var(--track-muted); font-size: 10px; }
        .track-panel { margin-bottom: 16px; padding: clamp(20px, 4vw, 29px); }
        .track-section-heading { display: flex; align-items: end; justify-content: space-between; gap: 16px; margin-bottom: 23px; }
        .track-section-heading h2 { margin: 0; font: 25px "DM Serif Display", serif; }
        .track-section-heading p { margin: 0; color: var(--track-muted); font-size: 10px; }
        .track-progress {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            position: relative;
            gap: 8px;
        }
        .track-progress::before {
            position: absolute;
            top: 12px;
            right: 10%;
            left: 10%;
            height: 2px;
            background: var(--track-line);
            content: "";
        }
        .track-progress-fill {
            position: absolute;
            top: 12px;
            left: 10%;
            width: <?= $milestoneIndex < 0 ? '0' : (string) ($milestoneIndex * 20) ?>%;
            height: 2px;
            background: var(--track-green);
            transition: width .35s ease;
        }
        .track-step { position: relative; z-index: 1; min-width: 0; }
        .track-step-dot {
            width: 25px;
            height: 25px;
            display: grid;
            place-items: center;
            margin-bottom: 11px;
            border: 1px solid var(--track-line);
            border-radius: 50%;
            background: var(--track-bg);
            color: var(--track-muted);
            font-size: 10px;
            font-weight: 800;
        }
        .track-step.is-complete .track-step-dot, .track-step.is-current .track-step-dot {
            border-color: var(--track-green);
            background: var(--track-green);
            color: #07160d;
            box-shadow: 0 0 0 4px rgba(143, 233, 181, .11);
        }
        .track-step strong { display: block; margin-bottom: 5px; font-size: 10px; line-height: 1.4; }
        .track-step span { display: block; color: var(--track-muted); font-size: 9px; line-height: 1.55; }
        .track-timeline { display: grid; gap: 0; }
        .track-event { position: relative; display: grid; grid-template-columns: 35px minmax(0, 1fr); gap: 13px; }
        .track-event:not(:last-child) { padding-bottom: 22px; }
        .track-event:not(:last-child)::before { position: absolute; top: 31px; bottom: -1px; left: 14px; width: 1px; background: var(--track-line); content: ""; }
        .track-event-marker {
            position: relative;
            z-index: 1;
            width: 30px;
            height: 30px;
            display: grid;
            place-items: center;
            border: 1px solid var(--track-line);
            border-radius: 50%;
            background: rgba(143, 233, 181, .08);
            color: var(--track-green);
            font-size: 12px;
        }
        .track-event.is-latest .track-event-marker { border-color: var(--track-green); background: var(--track-green); color: #07160d; }
        .track-event-copy { min-width: 0; padding-top: 2px; }
        .track-event-title { display: block; font-size: 12px; font-weight: 800; }
        .track-event time { display: block; margin-top: 5px; color: var(--track-muted); font-size: 10px; }
        .track-event-note { margin: 7px 0 0; color: var(--track-muted); font-size: 10px; line-height: 1.7; overflow-wrap: anywhere; }
        .track-empty { padding: clamp(28px, 6vw, 55px); text-align: center; }
        .track-empty h1 { margin: 0; font: 37px "DM Serif Display", serif; }
        .track-empty p { max-width: 430px; margin: 12px auto 20px; color: var(--track-muted); font-size: 12px; line-height: 1.7; }
        .track-link {
            min-height: 43px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 16px;
            border: 1px solid var(--track-line);
            border-radius: 999px;
            color: var(--track-text);
            font-size: 10px;
            font-weight: 800;
            transition: transform .2s ease, border-color .2s ease;
        }
        .track-link:hover { transform: translateY(-2px); border-color: var(--track-green); }
        .track-link-primary { border-color: transparent; background: linear-gradient(135deg, #9bf0bd, #59d692); color: #07160d; }
        .track-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }
        @media (max-width: 700px) {
            .track-hero { align-items: flex-start; flex-direction: column; }
            .track-code-card { min-width: 0; }
            .track-progress { grid-template-columns: minmax(0, 1fr); gap: 0; }
            .track-progress::before { top: 12px; bottom: 12px; left: 12px; width: 2px; height: auto; }
            .track-progress-fill { top: 12px; bottom: auto; left: 12px; width: 2px; height: <?= $milestoneIndex < 0 ? '0' : (string) ($milestoneIndex * 20) ?>%; }
            .track-step { display: grid; grid-template-columns: 25px minmax(0, 1fr); column-gap: 13px; min-height: 55px; }
            .track-step-dot { grid-row: 1 / span 2; margin: 0; }
            .track-step strong { padding-top: 3px; }
            .track-step span { padding-bottom: 11px; }
        }
        @media (max-width: 600px) {
            .track-wrap { width: calc(100% - 28px); }
            .track-main { padding: 105px 0 55px; }
            .track-hero { gap: 17px; padding: 23px 19px; border-radius: 19px; }
            .track-hero h1 { font-size: 39px; }
            .track-description { font-size: 11px; }
            .track-panel { padding: 19px; border-radius: 18px; }
            .track-section-heading { align-items: flex-start; flex-direction: column; gap: 7px; margin-bottom: 18px; }
            .track-actions { display: grid; grid-template-columns: minmax(0, 1fr); }
            .track-link { width: 100%; }
        }
        @media (prefers-reduced-motion: reduce) {
            .track-link, .track-progress-fill { transition: none; }
        }
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/user_nav.php'; ?>
<div class="track-wrap">
    <main class="track-main">
        <?php if (!$order): ?>
            <section class="track-empty">
                <div class="track-kicker">DunkHome Kicks / Order tracking</div>
                <h1>We couldn’t find this booking.</h1>
                <p>This booking isn’t available in your account. Check the reference or open your bookings to find the right order.</p>
                <a class="track-link track-link-primary" href="User/OrderHistory.php">View my bookings <span aria-hidden="true">→</span></a>
            </section>
        <?php else: ?>
            <section class="track-hero" aria-labelledby="trackTitle">
                <div class="track-hero-copy">
                    <div class="track-kicker">DunkHome Kicks / Order tracking</div>
                    <h1 id="trackTitle"><?= h($status) ?></h1>
                    <p class="track-description"><?= h($statusDescription) ?></p>
                    <span class="track-status"><?= h($status) ?><?= $normalizedStatus === 'pending' ? ' · Awaiting store confirmation' : '' ?></span>
                </div>
                <div class="track-code-card">
                    <span class="track-code-label">Booking reference</span>
                    <span class="track-code"><?= h((string) $order['order_code']) ?></span>
                    <span class="track-date">Placed <?= h((string) $order['created_at']) ?></span>
                </div>
            </section>

            <?php if ($normalizedStatus !== 'cancelled'): ?>
                <section class="track-panel" aria-labelledby="progressTitle">
                    <header class="track-section-heading">
                        <h2 id="progressTitle">Your delivery journey</h2>
                        <p>We’ll update this timeline as your booking moves along.</p>
                    </header>
                    <div class="track-progress" aria-label="Booking progress">
                        <?php foreach ($milestones as $index => $milestone): ?>
                            <?php
                            $stepClass = $index < $milestoneIndex ? ' is-complete' : ($index === $milestoneIndex ? ' is-current' : '');
                            $stepLabel = $index < $milestoneIndex ? 'Complete' : ($index === $milestoneIndex ? 'Current step' : 'Upcoming');
                            ?>
                            <div class="track-step<?= $stepClass ?>" aria-label="<?= h($stepLabel . ': ' . $milestone['label']) ?>">
                                <span class="track-step-dot" aria-hidden="true"><?= $index < $milestoneIndex ? '✓' : (string) ($index + 1) ?></span>
                                <strong><?= h($milestone['label']) ?></strong>
                                <span><?= h($milestone['description']) ?></span>
                            </div>
                        <?php endforeach; ?>
                        <span class="track-progress-fill" aria-hidden="true"></span>
                    </div>
                </section>
            <?php endif; ?>

            <section class="track-panel" aria-labelledby="updatesTitle">
                <header class="track-section-heading">
                    <h2 id="updatesTitle">Booking updates</h2>
                    <p><?= count($history) ?> <?= count($history) === 1 ? 'update' : 'updates' ?></p>
                </header>
                <?php if ($history): ?>
                    <div class="track-timeline">
                        <?php foreach ($history as $index => $event): ?>
                            <article class="track-event<?= $index === count($history) - 1 ? ' is-latest' : '' ?>">
                                <span class="track-event-marker" aria-hidden="true"><?= $index === count($history) - 1 ? '✓' : '·' ?></span>
                                <div class="track-event-copy">
                                    <strong class="track-event-title"><?= h((string) $event['new_status']) ?></strong>
                                    <time datetime="<?= h(date(DATE_ATOM, strtotime((string) $event['created_at']) ?: time())) ?>"><?= h((string) $event['created_at']) ?></time>
                                    <?php if (!empty($event['note'])): ?><p class="track-event-note"><?= h((string) $event['note']) ?></p><?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="track-event-note">Your booking is recorded. The store’s next update will appear here.</p>
                <?php endif; ?>
                <div class="track-actions">
                    <a class="track-link track-link-primary" href="User/BookingSuccess.php?code=<?= rawurlencode((string) $order['order_code']) ?>">View booking details <span aria-hidden="true">→</span></a>
                    <a class="track-link" href="User/OrderHistory.php">All my bookings</a>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="assets/dunkhome-ui.js?v=20261003-nav14" defer></script>
</body>
</html>
