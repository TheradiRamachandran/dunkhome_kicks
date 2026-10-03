USE dunkhome_kicks;

ALTER TABLE orders
    ADD COLUMN order_code VARCHAR(32) NULL AFTER id,
    ADD COLUMN request_token CHAR(64) NULL AFTER order_code,
    ADD COLUMN mobile VARCHAR(25) NULL AFTER customer_name,
    ADD COLUMN email VARCHAR(255) NULL AFTER mobile,
    ADD COLUMN address VARCHAR(500) NULL AFTER email,
    ADD COLUMN city VARCHAR(120) NULL AFTER address,
    ADD COLUMN state VARCHAR(120) NULL AFTER city,
    ADD COLUMN pincode VARCHAR(12) NULL AFTER state,
    ADD COLUMN total DECIMAL(12,2) NULL AFTER pincode,
    MODIFY COLUMN customer_name VARCHAR(160) NOT NULL,
    MODIFY COLUMN customer_email VARCHAR(255) NOT NULL;

UPDATE orders
SET order_code = CONCAT('DHK-', DATE_FORMAT(created_at, '%Y%m%d'), '-', LPAD(UPPER(HEX(id)), 6, '0')),
    request_token = SHA2(CONCAT('legacy-order-', id), 256),
    mobile = COALESCE(customer_phone, ''),
    email = customer_email,
    address = LEFT(shipping_address, 500),
    city = COALESCE(city, ''),
    state = COALESCE(state, ''),
    pincode = COALESCE(pincode, ''),
    total = total_amount;

ALTER TABLE orders
    MODIFY COLUMN order_code VARCHAR(32) NOT NULL,
    MODIFY COLUMN request_token CHAR(64) NOT NULL,
    MODIFY COLUMN total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD UNIQUE KEY uq_orders_code (order_code),
    ADD UNIQUE KEY uq_orders_request_token (request_token);

ALTER TABLE order_items
    ADD COLUMN product_image VARCHAR(255) NOT NULL DEFAULT '' AFTER product_name,
    ADD COLUMN unit_price DECIMAL(10,2) NULL AFTER product_image;

UPDATE order_items
SET unit_price = price;

UPDATE order_items oi
LEFT JOIN products p ON p.id = oi.product_id
SET oi.product_image = COALESCE(p.image1, '')
WHERE oi.product_image = '';
