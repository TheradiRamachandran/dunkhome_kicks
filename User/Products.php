<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session.php';

$categories = [];
$categoryResult = $conn->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name');
if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

$requestedCategoryId = filter_var($_GET['category'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$selectedCategoryId = $requestedCategoryId === false || $requestedCategoryId === null ? 0 : (int) $requestedCategoryId;
$selectedCategory = null;
foreach ($categories as $category) {
    if ((int) $category['id'] === $selectedCategoryId) {
        $selectedCategory = $category;
        break;
    }
}
if (!$selectedCategory) {
    $selectedCategoryId = 0;
}

$search = trim((string) ($_GET['q'] ?? ''));
$searchPattern = '%' . $search . '%';
$pageSize = 24;
$requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $requestedPage === false || $requestedPage === null ? 1 : (int) $requestedPage;
$filterSql = "p.is_active = 1
    AND (? = 0 OR c.id = ?)
    AND (? = '' OR p.name LIKE ? OR p.category LIKE ? OR COALESCE(p.description, '') LIKE ?)";

$countStatement = $conn->prepare("SELECT COUNT(*) AS total FROM products p LEFT JOIN categories c ON c.name COLLATE utf8mb4_unicode_ci = p.category COLLATE utf8mb4_unicode_ci WHERE $filterSql");
$totalProducts = 0;
if ($countStatement) {
    $countStatement->bind_param('iissss', $selectedCategoryId, $selectedCategoryId, $search, $searchPattern, $searchPattern, $searchPattern);
    if ($countStatement->execute()) {
        $countRow = $countStatement->get_result()->fetch_assoc();
        $totalProducts = (int) ($countRow['total'] ?? 0);
    }
    $countStatement->close();
}

$totalPages = max(1, (int) ceil($totalProducts / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;
$products = [];
$productStatement = $conn->prepare("SELECT p.id, p.name, p.category, p.price, p.description, p.image1 FROM products p LEFT JOIN categories c ON c.name COLLATE utf8mb4_unicode_ci = p.category COLLATE utf8mb4_unicode_ci WHERE $filterSql ORDER BY p.created_at DESC, p.id DESC LIMIT ? OFFSET ?");
if ($productStatement) {
    $productStatement->bind_param('iissssii', $selectedCategoryId, $selectedCategoryId, $search, $searchPattern, $searchPattern, $searchPattern, $pageSize, $offset);
    if ($productStatement->execute()) {
        $productResult = $productStatement->get_result();
        while ($row = $productResult->fetch_assoc()) {
            $products[] = $row;
        }
    }
    $productStatement->close();
}

$firstVisibleProduct = $totalProducts === 0 ? 0 : $offset + 1;
$lastVisibleProduct = min($offset + $pageSize, $totalProducts);
$paginationUrl = static function (int $targetPage) use ($search, $selectedCategoryId): string {
    $query = ['page' => $targetPage];
    if ($search !== '') {
        $query['q'] = $search;
    }
    if ($selectedCategoryId > 0) {
        $query['category'] = $selectedCategoryId;
    }
    return 'User/Products.php?' . http_build_query($query);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../includes/favicon.php'; ?>
    <base href="<?= h(appBaseUrl()) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07100b">
    <meta name="description" content="Browse and search the DunkHome Kicks sneaker collection.">
    <title>Shop the Collection | DunkHome Kicks</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/dunkhome-ui.css?v=20261003-user-nav25">
    <style>
        :root {
            --catalog-bg: #07100b;
            --catalog-panel: #102019;
            --catalog-text: #f4f8f5;
            --catalog-muted: #9aa99f;
            --catalog-green: #8fe9b5;
            --catalog-amber: #ffc98b;
            --catalog-line: rgba(143, 233, 181, 0.15);
        }

        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; color: var(--catalog-text); font-family: "Manrope", sans-serif; background: radial-gradient(ellipse at 8% 8%, rgba(143, 233, 181, 0.12), transparent 34%), radial-gradient(ellipse at 92% 24%, rgba(255, 201, 139, 0.08), transparent 30%), var(--catalog-bg); }
        body.light { --catalog-bg: #f4f8f6; --catalog-panel: #fff; --catalog-text: #102016; --catalog-muted: #607065; --catalog-line: rgba(16, 32, 22, 0.11); }
        a { color: inherit; text-decoration: none; }
        button, input, select { font: inherit; }
        .catalog-wrap { width: min(1240px, calc(100% - 40px)); margin: 0 auto; }
        .catalog-main { padding: 132px 0 80px; }
        .catalog-intro { display: flex; align-items: end; justify-content: space-between; gap: 24px; margin-bottom: 28px; padding-bottom: 24px; border-bottom: 1px solid var(--catalog-line); }
        .catalog-kicker { margin-bottom: 10px; color: var(--catalog-green); font-size: 11px; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; }
        .catalog-intro h1 { margin: 0; font: 48px/1.08 "DM Serif Display", serif; }
        .catalog-intro p { max-width: 520px; margin: 12px 0 0; color: var(--catalog-muted); font-size: 14px; line-height: 1.7; }
        .catalog-total { flex: 0 0 auto; padding: 10px 13px; border: 1px solid var(--catalog-line); border-radius: 999px; color: var(--catalog-muted); font-size: 11px; font-weight: 700; }
        .catalog-filters { display: grid; grid-template-columns: minmax(220px, 1fr) minmax(190px, 270px) auto auto; align-items: end; gap: 12px; margin: 0 0 24px; }
        .catalog-field { display: grid; gap: 7px; min-width: 0; }
        .catalog-field label { color: var(--catalog-muted); font-size: 10px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
        .catalog-input, .catalog-select { width: 100%; min-height: 46px; padding: 0 14px; border: 1px solid var(--catalog-line); border-radius: 12px; outline: none; background: rgba(255, 255, 255, 0.045); color: var(--catalog-text); font-size: 12px; transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease; }
        body.light .catalog-input, body.light .catalog-select { background: rgba(255,255,255,.82); }
        .catalog-input:focus, .catalog-select:focus { border-color: var(--catalog-green); background: rgba(143,233,181,.07); box-shadow: 0 0 0 3px rgba(143,233,181,.1); }
        .catalog-select option { color: #102016; }
        .catalog-submit, .catalog-reset { min-height: 46px; display: inline-flex; align-items: center; justify-content: center; padding: 0 17px; border: 1px solid transparent; border-radius: 12px; font-size: 12px; font-weight: 800; cursor: pointer; transition: transform 0.2s ease, border-color 0.2s ease, background 0.2s ease; }
        .catalog-submit { background: linear-gradient(135deg, #90efbb, #56d993); color: #041008; }
        .catalog-reset { border-color: var(--catalog-line); background: transparent; color: var(--catalog-muted); }
        .catalog-submit:hover, .catalog-reset:hover { transform: translateY(-2px); }
        .catalog-reset:hover { border-color: var(--catalog-green); color: var(--catalog-text); }
        .catalog-results-line { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin: 0 0 15px; color: var(--catalog-muted); font-size: 11px; }
        .catalog-results-line strong { color: var(--catalog-text); }
        .catalog-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
        .product-card { min-width: 0; overflow: hidden; border: 1px solid var(--catalog-line); border-radius: 15px; background: linear-gradient(180deg, rgba(17, 31, 24, 0.94), rgba(9, 19, 14, 0.96)); box-shadow: 0 18px 46px rgba(0,0,0,.16); transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease; animation: cardEnter 0.45s ease both; }
        body.light .product-card { background: linear-gradient(180deg, #fff, #edf5ef); box-shadow: 0 16px 38px rgba(16,32,22,.08); }
        .product-card:hover { transform: translateY(-5px); border-color: rgba(143,233,181,.48); box-shadow: 0 25px 55px rgba(0,0,0,.23); }
        @keyframes cardEnter { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .product-card-image { position: relative; display: block; overflow: hidden; aspect-ratio: 4 / 3; background: radial-gradient(circle at 50% 45%, rgba(143,233,181,.2), transparent 68%), var(--catalog-panel); }
        .product-card-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.45s ease; }
        .product-card:hover .product-card-image img { transform: scale(1.045); }
        .product-card-category { position: absolute; top: 12px; left: 12px; max-width: calc(100% - 24px); overflow: hidden; padding: 7px 10px; border: 1px solid rgba(255,255,255,.16); border-radius: 999px; background: rgba(5,15,10,.72); color: #d7f5e3; font-size: 9px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; backdrop-filter: blur(10px); }
        .product-card-content { padding: 16px; }
        .product-card-content h2 { min-height: 42px; margin: 8px 0 5px; overflow: hidden; color: var(--catalog-text); font: 21px/1.2 "DM Serif Display", serif; }
        .product-card-description { display: -webkit-box; min-height: 36px; margin: 0; overflow: hidden; color: var(--catalog-muted); font-size: 11px; line-height: 1.6; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
        .product-card-bottom { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 14px; }
        .product-card-price { color: var(--catalog-text); font-size: 13px; font-weight: 800; white-space: nowrap; }
        .product-card-link { min-height: 36px; display: inline-flex; align-items: center; gap: 7px; padding: 0 11px; border: 1px solid var(--catalog-line); border-radius: 9px; color: var(--catalog-text); font-size: 10px; font-weight: 800; white-space: nowrap; transition: background .2s ease, border-color .2s ease; }
        .product-card-link:hover { border-color: var(--catalog-green); background: rgba(143,233,181,.1); }
        .catalog-empty { grid-column: 1 / -1; padding: 48px 24px; border: 1px dashed var(--catalog-line); border-radius: 15px; color: var(--catalog-muted); text-align: center; }
        .catalog-empty h2 { margin: 0 0 8px; color: var(--catalog-text); font: 28px "DM Serif Display", serif; }
        .catalog-empty p { margin: 0; font-size: 12px; }
        .catalog-pagination { display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 28px; }
        .catalog-page-link { min-height: 38px; min-width: 38px; display: inline-grid; place-items: center; padding: 0 12px; border: 1px solid var(--catalog-line); border-radius: 10px; color: var(--catalog-text); font-size: 11px; font-weight: 800; }
        .catalog-page-link.current { border-color: var(--catalog-green); background: rgba(143,233,181,.12); color: var(--catalog-green); }
        .catalog-page-link.disabled { opacity: .42; pointer-events: none; }
        @media (max-width: 1050px) { .catalog-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 760px) { .catalog-main { padding-top: 112px; } .catalog-intro { align-items: flex-start; flex-direction: column; gap: 15px; } .catalog-intro h1 { font-size: 40px; } .catalog-filters { grid-template-columns: 1fr 1fr; } .catalog-field:first-child { grid-column: 1 / -1; } .catalog-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 13px; } .product-card-content { padding: 13px; } .product-card-content h2 { font-size: 18px; } }
        @media (max-width: 460px) { .catalog-wrap { width: calc(100% - 24px); } .catalog-main { padding-top: 98px; } .catalog-intro h1 { font-size: 34px; } .catalog-filters { grid-template-columns: 1fr; } .catalog-field:first-child { grid-column: auto; } .catalog-grid { gap: 10px; } .product-card-content { padding: 11px; } .product-card-content h2 { min-height: 40px; font-size: 16px; } .product-card-description { font-size: 10px; } .product-card-bottom { align-items: flex-start; flex-direction: column; } .product-card-link { width: 100%; justify-content: center; } }
        @media (prefers-reduced-motion: reduce) { .product-card, .product-card-image img { animation: none; transition: none; } }
    </style>
    <link rel="stylesheet" href="<?= h(appUrl('assets/user-premium.css?v=20261003-premium1')) ?>">
    <link rel="stylesheet" href="<?= h(appUrl('assets/dunkhome-footer.css?v=20261003-footer3')) ?>">
</head>
<body>
<?php require __DIR__ . '/../includes/user_nav.php'; ?>
<main class="catalog-main">
    <div class="catalog-wrap">
        <header class="catalog-intro">
            <div>
                <div class="catalog-kicker">DunkHome Kicks / Collection</div>
                <h1>Find your next pair.</h1>
                <p>Search the collection or narrow it by category to find a pair that fits your rotation.</p>
            </div>
            <div class="catalog-total"><?= number_format($totalProducts) ?> products</div>
        </header>

        <form class="catalog-filters" method="get" action="User/Products.php" role="search">
            <div class="catalog-field">
                <label for="productSearch">Search products</label>
                <input class="catalog-input" id="productSearch" type="search" name="q" value="<?= h($search) ?>" placeholder="Search name, category or details">
            </div>
            <div class="catalog-field">
                <label for="productCategory">Category</label>
                <select class="catalog-select" id="productCategory" name="category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"<?= (int) $category['id'] === $selectedCategoryId ? ' selected' : '' ?>><?= h((string) $category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="catalog-submit" type="submit">Search collection</button>
            <?php if ($search !== '' || $selectedCategory): ?>
                <a class="catalog-reset" href="User/Products.php">Clear filters</a>
            <?php endif; ?>
        </form>

        <div class="catalog-results-line" aria-live="polite">
            <span><?= $totalProducts > 0 ? 'Showing ' . number_format($firstVisibleProduct) . '–' . number_format($lastVisibleProduct) . ' of ' . number_format($totalProducts) : 'No matching products' ?></span>
            <?php if ($selectedCategory): ?><strong><?= h((string) $selectedCategory['name']) ?></strong><?php endif; ?>
        </div>

        <section class="catalog-grid" aria-label="Products">
            <?php if (!$products): ?>
                <div class="catalog-empty">
                    <h2>No pairs found.</h2>
                    <p>Try a different search or clear your filters to browse the full collection.</p>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <article class="product-card">
                        <a class="product-card-image" href="User/ProductDetails.php?id=<?= (int) $product['id'] ?>" aria-label="View <?= h((string) $product['name']) ?>">
                            <img src="<?= h(appUrl((string) $product['image1'])) ?>" alt="<?= h((string) $product['name']) ?>" loading="lazy">
                            <span class="product-card-category"><?= h((string) $product['category']) ?></span>
                        </a>
                        <div class="product-card-content">
                            <h2><?= h((string) $product['name']) ?></h2>
                            <p class="product-card-description"><?= h((string) ($product['description'] ?? '')) ?></p>
                            <div class="product-card-bottom">
                                <span class="product-card-price">₹<?= number_format((float) $product['price'], 2) ?></span>
                                <a class="product-card-link" href="User/ProductDetails.php?id=<?= (int) $product['id'] ?>">See details <span aria-hidden="true">&#8594;</span></a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <?php if ($totalPages > 1): ?>
            <nav class="catalog-pagination" aria-label="Product pages">
                <a class="catalog-page-link<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= h($paginationUrl(max(1, $page - 1))) ?>" aria-label="Previous page">&#8592;</a>
                <?php
                $pageStart = max(1, $page - 2);
                $pageEnd = min($totalPages, $page + 2);
                for ($pageNumber = $pageStart; $pageNumber <= $pageEnd; $pageNumber++):
                ?>
                    <a class="catalog-page-link<?= $pageNumber === $page ? ' current' : '' ?>" href="<?= h($paginationUrl($pageNumber)) ?>"<?= $pageNumber === $page ? ' aria-current="page"' : '' ?>><?= $pageNumber ?></a>
                <?php endfor; ?>
                <a class="catalog-page-link<?= $page >= $totalPages ? ' disabled' : '' ?>" href="<?= h($paginationUrl(min($totalPages, $page + 1))) ?>" aria-label="Next page">&#8594;</a>
            </nav>
        <?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="assets/dunkhome-ui.js?v=20261003-nav14" defer></script>
</body>
</html>