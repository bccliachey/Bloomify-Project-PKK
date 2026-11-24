<?php
/**
 * Bloomify - Product catalog with search, category filter, and sorting.
 */

require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Shop Flowers';
$activeNav = 'shop';
$bodyClass = 'shop-page';

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$categorySlug = isset($_GET['category']) ? trim((string) $_GET['category']) : '';
$sort = isset($_GET['sort']) ? (string) $_GET['sort'] : 'newest';

$allowedSorts = ['newest', 'price_low', 'price_high', 'name'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'newest';
}

$categories = get_categories();
$categoryNames = [];
foreach ($categories as $category) {
    $categoryNames[$category['slug']] = $category['name'];
}

$products = get_public_products($search, $categorySlug, $sort);

$activeCategoryName = $categoryNames[$categorySlug] ?? '';

function catalog_link(array $overrides = []): string
{
    global $search, $categorySlug, $sort;

    $params = array_filter([
        'q'        => $search,
        'category' => $categorySlug,
        'sort'     => $sort !== 'newest' ? $sort : '',
    ]);

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    $query = http_build_query($params);

    return url('catalog.php' . ($query !== '' ? '?' . $query : ''));
}

require __DIR__ . '/includes/header.php';
?>

<main class="page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= e(url('index.php')) ?>">Home</a>
        <span>/</span>
        <span>Shop</span>
        <?php if ($activeCategoryName !== ''): ?>
            <span>/</span>
            <span><?= e($activeCategoryName) ?></span>
        <?php endif; ?>
    </nav>

    <div class="page-heading">
        <p class="eyebrow">Our Collection</p>
        <h1>
            <?php if ($search !== ''): ?>
                Results for &ldquo;<?= e($search) ?>&rdquo;
            <?php elseif ($activeCategoryName !== ''): ?>
                <?= e($activeCategoryName) ?>
            <?php else: ?>
                Bloomify's Bouquet
            <?php endif; ?>
        </h1>
        <p>Browse hand bouquets, mix bouquets, and ready-made flower boxes.</p>
    </div>

    <div class="catalog-toolbar">
        <div class="category-chips">
            <a class="chip <?= $categorySlug === '' ? 'active' : '' ?>"
               href="<?= e(catalog_link(['category' => null])) ?>">All</a>
            <?php foreach ($categories as $category): ?>
                <a class="chip <?= $categorySlug === $category['slug'] ? 'active' : '' ?>"
                   href="<?= e(catalog_link(['category' => $category['slug']])) ?>">
                    <?= e($category['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form class="catalog-search" action="<?= e(url('catalog.php')) ?>" method="get" role="search">
            <input type="search" name="q" class="form-control"
                   placeholder="Search flowers..."
                   value="<?= e($search) ?>" aria-label="Search products">
            <?php if ($categorySlug !== ''): ?>
                <input type="hidden" name="category" value="<?= e($categorySlug) ?>">
            <?php endif; ?>
            <?php if ($sort !== 'newest'): ?>
                <input type="hidden" name="sort" value="<?= e($sort) ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Search</button>
        </form>

        <form class="catalog-sort" action="<?= e(url('catalog.php')) ?>" method="get">
            <?php if ($search !== ''): ?>
                <input type="hidden" name="q" value="<?= e($search) ?>">
            <?php endif; ?>
            <?php if ($categorySlug !== ''): ?>
                <input type="hidden" name="category" value="<?= e($categorySlug) ?>">
            <?php endif; ?>
            <label for="sort">Sort</label>
            <select name="sort" id="sort" data-auto-submit>
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: low to high</option>
                <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: high to low</option>
                <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A-Z</option>
            </select>
            <noscript><button type="submit" class="btn btn-soft btn-sm">Apply</button></noscript>
        </form>
    </div>

    <p class="result-count"><?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?> found</p>

    <?php if ($products): ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <?php require __DIR__ . '/includes/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-magnifying-glass"></i>
            <h2>No flowers found</h2>
            <p>
                <?php if ($search !== ''): ?>
                    We could not find a product matching &ldquo;<?= e($search) ?>&rdquo;.
                <?php else: ?>
                    There are no products in this category yet.
                <?php endif; ?>
            </p>
            <a href="<?= e(url('catalog.php')) ?>" class="btn btn-primary">Browse all products</a>
        </div>
    <?php endif; ?>
</main>

<?php
require __DIR__ . '/includes/product-modal.php';
require __DIR__ . '/includes/footer.php';
