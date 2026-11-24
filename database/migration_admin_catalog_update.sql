-- Bloomify admin/catalog update migration (non-destructive)
-- Run once in phpMyAdmin after selecting the existing `bloomify` database.
-- This does NOT drop or recreate any existing tables or customer/order data.

ALTER TABLE flower_types
    ADD COLUMN color VARCHAR(30) NOT NULL DEFAULT '' AFTER name;

ALTER TABLE flower_types
    DROP INDEX uq_flower_types_name,
    ADD UNIQUE KEY uq_flower_types_name_color (name, color);

CREATE TABLE IF NOT EXISTS category_flower_types (
    category_id INT UNSIGNED NOT NULL,
    flower_type_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (category_id, flower_type_id),
    KEY idx_category_flower_type (flower_type_id),
    CONSTRAINT fk_category_flower_category FOREIGN KEY (category_id)
        REFERENCES categories (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_category_flower_type FOREIGN KEY (flower_type_id)
        REFERENCES flower_types (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add Lily color variants. The existing Lily stock is retained as White;
-- Pink and Yellow start at zero until the admin records their actual stock.
UPDATE flower_types SET color = 'White' WHERE name = 'Lily' AND color = '';
INSERT IGNORE INTO flower_types (name, color, price_per_stem) VALUES
    ('Lily', 'Pink', 15000.00),
    ('Lily', 'Yellow', 15000.00);
INSERT IGNORE INTO flower_stocks (flower_type_id, quantity, low_stock_threshold)
SELECT id, 0, 10 FROM flower_types WHERE name = 'Lily' AND color IN ('Pink', 'Yellow');

-- Hand Bouquet becomes customizable like Flower Box.
UPDATE categories SET pricing_mode = 'mixed' WHERE LOWER(name) IN ('hand bouquet', 'flower box', 'mixed bouquet');

-- Customizable products are priced by selected stems plus the wrapping fee.
UPDATE products p JOIN categories c ON c.id = p.category_id
SET p.flower_type_id = NULL, p.fixed_price = NULL
WHERE LOWER(c.name) IN ('hand bouquet', 'flower box', 'mixed bouquet');

-- Preserve the current Flower Box choices and make all existing flowers
-- initially available to Hand Bouquet too. Admin can refine both lists later.
INSERT IGNORE INTO category_flower_types (category_id, flower_type_id)
SELECT c.id, ft.id
FROM categories c
CROSS JOIN flower_types ft
WHERE LOWER(c.name) IN ('flower box', 'hand bouquet', 'mixed bouquet');
