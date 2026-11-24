<?php
/**
 * Bloomify - manage product categories.
 */

require_once __DIR__ . '/includes/auth.php';

$adminTitle = 'Categories';
$activeAdmin = 'categories';

$modeLabels = [
    'per_stem' => 'Per Stem',
    'mixed'    => 'Mixed',
    'fixed'    => 'Fixed Price',
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $categoryId = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $existing = null;

        if ($categoryId > 0) {
            $lookup = db()->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
            $lookup->execute([$categoryId]);
            $existing = $lookup->fetch() ?: null;
        }

        if (!$existing) {
            flash_set('error', 'Category not found.');
        } else {
            $count = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
            $count->execute([$categoryId]);

            if ((int) $count->fetchColumn() > 0) {
                flash_set('error', 'This category still has products. Move or delete them first.');
            } else {
                $delete = db()->prepare('DELETE FROM categories WHERE id = ?');
                $delete->execute([$categoryId]);
                flash_set('success', $existing['name'] . ' has been deleted.');
            }
        }

        redirect('admin/categories.php');
    }

    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $slugInput = trim((string) ($_POST['slug'] ?? ''));
        $pricingMode = (string) ($_POST['pricing_mode'] ?? 'per_stem');
        $description = trim((string) ($_POST['description'] ?? ''));
        $errors = [];

        $existing = null;
        if ($categoryId > 0) {
            $lookup = db()->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
            $lookup->execute([$categoryId]);
            $existing = $lookup->fetch() ?: null;

            if (!$existing) {
                flash_set('error', 'Category not found.');
                redirect('admin/categories.php');
            }
        }

        if ($name === '' || mb_strlen($name) > 50) {
            $errors[] = 'Category name is required (maximum 50 characters).';
        }

        if (!isset($modeLabels[$pricingMode])) {
            $errors[] = 'Choose a valid pricing mode.';
        }

        if (mb_strlen($description) > 1000) {
            $errors[] = 'Description is too long.';
        }

        if (!$errors) {
            $duplicate = db()->prepare(
                'SELECT COUNT(*) FROM categories WHERE name = ? AND id <> ?'
            );
            $duplicate->execute([$name, $categoryId]);

            if ((int) $duplicate->fetchColumn() > 0) {
                $errors[] = 'Another category already uses that name.';
            }
        }

        if (!$errors) {
            $slug = admin_unique_slug(
                'categories',
                $slugInput !== '' ? $slugInput : $name,
                $existing ? (int) $existing['id'] : null,
                50
            );

            if ($existing) {
                $update = db()->prepare(
                    'UPDATE categories
                     SET name = ?, slug = ?, pricing_mode = ?, description = ?
                     WHERE id = ?'
                );
                $update->execute([
                    $name,
                    $slug,
                    $pricingMode,
                    $description !== '' ? $description : null,
                    $existing['id'],
                ]);
                flash_set('success', 'Category updated successfully.');
            } else {
                $insert = db()->prepare(
                    'INSERT INTO categories (name, slug, pricing_mode, description)
                     VALUES (?, ?, ?, ?)'
                );
                $insert->execute([
                    $name,
                    $slug,
                    $pricingMode,
                    $description !== '' ? $description : null,
                ]);
                $categoryId = (int) db()->lastInsertId();
                flash_set('success', 'Category created successfully.');
            }

            redirect('admin/categories.php');
        }

        flash_set('error', implode(' ', $errors));
        redirect('admin/categories.php?edit=' . $categoryId);
    }

    flash_set('error', 'Unknown action.');
    redirect('admin/categories.php');
}

$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;

if ($editId > 0) {
    $lookup = db()->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
    $lookup->execute([$editId]);
    $editing = $lookup->fetch() ?: null;
}

$form = [
    'name' => $editing['name'] ?? '',
    'slug' => $editing['slug'] ?? '',
    'pricing_mode' => $editing['pricing_mode'] ?? 'per_stem',
    'description' => $editing['description'] ?? '',
];


$categories = db()->query(
    'SELECT c.*, COUNT(DISTINCT p.id) AS product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id
     ORDER BY c.id ASC'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="grid-2">
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>All Categories</h2>
                <p>Pricing mode sets the default for products in each category. Individual products can override it.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Pricing</th>
                        <th>Products</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td>
                                <div class="cell-strong"><?= e($category['name']) ?></div>
                                <div class="cell-muted"><?= e($category['slug']) ?></div>
                            </td>
                            <td>
                                <span class="badge status-process">
                                    <?= e($modeLabels[$category['pricing_mode']] ?? $category['pricing_mode']) ?>
                                </span>
                            </td>
                            <td><?= (int) $category['product_count'] ?></td>
                            <td>
                                <div class="row-actions" style="justify-content: flex-end;">
                                    <a class="btn btn-soft btn-sm"
                                       href="<?= e(url('admin/categories.php?edit=' . (int) $category['id'])) ?>">
                                        Edit
                                    </a>

                                    <?php if ((int) $category['product_count'] > 0): ?>
                                        <button type="button" class="btn btn-danger btn-sm" disabled
                                                title="Move or delete the products first.">
                                            Delete
                                        </button>
                                    <?php else: ?>
                                        <form method="post" action="<?= e(url('admin/categories.php')) ?>"
                                              data-confirm="Delete category <?= e($category['name']) ?>?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2><?= $editing ? 'Edit Category' : 'New Category' ?></h2>
                <p><?= $editing ? 'Editing ' . e($editing['name']) : 'Create a new catalog group.' ?></p>
            </div>
            <?php if ($editing): ?>
                <a class="btn btn-outline btn-sm" href="<?= e(url('admin/categories.php')) ?>">New</a>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= e(url('admin/categories.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">

            <div class="form-field">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" class="form-control"
                       value="<?= e($form['name']) ?>" maxlength="50" required>
            </div>

            <div class="form-field" style="margin-top: 14px;">
                <label for="slug">Slug</label>
                <input type="text" id="slug" name="slug" class="form-control"
                       value="<?= e($form['slug']) ?>" maxlength="50"
                       placeholder="Generated from name if empty">
            </div>

            <div class="form-field" style="margin-top: 14px;">
                <label for="pricing_mode">Pricing mode</label>
                <select id="pricing_mode" name="pricing_mode" class="form-control">
                    <?php foreach ($modeLabels as $value => $label): ?>
                        <option value="<?= e($value) ?>"
                            <?= $form['pricing_mode'] === $value ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="form-help">
                    This is the default pricing structure. Each product can choose its own pricing mode.
                    Flower choices are configured separately in each product.
                </span>
            </div>


            <div class="form-field" style="margin-top: 14px;">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control"
                          maxlength="1000"><?= e($form['description']) ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $editing ? 'Save Changes' : 'Create Category' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
