ALTER TABLE orders
    MODIFY payment_method ENUM('COD', 'DANA', 'GoPay', 'OVO', 'ShopeePay', 'Bank BRI')
    NOT NULL DEFAULT 'COD';

ALTER TABLE orders
    MODIFY payment_status VARCHAR(30) NOT NULL DEFAULT 'Unpaid';

UPDATE orders
SET payment_status = 'Unpaid'
WHERE payment_status IN ('Belum Dibayar', '', 'Unpaid') OR payment_status IS NULL;

UPDATE orders
SET payment_status = 'Paid'
WHERE payment_status IN ('Lunas', 'Paid');

UPDATE orders
SET payment_status = 'Unpaid'
WHERE payment_status NOT IN ('Unpaid', 'Pending Verification', 'Paid', 'Rejected');

ALTER TABLE orders
    MODIFY payment_status ENUM('Unpaid', 'Pending Verification', 'Paid', 'Rejected')
    NOT NULL DEFAULT 'Unpaid';

DELIMITER //

DROP PROCEDURE IF EXISTS bloomify_add_order_column//
CREATE PROCEDURE bloomify_add_order_column(IN column_name_value VARCHAR(64), IN column_definition TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND COLUMN_NAME = column_name_value
    ) THEN
        SET @alter_sql = CONCAT(
            'ALTER TABLE orders ADD COLUMN `',
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

CALL bloomify_add_order_column('payment_proof', 'VARCHAR(255) NULL');
CALL bloomify_add_order_column('payment_proof_uploaded_at', 'DATETIME NULL');
CALL bloomify_add_order_column('payment_verified_at', 'DATETIME NULL');
CALL bloomify_add_order_column('payment_verified_by', 'INT UNSIGNED NULL');
CALL bloomify_add_order_column('payment_rejection_reason', 'VARCHAR(255) NULL');

DROP PROCEDURE IF EXISTS bloomify_add_order_column;

INSERT INTO store_settings (setting_key, setting_value) VALUES
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
    ('payment_bank_bri_holder', '')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
