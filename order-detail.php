<?php
/**
 * Bloomify - customer order detail (read only).
 */

require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Order Detail';
$activeNav = 'orders';

$user = current_user();
$orderCode = isset($_GET['code']) ? trim((string) $_GET['code']) : '';
$order = $orderCode !== '' ? get_order_by_code($orderCode, (int) $user['id']) : null;

if (!$order) {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    ?>
    <main class="page-main narrow">
        <div class="empty-state">
            <i class="fa-solid fa-receipt"></i>
            <h2>Order not found</h2>
            <p>This order does not belong to your account.</p>
            <a href="<?= e(url('orders.php')) ?>" class="btn btn-primary">Back to My Orders</a>
        </div>
    </main>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$items = get_order_items((int) $order['id']);
$history = get_order_history((int) $order['id']);
$fulfillmentLabel = $order['fulfillment_method'] === 'delivery' ? 'Delivery' : 'Self Pickup';
$paymentMethods = payment_methods();
$paymentMethodData = $paymentMethods[$order['payment_method']] ?? null;
$paymentMethodLabel = payment_method_label($order['payment_method']);
$canUploadProof = payment_method_is_digital($order['payment_method'])
    && in_array($order['payment_status'], ['Unpaid', 'Rejected'], true)
    && !in_array($order['order_status'], ['Selesai', 'Rejected'], true);
$canConfirmReceipt = order_is_awaiting_customer_confirmation($order);
$isCompleted = $order['order_status'] === 'Selesai';

require __DIR__ . '/includes/header.php';
?>

<main class="page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= e(url('index.php')) ?>">Home</a>
        <span>/</span>
        <a href="<?= e(url('account.php')) ?>">Account</a>
        <span>/</span>
        <a href="<?= e(url('orders.php')) ?>">My Orders</a>
        <span>/</span>
        <span><?= e($order['order_code']) ?></span>
    </nav>

    <div class="page-heading">
        <p class="eyebrow">Order <?= e($order['order_code']) ?></p>
        <h1>Order Detail</h1>
        <p>Placed on <?= e(format_datetime($order['created_at'])) ?>.</p>
    </div>

    <div class="order-detail-grid">
        <div>
            <div class="info-card">
                <h2>Items</h2>

                <?php foreach ($items as $item): ?>
                    <div class="order-line">
                        <img src="<?= e(public_image_url($item['product_image'])) ?>"
                             alt="<?= e($item['product_name']) ?>">
                        <div class="order-line-body">
                            <div class="order-line-title"><?= e($item['product_name']) ?></div>
                            <div class="order-line-meta">
                                <?= e($item['category_name']) ?>
                                <?php if ($item['item_type'] === 'fixed'): ?>
                                    â€”                                     <?= (int) $item['quantity'] ?> item(s)
                                <?php else: ?>
                                    <?php
                                    $parts = [];
                                    foreach ($item['details'] as $detail) {
                                        $parts[] = $detail['flower_type_name'] . ' x' . (int) $detail['stem_quantity'];
                                    }
                                    echo $parts ? 'â€” ' . e(implode(', ', $parts)) : '';
                                    ?>
                                    <br>Total stems: <?= (int) $item['stem_total'] ?>
                                    <?php if ((float) $item['wrapping_fee'] > 0): ?>
                                        <br>Wrapping fee: <?= e(format_rupiah($item['wrapping_fee'])) ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="order-line-price"><?= e(format_rupiah($item['item_subtotal'])) ?></div>
                    </div>
                    <?php if ($isCompleted && (int) ($item['product_id'] ?? 0) > 0): ?>
                        <?php
                        $itemReview = get_order_product_review(
                            (int) $user['id'],
                            (int) $order['id'],
                            (int) $item['product_id']
                        );
                        ?>
                        <div class="order-review-action">
                            <a class="btn btn-outline btn-sm"
                               href="<?= e(url($itemReview
                                   ? 'review-form.php?review=' . (int) $itemReview['id']
                                   : 'review-form.php?order=' . rawurlencode($order['order_code']) . '&product=' . (int) $item['product_id'])) ?>">
                                <i class="fa-regular fa-star"></i>
                                <?= $itemReview ? 'Edit Review' : 'Write a Review' ?>
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="summary-line" style="margin-top: 16px;">
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

            <div class="info-card">
                <h2>Order Status</h2>
                <ul class="timeline">
                    <?php foreach ($history as $entry): ?>
                        <li>
                            <div class="timeline-status"><?= e(order_status_label($entry['status'])) ?></div>
                            <div class="timeline-meta">
                                <?= e(format_datetime($entry['created_at'])) ?>
                                <?php if ($entry['note']): ?>
                                    &middot; <?= e($entry['note']) ?>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <aside>
            <div class="info-card">
                <h2>Status</h2>
                <?php if ($order['order_status'] === 'Rejected'): ?>
                    <div class="payment-rejection" style="margin: 0 0 12px;">
                        <strong>Order Rejected</strong><br>
                        Your order has been rejected by the Admin.<br>
                        <strong>Reason:</strong> <?= e($order['rejection_reason'] ?: 'No reason was provided.') ?>
                    </div>
                <?php endif; ?>
                <p style="margin-bottom: 12px;">
                    <span class="badge <?= e(order_status_class($order['order_status'])) ?>">
                        <?= e(order_status_label($order['order_status'])) ?>
                    </span>
                </p>
                <p>
                    <span class="badge <?= e(payment_status_class($order['payment_status'])) ?>">
                        <?= e(payment_status_label($order['payment_status'])) ?>
                    </span>
                    <span class="form-help">(<?= e($paymentMethodLabel) ?>)</span>
                </p>
                <?php if ($canConfirmReceipt): ?>
                    <div class="confirm-receipt">
                        <p class="form-help" style="margin: 0 0 10px;">
                            Have you received this order? Only you can confirm that it has been completed.
                        </p>
                        <form method="post" action="<?= e(url('actions/orders/confirm-receipt.php')) ?>"
                              data-confirm="Confirm that you have received this order? This cannot be undone."
                              data-confirm-title="Confirm Receipt"
                              data-confirm-label="Confirm Received">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                            <input type="hidden" name="order_code" value="<?= e($order['order_code']) ?>">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fa-solid fa-check-double"></i> Confirm Received
                            </button>
                        </form>
                    </div>
                <?php elseif ($isCompleted): ?>
                    <div class="alert alert-success" style="margin: 12px 0 0;">
                        You confirmed receipt of this order. Thank you for shopping with Bloomify.
                    </div>
                <?php else: ?>
                    <p class="form-help" style="margin-top: 12px;">
                        Orders cannot be edited or cancelled after checkout.
                    </p>
                <?php endif; ?>
            </div>

            <div class="info-card">
                <h2>Payment</h2>
                <ul class="info-list">
                    <li><span class="label">Method</span> <?= e($paymentMethodLabel) ?></li>
                    <li><span class="label">Amount</span> <strong><?= e(format_rupiah($order['grand_total'])) ?></strong></li>
                    <?php if ($paymentMethodData && payment_method_is_digital($order['payment_method'])): ?>
                        <li><span class="label">Account number</span> <strong><?= e($paymentMethodData['number']) ?></strong></li>
                        <li><span class="label">Account name</span> <?= e($paymentMethodData['holder']) ?></li>
                    <?php endif; ?>
                </ul>

                <?php if (payment_method_is_digital($order['payment_method'])): ?>
                    <?php if ($order['payment_proof']): ?>
                        <div class="payment-proof-preview">
                            <span class="form-help">Uploaded payment proof</span>
                            <a href="<?= e(payment_proof_url($order['order_code'])) ?>" target="_blank" rel="noopener">
                                <img src="<?= e(payment_proof_url($order['order_code'])) ?>" alt="Payment proof">
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($order['payment_status'] === 'Pending Verification'): ?>
                        <p class="form-help" style="margin-top: 12px;">
                            Your payment proof is being reviewed by our admin team.
                        </p>
                    <?php elseif ($order['payment_status'] === 'Rejected' && $order['order_status'] !== 'Rejected'): ?>
                        <p class="payment-rejection">
                            <?= e($order['rejection_reason'] ?: 'Your payment proof was rejected. Please upload a new proof.') ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($canUploadProof): ?>
                        <form action="<?= e(url('actions/upload-payment-proof.php')) ?>" method="post"
                              enctype="multipart/form-data" class="payment-proof-form" data-confirm="Upload this payment proof now?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_code" value="<?= e($order['order_code']) ?>">
                            <label class="form-label" for="payment_proof">Payment proof</label>
                            <input class="form-control" type="file" id="payment_proof" name="payment_proof"
                                   accept="image/jpeg,image/png,image/webp" required>
                            <p class="form-help">JPG, PNG, or WEBP. Maximum 2 MB.</p>
                            <button type="submit" class="btn btn-primary btn-block">Upload Payment Proof</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="form-help" style="margin-top: 12px;">
                        Please prepare the exact amount when your order is delivered or collected.
                    </p>
                <?php endif; ?>
            </div>

            <div class="info-card">
                <h2>Recipient</h2>
                <ul class="info-list">
                    <li><span class="label">Name</span> <?= e($order['recipient_name']) ?></li>
                    <li><span class="label">Phone</span> <?= e($order['recipient_phone']) ?></li>
                    <li><span class="label">Fulfillment</span> <?= e($fulfillmentLabel) ?></li>
                    <?php if ($order['fulfillment_method'] === 'delivery'): ?>
                        <li><span class="label">Address</span> <?= e($order['delivery_address']) ?></li>
                        <?php if ($order['address_detail']): ?>
                            <li><span class="label">Details</span> <?= e($order['address_detail']) ?></li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li><span class="label">Pickup at</span> <?= e(setting('store_address')) ?></li>
                        <li><span class="label">Hours</span> <?= e(setting('store_hours')) ?></li>
                    <?php endif; ?>
                    <?php if ($order['notes']): ?>
                        <li><span class="label">Notes</span> <?= e($order['notes']) ?></li>
                    <?php endif; ?>
                </ul>
            </div>

            <a href="<?= e(url('orders.php')) ?>" class="btn btn-soft btn-block">
                Back to My Orders
            </a>
        </aside>
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
