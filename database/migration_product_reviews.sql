-- =====================================================================
-- Bloomify - product reviews migration (safe for existing databases)
--
-- Run once on an existing Bloomify database after making a backup.
-- This migration preserves all existing users, products and orders.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `product_reviews` (
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

DELIMITER //

DROP PROCEDURE IF EXISTS bloomify_add_product_review_column//
CREATE PROCEDURE bloomify_add_product_review_column(IN column_name_value VARCHAR(64), IN column_definition TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'products'
          AND COLUMN_NAME = column_name_value
    ) THEN
        SET @alter_sql = CONCAT(
            'ALTER TABLE products ADD COLUMN `',
            column_name_value,
            '` ',
            column_definition
        );
        PREPARE alter_statement FROM @alter_sql;
        EXECUTE alter_statement;
        DEALLOCATE PREPARE alter_statement;
    END IF;
END//

DELIMITER ;

CALL bloomify_add_product_review_column('rating_avg', 'DECIMAL(3,2) NOT NULL DEFAULT 0.00 AFTER is_visible');
CALL bloomify_add_product_review_column('rating_count', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER rating_avg');

DROP PROCEDURE IF EXISTS bloomify_add_product_review_column;

DELIMITER //

DROP TRIGGER IF EXISTS bloomify_reviews_after_insert//
CREATE TRIGGER bloomify_reviews_after_insert
AFTER INSERT ON product_reviews
FOR EACH ROW
BEGIN
    UPDATE products p
    SET p.rating_avg = (SELECT COALESCE(AVG(r.rating), 0) FROM product_reviews r WHERE r.product_id = NEW.product_id),
        p.rating_count = (SELECT COUNT(*) FROM product_reviews r WHERE r.product_id = NEW.product_id)
    WHERE p.id = NEW.product_id;
END//

DROP TRIGGER IF EXISTS bloomify_reviews_after_update//
CREATE TRIGGER bloomify_reviews_after_update
AFTER UPDATE ON product_reviews
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

DROP TRIGGER IF EXISTS bloomify_reviews_after_delete//
CREATE TRIGGER bloomify_reviews_after_delete
AFTER DELETE ON product_reviews
FOR EACH ROW
BEGIN
    UPDATE products p
    SET p.rating_avg = (SELECT COALESCE(AVG(r.rating), 0) FROM product_reviews r WHERE r.product_id = OLD.product_id),
        p.rating_count = (SELECT COUNT(*) FROM product_reviews r WHERE r.product_id = OLD.product_id)
    WHERE p.id = OLD.product_id;
END//

DELIMITER ;

-- Safe backfill if reviews were imported before the summary columns existed.
UPDATE products p
LEFT JOIN (
    SELECT product_id, AVG(rating) AS average_rating, COUNT(*) AS total_reviews
    FROM product_reviews
    GROUP BY product_id
) r ON r.product_id = p.id
SET p.rating_avg = COALESCE(r.average_rating, 0),
    p.rating_count = COALESCE(r.total_reviews, 0);
