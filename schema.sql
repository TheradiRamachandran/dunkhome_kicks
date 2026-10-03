CREATE DATABASE IF NOT EXISTS dunkhome_kicks CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dunkhome_kicks;
CREATE TABLE IF NOT EXISTS users (id INT UNSIGNED NOT NULL AUTO_INCREMENT,email VARCHAR(255) NOT NULL,password VARCHAR(255) NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_users_email(email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS admins (id INT UNSIGNED NOT NULL AUTO_INCREMENT,email VARCHAR(255) NOT NULL,password VARCHAR(255) NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_admin_email(email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS products (id INT UNSIGNED NOT NULL AUTO_INCREMENT,name VARCHAR(180) NOT NULL,category VARCHAR(80) NOT NULL,price DECIMAL(10,2) NOT NULL,description TEXT NULL,image1 VARCHAR(255) NOT NULL,image2 VARCHAR(255) NOT NULL,image3 VARCHAR(255) NOT NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY idx_products_category(category),KEY idx_products_created(created_at),KEY idx_products_active(is_active)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS product_ratings (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	product_id INT UNSIGNED NOT NULL,
	user_id INT UNSIGNED NOT NULL,
	rating TINYINT UNSIGNED NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_product_ratings_user_product (user_id, product_id),
	KEY idx_product_ratings_product_rating (product_id, rating),
	CONSTRAINT fk_product_ratings_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
	CONSTRAINT fk_product_ratings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categories (name, slug, description, is_active) VALUES
	('Everyday Sneakers', 'everyday-sneakers', 'Comfortable sneakers for everyday wear.', 1),
	('Running', 'running', 'Lightweight shoes for running and training.', 1),
	('Basketball', 'basketball', 'Court-ready basketball sneakers.', 1),
	('Training', 'training', 'Supportive shoes for workouts and gym sessions.', 1),
	('Skate', 'skate', 'Durable sneakers for skateboarding and street style.', 1),
	('Trail', 'trail', 'Grip-focused shoes for outdoor routes.', 1),
	('High-Tops', 'high-tops', 'High-top silhouettes with added ankle coverage.', 1),
	('Low-Tops', 'low-tops', 'Low-profile sneakers for an easy everyday fit.', 1),
	('Retro Classics', 'retro-classics', 'Classic-inspired sneakers with a throwback look.', 1),
	('Slip-Ons', 'slip-ons', 'Easy slip-on shoes for quick everyday comfort.', 1);

CREATE TABLE IF NOT EXISTS orders (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	order_code VARCHAR(32) NOT NULL,
	user_id INT UNSIGNED NOT NULL,
	request_token CHAR(64) NOT NULL,
	customer_name VARCHAR(160) NOT NULL,
	mobile VARCHAR(25) NOT NULL,
	email VARCHAR(255) NOT NULL,
	address VARCHAR(500) NOT NULL,
	city VARCHAR(120) NOT NULL,
	state VARCHAR(120) NOT NULL,
	pincode VARCHAR(12) NOT NULL,
	total DECIMAL(12,2) NOT NULL,
	status ENUM('Pending','Confirmed','Processing','Ready','Out for Delivery','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_orders_code (order_code),
	UNIQUE KEY uq_orders_request_token (request_token),
	KEY idx_orders_user_date (user_id, created_at),
	KEY idx_orders_status_date (status, created_at),
	CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	order_id BIGINT UNSIGNED NOT NULL,
	product_id INT UNSIGNED NULL,
	product_name VARCHAR(180) NOT NULL,
	product_image VARCHAR(255) NOT NULL,
	unit_price DECIMAL(10,2) NOT NULL,
	quantity INT UNSIGNED NOT NULL,
	subtotal DECIMAL(12,2) NOT NULL,
	PRIMARY KEY (id),
	KEY idx_order_items_order (order_id),
	CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
	CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_status_history (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	order_id BIGINT UNSIGNED NOT NULL,
	previous_status VARCHAR(32) NULL,
	new_status ENUM('Pending','Confirmed','Processing','Ready','Out for Delivery','Delivered','Cancelled') NOT NULL,
	changed_by_type ENUM('user','admin','system') NOT NULL DEFAULT 'system',
	changed_by_id INT UNSIGNED NULL,
	note VARCHAR(500) NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_order_history_order_date (order_id, created_at),
	CONSTRAINT fk_order_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
