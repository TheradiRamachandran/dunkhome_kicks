<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../includes/category_image_helpers.php';
requireAdmin();

$adminEmail = (string) ($_SESSION['admin_email'] ?? 'Administrator');
$emailName = explode('@', $adminEmail)[0] ?? '';
$adminInitials = strtoupper(substr((string) (preg_replace('/[^a-zA-Z0-9]/', '', $emailName) ?: 'AD'), 0, 2));

$message = '';
$messageType = 'error';
$name = '';
$description = '';
$slugInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $slugInput = trim((string) ($_POST['slug'] ?? ''));
    $slugSource = $slugInput !== '' ? $slugInput : $name;
    $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $slugSource), '-'));

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $message = 'This form expired. Refresh the page and try again.';
    } elseif ($name === '' || strlen($name) > 80 || $slug === '' || strlen($slug) > 100) {
        $message = 'Enter a category name and a valid slug.';
    } else {
        $check = $conn->prepare('SELECT id FROM categories WHERE name = ? OR slug = ? LIMIT 1');
        if (!$check) {
            error_log('Category duplicate check preparation failed: ' . $conn->error);
            $message = 'Unable to check for an existing category. Please try again.';
        } else {
            $check->bind_param('ss', $name, $slug);
            $check->execute();
            $check->store_result();
            $exists = $check->num_rows > 0;
            $check->close();

            if ($exists) {
                $message = 'A category with this name or slug already exists.';
            } else {
                $imageUpload = storeCategoryImageUpload($_FILES['image'] ?? null);
                if ($imageUpload['error'] !== null) {
                    $message = (string) $imageUpload['error'];
                } elseif (!is_string($imageUpload['path'])) {
                    $message = 'Choose an image for this category.';
                } else {
                    $imagePath = $imageUpload['path'];
                    $stmt = $conn->prepare('INSERT INTO categories (name, slug, description, image) VALUES (?, ?, ?, ?)');
                    if (!$stmt) {
                        removeManagedCategoryImage($imagePath);
                        $message = 'Unable to prepare the category. Please try again.';
                    } else {
                        $stmt->bind_param('ssss', $name, $slug, $description, $imagePath);
                        if ($stmt->execute()) {
                            $message = 'Category added successfully.';
                            $messageType = 'success';
                            $name = '';
                            $description = '';
                            $slugInput = '';
                        } else {
                            removeManagedCategoryImage($imagePath);
                            $message = 'Unable to save this category. Check that its name and slug are unique.';
                        }
                        $stmt->close();
                    }
                }
            }
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
    <title>Add category | DunkHome Kicks</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-theme2">
    <link rel="stylesheet" href="assets/admin-pages.css?v=20261003-theme2">
    <link rel="stylesheet" href="assets/admin-navigation.css?v=20261003-theme1">
    <style>
        .category-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:20px; margin-bottom:26px; }
        .category-heading .admin-subtitle { max-width:540px; margin-bottom:0; }
        .category-form-panel { max-width:900px; padding:0; overflow:hidden; border:1px solid var(--admin-line); border-radius:16px; background:var(--admin-panel); box-shadow:0 22px 60px rgba(0,0,0,.18); }
        .category-form-head { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:22px 24px; border-bottom:1px solid var(--admin-line); }
        .category-form-head h2 { margin:0; color:var(--admin-text); font-size:15px; }
        .category-form-head p { margin:5px 0 0; color:var(--admin-muted); font-size:11px; line-height:1.5; }
        .category-form-icon { width:42px; height:42px; flex:0 0 42px; display:grid; place-items:center; border:1px solid rgba(121,230,170,.2); border-radius:12px; color:var(--admin-green); background:rgba(121,230,170,.08); }
        .category-form { padding:24px; }
        .category-fields { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px 16px; }
        .category-field { min-width:0; margin:0; }
        .category-field.full { grid-column:1/-1; }
        .category-field label { display:flex; justify-content:space-between; gap:12px; margin-bottom:8px; color:var(--admin-text); font-size:11px; font-weight:700; }
        .category-field label span { color:var(--admin-muted); font-size:10px; font-weight:500; }
        .category-field input,.category-field textarea { width:100%; min-height:44px; padding:11px 12px; border:1px solid var(--admin-line); border-radius:9px; outline:none; color:var(--admin-text); background:rgba(255,255,255,.035); font:12px "DM Sans",sans-serif; transition:border-color .18s,box-shadow .18s,background .18s; }
        .category-field input[type=file] { min-height:58px; padding:8px; border-color:rgba(121,230,170,.2); border-radius:11px; background:linear-gradient(135deg,rgba(121,230,170,.07),rgba(255,255,255,.025)); color:var(--admin-muted); cursor:pointer; }
        .category-field input[type=file]::file-selector-button { min-height:39px; margin-right:12px; padding:0 14px; border:0; border-radius:8px; color:#06140d; background:linear-gradient(135deg,#90efbb,#56d993); font:700 11px "DM Sans",sans-serif; cursor:pointer; transition:filter .18s,transform .18s; }
        .category-field input[type=file]::file-selector-button:hover { filter:brightness(1.06); transform:translateY(-1px); }
        .category-field input[type=file]:hover { border-color:rgba(121,230,170,.45); }
        .category-field input[type=file]:focus-visible { outline:2px solid rgba(121,230,170,.65); outline-offset:3px; }
        body.light .category-field input[type=file] { background:linear-gradient(135deg,rgba(121,230,170,.12),rgba(255,255,255,.95)); }
        .category-field textarea { min-height:116px; resize:vertical; line-height:1.6; }
        .category-field input::placeholder,.category-field textarea::placeholder { color:#78877e; }
        .category-field input:focus,.category-field textarea:focus { border-color:rgba(121,230,170,.62); background:rgba(121,230,170,.045); box-shadow:0 0 0 3px rgba(121,230,170,.09); }
        body.light .category-field input,body.light .category-field textarea { background:rgba(255,255,255,.85); }
        body.light .category-field input:focus,body.light .category-field textarea:focus { background:#fff; }
        .category-field-help { margin:7px 0 0; color:var(--admin-muted); font-size:10px; line-height:1.5; }
        .category-image-preview { display:block; width:min(220px,100%); aspect-ratio:16/10; margin-top:12px; border:1px solid var(--admin-line); border-radius:10px; object-fit:cover; }
        .category-image-preview[hidden] { display:none; }
        .slug-preview { display:flex; align-items:center; gap:8px; margin-top:8px; color:var(--admin-muted); font-size:10px; }
        .slug-preview code { max-width:100%; overflow:hidden; color:var(--admin-green); text-overflow:ellipsis; white-space:nowrap; }
        body.light .slug-preview code { color:#236844; }
        .category-form-footer { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-top:22px; padding-top:18px; border-top:1px solid var(--admin-line); }
        .category-actions { display:flex; flex-wrap:wrap; gap:9px; }
        .category-submit { min-width:150px; }
        @media(max-width:640px) { .category-heading { align-items:flex-start; flex-direction:column; gap:7px; margin-bottom:18px; } .category-form-head,.category-form { padding:18px; } .category-fields { grid-template-columns:1fr; gap:16px; } .category-field.full { grid-column:auto; } .category-form-footer { align-items:stretch; flex-direction:column; } .category-actions { display:grid; grid-template-columns:1fr 1fr; } .category-actions .admin-button { width:100%; } }
    </style>
</head>
<body>
<div class="admin-page admin-sidebar-layout">
    <div class="admin-menu-overlay" id="adminMenuOverlay"></div>
    <button class="admin-mobile-menu" id="adminMobileMenu" type="button" aria-label="Open administration menu" aria-expanded="false">
        <i class="fa-solid fa-bars"></i>
    </button>
    <div class="admin-mobile-actions" aria-label="Admin controls">
        <button class="admin-mobile-action" id="themeToggle" type="button" title="Toggle theme" aria-label="Switch to light theme">☀️</button>
        <a class="admin-mobile-action" href="<?= h(appUrl('Admin/Logout.php?scope=admin')) ?>" title="Sign out" aria-label="Sign out"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
        <button class="admin-mobile-action" id="categoryRefreshButton" type="button" title="Reload page" aria-label="Reload page"><i class="fa-solid fa-rotate-right"></i></button>
    </div>
    <header class="admin-topbar" id="adminSidebar">
        <a class="admin-brand" href="<?= h(appUrl('Admin/AdminDashboard.php')) ?>">
            <img src="image/logo.jpg" alt="">
            <div class="brand-name">dunkhome_<span>kicks</span></div>
        </a>
        <div class="admin-nav-title">Administration</div>
        <nav class="admin-nav-links"><?php $adminNavigationShowThemeToggle = false; require __DIR__ . '/../includes/nav.php'; ?></nav>
        <div class="admin-sidebar-identity">
            <div class="admin-sidebar-avatar"><?= h($adminInitials) ?></div>
            <div class="admin-sidebar-user"><strong><?= h($adminEmail) ?></strong><span>Administrator</span></div>
        </div>
    </header>
    <main class="admin-content">
        <div class="category-heading">
            <div>
                <div class="admin-eyebrow">Catalog management</div>
                <h1 class="admin-title">Add category</h1>
                <p class="admin-subtitle">Create a clear collection name and make it ready for product listings.</p>
            </div>
        </div>
        <?php if ($message !== ''): ?>
            <div class="admin-message <?= $messageType === 'success' ? 'success' : '' ?>" role="status"><?= h($message) ?></div>
        <?php endif; ?>
        <section class="category-form-panel">
            <div class="category-form-head">
                <div>
                    <h2>Category details</h2>
                    <p>Names and descriptions help customers find the right products.</p>
                </div>
                <span class="category-form-icon" aria-hidden="true"><i class="fa-solid fa-layer-group"></i></span>
            </div>
            <form class="category-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <div class="category-fields">
                <div class="admin-field category-field">
                    <label for="name">Category name <span><output id="nameCount">0 / 80</output></span></label>
                    <input id="name" name="name" maxlength="80" required value="<?= h($name) ?>" placeholder="e.g. Trail running">
                    <p class="category-field-help">Use a short name customers will recognize.</p>
                </div>
                <div class="admin-field category-field">
                    <label for="slug">URL slug <span>Optional</span></label>
                    <input id="slug" name="slug" maxlength="100" value="<?= h($slugInput) ?>" placeholder="Generated from the category name">
                    <div class="slug-preview"><span>Preview</span><code id="slugPreview">category-name</code></div>
                </div>
                <div class="admin-field category-field full">
                    <label for="description">Description <span><output id="descriptionCount">0 / 2000</output></span></label>
                    <textarea id="description" name="description" maxlength="2000" placeholder="Describe the products customers will find in this collection."><?= h($description) ?></textarea>
                </div>
                <div class="admin-field category-field full">
                    <label for="categoryImage">Category image <span>JPG, PNG or WEBP · max 5 MB</span></label>
                    <input id="categoryImage" name="image" type="file" accept="image/jpeg,image/png,image/webp" required>
                    <img class="category-image-preview" id="categoryImagePreview" alt="Selected category image preview" hidden>
                    <p class="category-field-help">Choose one image to represent this category.</p>
                </div>
                </div>
                <div class="category-form-footer">
                    <span class="category-field-help">You can edit visibility from Manage Categories.</span>
                    <div class="category-actions">
                        <a class="admin-button" href="ManageCategories.php">Manage categories</a>
                        <button class="admin-button primary category-submit" type="submit"><i class="fa-solid fa-plus"></i> Save category</button>
                    </div>
                </div>
            </form>
        </section>
    </main>
    <footer class="admin-footer">DunkHome Kicks administration</footer>
</div>
<script src="assets/dunkhome-ui.js?v=20261001-loader4" defer></script>
<script src="assets/admin-sidebar-drawer.js?v=20261001-1" defer></script>
<script>
document.getElementById('categoryRefreshButton')?.addEventListener('click', () => window.location.reload());

const categoryName = document.getElementById('name');
const categorySlug = document.getElementById('slug');
const categoryDescription = document.getElementById('description');
const nameCount = document.getElementById('nameCount');
const descriptionCount = document.getElementById('descriptionCount');
const slugPreview = document.getElementById('slugPreview');

function toCategorySlug(value) {
    return value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

function updateCategoryPreview() {
    nameCount.textContent = `${categoryName.value.length} / 80`;
    descriptionCount.textContent = `${categoryDescription.value.length} / 2000`;
    slugPreview.textContent = toCategorySlug(categorySlug.value) || toCategorySlug(categoryName.value) || 'category-name';
}

categoryName.addEventListener('input', updateCategoryPreview);
categorySlug.addEventListener('input', updateCategoryPreview);
categoryDescription.addEventListener('input', updateCategoryPreview);
updateCategoryPreview();

const categoryImageInput = document.getElementById('categoryImage');
const categoryImagePreview = document.getElementById('categoryImagePreview');
categoryImageInput.addEventListener('change', () => {
    const file = categoryImageInput.files[0];
    if (!file) {
        categoryImagePreview.hidden = true;
        categoryImagePreview.removeAttribute('src');
        return;
    }
    categoryImagePreview.src = URL.createObjectURL(file);
    categoryImagePreview.hidden = false;
});
</script>
</body>
</html>

