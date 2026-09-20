<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$conn = new mysqli('localhost', 'root', '', 'dunkhome_kicks');

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
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_products_category (category),
    KEY idx_products_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
