ALTER TABLE orders
    MODIFY order_status ENUM('Pending', 'Proses', 'Dikirim', 'Siap Diambil', 'Selesai', 'Rejected')
    NOT NULL DEFAULT 'Pending';

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

CALL bloomify_add_order_column('rejection_reason', 'VARCHAR(255) NULL');
CALL bloomify_add_order_column('rejected_at', 'DATETIME NULL');
CALL bloomify_add_order_column('rejected_by', 'INT UNSIGNED NULL');
CALL bloomify_add_order_column('stock_restored_at', 'DATETIME NULL');

DROP PROCEDURE IF EXISTS bloomify_add_order_column;
