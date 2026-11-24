<?php
/**
 * Bloomify - stock control for flowers and fixed-price products.
 */

require_once __DIR__ . '/includes/auth.php';

$adminTitle = 'Stock';
$activeAdmin = 'stock';

$admin = current_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_flower') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $color = trim((string) ($_POST['color'] ?? ''));
        $price = (float) preg_replace('/[^0-9.]/', '', (string) ($_POST['price_per_stem'] ?? ''));
        $quantity = max(0, (int) ($_POST['quantity'] ?? 0));
        $threshold = max(0, (int) ($_POST['low_stock_threshold'] ?? 10));
        $errors = [];

        if ($name === '' || mb_strlen($name) > 50 || mb_strlen($color) > 30) {
            $errors[] = 'Flower name is required (maximum 50 characters).';
        }

        if ($price <= 0) {
            $errors[] = 'Price per stem must be greater than zero.';
        }

        if (!$errors) {
            $duplicate = db()->prepare('SELECT COUNT(*) FROM flower_types WHERE name = ? AND color = ?');
            $duplicate->execute([$name, $color]);

            if ((int) $duplicate->fetchColumn() > 0) {
                $errors[] = 'A flower type with that name already exists.';
            }
        }

        if ($errors) {
            flash_set('error', implode(' ', $errors));
            redirect('admin/stock.php');
        }

        try {
            db()->beginTransaction();

            $insert = db()->prepare('INSERT INTO flower_types (name, color, price_per_stem) VALUES (?, ?, ?)');
            $insert->execute([$name, $color, $price]);
            $flowerId = (int) db()->lastInsertId();

            $stock = db()->prepare(
                'INSERT INTO flower_stocks (flower_type_id, quantity, low_stock_threshold)
                 VALUES (?, ?, ?)'
            );
            $stock->execute([$flowerId, $quantity, $threshold]);

            if ($quantity > 0) {
                $movement = db()->prepare(
                    'INSERT INTO stock_movements
                        (item_type, flower_type_id, product_id, change_qty, stock_after, note, admin_id)
                     VALUES (?, ?, NULL, ?, ?, ?, ?)'
                );
                $movement->execute([
                    'flower',
                    $flowerId,
                    $quantity,
                    $quantity,
                    'Initial stock for new flower type',
                    $admin['id'],
                ]);
            }

            db()->commit();
            flash_set('success', $name . ' has been added to the flower types.');
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash_set('error', 'The flower type could not be created.');
        }

        redirect('admin/stock.php');
    }

    if ($action === 'edit_flower') {
        $flowerId = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $color = trim((string) ($_POST['color'] ?? ''));
        $price = (float) preg_replace('/[^0-9.]/', '', (string) ($_POST['price_per_stem'] ?? ''));
        $threshold = max(0, (int) ($_POST['low_stock_threshold'] ?? 10));
        $errors = [];

        $lookup = db()->prepare('SELECT * FROM flower_types WHERE id = ? LIMIT 1');
        $lookup->execute([$flowerId]);
        $flower = $lookup->fetch();

        if (!$flower) {
            flash_set('error', 'Flower type not found.');
            redirect('admin/stock.php');
        }

        if ($name === '' || mb_strlen($name) > 50 || mb_strlen($color) > 30) {
            $errors[] = 'Flower name is required (maximum 50 characters).';
        }

        if ($price <= 0) {
            $errors[] = 'Price per stem must be greater than zero.';
        }

        if (!$errors) {
            $duplicate = db()->prepare(
                'SELECT COUNT(*) FROM flower_types WHERE name = ? AND color = ? AND id <> ?'
            );
            $duplicate->execute([$name, $color, $flowerId]);

            if ((int) $duplicate->fetchColumn() > 0) {
                $errors[] = 'Another flower type already uses that name.';
            }
        }

        if ($errors) {
            flash_set('error', implode(' ', $errors));
            redirect('admin/stock.php?edit_flower=' . $flowerId);
        }

        try {
            db()->beginTransaction();

            $update = db()->prepare(
                'UPDATE flower_types SET name = ?, color = ?, price_per_stem = ? WHERE id = ?'
            );
            $update->execute([$name, $color, $price, $flowerId]);

            $stock = db()->prepare(
                'INSERT INTO flower_stocks (flower_type_id, quantity, low_stock_threshold)
                 VALUES (?, 0, ?)
                 ON DUPLICATE KEY UPDATE low_stock_threshold = VALUES(low_stock_threshold)'
            );
            $stock->execute([$flowerId, $threshold]);

            db()->commit();
            flash_set('success', 'Flower type updated.');
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash_set('error', 'The flower type could not be updated.');
        }

        redirect('admin/stock.php');
    }

    if ($action === 'delete_flower') {
        $flowerId = (int) ($_POST['id'] ?? 0);
        $lookup = db()->prepare('SELECT name, color FROM flower_types WHERE id = ? LIMIT 1');
        $lookup->execute([$flowerId]);
        $flower = $lookup->fetch();
        if (!$flower) {
            flash_set('error', 'Flower variant not found.');
        } else {
            $references = 0;
            foreach (['products' => 'flower_type_id', 'cart_item_details' => 'flower_type_id'] as $table => $column) {
                $check = db()->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` = ?");
                $check->execute([$flowerId]);
                $references += (int) $check->fetchColumn();
            }
            if ($references > 0) {
                flash_set('error', 'This flower variant is used by a product or active cart. Remove those references before deleting it. Historical order details are preserved.');
            } else {
                $delete = db()->prepare('DELETE FROM flower_types WHERE id = ?');
                $delete->execute([$flowerId]);
                flash_set('success', $flower['name'] . (!empty($flower['color']) ? ' — ' . $flower['color'] : '') . ' has been deleted.');
            }
        }
        redirect('admin/stock.php');
    }

    if ($action === 'adjust') {
        $itemType = ($_POST['item_type'] ?? '') === 'product' ? 'product' : 'flower';
        $itemId = (int) ($_POST['id'] ?? 0);
        $mode = (string) ($_POST['mode'] ?? '');
        $quantity = (int) ($_POST['quantity'] ?? 0);
        $thresholdRaw = trim((string) ($_POST['low_stock_threshold'] ?? ''));
        $threshold = $thresholdRaw !== '' ? max(0, (int) $thresholdRaw) : null;

        if (!in_array($mode, ['add', 'reduce', 'set'], true)) {
            flash_set('error', 'Choose a valid stock adjustment mode.');
            redirect('admin/stock.php');
        }

        if ($quantity < 0) {
            flash_set('error', 'Quantity cannot be negative.');
            redirect('admin/stock.php');
        }

        if ($itemId <= 0) {
            flash_set('error', 'Invalid stock item.');
            redirect('admin/stock.php');
        }

        try {
            db()->beginTransaction();

            if ($itemType === 'flower') {
                $lookup = db()->prepare('SELECT name FROM flower_types WHERE id = ? LIMIT 1');
                $lookup->execute([$itemId]);
                $flower = $lookup->fetch();

                if (!$flower) {
                    throw new RuntimeException('Flower type not found.');
                }

                $stockLookup = db()->prepare(
                    'SELECT quantity, low_stock_threshold FROM flower_stocks
                     WHERE flower_type_id = ? FOR UPDATE'
                );
                $stockLookup->execute([$itemId]);
                $current = $stockLookup->fetch();

                $oldQuantity = $current ? (int) $current['quantity'] : 0;
                $newThreshold = $threshold ?? ($current ? (int) $current['low_stock_threshold'] : LOW_STOCK_FALLBACK);

                $newQuantity = $mode === 'add'
                    ? $oldQuantity + $quantity
                    : ($mode === 'reduce' ? $oldQuantity - $quantity : $quantity);

                if ($newQuantity < 0) {
                    throw new RuntimeException('Stock cannot go below zero.');
                }

                $save = db()->prepare(
                    'INSERT INTO flower_stocks (flower_type_id, quantity, low_stock_threshold)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE quantity = VALUES(quantity),
                                             low_stock_threshold = VALUES(low_stock_threshold)'
                );
                $save->execute([$itemId, $newQuantity, $newThreshold]);

                $change = $newQuantity - $oldQuantity;

                if ($change !== 0) {
                    $movement = db()->prepare(
                        'INSERT INTO stock_movements
                            (item_type, flower_type_id, product_id, change_qty, stock_after, note, admin_id)
                         VALUES (?, ?, NULL, ?, ?, ?, ?)'
                    );
                    $movement->execute([
                        'flower',
                        $itemId,
                        $change,
                        $newQuantity,
                        'Manual adjustment (' . $mode . ')',
                        $admin['id'],
                    ]);
                }

                $itemName = $flower['name'];
            } else {
                $lookup = db()->prepare(
                    'SELECT p.name, COALESCE(p.pricing_mode, c.pricing_mode) AS pricing_mode
                     FROM products p
                     JOIN categories c ON c.id = p.category_id
                     WHERE p.id = ?
                     LIMIT 1'
                );
                $lookup->execute([$itemId]);
                $product = $lookup->fetch();

                if (!$product || $product['pricing_mode'] !== 'fixed') {
                    throw new RuntimeException('Only fixed-price products keep product stock.');
                }

                $stockLookup = db()->prepare(
                    'SELECT quantity, low_stock_threshold FROM product_stocks
                     WHERE product_id = ? FOR UPDATE'
                );
                $stockLookup->execute([$itemId]);
                $current = $stockLookup->fetch();

                $oldQuantity = $current ? (int) $current['quantity'] : 0;
                $newThreshold = $threshold ?? ($current ? (int) $current['low_stock_threshold'] : 5);

                $newQuantity = $mode === 'add'
                    ? $oldQuantity + $quantity
                    : ($mode === 'reduce' ? $oldQuantity - $quantity : $quantity);

                if ($newQuantity < 0) {
                    throw new RuntimeException('Stock cannot go below zero.');
                }

                $save = db()->prepare(
                    'INSERT INTO product_stocks (product_id, quantity, low_stock_threshold)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE quantity = VALUES(quantity),
                                             low_stock_threshold = VALUES(low_stock_threshold)'
                );
                $save->execute([$itemId, $newQuantity, $newThreshold]);

                $change = $newQuantity - $oldQuantity;

                if ($change !== 0) {
                    $movement = db()->prepare(
                        'INSERT INTO stock_movements
                            (item_type, flower_type_id, product_id, change_qty, stock_after, note, admin_id)
                         VALUES (?, NULL, ?, ?, ?, ?, ?)'
                    );
                    $movement->execute([
                        'product',
                        $itemId,
                        $change,
                        $newQuantity,
                        'Manual adjustment (' . $mode . ')',
                        $admin['id'],
                    ]);
                }

                $itemName = $product['name'];
            }

            db()->commit();

            if ($change === 0) {
                flash_set('info', $itemName . ' stock is unchanged at ' . $newQuantity . '.');
            } else {
                flash_set('success', $itemName . ' stock updated to ' . $newQuantity . '.');
            }
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash_set('error', $exception->getMessage() ?: 'Stock could not be updated.');
        }

        redirect('admin/stock.php');
    }

    flash_set('error', 'Unknown action.');
    redirect('admin/stock.php');
}

$editFlowerId = (int) ($_GET['edit_flower'] ?? 0);
$editingFlower = null;

if ($editFlowerId > 0) {
    $lookup = db()->prepare(
        'SELECT ft.*, COALESCE(fs.quantity, 0) AS quantity,
                COALESCE(fs.low_stock_threshold, ?) AS low_stock_threshold
         FROM flower_types ft
         LEFT JOIN flower_stocks fs ON fs.flower_type_id = ft.id
         WHERE ft.id = ?
         LIMIT 1'
    );
    $lookup->execute([LOW_STOCK_FALLBACK, $editFlowerId]);
    $editingFlower = $lookup->fetch() ?: null;
}

$flowerStatement = db()->prepare(
    'SELECT ft.*, COALESCE(fs.quantity, 0) AS quantity,
            COALESCE(fs.low_stock_threshold, ?) AS low_stock_threshold
     FROM flower_types ft
     LEFT JOIN flower_stocks fs ON fs.flower_type_id = ft.id
     ORDER BY ft.name ASC, ft.color ASC'
);
$flowerStatement->execute([LOW_STOCK_FALLBACK]);
$flowers = $flowerStatement->fetchAll();

$stockProducts = db()->query(
    "SELECT p.id, p.name, p.image, p.fixed_price,
            c.id AS category_id, c.name AS category_name,
            COALESCE(ps.quantity, 0) AS quantity,
            COALESCE(ps.low_stock_threshold, 5) AS low_stock_threshold
     FROM products p
     JOIN categories c ON c.id = p.category_id
     LEFT JOIN product_stocks ps ON ps.product_id = p.id
     WHERE COALESCE(p.pricing_mode, c.pricing_mode) = 'fixed'
     ORDER BY c.id ASC, p.name ASC"
)->fetchAll();

$fixedStockCategories = [];
foreach (get_categories() as $category) {
    if ($category['pricing_mode'] === 'fixed') {
        $fixedStockCategories[(int) $category['id']] = [
            'name' => $category['name'],
            'products' => [],
        ];
    }
}

foreach ($stockProducts as $stockProduct) {
    $categoryId = (int) $stockProduct['category_id'];

    if (!isset($fixedStockCategories[$categoryId])) {
        $fixedStockCategories[$categoryId] = [
            'name' => $stockProduct['category_name'],
            'products' => [],
        ];
    }

    $fixedStockCategories[$categoryId]['products'][] = $stockProduct;
}

ksort($fixedStockCategories);

$movements = db()->query(
    'SELECT sm.*, ft.name AS flower_name, p.name AS product_name,
            a.full_name AS admin_name
     FROM stock_movements sm
     LEFT JOIN flower_types ft ON ft.id = sm.flower_type_id
     LEFT JOIN products p ON p.id = sm.product_id
     LEFT JOIN admin_accounts a ON a.id = sm.admin_id
     ORDER BY sm.created_at DESC, sm.id DESC
     LIMIT 20'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="grid-2">
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Flower Stock</h2>
                <p>Stock is deducted only when an order is confirmed.</p>
            </div>
        </div>

        <?php if ($flowers): ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Flower / Variant</th>
                            <th>Price / Stem</th>
                            <th>Stock</th>
                            <th>Alert At</th>
                            <th>Adjustment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($flowers as $flower): ?>
                            <?php $isLow = (int) $flower['quantity'] <= (int) $flower['low_stock_threshold']; ?>
                            <tr>
                                <td>
                                    <div class="cell-strong"><?= e($flower['name']) ?><?= !empty($flower['color']) ? ' — ' . e($flower['color']) : '' ?></div>
                                    <a class="cell-muted" href="<?= e(url('admin/stock.php?edit_flower=' . (int) $flower['id'])) ?>">
                                        Edit details
                                    </a>
                                </td>
                                <td><?= e(format_rupiah($flower['price_per_stem'])) ?></td>
                                <td>
                                    <span class="<?= $isLow ? 'low-stock' : 'stock-ok' ?>">
                                        <?= (int) $flower['quantity'] ?>
                                    </span>
                                </td>
                                <td><?= (int) $flower['low_stock_threshold'] ?></td>
                                <td>
                                    <form method="post" action="<?= e(url('admin/stock.php')) ?>" class="row-actions">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="adjust">
                                        <input type="hidden" name="item_type" value="flower">
                                        <input type="hidden" name="id" value="<?= (int) $flower['id'] ?>">

                                        <select name="mode" class="form-control" style="width: 86px; padding: 6px 24px 6px 8px;"
                                                aria-label="Adjustment mode">
                                            <option value="add">Add</option>
                                            <option value="reduce">Reduce</option>
                                            <option value="set">Set</option>
                                        </select>

                                        <input type="number" name="quantity" class="form-control" min="0" step="1"
                                               value="0" style="width: 74px; padding: 6px 8px;"
                                               aria-label="Quantity">

                                        <input type="number" name="low_stock_threshold" class="form-control"
                                               min="0" step="1" value="<?= (int) $flower['low_stock_threshold'] ?>"
                                               style="width: 64px; padding: 6px 8px;"
                                               aria-label="Low stock alert" title="Low stock alert at">

                                        <button type="submit" class="btn btn-soft btn-sm">Apply</button>
                                    </form>
                                    <form method="post" action="<?= e(url('admin/stock.php')) ?>" data-confirm="Delete this flower variant? This is available only when it is not used by products or active carts. Historical order details are preserved.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_flower">
                                        <input type="hidden" name="id" value="<?= (int) $flower['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-spa"></i>
                <h2>No flower variants</h2>
                <p>Add your first flower type and color variant to start customizing bouquets.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2><?= $editingFlower ? 'Edit Flower Type' : 'Add Flower Type' ?></h2>
                <p><?= $editingFlower ? 'Update the flower name, color variant, price, and alert level.' : 'Create a flower type or color variant with its initial stock.' ?></p>
            </div>
            <?php if ($editingFlower): ?>
                <a class="btn btn-outline btn-sm" href="<?= e(url('admin/stock.php')) ?>">Cancel</a>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= e(url('admin/stock.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editingFlower ? 'edit_flower' : 'add_flower' ?>">
            <?php if ($editingFlower): ?>
                <input type="hidden" name="id" value="<?= (int) $editingFlower['id'] ?>">
            <?php endif; ?>

            <div class="form-field">
                <label for="flower_name">Flower name</label>
                <input type="text" id="flower_name" name="name" class="form-control"
                       value="<?= e($editingFlower['name'] ?? '') ?>" maxlength="50" required>
            </div>

            <div class="form-field" style="margin-top: 14px;">
                <label for="flower_color">Color / Variant</label>
                <input type="text" id="flower_color" name="color" class="form-control"
                       value="<?= e($editingFlower['color'] ?? '') ?>" maxlength="30"
                       placeholder="e.g. Pink, White, Yellow">
                <span class="form-help">Create one stock entry for each color. Example: Lily Serenade — Pink.</span>
            </div>

            <div class="form-field" style="margin-top: 14px;">
                <label for="price_per_stem">Price per stem (Rp)</label>
                <input type="number" id="price_per_stem" name="price_per_stem" class="form-control"
                       value="<?= $editingFlower ? (int) $editingFlower['price_per_stem'] : '' ?>"
                       min="0" step="1000" required>
            </div>

            <div class="form-field" style="margin-top: 14px;">
                <label for="low_stock_threshold">Low stock alert at</label>
                <input type="number" id="low_stock_threshold" name="low_stock_threshold" class="form-control"
                       value="<?= $editingFlower ? (int) $editingFlower['low_stock_threshold'] : 10 ?>"
                       min="0" step="1" required>
                <span class="form-help">You will see a warning when stock reaches this number.</span>
            </div>

            <?php if (!$editingFlower): ?>
                <div class="form-field" style="margin-top: 14px;">
                    <label for="quantity">Initial stock (stems)</label>
                    <input type="number" id="quantity" name="quantity" class="form-control"
                           value="0" min="0" step="1" required>
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $editingFlower ? 'Save Flower Type' : 'Add Flower Type' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($fixedStockCategories as $stockCategory): ?>
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2><?= e($stockCategory['name']) ?> Stock</h2>
                <p>Fixed-price products in this category keep their own separate stock.</p>
            </div>
        </div>

        <?php if ($stockCategory['products']): ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Alert At</th>
                            <th>Adjustment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stockCategory['products'] as $product): ?>
                            <?php $isLow = (int) $product['quantity'] <= (int) $product['low_stock_threshold']; ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <img class="thumb" src="<?= e(public_image_url($product['image'])) ?>"
                                             alt="<?= e($product['name']) ?>">
                                        <div class="cell-strong"><?= e($product['name']) ?></div>
                                    </div>
                                </td>
                                <td><?= e(format_rupiah($product['fixed_price'])) ?></td>
                                <td>
                                    <span class="<?= $isLow ? 'low-stock' : 'stock-ok' ?>">
                                        <?= (int) $product['quantity'] ?>
                                    </span>
                                </td>
                                <td><?= (int) $product['low_stock_threshold'] ?></td>
                                <td>
                                    <form method="post" action="<?= e(url('admin/stock.php')) ?>" class="row-actions">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="adjust">
                                        <input type="hidden" name="item_type" value="product">
                                        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">

                                        <select name="mode" class="form-control" style="width: 86px; padding: 6px 24px 6px 8px;"
                                                aria-label="Adjustment mode">
                                            <option value="add">Add</option>
                                            <option value="reduce">Reduce</option>
                                            <option value="set">Set</option>
                                        </select>

                                        <input type="number" name="quantity" class="form-control" min="0" step="1"
                                               value="0" style="width: 74px; padding: 6px 8px;"
                                               aria-label="Quantity">

                                        <input type="number" name="low_stock_threshold" class="form-control"
                                               min="0" step="1" value="<?= (int) $product['low_stock_threshold'] ?>"
                                               style="width: 64px; padding: 6px 8px;"
                                               aria-label="Low stock alert" title="Low stock alert at">

                                        <button type="submit" class="btn btn-soft btn-sm">Apply</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding: 30px 10px;">
                <i class="fa-solid fa-box"></i>
                <h2>No fixed-price products</h2>
                <p>Add a product with Fixed Price pricing to <?= e($stockCategory['name']) ?> to manage its stock here.</p>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<div class="panel">
    <div class="panel-head">
        <h2>Recent Stock Movements</h2>
    </div>

    <?php if ($movements): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Item</th>
                        <th>Change</th>
                        <th>Stock After</th>
                        <th>Note</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movements as $movement): ?>
                        <?php
                        $itemName = $movement['item_type'] === 'flower'
                            ? ($movement['flower_name'] ?? 'Deleted flower')
                            : ($movement['product_name'] ?? 'Deleted product');
                        $change = (int) $movement['change_qty'];
                        ?>
                        <tr>
                            <td class="cell-muted"><?= e(format_datetime($movement['created_at'])) ?></td>
                            <td>
                                <span class="cell-strong"><?= e($itemName) ?></span>
                                <div class="cell-muted"><?= e($movement['item_type']) ?></div>
                            </td>
                            <td class="<?= $change >= 0 ? 'stock-ok' : 'low-stock' ?>">
                                <?= $change > 0 ? '+' : '' ?><?= $change ?>
                            </td>
                            <td><?= (int) $movement['stock_after'] ?></td>
                            <td class="cell-muted"><?= e($movement['note'] ?? '-') ?></td>
                            <td class="cell-muted"><?= e($movement['admin_name'] ?? 'System') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="form-help">No stock movements recorded yet.</p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
