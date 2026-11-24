<?php
/**
 * Bloomify - admin order detail: confirm, advance status, mark payment.
 */

require_once __DIR__ . '/includes/auth.php';

$adminTitle = 'Order Detail';
$activeAdmin = 'orders';

$admin = current_admin();
$orderCode = trim((string) ($_GET['code'] ?? ''));
$order = $orderCode !== '' ? get_order_by_code($orderCode) : null;

if (!$order) {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="empty-state">
        <i class="fa-solid fa-receipt"></i>
        <h2>Order not found</h2>
        <p>No order matches that code.</p>
        <a class="btn btn-primary" href="<?= e(url('admin/orders.php')) ?>">Back to Orders</a>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$detailUrl = 'admin/order-detail.php?code=' . rawurlencode($order['order_code']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'confirm') {
        try {
            db()->beginTransaction();

            $lock = db()->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $lock->execute([$order['id']]);
            $locked = $lock->fetch();

            if (!$locked || $locked['order_status'] !== 'Pending') {
                throw new RuntimeException('This order is no longer pending.');
            }

            if (payment_method_is_digital($locked['payment_method']) && $locked['payment_status'] !== 'Paid') {
                throw new RuntimeException('Verify the digital payment before confirming this order.');
            }

            $items = get_order_items((int) $locked['id']);
            $shortages = [];

            foreach ($items as $item) {
                if ($item['item_type'] === 'fixed') {
                    $units = (int) $item['quantity'];

                    if ($units <= 0) {
                        continue;
                    }

                    if (!$item['product_id']) {
                        $shortages[] = $item['product_name'] . ' is no longer available';
                        continue;
                    }

                    $stockLookup = db()->prepare(
                        'SELECT quantity FROM product_stocks WHERE product_id = ? FOR UPDATE'
                    );
                    $stockLookup->execute([$item['product_id']]);
                    $stock = $stockLookup->fetch();
                    $available = $stock ? (int) $stock['quantity'] : 0;

                    if ($available < $units) {
                        $shortages[] = $item['product_name'] . ' (need ' . $units . ', available ' . $available . ')';
                        continue;
                    }

                    $newQuantity = $available - $units;
                    $update = db()->prepare('UPDATE product_stocks SET quantity = ? WHERE product_id = ?');
                    $update->execute([$newQuantity, $item['product_id']]);

                    $movement = db()->prepare(
                        'INSERT INTO stock_movements
                            (item_type, flower_type_id, product_id, change_qty, stock_after, note, admin_id)
                         VALUES (?, NULL, ?, ?, ?, ?, ?)'
                    );
                    $movement->execute([
                        'product',
                        $item['product_id'],
                        -$units,
                        $newQuantity,
                        'Order confirmation ' . $locked['order_code'],
                        $admin['id'],
                    ]);

                    continue;
                }

                foreach ($item['details'] as $detail) {
                    $stems = (int) $detail['stem_quantity'];

                    if ($stems <= 0) {
                        continue;
                    }

                    if (!$detail['flower_type_id']) {
                        $shortages[] = $detail['flower_type_name'] . ' is no longer available';
                        continue;
                    }

                    $stockLookup = db()->prepare(
                        'SELECT quantity FROM flower_stocks WHERE flower_type_id = ? FOR UPDATE'
                    );
                    $stockLookup->execute([$detail['flower_type_id']]);
                    $stock = $stockLookup->fetch();
                    $available = $stock ? (int) $stock['quantity'] : 0;

                    if ($available < $stems) {
                        $shortages[] = $detail['flower_type_name'] . ' (need ' . $stems . ', available ' . $available . ')';
                        continue;
                    }

                    $newQuantity = $available - $stems;
                    $update = db()->prepare(
                        'UPDATE flower_stocks SET quantity = ? WHERE flower_type_id = ?'
                    );
                    $update->execute([$newQuantity, $detail['flower_type_id']]);

                    $movement = db()->prepare(
                        'INSERT INTO stock_movements
                            (item_type, flower_type_id, product_id, change_qty, stock_after, note, admin_id)
                         VALUES (?, ?, NULL, ?, ?, ?, ?)'
                    );
                    $movement->execute([
                        'flower',
                        $detail['flower_type_id'],
                        -$stems,
                        $newQuantity,
                        'Order confirmation ' . $locked['order_code'],
                        $admin['id'],
                    ]);
                }
            }

            if ($shortages) {
                throw new RuntimeException('Insufficient stock: ' . implode('; ', $shortages) . '.');
            }

            $update = db()->prepare(
                "UPDATE orders
                 SET order_status = 'Proses', confirmed_at = NOW(), updated_at = NOW()
                 WHERE id = ? AND order_status = 'Pending'"
            );
            $update->execute([$locked['id']]);

            add_status_history(
                (int) $locked['id'],
                'Proses',
                'Order confirmed. Stock deducted automatically.',
                (int) $admin['id']
            );

            create_notification(
                (int) $locked['user_id'],
                (int) $locked['id'],
                'Order confirmed',
                'Your order ' . $locked['order_code'] . ' is now being prepared.'
            );

            db()->commit();
            flash_set('success', 'Order confirmed and stock deducted.');
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            $message = $exception instanceof PDOException
                ? 'The order could not be confirmed because of a database error.'
                : $exception->getMessage();

            flash_set('error', $message ?: 'The order could not be confirmed.');
        }

        redirect($detailUrl);
    }

    if ($action === 'status') {
        $newStatus = (string) ($_POST['order_status'] ?? '');
        $note = trim((string) ($_POST['note'] ?? ''));

        try {
            db()->beginTransaction();

            $lock = db()->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $lock->execute([$order['id']]);
            $locked = $lock->fetch();

            if (!$locked) {
                throw new RuntimeException('Order not found.');
            }

            if ($locked['order_status'] === 'Pending') {
                throw new RuntimeException('Use Confirm Order to accept this order and deduct stock first.');
            }

            $allowed = next_order_statuses($locked['order_status'], $locked['fulfillment_method']);

            if ($newStatus === 'Selesai' || !in_array($newStatus, $allowed, true)) {
                throw new RuntimeException('Only the customer can mark an order as completed.');
            }

            $update = db()->prepare(
                'UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?'
            );
            $update->execute([$newStatus, $locked['id']]);

            $historyNote = $note !== ''
                ? mb_substr($note, 0, 255)
                : 'Status changed to ' . order_status_label($newStatus) . '.';

            add_status_history(
                (int) $locked['id'],
                $newStatus,
                $historyNote,
                (int) $admin['id']
            );

            create_notification(
                (int) $locked['user_id'],
                (int) $locked['id'],
                'Order status updated',
                'Your order ' . $locked['order_code'] . ' is now ' . order_status_label($newStatus) . '.'
            );

            db()->commit();
            flash_set('success', 'Order status updated to ' . order_status_label($newStatus) . '.');
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            $message = $exception instanceof PDOException
                ? 'The order status could not be updated because of a database error.'
                : $exception->getMessage();

            flash_set('error', $message ?: 'The order status could not be updated.');
        }

        redirect($detailUrl);
    }

    if ($action === 'reject') {
        $rejectionReason = trim((string) ($_POST['rejection_reason'] ?? ''));

        try {
            reject_order((int) $order['id'], (int) $admin['id'], $rejectionReason);
            flash_set('success', 'Order rejected. The customer has been notified.');
        } catch (Throwable $exception) {
            $message = $exception instanceof PDOException
                ? 'The order could not be rejected because of a database error.'
                : $exception->getMessage();

            flash_set('error', $message ?: 'The order could not be rejected.');
        }

        redirect($detailUrl);
    }

    if ($action === 'payment') {
        $newPayment = (string) ($_POST['payment_status'] ?? '');
        $allowedPayments = payment_status_options($order['payment_method']);

        try {
            if (!in_array($newPayment, $allowedPayments, true)) {
                throw new RuntimeException('Choose a valid payment status.');
            }

            if (payment_method_is_digital($order['payment_method'])
                && $newPayment === 'Paid'
                && !$order['payment_proof']) {
                throw new RuntimeException('The customer has not uploaded a payment proof yet.');
            }

            $verified = $newPayment === 'Paid';
            $update = db()->prepare(
                'UPDATE orders
                 SET payment_status = ?,
                     payment_verified_at = ?,
                     payment_verified_by = ?,
                     updated_at = NOW()
                 WHERE id = ?'
            );
            $update->execute([
                $newPayment,
                $verified ? date('Y-m-d H:i:s') : null,
                $verified ? (int) $admin['id'] : null,
                $order['id'],
            ]);

            if ($newPayment === 'Paid') {
                create_notification(
                    (int) $order['user_id'],
                    (int) $order['id'],
                    'Payment verified',
                    'Your payment for ' . $order['order_code'] . ' has been verified.'
                );
            }

            flash_set('success', 'Payment status updated to ' . payment_status_label($newPayment) . '.');
        } catch (Throwable $exception) {
            $message = $exception instanceof PDOException
                ? 'The payment status could not be updated because of a database error.'
                : $exception->getMessage();
            flash_set('error', $message ?: 'The payment status could not be updated.');
        }

        redirect($detailUrl);
    }

    flash_set('error', 'Unknown action.');
    redirect($detailUrl);
}

$order = get_order_by_code($orderCode);
$items = get_order_items((int) $order['id']);
$history = get_order_history((int) $order['id']);
$nextStatuses = next_order_statuses($order['order_status'], $order['fulfillment_method']);
$fulfillmentLabel = $order['fulfillment_method'] === 'delivery' ? 'Delivery' : 'Self Pickup';
$paymentMethods = payment_methods();
$paymentMethodLabel = payment_method_label($order['payment_method']);
$paymentOptions = array_values(array_filter(
    payment_status_options($order['payment_method']),
    static fn (string $status): bool => $status !== 'Rejected'
));

require __DIR__ . '/includes/header.php';
?>

<div class="grid-2 order-detail-layout">
    <div>
        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2>Order Details</h2>
                    <p><?= e($order['order_code']) ?> &middot; <?= e(format_datetime($order['created_at'])) ?></p>
                </div>
                <a class="btn btn-outline btn-sm" href="<?= e(url('admin/orders.php')) ?>">All Orders</a>
            </div>

            <?php foreach ($items as $item): ?>
                <div class="order-line">
                    <img src="<?= e(public_image_url($item['product_image'])) ?>"
                         alt="<?= e($item['product_name']) ?>">
                    <div class="order-line-body">
                        <div class="order-line-title"><?= e($item['product_name']) ?></div>
                        <div class="order-line-meta">
                            <?= e($item['category_name']) ?>
                            <?php if ($item['item_type'] === 'fixed'): ?>
                                &middot; <?= (int) $item['quantity'] ?> item(s)
                                &middot; <?= e(format_rupiah($item['unit_price'])) ?> each
                            <?php else: ?>
                                <?php
                                $parts = [];
                                foreach ($item['details'] as $detail) {
                                    $parts[] = $detail['flower_type_name'] . ' x' . (int) $detail['stem_quantity'];
                                }
                                echo $parts ? '&middot; ' . e(implode(', ', $parts)) : '';
                                ?>
                                <br>Total stems: <?= (int) $item['stem_total'] ?>
                                <?php if ((float) $item['wrapping_fee'] > 0): ?>
                                    &middot; Wrapping: <?= e(format_rupiah($item['wrapping_fee'])) ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="order-line-price"><?= e(format_rupiah($item['item_subtotal'])) ?></div>
                </div>
            <?php endforeach; ?>

            <div class="summary-line" style="margin-top: 14px;">
                <span>Product subtotal</span>
                <span><?= e(format_rupiah($order['product_subtotal'])) ?></span>
            </div>
            <div class="summary-line">
                <span>Wrapping fees</span>
                <span><?= e(format_rupiah($order['wrapping_total'])) ?></span>
            </div>
            <div class="summary-line">
                <span>Delivery fee</span>
                <span><?= e(format_rupiah($order['delivery_fee'])) ?></span>
            </div>
            <div class="summary-line total">
                <span>Grand total</span>
                <span><?= e(format_rupiah($order['grand_total'])) ?></span>
            </div>
        </div>

        <div class="info-card customer-info-card">
            <div class="customer-identity" style="margin-bottom: 14px;">
                <?php if (!empty($order['customer_profile_photo'])): ?>
                    <img class="customer-avatar large" src="<?= e(public_image_url($order['customer_profile_photo'])) ?>" alt="<?= e($order['customer_name']) ?> profile picture">
                <?php else: ?>
                    <div class="customer-avatar large" style="display:grid;place-items:center;font-size:28px;color:var(--maroon);font-weight:700;"><?= e(mb_strtoupper(mb_substr($order['customer_name'], 0, 1))) ?></div>
                <?php endif; ?>
                <div><h2 style="margin:0;">Customer Information</h2><p class="cell-muted">Customer profile</p></div>
            </div>
            <ul class="info-list">
                <li><span class="label">Name</span> <?= e($order['customer_name']) ?></li>
                <li><span class="label">Username</span> @<?= e($order['customer_username']) ?></li>
                <li><span class="label">Phone</span> <?= e($order['customer_phone']) ?></li>
                <li><span class="label">Recipient</span> <?= e($order['recipient_name']) ?></li>
                <li><span class="label">Recipient phone</span> <?= e($order['recipient_phone']) ?></li>
                <li><span class="label">Fulfillment</span> <?= e($fulfillmentLabel) ?></li>
                <?php if ($order['fulfillment_method'] === 'delivery'): ?>
                    <li><span class="label">Address</span> <?= e($order['delivery_address']) ?></li>
                    <?php if ($order['address_detail']): ?>
                        <li><span class="label">Details</span> <?= e($order['address_detail']) ?></li>
                    <?php endif; ?>
                <?php else: ?>
                    <li><span class="label">Pickup at</span> <?= e(setting('store_address')) ?></li>
                <?php endif; ?>
                <?php if ($order['notes']): ?>
                    <li><span class="label">Notes</span> <?= e($order['notes']) ?></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Status Timeline</h2>
            </div>

            <?php if ($history): ?>
                <ul class="timeline">
                    <?php foreach ($history as $entry): ?>
                        <li>
                            <div class="timeline-status"><?= e(order_status_label($entry['status'])) ?></div>
                            <div class="timeline-meta">
                                <?= e(format_datetime($entry['created_at'])) ?>
                                &middot; <?= e($entry['admin_name'] ?? 'System') ?>
                                <?php if ($entry['note']): ?>
                                    <br><?= e($entry['note']) ?>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="form-help">No status history recorded yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="info-card">
            <h2>Manage Order</h2>

            <p style="margin-bottom: 10px;">
                <span class="badge <?= e(order_status_class($order['order_status'])) ?>">
                    <?= e(order_status_label($order['order_status'])) ?>
                </span>
                <span class="badge <?= e(payment_status_class($order['payment_status'])) ?>">
                    <?= e(payment_status_label($order['payment_status'])) ?>
                </span>
            </p>

            <?php if ($order['order_status'] === 'Rejected'): ?>
                <p class="form-help" style="margin: 12px 0;">
                    This order is closed. The rejection reason and payment records are preserved for history.
                </p>
            <?php elseif ($order['order_status'] === 'Pending'): ?>
                <?php if (payment_method_is_digital($order['payment_method']) && $order['payment_status'] !== 'Paid'): ?>
                    <div class="alert alert-warning" style="margin: 14px 0;">
                        Verify this digital payment as Paid before confirming the order.
                    </div>
                <?php else: ?>
                    <div class="alert alert-info" style="margin: 14px 0;">
                        Confirming this order deducts the required flower and fixed-price product stock.
                        This action cannot be undone.
                    </div>

                    <form method="post" action="<?= e(url($detailUrl)) ?>"
                          data-confirm="Confirm <?= e($order['order_code']) ?> and deduct stock now?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="confirm">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fa-solid fa-circle-check"></i> Confirm Order &amp; Deduct Stock
                        </button>
                    </form>
                <?php endif; ?>
            <?php elseif ($nextStatuses): ?>
                <form method="post" action="<?= e(url($detailUrl)) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="status">

                    <div class="form-field">
                        <label for="order_status">Next status</label>
                        <select id="order_status" name="order_status" class="form-control">
                            <?php foreach ($nextStatuses as $status): ?>
                                <option value="<?= e($status) ?>"><?= e(order_status_label($status)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field" style="margin-top: 12px;">
                        <label for="note">Note (optional)</label>
                        <input type="text" id="note" name="note" class="form-control"
                               maxlength="255" placeholder="Visible in the order timeline">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" style="margin-top: 14px;">
                        Update Status
                    </button>
                </form>
            <?php elseif (order_is_awaiting_customer_confirmation($order)): ?>
                <div class="alert alert-info" style="margin: 14px 0;">
                    The order has reached the customer. Only the customer can confirm receipt and complete it.
                </div>
            <?php else: ?>
                <p class="form-help" style="margin: 12px 0;">
                    This order is complete. No further status changes are available.
                </p>
            <?php endif; ?>

            <hr style="border: none; border-top: 1px solid var(--line); margin: 18px 0;">

            <p style="margin-bottom: 12px;">
                <span class="form-help">Payment method</span><br>
                <strong><?= e($paymentMethodLabel) ?></strong>
            </p>

            <?php if (payment_method_is_digital($order['payment_method'])): ?>
                <?php if ($order['payment_proof']): ?>
                    <div class="payment-proof-preview">
                        <span class="form-help">Uploaded payment proof</span>
                        <a href="<?= e(payment_proof_url($order['order_code'])) ?>" target="_blank" rel="noopener">
                            <img src="<?= e(payment_proof_url($order['order_code'])) ?>" alt="Payment proof">
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning" style="margin-bottom: 14px;">
                        Waiting for the customer to upload payment proof.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (in_array($order['order_status'], ['Rejected'], true) || $order['payment_status'] === 'Rejected'): ?>
                <div class="alert alert-info" style="margin-top: 12px;">
                    <?= $order['order_status'] === 'Rejected'
                        ? 'This order has been rejected, so the payment status is locked and preserved as a record.'
                        : 'The payment was rejected, so this status is locked and preserved as a record.' ?>
                </div>
                <?php if ($order['order_status'] !== 'Rejected' && !empty($order['rejection_reason'])): ?>
                    <p class="form-help"><strong>Rejection reason:</strong> <?= e($order['rejection_reason']) ?></p>
                <?php endif; ?>
            <?php else: ?>
                <form method="post" action="<?= e(url($detailUrl)) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="payment">

                    <div class="form-field">
                        <label for="payment_status">Payment status</label>
                        <select id="payment_status" name="payment_status" class="form-control">
                            <?php foreach ($paymentOptions as $option): ?>
                                <option value="<?= e($option) ?>" <?= $order['payment_status'] === $option ? 'selected' : '' ?>>
                                    <?= e(payment_status_label($option)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-soft btn-block" style="margin-top: 12px;">
                        Save Payment Status
                    </button>
                </form>
            <?php endif; ?>

            <ul class="info-list" style="margin-top: 16px;">
                <li><span class="label">Confirmed</span> <?= e(format_datetime($order['confirmed_at'])) ?></li>
                <li><span class="label">Completed</span> <?= e(format_datetime($order['completed_at'])) ?></li>
            </ul>
        </div>

        <div class="info-card">
            <h2>Reject Order</h2>

            <?php if ($order['order_status'] === 'Rejected'): ?>
                <div class="alert alert-error" style="margin-top: 12px;">
                    <strong>Rejected by Admin</strong><br>
                    <strong>Reason:</strong> <?= e($order['rejection_reason'] ?: 'No reason was provided.') ?><br>
                    <span class="form-help">Rejected on <?= e(format_datetime($order['rejected_at'])) ?>.</span>
                </div>
                <p class="form-help">This order is closed. The rejection reason and payment records are preserved for history.</p>
            <?php elseif ($order['order_status'] === 'Selesai'): ?>
                <p class="form-help">Completed orders cannot be rejected.</p>
            <?php else: ?>
                <p class="form-help">
                    Use this section to reject the order and its payment proof together.
                    The customer will see this reason in their order history.
                </p>

                <form method="post" action="<?= e(url($detailUrl)) ?>"
                      data-confirm="Reject this order? This action cannot be undone."
                      data-confirm-title="Reject Order"
                      data-confirm-label="Reject Order"
                      data-confirm-danger="true">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reject">

                    <div class="form-field">
                        <label for="rejection_reason">Rejection reason</label>
                        <textarea id="rejection_reason" name="rejection_reason" class="form-control"
                                  rows="3" maxlength="255" required
                                  placeholder="Explain why this order is being rejected."></textarea>
                    </div>

                    <button type="submit" class="btn btn-danger btn-block" style="margin-top: 12px;">
                        <i class="fa-solid fa-ban"></i> Reject Order
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
