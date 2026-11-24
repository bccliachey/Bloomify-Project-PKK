-- =====================================================================
-- Bloomify Florist - Database Schema
-- MySQL / MariaDB (Laragon, phpMyAdmin/HeidiSQL compatible)
--
-- IMPORTANT: This script DROPS existing Bloomify tables.
-- Only run it on a fresh development database.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `bloomify`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `bloomify`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TRIGGER IF EXISTS `bloomify_reviews_after_insert`;
DROP TRIGGER IF EXISTS `bloomify_reviews_after_update`;
DROP TRIGGER IF EXISTS `bloomify_reviews_after_delete`;

DROP TABLE IF EXISTS `product_reviews`;
DROP TABLE IF EXISTS `order_status_history`;
DROP TABLE IF EXISTS `order_item_details`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `cart_item_details`;
DROP TABLE IF EXISTS `cart_items`;
DROP TABLE IF EXISTS `carts`;
DROP TABLE IF EXISTS `stock_movements`;
DROP TABLE IF EXISTS `product_stocks`;
DROP TABLE IF EXISTS `product_flower_types`;
DROP TABLE IF EXISTS `flower_stocks`;
DROP TABLE IF EXISTS `category_flower_types`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `flower_types`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `store_settings`;
DROP TABLE IF EXISTS `admin_accounts`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- ACCOUNTS
-- =====================================================================

CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `address` TEXT NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `profile_photo` VARCHAR(255) DEFAULT NULL,
    `profile_description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_accounts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admin_accounts_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- CATALOG
-- =====================================================================

CREATE TABLE `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `slug` VARCHAR(50) NOT NULL,
    `pricing_mode` ENUM('per_stem','mixed','fixed') NOT NULL DEFAULT 'per_stem',
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_name` (`name`),
    UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `flower_types` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `color` VARCHAR(30) NOT NULL DEFAULT '',
    `price_per_stem` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_flower_types_name_color` (`name`, `color`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `category_flower_types` (
    `category_id` INT UNSIGNED NOT NULL,
    `flower_type_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`category_id`, `flower_type_id`),
    KEY `idx_category_flower_type` (`flower_type_id`),
    CONSTRAINT `fk_category_flower_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_category_flower_type` FOREIGN KEY (`flower_type_id`) REFERENCES `flower_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `flower_stocks` (
    `flower_type_id` INT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    `low_stock_threshold` INT UNSIGNED NOT NULL DEFAULT 10,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`flower_type_id`),
    CONSTRAINT `fk_flower_stocks_type`
        FOREIGN KEY (`flower_type_id`) REFERENCES `flower_types` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT UNSIGNED NOT NULL,
    `flower_type_id` INT UNSIGNED DEFAULT NULL,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    `fixed_price` DECIMAL(12,2) DEFAULT NULL,
    `pricing_mode` ENUM('per_stem','mixed','fixed') DEFAULT NULL,
    `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
    `rating_avg` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    `rating_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_products_slug` (`slug`),
    KEY `idx_products_category` (`category_id`),
    KEY `idx_products_flower_type` (`flower_type_id`),
    CONSTRAINT `fk_products_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_products_flower_type`
        FOREIGN KEY (`flower_type_id`) REFERENCES `flower_types` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_flower_types` (
    `product_id` INT UNSIGNED NOT NULL,
    `flower_type_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`product_id`, `flower_type_id`),
    KEY `idx_product_flower_type` (`flower_type_id`),
    CONSTRAINT `fk_product_flower_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_product_flower_type` FOREIGN KEY (`flower_type_id`) REFERENCES `flower_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_stocks` (
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    `low_stock_threshold` INT UNSIGNED NOT NULL DEFAULT 5,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`product_id`),
    CONSTRAINT `fk_product_stocks_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stock_movements` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_type` ENUM('flower','product') NOT NULL,
    `flower_type_id` INT UNSIGNED DEFAULT NULL,
    `product_id` INT UNSIGNED DEFAULT NULL,
    `change_qty` INT NOT NULL,
    `stock_after` INT UNSIGNED NOT NULL,
    `note` VARCHAR(255) DEFAULT NULL,
    `admin_id` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_stock_movements_flower` (`flower_type_id`),
    KEY `idx_stock_movements_product` (`product_id`),
    KEY `idx_stock_movements_admin` (`admin_id`),
    CONSTRAINT `fk_stock_movements_flower`
        FOREIGN KEY (`flower_type_id`) REFERENCES `flower_types` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_stock_movements_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_stock_movements_admin`
        FOREIGN KEY (`admin_id`) REFERENCES `admin_accounts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- CART
-- =====================================================================

CREATE TABLE `carts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_carts_user` (`user_id`),
    CONSTRAINT `fk_carts_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cart_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cart_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `item_type` ENUM('per_stem','mixed','fixed') NOT NULL,
    `config_hash` CHAR(32) NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cart_items_config` (`cart_id`, `config_hash`),
    KEY `idx_cart_items_product` (`product_id`),
    CONSTRAINT `fk_cart_items_cart`
        FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cart_items_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cart_item_details` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cart_item_id` INT UNSIGNED NOT NULL,
    `flower_type_id` INT UNSIGNED NOT NULL,
    `stem_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cart_item_details` (`cart_item_id`, `flower_type_id`),
    KEY `idx_cart_item_details_flower` (`flower_type_id`),
    CONSTRAINT `fk_cart_item_details_item`
        FOREIGN KEY (`cart_item_id`) REFERENCES `cart_items` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cart_item_details_flower`
        FOREIGN KEY (`flower_type_id`) REFERENCES `flower_types` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- ORDERS
-- =====================================================================

CREATE TABLE `orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_code` VARCHAR(30) NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `recipient_name` VARCHAR(100) NOT NULL,
    `recipient_phone` VARCHAR(20) NOT NULL,
    `fulfillment_method` ENUM('delivery','pickup') NOT NULL DEFAULT 'delivery',
    `delivery_address` TEXT DEFAULT NULL,
    `address_detail` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `payment_method` ENUM('COD','DANA','GoPay','OVO','ShopeePay','Bank BRI') NOT NULL DEFAULT 'COD',
    `payment_status` ENUM('Unpaid','Pending Verification','Paid','Rejected') NOT NULL DEFAULT 'Unpaid',
    `order_status` ENUM('Pending','Proses','Dikirim','Siap Diambil','Selesai','Rejected') NOT NULL DEFAULT 'Pending',
    `product_subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `wrapping_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `delivery_fee` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `grand_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `confirmed_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `payment_proof` VARCHAR(255) DEFAULT NULL,
    `payment_proof_uploaded_at` DATETIME DEFAULT NULL,
    `payment_verified_at` DATETIME DEFAULT NULL,
    `payment_verified_by` INT UNSIGNED DEFAULT NULL,
    `rejection_reason` VARCHAR(255) DEFAULT NULL,
    `rejected_at` DATETIME DEFAULT NULL,
    `rejected_by` INT UNSIGNED DEFAULT NULL,
    `stock_restored_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orders_code` (`order_code`),
    KEY `idx_orders_user` (`user_id`),
    KEY `idx_orders_status` (`order_status`),
    KEY `idx_orders_payment` (`payment_status`),
    KEY `idx_orders_created` (`created_at`),
    CONSTRAINT `fk_orders_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED DEFAULT NULL,
    `product_name` VARCHAR(100) NOT NULL,
    `category_name` VARCHAR(50) NOT NULL,
    `product_image` VARCHAR(255) DEFAULT NULL,
    `item_type` ENUM('per_stem','mixed','fixed') NOT NULL,
    `unit_price` DECIMAL(12,2) DEFAULT NULL,
    `stem_total` INT UNSIGNED NOT NULL DEFAULT 0,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    `flower_subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `wrapping_fee` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `item_subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    KEY `idx_order_items_order` (`order_id`),
    KEY `idx_order_items_product` (`product_id`),
    CONSTRAINT `fk_order_items_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_order_items_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_item_details` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_item_id` BIGINT UNSIGNED NOT NULL,
    `flower_type_id` INT UNSIGNED DEFAULT NULL,
    `flower_type_name` VARCHAR(50) NOT NULL,
    `price_per_stem` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `stem_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    `line_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    KEY `idx_order_item_details_item` (`order_item_id`),
    KEY `idx_order_item_details_flower` (`flower_type_id`),
    CONSTRAINT `fk_order_item_details_item`
        FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_order_item_details_flower`
        FOREIGN KEY (`flower_type_id`) REFERENCES `flower_types` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_reviews` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `order_item_id` BIGINT UNSIGNED NOT NULL,
    `rating` TINYINT UNSIGNED NOT NULL,
    `comment` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_product_reviews_order_product` (`order_id`, `product_id`),
    KEY `idx_product_reviews_product` (`product_id`, `created_at`),
    KEY `idx_product_reviews_user` (`user_id`),
    KEY `idx_product_reviews_order` (`order_id`),
    CONSTRAINT `chk_product_reviews_rating` CHECK (`rating` BETWEEN 1 AND 5),
    CONSTRAINT `fk_product_reviews_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_product_reviews_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_product_reviews_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_product_reviews_order_item`
        FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_status_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(30) NOT NULL,
    `note` VARCHAR(255) DEFAULT NULL,
    `admin_id` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_status_history_order` (`order_id`),
    KEY `idx_order_status_history_admin` (`admin_id`),
    CONSTRAINT `fk_order_status_history_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_order_status_history_admin`
        FOREIGN KEY (`admin_id`) REFERENCES `admin_accounts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- NOTIFICATIONS
-- =====================================================================

CREATE TABLE `notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED DEFAULT NULL,
    `title` VARCHAR(100) NOT NULL,
    `message` VARCHAR(255) NOT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_user` (`user_id`, `is_read`),
    KEY `idx_notifications_order` (`order_id`),
    CONSTRAINT `fk_notifications_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_notifications_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- STORE SETTINGS
-- =====================================================================

CREATE TABLE `store_settings` (
    `setting_key` VARCHAR(50) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- INITIAL DATA
-- =====================================================================

INSERT INTO `categories` (`name`, `slug`, `pricing_mode`, `description`) VALUES
('Hand Bouquet', 'hand-bouquet', 'per_stem', 'A customizable hand bouquet. Choose flower types, colors, and stem quantities.'),
('Mix Bouquet', 'mix-bouquet', 'mixed', 'A customizable bouquet combining several flower types.'),
('Flower Box', 'flower-box', 'fixed', 'A ready-made flower arrangement with a fixed composition.');

INSERT INTO `flower_types` (`name`, `color`, `price_per_stem`) VALUES
('Lily', 'Pink', 15000.00),
('Lily', 'White', 15000.00),
('Lily', 'Yellow', 15000.00),
('Rose', 'White', 18000.00),
('Rose', 'Pink', 18000.00),
('Rose', 'Red', 18000.00),
('Peony', '', 22000.00),
('Tulip', '', 20000.00);

INSERT INTO `category_flower_types` (`category_id`, `flower_type_id`)
SELECT c.id, ft.id
FROM categories c
CROSS JOIN flower_types ft
WHERE c.pricing_mode = 'mixed';

INSERT INTO `flower_stocks` (`flower_type_id`, `quantity`, `low_stock_threshold`)
SELECT `id`, 
       CASE `name`
           WHEN 'Lily' THEN 80
           WHEN 'Rose' THEN 100
           WHEN 'Peony' THEN 60
           WHEN 'Tulip' THEN 50
       END,
       10
FROM `flower_types`;

INSERT INTO `products`
    (`category_id`, `flower_type_id`, `name`, `slug`, `description`, `image`, `fixed_price`, `is_visible`)
VALUES
(
    (SELECT `id` FROM `categories` WHERE `slug` = 'hand-bouquet'),
    NULL,
    'Harmonies', 'harmonies',
    'A graceful hand-tied bouquet of fresh lilies, wrapped in soft paper and finished with a satin ribbon.',
    'assets/images/Lily.jpeg', NULL, 1
),
(
    (SELECT `id` FROM `categories` WHERE `slug` = 'hand-bouquet'),
    NULL,
    'Rose Amour', 'rose-amour',
    'Classic romantic roses arranged by hand. Choose your stem count and let us wrap it beautifully.',
    'assets/images/Rose.jpeg', NULL, 1
),
(
    (SELECT `id` FROM `categories` WHERE `slug` = 'hand-bouquet'),
    NULL,
    'Ivory Puff', 'ivory-puff',
    'Soft, voluminous peonies for a dreamy and elegant moment.',
    'assets/images/Peony.jpeg', NULL, 1
),
(
    (SELECT `id` FROM `categories` WHERE `slug` = 'hand-bouquet'),
    NULL,
    'Lily Serenade', 'lily-serenade',
    'A full bouquet of white lilies, perfect for celebrations and quiet gestures alike.',
    'assets/images/Lily-Bouquet.jpeg', NULL, 1
),
(
    (SELECT `id` FROM `categories` WHERE `slug` = 'flower-box'),
    NULL,
    'Elodie Blush', 'elodie-blush',
    'A ready-made blush flower box combining roses and peonies in a keepsake box.',
    'assets/images/FB-A.jpeg', NULL, 1
),
(
    (SELECT `id` FROM `categories` WHERE `slug` = 'mix-bouquet'),
    NULL,
    'Amara Relios', 'amara-relios',
    'Build your own mixed bouquet. Choose any combination of our fresh flowers, at least three stems in total.',
    'assets/images/Relios.jpeg', NULL, 1
);

-- Product-specific flower choices (independent for every product).
INSERT INTO `product_flower_types` (`product_id`, `flower_type_id`)
SELECT p.id, ft.id
FROM products p
JOIN flower_types ft ON (
    (p.slug = 'harmonies' AND ft.name = 'Lily') OR
    (p.slug = 'rose-amour' AND ft.name = 'Rose' AND ft.color IN ('White', 'Pink', 'Red')) OR
    (p.slug = 'ivory-puff' AND ft.name = 'Peony') OR
    (p.slug = 'lily-serenade' AND ft.name = 'Lily') OR
    (p.slug = 'amara-relios' AND ft.name IN ('Tulip', 'Rose', 'Peony'))
);

INSERT INTO `product_stocks` (`product_id`, `quantity`, `low_stock_threshold`)
SELECT p.id, 0, 5
FROM products p
JOIN categories c ON c.id = p.category_id
WHERE COALESCE(p.pricing_mode, c.pricing_mode) = 'fixed';

-- Customized flower products use flower stem stock rather than fixed-product stock.

INSERT INTO `store_settings` (`setting_key`, `setting_value`) VALUES
('store_name', 'Bloomify'),
('store_logo', 'assets/images/Flower.png'),
('store_address', 'Jl. Melati No. 12, Kebayoran Baru, Jakarta Selatan 12110'),
('store_whatsapp', '+62 856 9167 4330'),
('store_hours', 'Monday - Saturday, 09.00 - 19.00 WIB'),
('store_info', 'Fresh flowers arranged daily. Contact us on WhatsApp for custom requests.'),
('delivery_fee', '20000'),
('delivery_area_note', 'Delivery is handled directly by the Bloomify team.'),
('payment_cod_note', 'Pay in cash when your order is delivered or collected at the store.'),
('payment_dana_number', ''),
('payment_dana_name', ''),
('payment_gopay_number', ''),
('payment_gopay_name', ''),
('payment_ovo_number', ''),
('payment_ovo_name', ''),
('payment_shopeepay_number', ''),
('payment_shopeepay_name', ''),
('payment_bank_bri_account', ''),
('payment_bank_bri_holder', '');

-- =====================================================================
-- INITIAL ADMIN
-- =====================================================================
-- No admin is inserted here on purpose.
-- Open admin/setup.php once in the browser to create the first admin
-- account securely (the password is hashed with password_hash()).

-- =====================================================================
-- REVIEW AGGREGATES
-- Keeps the product rating summary correct for create/edit/delete/cascade.
-- =====================================================================

DELIMITER //

CREATE TRIGGER `bloomify_reviews_after_insert`
AFTER INSERT ON `product_reviews`
FOR EACH ROW
BEGIN
    UPDATE products p
    SET p.rating_avg = (SELECT COALESCE(AVG(r.rating), 0) FROM product_reviews r WHERE r.product_id = NEW.product_id),
        p.rating_count = (SELECT COUNT(*) FROM product_reviews r WHERE r.product_id = NEW.product_id)
    WHERE p.id = NEW.product_id;
END//

CREATE TRIGGER `bloomify_reviews_after_update`
AFTER UPDATE ON `product_reviews`
FOR EACH ROW
BEGIN
    UPDATE products p
    SET p.rating_avg = (SELECT COALESCE(AVG(r.rating), 0) FROM product_reviews r WHERE r.product_id = NEW.product_id),
        p.rating_count = (SELECT COUNT(*) FROM product_reviews r WHERE r.product_id = NEW.product_id)
    WHERE p.id = NEW.product_id;

    IF OLD.product_id <> NEW.product_id THEN
        UPDATE products p
        SET p.rating_avg = (SELECT COALESCE(AVG(r.rating), 0) FROM product_reviews r WHERE r.product_id = OLD.product_id),
            p.rating_count = (SELECT COUNT(*) FROM product_reviews r WHERE r.product_id = OLD.product_id)
        WHERE p.id = OLD.product_id;
    END IF;
END//

CREATE TRIGGER `bloomify_reviews_after_delete`
AFTER DELETE ON `product_reviews`
FOR EACH ROW
BEGIN
    UPDATE products p
    SET p.rating_avg = (SELECT COALESCE(AVG(r.rating), 0) FROM product_reviews r WHERE r.product_id = OLD.product_id),
        p.rating_count = (SELECT COUNT(*) FROM product_reviews r WHERE r.product_id = OLD.product_id)
    WHERE p.id = OLD.product_id;
END//

DELIMITER ;
