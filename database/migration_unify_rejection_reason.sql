-- Unify order and payment rejection reasons into orders.rejection_reason.

UPDATE orders
SET rejection_reason = payment_rejection_reason
WHERE rejection_reason IS NULL
  AND payment_rejection_reason IS NOT NULL;

DELIMITER //

DROP PROCEDURE IF EXISTS bloomify_drop_order_column//
CREATE PROCEDURE bloomify_drop_order_column(IN column_name_value VARCHAR(64))
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND COLUMN_NAME = column_name_value
    ) THEN
        SET @alter_sql = CONCAT(
            'ALTER TABLE orders DROP COLUMN `',
            column_name_value,
            '`'
        );
        PREPARE alter_statement FROM @alter_sql;
        EXECUTE alter_statement;
        DEALLOCATE PREPARE alter_statement;
    END IF;
END//

DELIMITER ;

CALL bloomify_drop_order_column('payment_rejection_reason');

DROP PROCEDURE IF EXISTS bloomify_drop_order_column;
