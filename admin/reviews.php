<?php
/**
 * Bloomify - admin review moderation.
 */

require_once __DIR__ . '/includes/auth.php';

$adminTitle = 'Reviews';
$activeAdmin = 'reviews';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $reviewId = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $review = db()->prepare(
            'SELECT r.id, p.name AS product_name
             FROM product_reviews r
             JOIN products p ON p.id = r.product_id
             WHERE r.id = ?
             LIMIT 1'
        );
        $review->execute([$reviewId]);
        $found = $review->fetch();

        if (!$found) {
            flash_set('error', 'That review no longer exists.');
        } else {
            $delete = db()->prepare('DELETE FROM product_reviews WHERE id = ?');
            $delete->execute([$reviewId]);
            flash_set('success', 'Review for ' . $found['product_name'] . ' was removed. The product rating has been recalculated.');
        }
    } else {
        flash_set('error', 'Unknown action.');
    }

    redirect('admin/reviews.php');
}

$summary = db()->query(
    'SELECT COUNT(*) AS total_reviews, COALESCE(AVG(rating), 0) AS average_rating
     FROM product_reviews'
)->fetch();

$sql = 'SELECT r.*, p.name AS product_name, p.slug AS product_slug,
               u.full_name AS customer_name, u.username AS customer_username,
               o.order_code
        FROM product_reviews r
        JOIN products p ON p.id = r.product_id
        JOIN users u ON u.id = r.user_id
        JOIN orders o ON o.id = r.order_id
        ORDER BY r.created_at DESC, r.id DESC
        LIMIT 300';

$reviews = db()->query($sql)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Customer Reviews</h2>
            <p>
                <?= (int) $summary['total_reviews'] ?> review(s) &middot;
                average rating <?= number_format((float) $summary['average_rating'], 2) ?>/5
            </p>
        </div>
        <a class="btn btn-outline btn-sm" href="<?= e(url('admin/products.php')) ?>">Products</a>
    </div>

    <?php if ($reviews): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Customer</th>
                        <th>Order</th>
                        <th>Rating</th>
                        <th>Review</th>
                        <th>Date</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $review): ?>
                        <?php $stars = max(1, min(5, (int) $review['rating'])); ?>
                        <tr>
                            <td>
                                <div class="cell-strong"><?= e($review['product_name']) ?></div>
                                <div class="cell-muted">#<?= (int) $review['product_id'] ?></div>
                            </td>
                            <td>
                                <div><?= e($review['customer_name']) ?></div>
                                <div class="cell-muted">@<?= e($review['customer_username']) ?></div>
                            </td>
                            <td>
                                <a class="text-link" href="<?= e(url('admin/order-detail.php?code=' . rawurlencode($review['order_code']))) ?>">
                                    <?= e($review['order_code']) ?>
                                </a>
                            </td>
                            <td>
                                <span class="admin-review-stars" aria-label="<?= $stars ?> out of 5">
                                    <?= str_repeat('★', $stars) . str_repeat('☆', 5 - $stars) ?>
                                </span>
                            </td>
                            <td style="max-width: 320px;">
                                <?= nl2br(e($review['comment'])) ?>
                            </td>
                            <td>
                                <div><?= e(format_date($review['created_at'])) ?></div>
                                <?php if (strtotime($review['updated_at']) > strtotime($review['created_at'])): ?>
                                    <div class="cell-muted">Edited <?= e(format_date($review['updated_at'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <form method="post" action="<?= e(url('admin/reviews.php')) ?>"
                                      style="display:inline;"
                                      data-confirm="Delete this review? The product rating will be recalculated."
                                      data-confirm-title="Delete Review"
                                      data-confirm-label="Delete Review"
                                      data-confirm-danger="true">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-regular fa-star"></i>
            <h2>No reviews yet</h2>
            <p>Customer reviews will appear here after orders are completed.</p>
            <a class="btn btn-outline" href="<?= e(url('admin/orders.php')) ?>">View Orders</a>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
