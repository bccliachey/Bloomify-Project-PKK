<?php
/**
 * Bloomify - store settings.
 */

require_once __DIR__ . '/includes/auth.php';

$adminTitle = 'Store Settings';
$activeAdmin = 'settings';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();

    $storeName = trim((string) ($_POST['store_name'] ?? ''));
    $storeAddress = trim((string) ($_POST['store_address'] ?? ''));
    $storeWhatsapp = trim((string) ($_POST['store_whatsapp'] ?? ''));
    $storeHours = trim((string) ($_POST['store_hours'] ?? ''));
    $storeInfo = trim((string) ($_POST['store_info'] ?? ''));
    $paymentSettings = [
        'payment_cod_note'         => trim((string) ($_POST['payment_cod_note'] ?? '')),
        'payment_dana_number'      => trim((string) ($_POST['payment_dana_number'] ?? '')),
        'payment_dana_name'        => trim((string) ($_POST['payment_dana_name'] ?? '')),
        'payment_gopay_number'     => trim((string) ($_POST['payment_gopay_number'] ?? '')),
        'payment_gopay_name'       => trim((string) ($_POST['payment_gopay_name'] ?? '')),
        'payment_ovo_number'       => trim((string) ($_POST['payment_ovo_number'] ?? '')),
        'payment_ovo_name'         => trim((string) ($_POST['payment_ovo_name'] ?? '')),
        'payment_shopeepay_number' => trim((string) ($_POST['payment_shopeepay_number'] ?? '')),
        'payment_shopeepay_name'   => trim((string) ($_POST['payment_shopeepay_name'] ?? '')),
        'payment_bank_bri_account' => trim((string) ($_POST['payment_bank_bri_account'] ?? '')),
        'payment_bank_bri_holder'  => trim((string) ($_POST['payment_bank_bri_holder'] ?? '')),
    ];
    $removeLogo = isset($_POST['remove_logo']);
    $errors = [];

    if ($storeName === '' || mb_strlen($storeName) > 100) {
        $errors[] = 'Store name is required (maximum 100 characters).';
    }

    if ($storeAddress === '' || mb_strlen($storeAddress) > 500) {
        $errors[] = 'Store address is required (maximum 500 characters).';
    }

    if ($storeWhatsapp === '' || mb_strlen($storeWhatsapp) > 30) {
        $errors[] = 'WhatsApp number is required (maximum 30 characters).';
    }

    if ($storeHours === '' || mb_strlen($storeHours) > 100) {
        $errors[] = 'Store hours are required (maximum 100 characters).';
    }

    if (mb_strlen($storeInfo) > 500) {
        $errors[] = 'Store information is too long (maximum 500 characters).';
    }

    foreach ($paymentSettings as $paymentKey => $paymentValue) {
        $limit = $paymentKey === 'payment_cod_note' ? 500 : 100;
        if (mb_strlen($paymentValue) > $limit) {
            $errors[] = str_replace('_', ' ', $paymentKey) . ' is too long (maximum ' . $limit . ' characters).';
        }
    }

    if (!array_key_exists('payment_cod_note', $paymentSettings) || $paymentSettings['payment_cod_note'] === '') {
        $paymentSettings['payment_cod_note'] = 'Pay in cash when your order is delivered or collected at the store.';
    }

    $logo = setting('store_logo', 'assets/images/Flower.png');

    if ($removeLogo) {
        $logo = 'assets/images/Flower.png';
    }

    if (isset($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $logo = save_uploaded_image($_FILES['logo'], 'settings', 'logo');
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    if ($errors) {
        flash_set('error', implode(' ', $errors));
        redirect('admin/settings.php');
    }

    try {
        db()->beginTransaction();

        $upsert = db()->prepare(
            'INSERT INTO store_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );

        $upsert->execute(['store_name', $storeName]);
        $upsert->execute(['store_address', $storeAddress]);
        $upsert->execute(['store_whatsapp', $storeWhatsapp]);
        $upsert->execute(['store_hours', $storeHours]);
        $upsert->execute(['store_info', $storeInfo]);
        $upsert->execute(['store_logo', $logo]);

        foreach ($paymentSettings as $paymentKey => $paymentValue) {
            $upsert->execute([$paymentKey, $paymentValue]);
        }

        db()->commit();
        flash_set('success', 'Store settings saved.');
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        flash_set('error', 'The settings could not be saved.');
    }

    redirect('admin/settings.php');
}

$logoPath = setting('store_logo', 'assets/images/Flower.png');

require __DIR__ . '/includes/header.php';
?>

<div class="grid-2">
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Store Information</h2>
                <p>These details appear on the storefront and in order summaries.</p>
            </div>
        </div>

        <form method="post" action="<?= e(url('admin/settings.php')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-field">
                    <label for="store_name">Store name</label>
                    <input type="text" id="store_name" name="store_name" class="form-control"
                           value="<?= e(setting('store_name')) ?>" maxlength="100" required>
                </div>

                <div class="form-field">
                    <label for="store_whatsapp">WhatsApp number</label>
                    <input type="text" id="store_whatsapp" name="store_whatsapp" class="form-control"
                           value="<?= e(setting('store_whatsapp')) ?>" maxlength="30" required>
                </div>

                <div class="form-field full">
                    <label for="store_address">Store address</label>
                    <textarea id="store_address" name="store_address" class="form-control"
                              maxlength="500" required><?= e(setting('store_address')) ?></textarea>
                </div>

                <div class="form-field full">
                    <label for="store_hours">Opening hours</label>
                    <input type="text" id="store_hours" name="store_hours" class="form-control"
                           value="<?= e(setting('store_hours')) ?>" maxlength="100" required>
                </div>

                <div class="form-field full">
                    <label for="store_info">Store information</label>
                    <textarea id="store_info" name="store_info" class="form-control"
                              maxlength="500"><?= e(setting('store_info')) ?></textarea>
                    <span class="form-help">Short note shown on the storefront, such as custom request info.</span>
                </div>

                <div class="form-field full">
                    <h3 style="margin: 4px 0 0;">Payment Details</h3>
                    <span class="form-help">These details are shown to customers at checkout and on their order pages.</span>
                </div>

                <div class="form-field full">
                    <label for="payment_cod_note">COD note</label>
                    <textarea id="payment_cod_note" name="payment_cod_note" class="form-control"
                              maxlength="500"><?= e(setting('payment_cod_note')) ?></textarea>
                </div>

                <div class="form-field">
                    <label for="payment_dana_number">DANA number</label>
                    <input type="text" id="payment_dana_number" name="payment_dana_number" class="form-control"
                           maxlength="100" value="<?= e(setting('payment_dana_number')) ?>">
                </div>

                <div class="form-field">
                    <label for="payment_dana_name">DANA account name</label>
                    <input type="text" id="payment_dana_name" name="payment_dana_name" class="form-control"
                           maxlength="100" value="<?= e(setting('payment_dana_name')) ?>">
                </div>

                <div class="form-field">
                    <label for="payment_gopay_number">GoPay number</label>
                    <input type="text" id="payment_gopay_number" name="payment_gopay_number" class="form-control"
                           maxlength="100" value="<?= e(setting('payment_gopay_number')) ?>">
                </div>

                <div class="form-field">
                    <label for="payment_gopay_name">GoPay account name</label>
                    <input type="text" id="payment_gopay_name" name="payment_gopay_name" class="form-control"
                           maxlength="100" value="<?= e(setting('payment_gopay_name')) ?>">
                </div>

                <div class="form-field">
                    <label for="payment_ovo_number">OVO number</label>
                    <input type="text" id="payment_ovo_number" name="payment_ovo_number" class="form-control"
                           maxlength="100" value="<?= e(setting('payment_ovo_number')) ?>">
                </div>

                <div class="form-field">
                    <label for="payment_ovo_name">OVO account name</label>
                    <input type="text" id="payment_ovo_name" name="payment_ovo_name" class="form-control"
                           maxlength="100" value="<?= e(setting('payment_ovo_name')) ?>">
                </div>

                <div class="form-field">
                    <label for="payment_shopeepay_number">ShopeePay number</label>
                    <input type="text" id="payment_shopeepay_number" name="payment_shopeepay_number" class="form-control"
                           maxlength="100" value="<?= e(setting('payment_shopeepay_number')) ?>">
                </div>

                <div class="form-field">
                    <label for="payment_shopeepay_name">ShopeePay account name</label>
                    <input type="text" id="payment_shopeepay_name" name="payment_shopeepay_name" class="form-control"
                           maxlength="100" value="<?= e(setting('payment_shopeepay_name')) ?>">
                </div>

                <div class="form-field">
                    <label for="payment_bank_bri_account">Bank BRI account number</label>
                    <input type="text" id="payment_bank_bri_account" name="payment_bank_bri_account" class="form-control"
                           maxlength="100" value="<?= e(setting('payment_bank_bri_account')) ?>">
                </div>

                <div class="form-field">
                    <label for="payment_bank_bri_holder">Bank BRI account holder</label>
                    <input type="text" id="payment_bank_bri_holder" name="payment_bank_bri_holder" class="form-control"
                           maxlength="100" value="<?= e(setting('payment_bank_bri_holder')) ?>">
                </div>

                <div class="form-field">
                    <label for="logo">Store logo</label>
                    <input type="file" id="logo" name="logo" class="form-control"
                           accept="image/jpeg,image/png,image/webp" data-image-input="logoPreview">
                    <span class="form-help">JPG, PNG, or WEBP. Maximum 2 MB.</span>

                    <label class="checkbox-row" style="margin-top: 8px;">
                        <input type="checkbox" name="remove_logo" value="1">
                        Reset to the default logo
                    </label>
                </div>

                <div class="form-field">
                    <label>Logo preview</label>
                    <img id="logoPreview" class="image-preview"
                         src="<?= e(public_image_url($logoPath)) ?>" alt="Store logo">
                </div>

                <div class="form-field full">
                    <div class="alert alert-info" style="margin: 0;">
                        Delivery fee is fixed at <strong><?= e(format_rupiah(DELIVERY_FEE)) ?></strong>
                        and wrapping fees follow the per-stem rules. They are applied automatically at checkout.
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2>Preview</h2>
        </div>

        <div style="background: var(--ivory); border-radius: 10px; padding: 22px;">
            <p style="font-family: var(--font-script); font-size: 38px; line-height: 1; color: var(--maroon);">
                <?= e(setting('store_name')) ?>
            </p>
            <p class="form-help" style="margin-top: 6px;"><?= e(setting('store_hours')) ?></p>

            <hr style="border: none; border-top: 1px solid var(--line); margin: 14px 0;">

            <p style="font-size: 12px; color: var(--text);">
                <?= e(setting('store_address')) ?>
            </p>
            <p style="font-size: 12px; color: var(--text); margin-top: 8px;">
                <i class="fa-brands fa-whatsapp"></i>
                <?= e(setting('store_whatsapp')) ?>
            </p>
            <?php if (setting('store_info') !== ''): ?>
                <p class="form-help" style="margin-top: 10px;">
                    <?= e(setting('store_info')) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="alert alert-info" style="margin-top: 16px;">
            Changing the store name or logo updates the storefront on the next page load.
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
