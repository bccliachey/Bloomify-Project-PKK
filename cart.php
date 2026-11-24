<?php
/**
 * Bloomify - shopping cart (logged-in customers only).
 */

require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'My Cart';
$activeNav = 'cart';
$bodyClass = 'cart-page';

$user = current_user();
$cart = get_cart((int) $user['id']);
$items = $cart ? get_cart_items((int) $cart['id']) : [];
$totals = cart_totals($items);

$canCheckout = !empty($items);
foreach ($items as $item) {
    if (!$item['product_available'] || !$item['stock_ok']) {
        $canCheckout = false;
    }
}

require __DIR__ . '/includes/header.php';
?>

<main class="page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= e(url('index.php')) ?>">Home</a>
        <span>/</span>
        <span>Cart</span>
    </nav>

    <div class="page-heading">
        <p class="eyebrow">Your Selection</p>
        <h1>Shopping Cart</h1>
        <p>Review your flowers, adjust quantities, and continue to checkout.</p>
    </div>

    <?php if (!$items): ?>
        <div class="empty-state">
            <i class="fa-solid fa-bag-shopping"></i>
            <h2>Your cart is empty</h2>
            <p>Let's find the perfect flowers for your moment.</p>
            <a href="<?= e(url('catalog.php')) ?>" class="btn btn-primary">
                Explore Products
            </a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <div class="cart-list">
                <?php foreach ($items as $item): ?>
                    <?php
                    $itemEditDisabled = !$item['product_available'];
                    $typeLabel = $item['category_name'];
                    ?>
                    <article class="cart-item">
                        <div class="cart-item-image">
                            <img src="<?= e(public_image_url($item['product_image'])) ?>"
                                 alt="<?= e($item['product_name']) ?>">
                        </div>

                        <div>
                            <div class="cart-item-head">
                                <div>
                                    <p class="cart-item-type"><?= e($typeLabel) ?></p>
                                    <h2 class="cart-item-title"><?= e($item['product_name']) ?></h2>
                                </div>
                                <div class="cart-item-total"><?= e(format_rupiah($item['item_subtotal'])) ?></div>
                            </div>

                            <div class="cart-item-meta">
                                <?php if ($item['item_type'] === 'fixed'): ?>
                                    <?= (int) $item['units'] ?> item(s)
                                    &times; <?= e(format_rupiah($item['fixed_price'])) ?>
                                <?php else: ?>
                                    <?php foreach ($item['details'] as $detail): ?>
                                        <div class="cart-item-detail-row">
                                            <span>
                                                <?= e($detail['flower_type_name']) ?>
                                                &times; <?= (int) $detail['stem_quantity'] ?> stems
                                                (<?= e(format_rupiah($detail['price_per_stem'])) ?>/stem)
                                            </span>
                                            <span><?= e(format_rupiah((float) $detail['price_per_stem'] * (int) $detail['stem_quantity'])) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="cart-item-detail-row">
                                        <span>Total stems</span>
                                        <span><?= (int) $item['stems'] ?></span>
                                    </div>
                                    <div class="cart-item-detail-row">
                                        <span>Wrapping fee</span>
                                        <span><?= e(format_rupiah($item['wrapping_fee'])) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!$item['product_available']): ?>
                                <p class="cart-warning">
                                    This product is no longer available. Please remove it from your cart.
                                </p>
                            <?php elseif (!$item['stock_ok']): ?>
                                <p class="cart-warning">
                                    The requested quantity exceeds current stock. Please adjust it before checkout.
                                </p>
                            <?php endif; ?>

                            <form method="post" action="<?= e(url('actions/cart/update.php')) ?>"
                                  class="cart-edit-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="cart_item_id" value="<?= (int) $item['id'] ?>">

                                <?php if ($item['item_type'] === 'fixed'): ?>
                                    <div class="cart-edit-row">
                                        <label for="qty-<?= (int) $item['id'] ?>">Boxes</label>
                                        <input type="number"
                                               class="qty-input"
                                               id="qty-<?= (int) $item['id'] ?>"
                                               name="quantity"
                                               value="<?= (int) $item['units'] ?>"
                                               min="1"
                                               max="<?= (int) ($item['product_stock'] ?? 0) ?>"
                                               <?= $itemEditDisabled ? 'disabled' : '' ?>>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($item['details'] as $detail): ?>
                                        <div class="cart-edit-row">
                                            <label for="flower-<?= (int) $item['id'] ?>-<?= (int) $detail['flower_type_id'] ?>">
                                                <?= e($detail['flower_type_name']) ?>
                                                <span class="form-help">(<?= (int) $detail['stock'] ?> in stock)</span>
                                            </label>
                                            <input type="number"
                                                   class="qty-input"
                                                   id="flower-<?= (int) $item['id'] ?>-<?= (int) $detail['flower_type_id'] ?>"
                                                   name="flowers[<?= (int) $detail['flower_type_id'] ?>]"
                                                   value="<?= (int) $detail['stem_quantity'] ?>"
                                                   min="0"
                                                   max="<?= (int) $detail['stock'] ?>"
                                                   <?= $itemEditDisabled ? 'disabled' : '' ?>>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <div class="form-actions">
                                    <button type="submit" class="btn btn-soft btn-sm"
                                            <?= $itemEditDisabled ? 'disabled' : '' ?>>
                                        Update item
                                    </button>
                                </div>
                            </form>

                            <form method="post" action="<?= e(url('actions/cart/remove.php')) ?>"
                                  class="form-actions"
                                  data-confirm="Remove <?= e($item['product_name']) ?> from your cart?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="cart_item_id" value="<?= (int) $item['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm">Remove</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>

                <div class="form-actions">
                    <a href="<?= e(url('catalog.php')) ?>" class="btn btn-soft">
                        Continue shopping
                    </a>
                </div>
            </div>

            <aside class="cart-summary">
                <h2>Cart Summary</h2>

                <div class="summary-line">
                    <span>Product subtotal</span>
                    <span><?= e(format_rupiah($totals['product_subtotal'])) ?></span>
                </div>

                <div class="summary-line">
                    <span>Wrapping fees</span>
                    <span><?= e(format_rupiah($totals['wrapping_total'])) ?></span>
                </div>

                <div class="summary-line total">
                    <span>Cart total</span>
                    <span><?= e(format_rupiah($totals['total'])) ?></span>
                </div>

                <p class="form-help" style="margin-top: 12px;">
                    Delivery fee is added at checkout. Fixed-price items have no wrapping fee.
                </p>

                <?php if ($canCheckout): ?>
                    <a href="<?= e(url('checkout.php')) ?>" class="btn btn-primary btn-block">
                        Proceed to Checkout
                    </a>
                <?php else: ?>
                    <button type="button" class="btn btn-primary btn-block" disabled>
                        Proceed to Checkout
                    </button>
                    <p class="form-help" style="margin-top: 12px;">
                        Please fix the warnings above before checking out.
                    </p>
                <?php endif; ?>
            </aside>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
