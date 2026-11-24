<?php
/**
 * Reusable product card.
 *
 * Expects $product from get_public_products()/decorate_product().
 */

if (!isset($product) || !is_array($product)) {
    return;
}

$availabilityText = 'Available';
$availabilityClass = 'stock-available';

if ($product['is_sold_out']) {
    $availabilityText = 'Sold Out';
    $availabilityClass = 'stock-out';
} elseif (!$product['can_buy']) {
    $availabilityText = 'Low stock';
    $availabilityClass = 'stock-low';
} elseif ($product['stock'] <= 10) {
    $availabilityText = 'Only ' . (int) $product['stock'] . ' ' . $product['unit_label'] . 's left';
    $availabilityClass = 'stock-low';
}

$productUrl = url('product.php?id=' . (int) $product['id']);
?>
<article class="product-card js-open-product"
         data-product-id="<?= (int) $product['id'] ?>"
         data-product-url="<?= e($productUrl) ?>"
         tabindex="0"
         role="button"
         aria-label="View <?= e($product['name']) ?>">
    <div class="product-image">
        <img src="<?= e(public_image_url($product['image'])) ?>"
             alt="<?= e($product['name']) ?>"
             loading="lazy">

        <?php if ($product['is_sold_out']): ?>
            <span class="sold-out-overlay">Sold Out</span>
        <?php endif; ?>
    </div>

    <div class="product-info">
        <p class="product-type"><?= e($product['category_name']) ?></p>
        <h3><?= e($product['name']) ?></h3>

        <div class="product-bottom">
            <span class="price"><?= e($product['price_label']) ?></span>
            <span class="add-cart <?= $product['can_buy'] ? '' : 'is-disabled' ?>"
                  aria-hidden="true">
                <i class="fa-solid fa-plus"></i>
            </span>
        </div>

        <p class="stock-note <?= e($availabilityClass) ?>"><?= e($availabilityText) ?></p>
    </div>
</article>
