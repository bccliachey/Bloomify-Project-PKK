<?php
/**
 * Bloomify - admin product list.
 */

require_once __DIR__ . '/includes/auth.php';

$adminTitle = 'Products';
$activeAdmin = 'products';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $productId = (int) ($_POST['id'] ?? 0);

    $lookup = db()->prepare('SELECT id, name FROM products WHERE id = ? LIMIT 1');
    $lookup->execute([$productId]);
    $product = $lookup->fetch();

    if (!$product) {
        flash_set('error', 'Product not found.');
    } elseif ($action === 'toggle') {
        $newVisibility = (int) ($_POST['visibility'] ?? 0) === 1 ? 1 : 0;
        $update = db()->prepare('UPDATE products SET is_visible = ? WHERE id = ?');
        $update->execute([$newVisibility, $productId]);
        flash_set('success', $product['name'] . ' is now ' . ($newVisibility ? 'visible' : 'hidden') . ' in the store.');
    } elseif ($action === 'delete') {
        $activeOrders = db()->prepare(
            "SELECT COUNT(*)
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
              WHERE oi.product_id = ? AND o.order_status NOT IN ('Selesai', 'Rejected')"
        );
        $activeOrders->execute([$productId]);

        if ((int) $activeOrders->fetchColumn() > 0) {
            flash_set('error', 'This product is part of an active order. Hide it instead of deleting it.');
        } else {
            $delete = db()->prepare('DELETE FROM products WHERE id = ?');
            $delete->execute([$productId]);
            flash_set('success', $product['name'] . ' has been deleted.');
        }
    } else {
        flash_set('error', 'Unknown action.');
    }

    redirect('admin/products.php');
}

$search = trim((string) ($_GET['q'] ?? ''));
$categoryId = (int) ($_GET['category'] ?? 0);
$visibility = (string) ($_GET['visibility'] ?? '');

$sql = 'SELECT p.*, c.name AS category_name,
               COALESCE(p.pricing_mode, c.pricing_mode) AS effective_pricing_mode,
               ft.name AS flower_type_name, ft.price_per_stem,
               fs.quantity AS flower_stock,
               ps.quantity AS product_stock,
               (SELECT COALESCE(SUM(fs2.quantity), 0) FROM product_flower_types pft2 JOIN flower_stocks fs2 ON fs2.flower_type_id = pft2.flower_type_id WHERE pft2.product_id = p.id) AS total_flower_stock,
               (SELECT MIN(ft2.price_per_stem) FROM product_flower_types pft3 JOIN flower_types ft2 ON ft2.id = pft3.flower_type_id WHERE pft3.product_id = p.id) AS min_flower_price
        FROM products p
        JOIN categories c ON c.id = p.category_id
        LEFT JOIN flower_types ft ON ft.id = p.flower_type_id
        LEFT JOIN flower_stocks fs ON fs.flower_type_id = p.flower_type_id
        LEFT JOIN product_stocks ps ON ps.product_id = p.id
        WHERE 1 = 1';

$parameters = [];

if ($search !== '') {
    $sql .= ' AND (p.name LIKE ? OR p.slug LIKE ?)';
    $parameters[] = '%' . $search . '%';
    $parameters[] = '%' . $search . '%';
}

if ($categoryId > 0) {
    $sql .= ' AND p.category_id = ?';
    $parameters[] = $categoryId;
}

if ($visibility === 'visible') {
    $sql .= ' AND p.is_visible = 1';
} elseif ($visibility === 'hidden') {
    $sql .= ' AND p.is_visible = 0';
}

$sql .= ' ORDER BY p.created_at DESC, p.id DESC';

$statement = db()->prepare($sql);
$statement->execute($parameters);
$products = array_map('decorate_product', $statement->fetchAll());

$categories = get_categories();

require __DIR__ . '/includes/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Product Catalog</h2>
            <p><?= count($products) ?> product(s) found.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('admin/product-form.php')) ?>">
            <i class="fa-solid fa-plus"></i> Add Product
        </a>
    </div>

    <form class="filter-form" method="get" action="<?= e(url('admin/products.php')) ?>">
        <div class="form-field search">
            <label for="q">Search</label>
            <input type="text" id="q" name="q" class="form-control"
                   value="<?= e($search) ?>" placeholder="Product name or slug...">
        </div>

        <div class="form-field">
            <label for="category">Category</label>
            <select id="category" name="category" class="form-control">
                <option value="0">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"
                        <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field">
            <label for="visibility">Visibility</label>
            <select id="visibility" name="visibility" class="form-control">
                <option value="">All</option>
                <option value="visible" <?= $visibility === 'visible' ? 'selected' : '' ?>>Visible</option>
                <option value="hidden" <?= $visibility === 'hidden' ? 'selected' : '' ?>>Hidden</option>
            </select>
        </div>

        <button type="submit" class="btn btn-soft">Filter</button>
    </form>

    <?php if ($products): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <img class="thumb" src="<?= e(public_image_url($product['image'])) ?>"
                                         alt="<?= e($product['name']) ?>">
                                    <div>
                                        <div class="cell-strong"><?= e($product['name']) ?></div>
                                        <div class="cell-muted"><?= e($product['slug']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?= e($product['category_name']) ?>
                                <?php if ($product['pricing_mode'] === 'per_stem' && $product['flower_type_name']): ?>
                                    <div class="cell-muted"><?= e($product['flower_type_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($product['price_label']) ?></td>
                            <td>
                                <?php if ($product['pricing_mode'] === 'fixed'): ?>
                                    <span class="<?= (int) $product['stock'] <= 0 ? 'low-stock' : '' ?>">
                                        <?= (int) $product['stock'] ?> unit(s)
                                    </span>
                                <?php elseif ($product['pricing_mode'] === 'mixed'): ?>
                                    <span class="<?= (int) $product['stock'] < MIN_STEMS ? 'low-stock' : '' ?>">
                                        <?= (int) $product['stock'] ?> stems total
                                    </span>
                                <?php else: ?>
                                    <span class="<?= (int) $product['stock'] < MIN_STEMS ? 'low-stock' : '' ?>">
                                        <?= (int) $product['stock'] ?> stems
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) $product['is_visible'] === 1): ?>
                                    <span class="badge status-visible">Visible</span>
                                <?php else: ?>
                                    <span class="badge status-hidden">Hidden</span>
                                <?php endif; ?>
                                <?php if ($product['is_sold_out']): ?>
                                    <div class="cell-muted" style="color: var(--red); margin-top: 4px;">Sold out</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="row-actions" style="justify-content: flex-end;">
                                    <a class="btn btn-soft btn-sm"
                                       href="<?= e(url('admin/product-form.php?id=' . (int) $product['id'])) ?>">
                                        Edit
                                    </a>

                                    <form method="post" action="<?= e(url('admin/products.php')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                        <input type="hidden" name="visibility" value="<?= (int) $product['is_visible'] === 1 ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-outline btn-sm">
                                            <?= (int) $product['is_visible'] === 1 ? 'Hide' : 'Show' ?>
                                        </button>
                                    </form>

                                    <form method="post" action="<?= e(url('admin/products.php')) ?>"
                                          data-confirm="Delete <?= e($product['name']) ?>? Existing order history is kept.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-seedling"></i>
            <h2>No products found</h2>
            <p>Try a different filter, or add a new product to the catalog.</p>
            <a class="btn btn-primary" href="<?= e(url('admin/product-form.php')) ?>">Add Product</a>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
