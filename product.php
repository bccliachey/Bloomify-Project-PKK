<?php
/**
 * Bloomify - full product detail page with a server-rendered add-to-cart form.
 */

require_once __DIR__ . '/includes/functions.php';

$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$product = get_visible_product($productId);

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product Not Found';
    $activeNav = 'shop';
    require __DIR__ . '/includes/header.php';
    ?>
    <main class="page-main narrow">
        <div class="empty-state">
            <i class="fa-solid fa-spa"></i>
            <h2>Product not found</h2>
            <p>This flower may have been removed or is no longer available.</p>
            <a href="<?= e(url('catalog.php')) ?>" class="btn btn-primary">Back to shop</a>
        </div>
    </main>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $product['name'];
$activeNav = 'shop';

$flowers = $product['pricing_mode'] === 'fixed' ? [] : get_flower_types_for_product((int) $product['id']);
$reviewSummary = product_review_summary((int) $product['id']);
$reviews = get_product_reviews((int) $product['id']);
$reviewableOrder = is_logged_in()
    ? find_reviewable_order_item((int) current_user()['id'], (int) $product['id'])
    : null;

$stockClass = 'out';
$stockText = 'Sold Out';
if (!$product['is_sold_out']) {
    $stockClass = $product['can_buy'] ? 'available' : 'low';
    $stockText = $product['can_buy']
        ? 'Available — ' . (int) $product['stock'] . ' ' . $product['unit_label'] . '(s) in stock'
        : 'Not enough stock for the minimum order';
}

require __DIR__ . '/includes/header.php';
?>

<main class="page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= e(url('index.php')) ?>">Home</a>
        <span>/</span>
        <a href="<?= e(url('catalog.php')) ?>">Shop</a>
        <span>/</span>
        <a href="<?= e(url('catalog.php?category=' . $product['category_slug'])) ?>">
            <?= e($product['category_name']) ?>
        </a>
        <span>/</span>
        <span><?= e($product['name']) ?></span>
    </nav>

    <div class="product-detail-page">
        <div class="detail-image">
            <img src="<?= e(public_image_url($product['image'])) ?>"
                 alt="<?= e($product['name']) ?>">
        </div>

        <div class="detail-content">
            <p class="detail-type"><?= e($product['category_name']) ?></p>
            <h2><?= e($product['name']) ?></h2>
            <p class="detail-description"><?= e($product['description']) ?></p>
            <p class="detail-price"><?= e($product['price_label']) ?></p>

            <span class="stock-pill <?= e($stockClass) ?>"><?= e($stockText) ?></span>

            <form method="post" action="<?= e(url('actions/cart/add.php')) ?>" id="productDetailForm"
                  data-min-stems="<?= MIN_STEMS ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <input type="hidden" name="return_to" value="<?= e('product.php?id=' . (int) $product['id']) ?>">

                <?php if ($product['pricing_mode'] === 'fixed'): ?>
                    <h3>Quantity</h3>

                    <div class="flower-row">
                        <span>
                            <?= e($product['name']) ?> —
                            <?= e(format_rupiah($product['fixed_price'])) ?>/item
                            <br>
                            <small class="quantity-note">
                                <?= (int) $product['stock'] ?> item(s) in stock. Ready-made, no wrapping fee.
                            </small>
                        </span>
                        <div class="quantity">
                            <input type="number"
                                   class="qty-input detail-qty"
                                   name="quantity"
                                   value="0"
                                   min="0"
                                   max="<?= (int) $product['stock'] ?>"
                                   data-price="<?= e($product['fixed_price']) ?>"
                                   data-mode="fixed"
                                   aria-label="Number of boxes">
                        </div>
                    </div>
                <?php else: ?>
                    <h3>Choose Your Flowers</h3>

                    <?php foreach ($flowers as $flower): ?>
                        <div class="flower-row">
                            <span>
                                <?= e($flower['name']) ?><?= !empty($flower['color']) ? ' — ' . e($flower['color']) : '' ?> —
                                <?= e(format_rupiah($flower['price_per_stem'])) ?>/stem
                                <br>
                                <small class="quantity-note">
                                    <?= (int) $flower['stock'] ?> stems in stock
                                </small>
                            </span>
                            <div class="quantity">
                                <input type="number"
                                       class="qty-input detail-qty"
                                       name="flowers[<?= (int) $flower['id'] ?>]"
                                       value="0"
                                       min="0"
                                       max="<?= (int) $flower['stock'] ?>"
                                       data-price="<?= e($flower['price_per_stem']) ?>"
                                       data-mode="variable"
                                       aria-label="Number of <?= e($flower['name']) ?> stems">
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <p class="minimum">
                    <?php if ($product['pricing_mode'] === 'fixed'): ?>
                        Ready-made product. Fixed price, no wrapping fee.
                    <?php else: ?>
                        Minimum <?= MIN_STEMS ?> stems in total.
                        Wrapping is free for the first 5 stems, then Rp10.000 per additional 5 stems.
                    <?php endif; ?>
                </p>

                <div class="detail-summary" id="detailSummary">
                    <div class="summary-line">
                        <span>Subtotal</span>
                        <strong id="detailSubtotal">Rp0</strong>
                    </div>
                    <div class="summary-line" id="wrappingRow">
                        <span>Wrapping fee</span>
                        <strong id="detailWrapping">Rp0</strong>
                    </div>
                    <div class="summary-line" id="stemsRow">
                        <span>Total stems</span>
                        <strong id="detailStems">0</strong>
                    </div>
                </div>

                <div class="detail-bottom">
                    <strong id="detailTotal">Rp0</strong>
                    <button type="submit"
                            class="add-to-cart"
                            id="detailAddButton"
                            <?= $product['can_buy'] ? '' : 'disabled' ?>>
                        <?= $product['can_buy'] ? 'Add to Cart' : 'Sold Out' ?>
                    </button>
                </div>

                <p class="cart-message" id="detailMessage" role="status"></p>

                <?php if (!is_logged_in()): ?>
                    <p class="form-help">
                        <a class="text-link" href="<?= e(url('login.php?redirect=' . rawurlencode('product.php?id=' . (int) $product['id']))) ?>">
                            Login
                        </a>
                        to add this product to your cart.
                    </p>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <section class="reviews-section" id="reviews">
        <div class="reviews-head">
            <div>
                <p class="eyebrow">Customer Reviews</p>
                <h2>What Bloomify customers say</h2>
            </div>
            <?php
            $averageStars = (int) round($reviewSummary['average']);
            $averageStars = max(0, min(5, $averageStars));
            ?>
            <div class="rating-summary" aria-label="Average rating <?= number_format($reviewSummary['average'], 1) ?> out of 5">
                <strong><?= number_format($reviewSummary['average'], 1) ?></strong>
                <span class="review-stars" aria-hidden="true">
                    <?= str_repeat('★', $averageStars) . str_repeat('☆', 5 - $averageStars) ?>
                </span>
                <span class="form-help"><?= (int) $reviewSummary['count'] ?> review<?= (int) $reviewSummary['count'] === 1 ? '' : 's' ?></span>
            </div>
        </div>

        <?php if ($reviewableOrder): ?>
            <div class="review-cta">
                <div>
                    <strong>Purchased this bouquet?</strong>
                    <p class="form-help">You can share your experience from your completed order.</p>
                </div>
                <a class="btn btn-primary"
                   href="<?= e(url('review-form.php?order=' . rawurlencode($reviewableOrder['order_code']) . '&product=' . (int) $product['id'])) ?>">
                    <i class="fa-regular fa-star"></i> Write a Review
                </a>
            </div>
        <?php endif; ?>

        <?php if (!$reviews): ?>
            <div class="reviews-empty">
                <i class="fa-regular fa-comment-dots" aria-hidden="true"></i>
                <p>No reviews yet. Be the first to share your experience after receiving your order.</p>
            </div>
        <?php else: ?>
            <div class="reviews-list">
                <?php foreach ($reviews as $review): ?>
                    <?php
                    $reviewStars = max(1, min(5, (int) $review['rating']));
                    $isOwnReview = is_logged_in() && (int) $review['user_id'] === (int) current_user()['id'];
                    ?>
                    <article class="review-item">
                        <div class="review-top">
                            <div class="review-author">
                                <span class="review-avatar" aria-hidden="true">
                                    <?= e(mb_strtoupper(mb_substr($review['customer_name'], 0, 1))) ?>
                                </span>
                                <div>
                                    <strong><?= e($review['customer_name']) ?></strong>
                                    <span class="form-help">
                                        @<?= e($review['customer_username']) ?> &middot; <?= e(format_date($review['created_at'])) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="review-rating">
                                <span class="review-stars" aria-hidden="true">
                                    <?= str_repeat('★', $reviewStars) . str_repeat('☆', 5 - $reviewStars) ?>
                                </span>
                                <span class="review-score"><?= $reviewStars ?>/5</span>
                            </div>
                        </div>
                        <p class="review-text"><?= nl2br(e($review['comment'])) ?></p>
                        <?php if (strtotime($review['updated_at']) > strtotime($review['created_at'])): ?>
                            <p class="form-help review-edited">Edited</p>
                        <?php endif; ?>
                        <?php if ($isOwnReview): ?>
                            <a class="btn btn-outline btn-sm"
                               href="<?= e(url('review-form.php?review=' . (int) $review['id'])) ?>">
                                <i class="fa-regular fa-pen-to-square"></i> Edit Review
                            </a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php $detailScript = __DIR__ . '/assets/js/product-detail.js'; ?>
<script src="<?= e(url('assets/js/product-detail.js') . '?v=' . filemtime($detailScript)) ?>"></script>

<?php
require __DIR__ . '/includes/footer.php';
