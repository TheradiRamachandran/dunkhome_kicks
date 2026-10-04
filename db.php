<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$localConfigPath = __DIR__ . '/includes/mail-config.local.php';
$localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
if (!is_array($localConfig)) {
    error_log('DunkHome private configuration file must return an array.');
    http_response_code(500);
    exit('Application configuration is invalid.');
}

$setting = static function (string $name, string $default = '') use ($localConfig): string {
    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return (string) $value;
    }
    return (string) ($localConfig[$name] ?? $default);
};


// $dbHost = getenv('DHK_DB_HOST') ?: 'sql204.infinityfree.com';
// $dbUser = getenv('DHK_DB_USER') ?: 'if0_43077908';
// $dbPassword = getenv('DHK_DB_PASS') ?: '060kyt3vZqHl4u';
// $dbName = getenv('DHK_DB_NAME') ?: 'if0_43077908_dunkhome_kicks';

$dbHost = $setting('DHK_DB_HOST')?: 'sql204.infinityfree.com';
$dbUser = $setting('DHK_DB_USER')?: 'if0_43077908';
$dbPassword = $setting('DHK_DB_PASS')?: '060kyt3vZqHl4u';
$dbName = $setting('DHK_DB_NAME')?: 'if0_43077908_dunkhome_kicks';
// $conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);


try {
    $conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
} catch (mysqli_sql_exception $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to connect to the database. Check the private database settings.');
}

if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    exit('Unable to connect to the database.');
}

$conn->set_charset('utf8mb4');

/* Product table is created automatically so the enhancement works with the
   existing DunkHome Kicks database without changing the supplied UI. */
$conn->query("CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    category VARCHAR(80) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT NULL,
    image1 VARCHAR(255) NOT NULL,
    image2 VARCHAR(255) NOT NULL,
    image3 VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_products_category (category),
    KEY idx_products_created (created_at),
    KEY idx_products_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$activeColumn = $conn->query("SHOW COLUMNS FROM products LIKE 'is_active'");
if ($activeColumn && $activeColumn->num_rows === 0) {
    $conn->query('ALTER TABLE products ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER image3');
    $conn->query('ALTER TABLE products ADD KEY idx_products_active (is_active)');
}

$conn->query("CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    image VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$categoryImageColumn = $conn->query("SHOW COLUMNS FROM categories LIKE 'image'");
if ($categoryImageColumn && $categoryImageColumn->num_rows === 0) {
    $conn->query('ALTER TABLE categories ADD COLUMN image VARCHAR(255) NULL AFTER description');
}
