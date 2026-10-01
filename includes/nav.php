<?php
$adminNavigationItems = [
    ['Admin/AdminDashboard.php', 'Dashboard'],
    ['Admin/AddProduct.php', 'Add product'],
    ['Admin/ManageProducts.php', 'Manage products'],
    ['Admin/AddCategory.php', 'Add category'],
    ['Admin/ManageCategories.php', 'Manage categories'],
    ['Admin/ManageUsers.php', 'Manage users'],
    ['Admin/AdminOrders.php', 'Manage orders'],
    ['Admin/AdminReviews.php', 'Reviews'],
    ['Admin/AdminContact.php', 'Contact'],
    ['Admin/ChangePassword.php', 'Change password'],
    ['index.php', 'View website'],
];
$adminCurrentPage = basename((string) parse_url((string) ($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH));
foreach ($adminNavigationItems as [$path, $label]):
    $active = $adminCurrentPage === basename($path)
        || ($adminCurrentPage === 'AdminOrderDetails.php' && basename($path) === 'AdminOrders.php');
?>
<a class="nav-link admin-nav-link<?= $active ? ' active' : '' ?>" href="<?= h(appUrl($path)) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= h($label) ?></a>
<?php endforeach; ?>
<?php if (($adminNavigationShowThemeToggle ?? true) && $adminCurrentPage !== 'AdminDashboard.php'): ?>
<button class="admin-nav-link admin-theme-toggle" type="button" id="themeToggle" title="Toggle theme" aria-label="Switch to light theme">☀️</button>
<?php endif; ?>
<a class="nav-link admin-nav-link" href="<?= h(appUrl('Admin/Logout.php?scope=admin')) ?>">Sign out</a>
