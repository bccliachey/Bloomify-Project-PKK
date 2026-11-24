<?php
/**
 * Bloomify - customer notifications.
 */

require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Notifications';
$activeNav = 'account';

$user = current_user();

$statement = db()->prepare(
    'SELECT n.*, o.order_code
     FROM notifications n
     LEFT JOIN orders o ON o.id = n.order_id
     WHERE n.user_id = ?
     ORDER BY n.created_at DESC, n.id DESC
     LIMIT 100'
);
$statement->execute([$user['id']]);
$notifications = $statement->fetchAll();

$unreadCount = 0;
foreach ($notifications as $notification) {
    if ((int) $notification['is_read'] === 0) {
        $unreadCount++;
    }
}

require __DIR__ . '/includes/header.php';
?>

<main class="page-main narrow">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= e(url('index.php')) ?>">Home</a>
        <span>/</span>
        <a href="<?= e(url('account.php')) ?>">Account</a>
        <span>/</span>
        <span>Notifications</span>
    </nav>

    <div class="page-heading">
        <p class="eyebrow">Updates</p>
        <h1>Notifications</h1>
        <p>Status updates about your Bloomify orders appear here.</p>
    </div>

    <?php if ($unreadCount > 0): ?>
        <div class="form-actions" style="margin-bottom: 18px;">
            <form method="post" action="<?= e(url('actions/notifications/read.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="mark_all" value="1">
                <button type="submit" class="btn btn-soft btn-sm">
                    Mark all as read (<?= $unreadCount ?>)
                </button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($notifications): ?>
        <div class="notification-list">
            <?php foreach ($notifications as $notification): ?>
                <?php $isUnread = (int) $notification['is_read'] === 0; ?>
                <article class="notification-item <?= $isUnread ? 'unread' : '' ?>">
                    <div>
                        <p class="notification-title"><?= e($notification['title']) ?></p>
                        <p class="notification-message"><?= e($notification['message']) ?></p>
                        <p class="notification-meta"><?= e(format_datetime($notification['created_at'])) ?></p>

                        <?php if ($notification['order_id'] && $notification['order_code']): ?>
                            <a class="text-link"
                               style="font-size: 12px;"
                               href="<?= e(url('order-detail.php?code=' . rawurlencode($notification['order_code']))) ?>">
                                View order <?= e($notification['order_code']) ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($isUnread): ?>
                        <form method="post" action="<?= e(url('actions/notifications/read.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm">Mark as read</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-regular fa-bell"></i>
            <h2>No notifications yet</h2>
            <p>We will let you know here when your order status changes.</p>
            <a href="<?= e(url('orders.php')) ?>" class="btn btn-primary">View My Orders</a>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
