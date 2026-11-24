<?php
/**
 * Bloomify - order confirmation page shown right after checkout.
 */

require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Order Confirmation';
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
            <p>We could not find this order in your account.</p>
            <a href="<?= e(url('orders.php')) ?>" class="btn btn-primary">My Orders</a>
        </div>
    </main>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$items = get_order_items((int) $order['id']);
$fulfillmentLabel = $order['fulfillment_method'] === 'delivery' ? 'Delivery' : 'Self Pickup';
$paymentMethods = payment_methods();
$paymentMethodData = $paymentMethods[$order['payment_method']] ?? null;
$paymentMethodLabel = payment_method_label($order['payment_method']);

require __DIR__ . '/includes/header.php';
?>

<main class="page-main narrow">
    <div class="empty-state" style="margin-bottom: 24px;">
        <i class="fa-solid fa-circle-check" style="color: #2f7a52;"></i>
        <h2>Thank you, <?= e($order['recipient_name']) ?>!</h2>
        <p>
            Your order <strong><?= e($order['order_code']) ?></strong> has been placed.
            We will confirm it and start preparing your flowers soon.
        </p>
        <div class="form-actions" style="justify-content: center;">
            <a href="<?= e(url('order-detail.php?code=' . rawurlencode($order['order_code']))) ?>"
               class="btn btn-primary">View Order Detail</a>
            <a href="<?= e(url('catalog.php')) ?>" class="btn btn-soft">Continue Shopping</a>
        </div>
    </div>

    <div class="info-card">
        <h2>Order Summary</h2>

        <ul class="info-list">
            <li><span class="label">Order code</span> <?= e($order['order_code']) ?></li>
            <li><span class="label">Order date</span> <?= e(format_datetime($order['created_at'])) ?></li>
            <li>
                <span class="label">Status</span>
                <span class="badge <?= e(order_status_class($order['order_status'])) ?>">
                    <?= e(order_status_label($order['order_status'])) ?>
                </span>
            </li>
            <li>
                <span class="label">Payment</span>
                <span class="badge <?= e(payment_status_class($order['payment_status'])) ?>">
                    <?= e(payment_status_label($order['payment_status'])) ?>
                </span>
                (<?= e($paymentMethodLabel) ?>)
            </li>
            <li><span class="label">Fulfillment</span> <?= e($fulfillmentLabel) ?></li>
            <li><span class="label">Recipient</span> <?= e($order['recipient_name']) ?> (<?= e($order['recipient_phone']) ?>)</li>
            <?php if ($order['fulfillment_method'] === 'delivery'): ?>
                <li><span class="label">Address</span> <?= e($order['delivery_address']) ?></li>
                <?php if ($order['address_detail']): ?>
                    <li><span class="label">Details</span> <?= e($order['address_detail']) ?></li>
                <?php endif; ?>
            <?php else: ?>
                <li><span class="label">Pickup at</span> <?= e(setting('store_address')) ?></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="info-card payment-instructions">
        <h2>Payment Instructions</h2>

        <?php if (payment_method_is_digital($order['payment_method'])): ?>
            <?php if ($order['payment_proof']): ?>
                <p>
                    Your payment proof was uploaded during checkout and is now
                    <strong>Pending Verification</strong>. Our admin team will review it before preparing your order.
                </p>
            <?php else: ?>
                <p>Please transfer the grand total using the details below, then upload your payment proof.</p>
            <?php endif; ?>
            <ul class="info-list">
                <li><span class="label">Payment method</span> <?= e($paymentMethodLabel) ?></li>
                <li><span class="label">Amount</span> <strong><?= e(format_rupiah($order['grand_total'])) ?></strong></li>
                <?php if ($paymentMethodData): ?>
                    <li><span class="label">Account number</span> <strong><?= e($paymentMethodData['number']) ?></strong></li>
                    <li><span class="label">Account name</span> <?= e($paymentMethodData['holder']) ?></li>
                <?php endif; ?>
            </ul>
            <?php if (!$order['payment_proof']): ?>
                <a href="<?= e(url('order-detail.php?code=' . rawurlencode($order['order_code']))) ?>"
                   class="btn btn-primary" style="margin-top: 12px;">Upload Payment Proof</a>
            <?php endif; ?>
        <?php else: ?>
            <p>
                Pay in cash when your order is delivered or collected at the store.
                Your payment status will remain Unpaid until the admin receives your payment.
            </p>
        <?php endif; ?>
    </div>

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
                            â€”                             <?= (int) $item['quantity'] ?> item(s)
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
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
