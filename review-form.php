<?php
/**
 * Bloomify - create or edit a product review.
 */

require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Write a Review';
$activeNav = 'shop';
$user = current_user();

$reviewId = (int) ($_GET['review'] ?? 0);
$orderCode = trim((string) ($_GET['order'] ?? ''));
$productId = (int) ($_GET['product'] ?? 0);

$editing = false;
$review = null;
$product = null;
$selectedRating = 0;
$commentValue = '';

if ($reviewId > 0) {
    $review = get_review_by_id($reviewId);

    if (!$review || (int) $review['user_id'] !== (int) $user['id']) {
        http_response_code(403);
        require __DIR__ . '/includes/header.php';
        ?>
        <main class="page-main narrow">
            <div class="empty-state">
                <i class="fa-solid fa-star"></i>
                <h2>Review unavailable</h2>
                <p>You can only edit reviews that you submitted yourself.</p>
                <a class="btn btn-primary" href="<?= e(url('orders.php')) ?>">Back to My Orders</a>
            </div>
        </main>
        <?php
        require __DIR__ . '/includes/footer.php';
        exit;
    }

    $editing = true;
    $productId = (int) $review['product_id'];
    $orderCode = $review['order_code'];
    $selectedRating = (int) $review['rating'];
    $commentValue = $review['comment'];
    $pageTitle = 'Edit Review';
} else {
    if ($orderCode === '' || $productId <= 0) {
        http_response_code(400);
        require __DIR__ . '/includes/header.php';
        ?>
        <main class="page-main narrow">
            <div class="empty-state">
                <i class="fa-solid fa-star"></i>
                <h2>Review unavailable</h2>
                <p>Open a completed order to choose a product to review.</p>
                <a class="btn btn-primary" href="<?= e(url('orders.php')) ?>">Back to My Orders</a>
            </div>
        </main>
        <?php
        require __DIR__ . '/includes/footer.php';
        exit;
    }

    $order = get_order_by_code($orderCode, (int) $user['id']);

    if (!$order || $order['order_status'] !== 'Selesai') {
        http_response_code(403);
        require __DIR__ . '/includes/header.php';
        ?>
        <main class="page-main narrow">
            <div class="empty-state">
                <i class="fa-solid fa-star"></i>
                <h2>Review unavailable</h2>
                <p>Only products from your completed orders can be reviewed.</p>
                <a class="btn btn-primary" href="<?= e(url('orders.php')) ?>">Back to My Orders</a>
            </div>
        </main>
        <?php
        require __DIR__ . '/includes/footer.php';
        exit;
    }

    $itemStatement = db()->prepare(
        'SELECT id FROM order_items WHERE order_id = ? AND product_id = ? LIMIT 1'
    );
    $itemStatement->execute([$order['id'], $productId]);

    if (!$itemStatement->fetch()) {
        http_response_code(403);
        require __DIR__ . '/includes/header.php';
        ?>
        <main class="page-main narrow">
            <div class="empty-state">
                <i class="fa-solid fa-star"></i>
                <h2>Review unavailable</h2>
                <p>This product is not part of that order.</p>
                <a class="btn btn-primary" href="<?= e(url('orders.php')) ?>">Back to My Orders</a>
            </div>
        </main>
        <?php
        require __DIR__ . '/includes/footer.php';
        exit;
    }

    $existingReview = get_order_product_review((int) $user['id'], (int) $order['id'], $productId);
    if ($existingReview) {
        flash_set('info', 'You already reviewed this product for that order. You can edit your existing review below.');
        redirect('review-form.php?review=' . (int) $existingReview['id']);
    }
}

$product = get_product($productId);
if (!$product) {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    ?>
    <main class="page-main narrow">
        <div class="empty-state">
            <i class="fa-solid fa-spa"></i>
            <h2>Product not found</h2>
            <p>This product is no longer available for review.</p>
            <a class="btn btn-primary" href="<?= e(url('orders.php')) ?>">Back to My Orders</a>
        </div>
    </main>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$old = flash_get('review_old');
if (is_array($old)) {
    $selectedRating = (int) ($old['rating'] ?? $selectedRating);
    $commentValue = (string) ($old['comment'] ?? $commentValue);
}

require __DIR__ . '/includes/header.php';
?>

<main class="page-main narrow">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= e(url('index.php')) ?>">Home</a>
        <span>/</span>
        <a href="<?= e(url('account.php')) ?>">Account</a>
        <span>/</span>
        <a href="<?= e(url('orders.php')) ?>">My Orders</a>
        <span>/</span>
        <span><?= $editing ? 'Edit Review' : 'Write a Review' ?></span>
    </nav>

    <div class="page-heading">
        <p class="eyebrow">Customer Review</p>
        <h1><?= $editing ? 'Edit Your Review' : 'Share Your Experience' ?></h1>
        <p>Your review helps other flower lovers choose with confidence.</p>
    </div>

    <div class="form-card review-form-card">
        <div class="review-product">
            <img src="<?= e(public_image_url($product['image'])) ?>" alt="<?= e($product['name']) ?>">
            <div>
                <strong><?= e($product['name']) ?></strong>
                <p class="form-help">Order <?= e($orderCode) ?></p>
            </div>
        </div>

        <form method="post" action="<?= e(url('actions/reviews/save.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="review_action" value="<?= $editing ? 'update' : 'create' ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
            <?php else: ?>
                <input type="hidden" name="order_code" value="<?= e($orderCode) ?>">
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <?php endif; ?>

            <fieldset class="form-field review-rating-field">
                <legend>Your rating</legend>
                <div class="star-rating" role="radiogroup" aria-label="Star rating from 1 to 5">
                    <?php for ($star = 5; $star >= 1; $star--): ?>
                        <input type="radio" id="rating-<?= $star ?>" name="rating"
                               value="<?= $star ?>" <?= $selectedRating === $star ? 'checked' : '' ?> required>
                        <label for="rating-<?= $star ?>" title="<?= $star ?> star<?= $star > 1 ? 's' : '' ?>">
                            <i class="fa-solid fa-star" aria-hidden="true"></i>
                            <span class="sr-only"><?= $star ?> star<?= $star > 1 ? 's' : '' ?></span>
                        </label>
                    <?php endfor; ?>
                </div>
                <p class="form-help">Select 1 star for a poor experience and 5 stars for an excellent one.</p>
            </fieldset>

            <div class="form-field">
                <label for="review-comment">Your review</label>
                <textarea id="review-comment" name="comment" class="form-control"
                          rows="6" minlength="5" maxlength="1000" required
                          placeholder="Tell us about the flowers, packaging, and delivery..."><?= e($commentValue) ?></textarea>
                <p class="form-help">Between 5 and 1000 characters.</p>
            </div>

            <div class="review-form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-paper-plane"></i>
                    <?= $editing ? 'Save Changes' : 'Submit Review' ?>
                </button>
                <a class="btn btn-outline" href="<?= e(url('product.php?id=' . (int) $product['id'] . '#reviews')) ?>">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
