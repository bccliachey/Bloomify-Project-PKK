<?php
/**
 * Bloomify - create or edit a product.
 */

require_once __DIR__ . '/includes/auth.php';

$adminTitle = 'Product Form';
$activeAdmin = 'products';

$productId = (int) ($_GET['id'] ?? 0);
$editing = null;

if ($productId > 0) {
    $lookup = db()->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $lookup->execute([$productId]);
    $editing = $lookup->fetch() ?: null;

    if (!$editing) {
        flash_set('error', 'Product not found.');
        redirect('admin/products.php');
    }
}

$adminTitle = $editing ? 'Edit Product' : 'Add Product';

$categories = get_categories();
$pricingModeLabels = [
    'per_stem' => 'Per Stem',
    'mixed'    => 'Mixed',
    'fixed'    => 'Fixed Price',
];
$flowerTypes = get_flower_types();
$selectedFlowerIds = [];
if ($editing) {
    $selectedFlowerIds = array_map('intval', array_column(get_flower_types_for_product((int) $editing['id']), 'id'));
}

$form = [
    'name' => $editing['name'] ?? '',
    'slug' => $editing['slug'] ?? '',
    'category_id' => (int) ($editing['category_id'] ?? ($categories[0]['id'] ?? 0)),
    'flower_type_ids' => $selectedFlowerIds,
    'pricing_mode' => $editing['pricing_mode'] ?? '',
    'fixed_price' => ($editing['fixed_price'] ?? null) !== null ? (string) (int) $editing['fixed_price'] : '',
    'description' => $editing['description'] ?? '',
    'image' => $editing['image'] ?? null,
    'is_visible' => (int) ($editing['is_visible'] ?? 1),
    'initial_stock' => '0',
    'remove_image' => 0,
];

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();

    $form['name'] = trim((string) ($_POST['name'] ?? ''));
    $form['slug'] = trim((string) ($_POST['slug'] ?? ''));
    $form['category_id'] = (int) ($_POST['category_id'] ?? 0);
    $form['flower_type_ids'] = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['flower_type_ids'] ?? [])))));
    $form['pricing_mode'] = trim((string) ($_POST['pricing_mode'] ?? ''));
    $form['fixed_price'] = trim((string) ($_POST['fixed_price'] ?? ''));
    $form['description'] = trim((string) ($_POST['description'] ?? ''));
    $form['is_visible'] = isset($_POST['is_visible']) ? 1 : 0;
    $form['initial_stock'] = (string) max(0, (int) ($_POST['initial_stock'] ?? 0));
    $form['remove_image'] = isset($_POST['remove_image']) ? 1 : 0;

    $category = null;
    foreach ($categories as $candidate) {
        if ((int) $candidate['id'] === $form['category_id']) {
            $category = $candidate;
            break;
        }
    }

    if (!$category) {
        $errors[] = 'Please choose a valid category.';
    }

    if ($form['name'] === '' || mb_strlen($form['name']) > 100) {
        $errors[] = 'Product name is required (maximum 100 characters).';
    }

    if (mb_strlen($form['description']) > 5000) {
        $errors[] = 'Description is too long.';
    }

    $allowedModes = ['per_stem', 'mixed', 'fixed'];

    if ($form['pricing_mode'] !== '' && !in_array($form['pricing_mode'], $allowedModes, true)) {
        $errors[] = 'Choose a valid pricing mode.';
    }

    $mode = in_array($form['pricing_mode'], $allowedModes, true)
        ? $form['pricing_mode']
        : ($category['pricing_mode'] ?? 'per_stem');
    $productPricingMode = in_array($form['pricing_mode'], $allowedModes, true)
        ? $form['pricing_mode']
        : null;
    $fixedPrice = null;
    $flowerTypeId = null;

    if ($mode === 'fixed') {
        $fixedPrice = (float) preg_replace('/[^0-9.]/', '', $form['fixed_price']);
        if ($fixedPrice <= 0) {
            $errors[] = 'Fixed price must be greater than zero for fixed-price products.';
        }
    } else {
        $validIds = array_map('intval', array_column($flowerTypes, 'id'));
        $form['flower_type_ids'] = array_values(array_intersect($form['flower_type_ids'], $validIds));
        if (!$form['flower_type_ids']) {
            $errors[] = 'Choose at least one flower option for this product.';
        }
        $flowerTypeId = $form['flower_type_ids'][0];
    }

    $newImage = $form['remove_image'] ? null : $form['image'];

    if (!$errors && isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $newImage = save_uploaded_image($_FILES['image'], 'products', 'product');
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    $slug = admin_unique_slug(
        'products',
        $form['slug'] !== '' ? $form['slug'] : $form['name'],
        $editing ? (int) $editing['id'] : null
    );

    if (!$errors) {
        try {
            db()->beginTransaction();

            if ($editing) {
                $update = db()->prepare(
                    'UPDATE products
                     SET category_id = ?, flower_type_id = ?, name = ?, slug = ?,
                         description = ?, image = ?, fixed_price = ?, pricing_mode = ?, is_visible = ?
                     WHERE id = ?'
                );
                $update->execute([
                    $form['category_id'],
                    $flowerTypeId,
                    $form['name'],
                    $slug,
                    $form['description'] !== '' ? $form['description'] : null,
                    $newImage,
                    $fixedPrice,
                    $productPricingMode,
                    $form['is_visible'],
                    $editing['id'],
                ]);

                if ($mode === 'fixed') {
                    // A product switched to fixed pricing may not have had a
                    // stock row before. Create it without overwriting any
                    // existing quantity.
                    $stock = db()->prepare(
                        'INSERT IGNORE INTO product_stocks (product_id, quantity, low_stock_threshold)
                         VALUES (?, 0, 5)'
                    );
                    $stock->execute([$editing['id']]);
                }

                $message = 'Product updated successfully.';
            } else {
                $insert = db()->prepare(
                    'INSERT INTO products
                        (category_id, flower_type_id, name, slug, description, image, fixed_price, pricing_mode, is_visible)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([
                    $form['category_id'],
                    $flowerTypeId,
                    $form['name'],
                    $slug,
                    $form['description'] !== '' ? $form['description'] : null,
                    $newImage,
                    $fixedPrice,
                    $productPricingMode,
                    $form['is_visible'],
                ]);

                $newProductId = (int) db()->lastInsertId();

                if ($mode === 'fixed') {
                    $initialStock = (int) $form['initial_stock'];
                    $stock = db()->prepare(
                        'INSERT INTO product_stocks (product_id, quantity, low_stock_threshold)
                         VALUES (?, ?, 5)'
                    );
                    $stock->execute([$newProductId, $initialStock]);

                    if ($initialStock > 0) {
                        $movement = db()->prepare(
                            "INSERT INTO stock_movements
                                (item_type, flower_type_id, product_id, change_qty, stock_after, note, admin_id)
                             VALUES ('product', NULL, ?, ?, ?, ?, ?)"
                        );
                        $movement->execute([
                            $newProductId,
                            $initialStock,
                            $initialStock,
                            'Initial stock for new fixed-price product',
                            (int) current_admin()['id'],
                        ]);
                    }
                }

                $message = 'Product created successfully.';
            }

            // Save flower choices per product, never per category.
            $deleteChoices = db()->prepare('DELETE FROM product_flower_types WHERE product_id = ?');
            $savedProductId = $editing ? (int) $editing['id'] : (int) $newProductId;
            $deleteChoices->execute([$savedProductId]);
            if ($mode !== 'fixed' && $form['flower_type_ids']) {
                $saveChoice = db()->prepare('INSERT INTO product_flower_types (product_id, flower_type_id) VALUES (?, ?)');
                foreach ($form['flower_type_ids'] as $flowerId) {
                    $saveChoice->execute([$savedProductId, $flowerId]);
                }
            }

            db()->commit();

            flash_set('success', $message);
            redirect('admin/products.php');
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            $errors[] = 'The product could not be saved. Please try again.';
        }
    }

    $form['image'] = $newImage;
}

$categoryDefaultMode = 'per_stem';
foreach ($categories as $candidate) {
    if ((int) $candidate['id'] === (int) $form['category_id']) {
        $categoryDefaultMode = $candidate['pricing_mode'];
        break;
    }
}
?>
<?php require __DIR__ . '/includes/header.php'; ?>

<?= admin_error_alert($errors) ?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2><?= $editing ? 'Edit Product' : 'New Product' ?></h2>
            <p>Set this product's pricing mode and flower options. Each product has its own configuration.</p>
        </div>
        <a class="btn btn-outline btn-sm" href="<?= e(url('admin/products.php')) ?>">&larr; Back to Products</a>
    </div>

    <form method="post" action="<?= e(url('admin/product-form.php' . ($editing ? '?id=' . (int) $editing['id'] : ''))) ?>"
          enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="form-grid">
            <div class="form-field">
                <label for="name">Product name</label>
                <input type="text" id="name" name="name" class="form-control"
                       value="<?= e($form['name']) ?>" maxlength="100" required>
            </div>

            <div class="form-field">
                <label for="slug">Slug</label>
                <input type="text" id="slug" name="slug" class="form-control"
                       value="<?= e($form['slug']) ?>" maxlength="120"
                       placeholder="Leave empty to generate from name">
                <span class="form-help">URL friendly identifier. Must be unique.</span>
            </div>

            <div class="form-field">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" class="form-control" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"
                                data-mode="<?= e($category['pricing_mode']) ?>"
                            <?= (int) $category['id'] === $form['category_id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                            (<?= e($category['pricing_mode']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="pricing_mode">Pricing mode</label>
                <select id="pricing_mode" name="pricing_mode" class="form-control">
                    <option value="" <?= $form['pricing_mode'] === '' ? 'selected' : '' ?>>
                        Category default (<?= e($pricingModeLabels[$categoryDefaultMode] ?? $categoryDefaultMode) ?>)
                    </option>
                    <?php foreach ($pricingModeLabels as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $form['pricing_mode'] === $value ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="form-help">
                    Choose Fixed Price for a ready-made product, or keep the category default.
                    Fixed pricing is available in every category.
                </span>
            </div>

            <div class="form-field full" data-field="flowers">
                <label>Available flower options for this product</label>
                <div class="form-help" style="margin-bottom:10px;">Select only the flowers or color variants customers may choose for this product. These selections do not affect other products.</div>
                <div class="form-grid">
                    <?php foreach ($flowerTypes as $flowerType): ?>
                        <label class="checkbox-row">
                            <input type="checkbox" name="flower_type_ids[]" value="<?= (int) $flowerType['id'] ?>"
                                <?= in_array((int) $flowerType['id'], $form['flower_type_ids'], true) ? 'checked' : '' ?>>
                            <?= e($flowerType['name']) ?><?= $flowerType['color'] !== '' ? ' — ' . e($flowerType['color']) : '' ?>
                            &middot; <?= e(format_rupiah($flowerType['price_per_stem'])) ?>/stem
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-field" data-field="fixed">
                <label for="fixed_price">Fixed price (Rp)</label>
                <input type="number" id="fixed_price" name="fixed_price" class="form-control"
                       value="<?= e($form['fixed_price']) ?>" min="0" step="1000"
                       placeholder="e.g. 1500000">
                <span class="form-help">Used by any product set to the Fixed Price pricing mode.</span>
            </div>

            <div class="form-field" data-field="fixed">
                <?php if ($editing): ?>
                    <label>Stock</label>
                    <span class="form-help">
                        Fixed-price product stock is managed on the
                        <a href="<?= e(url('admin/stock.php')) ?>" style="color: var(--rose);">Stock page</a>.
                    </span>
                <?php else: ?>
                    <label for="initial_stock">Initial stock (units)</label>
                    <input type="number" id="initial_stock" name="initial_stock" class="form-control"
                           value="<?= e($form['initial_stock']) ?>" min="0" step="1">
                    <span class="form-help">You can adjust stock later at any time.</span>
                <?php endif; ?>
            </div>


            <div class="form-field full">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control"
                          maxlength="5000"><?= e($form['description']) ?></textarea>
            </div>

            <div class="form-field">
                <label for="image">Product image</label>
                <input type="file" id="image" name="image" class="form-control"
                       accept="image/jpeg,image/png,image/webp" data-image-input="imagePreview">
                <span class="form-help">JPG, PNG, or WEBP. Maximum 2 MB.</span>

                <?php if ($form['image']): ?>
                    <label class="checkbox-row" style="margin-top: 8px;">
                        <input type="checkbox" name="remove_image" value="1"
                            <?= $form['remove_image'] ? 'checked' : '' ?>>
                        Remove current image
                    </label>
                <?php endif; ?>
            </div>

            <div class="form-field">
                <label>Preview</label>
                <img id="imagePreview"
                     class="image-preview <?= $form['image'] ? '' : 'hidden' ?>"
                     src="<?= $form['image'] ? e(public_image_url($form['image'])) : '' ?>"
                     alt="Product preview">
            </div>

            <div class="form-field full">
                <label class="checkbox-row">
                    <input type="checkbox" name="is_visible" value="1"
                        <?= $form['is_visible'] ? 'checked' : '' ?>>
                    Visible in the store
                </label>
            </div>
        </div>

        <div class="form-actions between">
            <div class="row-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $editing ? 'Save Changes' : 'Create Product' ?>
                </button>
                <a href="<?= e(url('admin/products.php')) ?>" class="btn btn-outline">Cancel</a>
            </div>

            <?php if ($editing): ?>
                <a class="btn btn-soft btn-sm" target="_blank" rel="noopener"
                   href="<?= e(url('product.php?id=' . (int) $editing['id'])) ?>">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View in Store
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
