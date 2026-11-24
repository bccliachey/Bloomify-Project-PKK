-- Bloomify product-specific customization migration
-- Import this file once in phpMyAdmin after backing up the database.
CREATE TABLE IF NOT EXISTS product_flower_types (
    product_id INT UNSIGNED NOT NULL,
    flower_type_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id, flower_type_id),
    KEY idx_product_flower_type (flower_type_id),
    CONSTRAINT fk_product_flower_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_product_flower_type FOREIGN KEY (flower_type_id) REFERENCES flower_types(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserve existing single-flower product choices.
INSERT IGNORE INTO product_flower_types (product_id, flower_type_id)
SELECT id, flower_type_id FROM products WHERE flower_type_id IS NOT NULL;

-- Configure existing sample Hand Bouquet products independently by their current flower composition.
INSERT IGNORE INTO product_flower_types (product_id, flower_type_id)
SELECT p.id, ft.id
FROM products p
JOIN flower_types ft ON (
    (p.slug IN ('harmonies', 'lily-serenade') AND ft.name = 'Lily') OR
    (p.slug = 'rose-amour' AND ft.name = 'Rose' AND ft.color IN ('White', 'Pink', 'Red')) OR
    (p.slug = 'ivory-puff' AND ft.name = 'Peony')
);

-- Migrate each category's previous flower options to its existing products once.
INSERT IGNORE INTO product_flower_types (product_id, flower_type_id)
SELECT p.id, cft.flower_type_id
FROM products p
JOIN category_flower_types cft ON cft.category_id = p.category_id
JOIN categories c ON c.id = p.category_id
WHERE c.pricing_mode = 'mixed' AND c.slug IN ('mixed-bouquet', 'mix-bouquet');

-- Add the Rose color variants used by individual Hand Bouquet products.
INSERT IGNORE INTO flower_types (name, color, price_per_stem) VALUES
('Rose', 'White', 18000.00),
('Rose', 'Pink', 18000.00),
('Rose', 'Red', 18000.00);
INSERT IGNORE INTO flower_stocks (flower_type_id, quantity, low_stock_threshold)
SELECT id, 100, 10 FROM flower_types WHERE name = 'Rose' AND color IN ('White', 'Pink', 'Red');
INSERT IGNORE INTO product_flower_types (product_id, flower_type_id)
SELECT p.id, ft.id FROM products p JOIN flower_types ft
WHERE p.slug = 'rose-amour' AND ft.name = 'Rose' AND ft.color IN ('White', 'Pink', 'Red');
UPDATE categories SET pricing_mode = 'per_stem' WHERE slug = 'hand-bouquet';

-- Ensure the flower box category is fixed-price and the mix category uses its correct name/slug.
UPDATE categories SET name = 'Mix Bouquet', slug = 'mix-bouquet' WHERE slug = 'mixed-bouquet';
UPDATE categories SET pricing_mode = 'fixed', description = 'A ready-made flower arrangement with a fixed composition.' WHERE slug = 'flower-box';
INSERT IGNORE INTO product_stocks (product_id, quantity, low_stock_threshold)
SELECT id, 0, 5 FROM products WHERE slug = 'elodie-blush';
