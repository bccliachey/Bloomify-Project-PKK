<?php
/**
 * Bloomify - customer order history.
 */

require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'My Orders';
$activeNav = 'orders';

$user = current_user();
$statement = db()->prepare(
    'SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC'
);
$statement->execute([$user['id']]);
$orders = $statement->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<main class="page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= e(url('index.php')) ?>">Home</a>
        <span>/</span>
        <a href="<?= e(url('account.php')) ?>">Account</a>
        <span>/</span>
        <span>My Orders</span>
    </nav>

    <div class="page-heading">
        <p class="eyebrow">Order History</p>
        <h1>My Orders</h1>
        <p>Track the status of your current orders and revisit your past bouquets.</p>
    </div>

    <?php if ($orders): ?>
        <div class="order-list">
            <?php foreach ($orders as $order): ?>
                <?php
                $fulfillmentLabel = $order['fulfillment_method'] === 'delivery' ? 'Delivery' : 'Self Pickup';
                $paymentMethodLabel = payment_method_label($order['payment_method']);
                ?>
                <article class="order-card">
                    <div>
                        <p class="order-code"><?= e($order['order_code']) ?></p>
                        <p class="order-meta">
                            <?= e(format_datetime($order['created_at'])) ?><br>
                            <?= e($fulfillmentLabel) ?> &middot; <?= e($paymentMethodLabel) ?> &middot; <?= e(payment_status_label($order['payment_status'])) ?>
                        </p>
                    </div>

                    <div class="order-side">
                        <span class="badge <?= e(order_status_class($order['order_status'])) ?>">
                            <?= e(order_status_label($order['order_status'])) ?>
                        </span>
                        <span class="order-total"><?= e(format_rupiah($order['grand_total'])) ?></span>
                        <a class="btn btn-soft btn-sm"
                           href="<?= e(url('order-detail.php?code=' . rawurlencode($order['order_code']))) ?>">
                            View Detail
                        </a>
                    </div>

                    <?php if ($order['order_status'] === 'Rejected'): ?>
                        <div class="payment-rejection" style="flex-basis: 100%;">
                            <strong>Order Rejected</strong><br>
                            Your order has been rejected by the Admin.<br>
                            <strong>Reason:</strong> <?= e($order['rejection_reason'] ?: 'No reason was provided.') ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-receipt"></i>
            <h2>No orders yet</h2>
            <p>Your order history will appear here after your first checkout.</p>
            <a href="<?= e(url('catalog.php')) ?>" class="btn btn-primary">Explore Products</a>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
