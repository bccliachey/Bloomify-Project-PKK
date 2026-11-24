<?php
/**
 * Customer-only order completion.
 *
 * Only the owner of an order that has reached its handover state may confirm
 * receipt. Admin accounts have no route that sets an order to Completed.
 */

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_login();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('orders.php');
}

require_csrf();

$user = current_user();
$orderId = (int) ($_POST['order_id'] ?? 0);
$orderCode = trim((string) ($_POST['order_code'] ?? ''));
$detailPath = 'order-detail.php?code=' . rawurlencode($orderCode);

try {
    if ($orderId <= 0 || $orderCode === '') {
        throw new RuntimeException('Invalid order reference.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    $lock = $pdo->prepare(
        'SELECT * FROM orders
         WHERE id = ? AND order_code = ? AND user_id = ?
         LIMIT 1 FOR UPDATE'
    );
    $lock->execute([$orderId, $orderCode, $user['id']]);
    $order = $lock->fetch();

    if (!$order) {
        throw new RuntimeException('This order was not found in your order history.');
    }

    if (!order_is_awaiting_customer_confirmation($order)) {
        throw new RuntimeException('This order is not waiting for your confirmation.');
    }

    $update = $pdo->prepare(
        "UPDATE orders
         SET order_status = 'Selesai',
             completed_at = NOW(),
             updated_at = NOW()
         WHERE id = ?
           AND user_id = ?
           AND order_status IN ('Dikirim', 'Siap Diambil')"
    );
    $update->execute([$orderId, $user['id']]);

    if ($update->rowCount() !== 1) {
        throw new RuntimeException('Receipt has already been confirmed for this order.');
    }

    add_status_history(
        $orderId,
        'Selesai',
        'Receipt confirmed by the customer.',
        null
    );

    create_notification(
        (int) $order['user_id'],
        $orderId,
        'Order completed',
        'You confirmed receipt of order ' . $order['order_code'] . '. You can now review your flowers.'
    );

    $pdo->commit();
    flash_set('success', 'Thank you. Your order is now completed and your purchases are ready to review.');
} catch (Throwable $exception) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    $message = $exception instanceof PDOException
        ? 'Receipt could not be confirmed because of a database error. Please try again.'
        : $exception->getMessage();

    flash_set('error', $message ?: 'Receipt could not be confirmed.');
}

redirect($detailPath);
