<?php
/**
 * Bloomify - checkout form.
 */

require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Checkout';
$activeNav = 'cart';

$user = current_user();
$cart = get_cart((int) $user['id']);
$items = $cart ? get_cart_items((int) $cart['id']) : [];

if (!$items) {
    flash_set('error', 'Your cart is empty.');
    redirect('cart.php');
}

foreach ($items as $item) {
    if (!$item['product_available'] || !$item['stock_ok']) {
        flash_set('error', 'Please fix the cart warnings before checkout.');
        redirect('cart.php');
    }
}

$totals = cart_totals($items);

$checkoutErrors = flash_get('checkout_errors', []);
$checkoutOld = flash_get('checkout_old', []);

$value = function (string $key, string $fallback = '') use ($checkoutOld): string {
    return array_key_exists($key, $checkoutOld)
        ? (string) $checkoutOld[$key]
        : $fallback;
};

$fulfillment = $checkoutOld['fulfillment_method'] ?? 'delivery';
$saveProfile = !empty($checkoutOld['save_profile']);
$paymentMethods = payment_methods();
$paymentMethod = (string) ($checkoutOld['payment_method'] ?? 'COD');
if (!array_key_exists($paymentMethod, $paymentMethods)) {
    $paymentMethod = 'COD';
}
$paymentMethodData = $paymentMethods[$paymentMethod];
$paymentIsDigital = payment_method_is_digital($paymentMethod);
$proofError = (string) flash_get('checkout_proof_error', '');

if (empty($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(16));
}
$checkoutToken = $_SESSION['checkout_token'];

$deliveryFee = DELIVERY_FEE;
$grandDelivery = $totals['total'] + $deliveryFee;
$grandPickup = $totals['total'];
$storeAddress = setting('store_address');
$storeHours = setting('store_hours');

require __DIR__ . '/includes/header.php';
?>

<main class="page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= e(url('index.php')) ?>">Home</a>
        <span>/</span>
        <a href="<?= e(url('cart.php')) ?>">Cart</a>
        <span>/</span>
        <span>Checkout</span>
    </nav>

    <div class="page-heading">
        <p class="eyebrow">Almost There</p>
        <h1>Checkout</h1>
        <p>Complete your details, choose a payment method, then review and confirm your order.</p>
    </div>

    <?php if ($checkoutErrors): ?>
        <ul class="input-error-list">
            <?php foreach ($checkoutErrors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?= e(url('actions/checkout.php')) ?>"
          class="checkout-layout"
          enctype="multipart/form-data"
          id="checkoutForm"
          data-confirm="Place this order now? Please make sure your details are correct.">
        <?= csrf_field() ?>
        <input type="hidden" name="checkout_token" value="<?= e($checkoutToken) ?>">

        <div>
            <section class="form-card" style="margin-bottom: 22px;">
                <h2 class="account-section-title">Customer Information</h2>
                <p class="account-section-sub">We use this information to prepare and deliver your flowers.</p>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="recipient_name">Recipient name</label>
                        <input type="text" id="recipient_name" name="recipient_name" class="form-control"
                               value="<?= e($value('recipient_name', $user['full_name'])) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="recipient_phone">WhatsApp / phone number</label>
                        <input type="text" id="recipient_phone" name="recipient_phone" class="form-control"
                               value="<?= e($value('recipient_phone', $user['phone'])) ?>" required>
                    </div>
                </div>
            </section>

            <section class="form-card" style="margin-bottom: 22px;">
                <h2 class="account-section-title">Fulfillment Method</h2>
                <p class="account-section-sub">Delivery is handled by the Bloomify team.</p>

                <div class="fulfillment-options">
                    <label class="option-card">
                        <input type="radio" name="fulfillment_method" value="delivery"
                               <?= $fulfillment === 'delivery' ? 'checked' : '' ?>>
                        <span>
                            <strong>Delivery</strong>
                            <span>Fixed delivery fee of <?= e(format_rupiah(DELIVERY_FEE)) ?>.</span>
                        </span>
                    </label>

                    <label class="option-card">
                        <input type="radio" name="fulfillment_method" value="pickup"
                               <?= $fulfillment === 'pickup' ? 'checked' : '' ?>>
                        <span>
                            <strong>Self Pickup</strong>
                            <span>Collect your order at the Bloomify store, no delivery fee.</span>
                        </span>
                    </label>
                </div>

                <div class="hidden" data-delivery-only style="margin-top: 18px;">
                    <div class="form-group">
                        <label for="delivery_address">Delivery address</label>
                        <textarea id="delivery_address" name="delivery_address" class="form-control"
                                  data-required-delivery
                                  placeholder="Street, number, city, postal code"><?= e($value('delivery_address', $user['address'])) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="address_detail">Address details / landmark (optional)</label>
                        <input type="text" id="address_detail" name="address_detail" class="form-control"
                               maxlength="255"
                               value="<?= e($value('address_detail', '')) ?>"
                               placeholder="House color, gate, floor, etc.">
                    </div>
                </div>

                <div class="alert alert-info hidden" data-pickup-only style="margin-top: 18px;">
                    <strong>Pickup address:</strong> <?= e($storeAddress) ?><br>
                    <strong>Operating hours:</strong> <?= e($storeHours) ?>
                </div>
            </section>

            <section class="form-card">
                <h2 class="account-section-title">Order Notes</h2>
                <div class="form-group">
                    <label for="notes">Additional notes (optional)</label>
                    <textarea id="notes" name="notes" class="form-control" maxlength="1000"
                              placeholder="Greeting card message, delivery instructions, etc."><?= e($value('notes', '')) ?></textarea>
                </div>

                <div class="checkbox-row">
                    <input type="checkbox" id="save_profile" name="save_profile" value="1"
                           <?= $saveProfile ? 'checked' : '' ?>>
                    <label for="save_profile">
                        Save my phone number and delivery address to my profile.
                    </label>
                </div>

                <h2 class="account-section-title" style="margin-top: 24px;">Payment Method</h2>
                <p class="account-section-sub">Choose how you would like to pay for this order.</p>

                <div class="payment-methods">
                    <?php foreach ($paymentMethods as $methodKey => $method): ?>
                        <label class="payment-option <?= $method['type'] === 'digital' ? 'payment-option-digital' : '' ?>">
                            <input type="radio" name="payment_method" value="<?= e($methodKey) ?>"
                                data-payment-type="<?= e($method['type']) ?>"
                                data-payment-label="<?= e($method['label']) ?>"
                                data-payment-name="<?= e($method['name']) ?>"
                                data-payment-number="<?= e($method['number'] !== '' ? $method['number'] : 'Not configured yet') ?>"
                                data-payment-holder="<?= e($method['holder'] !== '' ? $method['holder'] : '-') ?>"
                                data-payment-description="<?= e($method['description']) ?>"
                                <?= $paymentMethod === $methodKey ? 'checked' : '' ?> required>

                            <span class="payment-option-body">
                                <span class="payment-option-head">
                                    <strong><?= e($method['label']) ?></strong>
                                    <span><?= e($method['name']) ?></span>
                                </span>
                                <span class="payment-option-description"><?= e($method['description']) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="payment-digital-panel <?= $paymentIsDigital ? '' : 'hidden' ?>" data-payment-digital-panel>
                    <div class="alert alert-info" style="margin: 16px 0;">
                        <p style="margin: 0 0 6px;">
                            <strong>Pay with <span data-payment-label><?= e($paymentMethodData['label']) ?></span></strong>
                        </p>
                        <p style="margin: 0;" data-payment-description><?= e($paymentMethodData['description']) ?></p>
                        <ul class="info-list" style="margin-top: 8px;">
                            <li>
                                <span class="label">Account number</span>
                                <strong data-payment-number><?= e($paymentMethodData['number'] !== '' ? $paymentMethodData['number'] : 'Not configured yet') ?></strong>
                            </li>
                            <li>
                                <span class="label">Account name</span>
                                <span data-payment-holder><?= e($paymentMethodData['holder'] !== '' ? $paymentMethodData['holder'] : '-') ?></span>
                            </li>
                        </ul>
                        <p class="form-help" style="margin-top: 8px;">
                            Transfer the exact grand total, then upload the payment proof below before placing the order.
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="payment_proof">Upload Payment Proof <span aria-hidden="true">*</span></label>
                        <input type="file" id="payment_proof" name="payment_proof" class="form-control"
                               accept="image/jpeg,image/png,image/webp"
                               <?= $paymentIsDigital ? 'required' : '' ?>>
                        <p class="form-help">JPG, PNG, or WEBP. Maximum 2 MB.</p>
                        <p class="payment-rejection <?= $proofError === '' ? 'hidden' : '' ?>"
                           data-payment-proof-warning role="alert">
                            <?= e($proofError !== '' ? $proofError : 'Please upload your payment proof before placing the order.') ?>
                        </p>
                    </div>
                </div>

                <p class="form-help <?= $paymentIsDigital ? 'hidden' : '' ?>" data-payment-cod-note style="margin-top: 10px;">
                    <?= e($paymentMethods['COD']['description']) ?>
                </p>

                <div class="checkbox-row" style="margin-top: 20px;">
                    <input type="checkbox" id="confirm_order" name="confirm_order" value="1" required>
                    <label for="confirm_order">
                        I have reviewed my order and confirm it is correct.
                    </label>
                </div>
            </section>
        </div>

        <aside class="checkout-summary">
            <h2>Order Summary</h2>

            <?php foreach ($items as $item): ?>
                <div class="summary-item">
                    <strong><?= e($item['product_name']) ?></strong>
                    <?php if ($item['item_type'] === 'fixed'): ?>
                        <?= (int) $item['units'] ?> item(s)
                    <?php else: ?>
                        <?php
                        $parts = [];
                        foreach ($item['details'] as $detail) {
                            $parts[] = $detail['flower_type_name'] . ' x' . (int) $detail['stem_quantity'];
                        }
                        echo e(implode(', ', $parts));
                        ?>
                    <?php endif; ?>
                    <div class="summary-line" style="margin: 6px 0 0;">
                        <span>Subtotal</span>
                        <span><?= e(format_rupiah($item['item_subtotal'])) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="summary-line" style="margin-top: 16px;">
                <span>Product subtotal</span>
                <span><?= e(format_rupiah($totals['product_subtotal'])) ?></span>
            </div>

            <div class="summary-line">
                <span>Wrapping fees</span>
                <span><?= e(format_rupiah($totals['wrapping_total'])) ?></span>
            </div>

            <div class="summary-line">
                <span>Delivery fee</span>
                <span data-delivery-fee="<?= e(format_rupiah(DELIVERY_FEE)) ?>"><?= e(format_rupiah(DELIVERY_FEE)) ?></span>
            </div>

            <div class="summary-line total">
                <span>Grand total</span>
                <span data-grand-total
                      data-delivery="<?= e(format_rupiah($grandDelivery)) ?>"
                      data-pickup="<?= e(format_rupiah($grandPickup)) ?>">
                    <?= e(format_rupiah($grandDelivery)) ?>
                </span>
            </div>

            <p class="form-help" style="margin-top: 12px;">
                Delivery fee is Rp0 for self pickup. Digital payment proof is verified by our admin team.
            </p>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 16px;">
                Place Order
            </button>

            <a href="<?= e(url('cart.php')) ?>" class="btn btn-soft btn-block" style="margin-top: 10px;">
                Back to Cart
            </a>
        </aside>
    </form>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
