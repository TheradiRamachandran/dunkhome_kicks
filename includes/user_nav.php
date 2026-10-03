<?php
$userNavLoggedIn = function_exists('userLoggedIn') && userLoggedIn();
$userNavAdminLoggedIn = function_exists('adminLoggedIn') && adminLoggedIn();
$userNavEmail = trim((string) ($_SESSION['user_email'] ?? ''));
$userNavInitial = $userNavEmail !== '' ? strtoupper(substr($userNavEmail, 0, 1)) : 'G';
$userNavCart = is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : [];
$userNavCartCount = array_sum(array_map('intval', $userNavCart));
?>
<header class="dh-user-header" id="header">
    <div class="dh-user-header-inner">
        <a class="dh-user-logo" href="<?= h(appUrl('index.php')) ?>">
            <img src="<?= h(appUrl('image/logo.jpeg')) ?>" alt="" width="42" height="42">
            <span>DunkHome <strong>Kicks</strong></span>
        </a>
        <?php if ($userNavLoggedIn && $userNavEmail !== ''): ?>
            <a class="dh-user-identity" href="<?= h(appUrl('User/MyProfile.php')) ?>" title="<?= h($userNavEmail) ?>">
                <span class="dh-user-avatar" aria-hidden="true"><?= h($userNavInitial) ?></span>
                <span class="dh-user-identity-copy"><strong><?= h($userNavEmail) ?></strong><small>My account</small></span>
            </a>
        <?php endif; ?>
        <div class="dh-user-nav-scrim" id="navScrim" aria-hidden="true"></div>
        <nav class="dh-user-links" id="navLinks" aria-label="Main navigation">
            <div class="dh-user-drawer-head">
                <a class="dh-user-drawer-logo" href="<?= h(appUrl('User/Home.php')) ?>">
                    <img src="<?= h(appUrl('image/logo.jpeg')) ?>" alt="" width="40" height="40">
                    <span>DunkHome <strong>Kicks</strong></span>
                </a>
                <button type="button" class="dh-user-drawer-theme" id="drawerThemeToggle" aria-label="Switch theme">☀️</button>
                <button type="button" class="dh-user-drawer-close" id="drawerCloseBtn" aria-label="Close navigation menu" aria-controls="navLinks">×</button>
            </div>
            <p class="dh-user-nav-label">YOUR DUNKHOME</p>
            <a href="<?= h(appUrl('User/Home.php')) ?>">Home</a>
            <a href="<?= h(appUrl('User/Products.php')) ?>">Products</a>
            <a href="<?= h(appUrl('User/Cart.php')) ?>" class="dh-user-cart-link">Cart<?php if ($userNavCartCount > 0): ?><span class="dh-user-cart-count"><?= (int) $userNavCartCount ?></span><?php endif; ?></a>
            <a href="<?= h(appUrl('User/OrderHistory.php')) ?>">Order History</a>
            <a href="<?= h(appUrl('User/TrackOrderLookup.php')) ?>">Track Order</a>
            <a href="<?= h(appUrl('User/About.php')) ?>">About</a>
            <a href="<?= h(appUrl('User/Contact.php')) ?>">Contact Us</a>
            <a href="<?= h(appUrl('index.php#categories')) ?>">Categories</a>
            <?php if ($userNavLoggedIn): ?>
                <a href="<?= h(appUrl('User/MyProfile.php')) ?>">My Profile</a>
                <a href="<?= h(appUrl('User/ChangePassword.php')) ?>">Change Password</a>
                <a class="dh-user-logout" href="<?= h(appUrl('Logout.php?scope=user')) ?>">Logout</a>
            <?php else: ?>
                <a href="<?= h(appUrl('User/SignIn.php')) ?>">My Profile</a>
            <?php endif; ?>
            <?php if ($userNavAdminLoggedIn): ?>
                <a href="<?= h(appUrl('Admin/AdminDashboard.php')) ?>">Admin</a>
            <?php endif; ?>
            <a class="dh-user-drawer-profile" href="<?= h(appUrl($userNavLoggedIn ? 'User/MyProfile.php' : 'User/SignIn.php')) ?>">
                <span class="dh-user-avatar" aria-hidden="true"><?= h($userNavInitial) ?></span>
                <span class="dh-user-identity-copy">
                    <strong><?= h($userNavLoggedIn && $userNavEmail !== '' ? $userNavEmail : 'Welcome to DunkHome') ?></strong>
                    <small><?= $userNavLoggedIn ? 'Customer account' : 'Sign in to your account' ?></small>
                </span>
                <span class="dh-user-profile-arrow" aria-hidden="true">↗</span>
            </a>
        </nav>
        <div class="dh-user-actions">
            <button type="button" class="dh-user-theme" id="themeToggle" aria-label="Toggle theme" title="Toggle theme">☀️</button>
            <?php if (!$userNavLoggedIn): ?>
                <a class="dh-user-account" href="<?= h(appUrl('User/SignIn.php')) ?>">Sign in</a>
            <?php endif; ?>
            <button type="button" class="dh-user-menu" id="menuBtn" aria-label="Open menu" aria-expanded="false" aria-controls="navLinks">☰</button>
        </div>
    </div>
</header>