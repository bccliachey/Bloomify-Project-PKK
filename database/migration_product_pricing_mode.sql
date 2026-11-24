-- Bloomify product-level pricing mode migration (non-destructive)
-- Run once in phpMyAdmin after selecting the existing `bloomify` database.
--
-- Adds an optional pricing_mode override to each product. When the value is
-- NULL the product keeps inheriting its category's pricing mode, so existing
-- products and categories continue to work exactly as they do today.

SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'pricing_mode'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `products` ADD COLUMN `pricing_mode` ENUM(''per_stem'',''mixed'',''fixed'') NULL DEFAULT NULL AFTER `fixed_price`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Fixed-price products keep their own product stock. Create any missing stock
-- rows without changing quantities that are already recorded.
INSERT IGNORE INTO `product_stocks` (`product_id`, `quantity`, `low_stock_threshold`)
SELECT p.id, 0, 5
FROM products p
JOIN categories c ON c.id = p.category_id
WHERE COALESCE(p.pricing_mode, c.pricing_mode) = 'fixed';
