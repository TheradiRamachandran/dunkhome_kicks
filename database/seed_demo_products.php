<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../db.php';

$demoCategorySlugs = [
    'everyday-sneakers',
    'running',
    'basketball',
    'training',
    'skate',
    'trail',
    'high-tops',
    'low-tops',
    'retro-classics',
    'slip-ons',
];
$categorySlugsSql = "'" . implode("','", array_map([$conn, 'real_escape_string'], $demoCategorySlugs)) . "'";
$categoryResult = $conn->query("SELECT name FROM categories WHERE is_active = 1 AND slug IN ($categorySlugsSql) ORDER BY slug");
$categories = [];
if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = (string) $row['name'];
    }
}

if (count($categories) !== count($demoCategorySlugs)) {
    fwrite(STDERR, "Expected all ten seeded sneaker categories to be active.\n");
    exit(1);
}

$projectRoot = dirname(__DIR__);
$sneakerImage = 'image/background.jpg';

if (!is_file($projectRoot . DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR . 'background.jpg')) {
    fwrite(STDERR, "The local sneaker artwork was not found.\n");
    exit(1);
}

$existingResult = $conn->query("SELECT name FROM products WHERE name LIKE '[DEMO] %'");
$existingNames = [];
if ($existingResult) {
    while ($row = $existingResult->fetch_assoc()) {
        $existingNames[(string) $row['name']] = true;
    }
}

$insert = $conn->prepare('INSERT INTO products (name, category, price, description, image1, image2, image3, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
if (!$insert) {
    fwrite(STDERR, "Unable to prepare demo product inserts: {$conn->error}\n");
    exit(1);
}

$inserted = 0;
try {
    $conn->begin_transaction();
    for ($sequence = 1; $sequence <= 1000; $sequence++) {
        $category = $categories[($sequence - 1) % count($categories)];
        $productName = sprintf('[DEMO] %s Model %04d', $category, $sequence);
        if (isset($existingNames[$productName])) {
            continue;
        }

        $price = round(49.99 + (($sequence * 173) % 25000) / 100, 2);
        $description = 'Sample catalog item. Replace the demo details and image with real product information before taking orders.';
        $image1 = $sneakerImage;
        $image2 = $sneakerImage;
        $image3 = $sneakerImage;
        $insert->bind_param('ssdssss', $productName, $category, $price, $description, $image1, $image2, $image3);
        if (!$insert->execute()) {
            throw new RuntimeException('Unable to insert demo product: ' . $insert->error);
        }
        $inserted++;
    }
    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    $insert->close();
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

$insert->close();
printf("Inserted %d demo products. The seed is safe to rerun.\n", $inserted);