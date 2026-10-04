<?php
$footerYear = date('Y');
?>
<footer class="dk-site-footer">
    <div class="dk-footer-glow" aria-hidden="true"></div>
    <div class="dk-footer-inner">
        <section class="dk-footer-featured" aria-label="Explore DunkHome Kicks">
            <div>
                <span class="dk-footer-eyebrow">YOUR NEXT MOVE STARTS HERE</span>
                <h2>Find the pair that<br><span>feels like you.</span></h2>
            </div>
            <div class="dk-footer-featured-actions">
                <a href="<?= h(appUrl('User/Products.php')) ?>">Shop the collection <span aria-hidden="true">↗</span></a>
                <a href="<?= h(appUrl('User/TrackOrderLookup.php')) ?>">Track an order <span aria-hidden="true">→</span></a>
            </div>
        </section>
        <div class="dk-footer-main">
            <div class="dk-footer-brand">
                <a class="dk-footer-logo" href="<?= h(appUrl('index.php')) ?>">
                    <img src="<?= h(appUrl('image/logo.jpg')) ?>" alt="" width="48" height="48" loading="lazy">
                    <span>DunkHome <strong>Kicks</strong></span>
                </a>
                <p>Find your next favorite pair. Discover sneakers made for everyday movement, personal style and your own rotation.</p>
                <a class="dk-footer-cta" href="<?= h(appUrl('User/Products.php')) ?>">Explore the collection <span aria-hidden="true">↗</span></a>
            </div>

            <nav class="dk-footer-column" aria-label="Shop links">
                <h2>Explore</h2>
                <a href="<?= h(appUrl('User/Home.php')) ?>">Home</a>
                <a href="<?= h(appUrl('User/Products.php')) ?>">Products</a>
                <a href="<?= h(appUrl('index.php#categories')) ?>">Categories</a>
                <a href="<?= h(appUrl('index.php#featured')) ?>">Featured pairs</a>
                <a href="<?= h(appUrl('User/Cart.php')) ?>">Your cart</a>
            </nav>

            <nav class="dk-footer-column" aria-label="Customer links">
                <h2>Customer care</h2>
                <a href="<?= h(appUrl('User/TrackOrderLookup.php')) ?>">Track order</a>
                <a href="<?= h(appUrl('User/OrderHistory.php')) ?>">Order history</a>
                <a href="<?= h(appUrl('User/MyProfile.php')) ?>">My profile</a>
                <a href="<?= h(appUrl('User/About.php')) ?>">About us</a>
                <a href="<?= h(appUrl('User/Contact.php')) ?>">Contact us</a>
                <?php if (function_exists('userLoggedIn') && userLoggedIn()): ?>
                    <a href="<?= h(appUrl('Logout.php?scope=user')) ?>">Logout</a>
                <?php else: ?>
                    <a href="<?= h(appUrl('User/SignIn.php')) ?>">Sign in</a>
                <?php endif; ?>
            </nav>

            <div class="dk-footer-note">
                <span class="dk-footer-eyebrow">The DunkHome promise</span>
                <p>Find your fit. Follow every step. Make every move yours.</p>
                <a class="dk-footer-note-link" href="<?= h(appUrl('User/Products.php')) ?>">Find your next pair <span aria-hidden="true">→</span></a>
                <div class="dk-footer-contact">
                    <a href="tel:+917012372216">+91 7012372216</a>
                    <a href="mailto:Dunkhomekicks@gmail.com">Dunkhomekicks@gmail.com</a>
                </div>
            </div>
        </div>

        <div class="dk-footer-bottom">
            <span>&copy; <?= (int) $footerYear ?> DunkHome Kicks. All rights reserved.</span>
            <span class="dk-footer-made">Made for every move <span aria-hidden="true">✦</span></span>
            <a href="<?= h(appUrl('index.php')) ?>">Back to top ↑</a>
        </div>
    </div>
</footer>
