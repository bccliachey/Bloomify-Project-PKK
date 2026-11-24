<?php
/**
 * Admin layout - top part.
 *
 * Expects $adminTitle and $activeAdmin to be set by the page.
 */

$adminTitle = $adminTitle ?? 'Dashboard';
$activeAdmin = $activeAdmin ?? 'dashboard';
$admin = current_admin();

$pendingOrderCount = 0;
try {
    $pendingOrderCount = (int) db()
        ->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Pending'")
        ->fetchColumn();
} catch (Throwable $exception) {
    $pendingOrderCount = 0;
}

$adminNavigation = [
    'dashboard'  => ['index.php', 'fa-gauge-high', 'Dashboard'],
    'products'   => ['products.php', 'fa-seedling', 'Products'],
    'categories' => ['categories.php', 'fa-layer-group', 'Categories'],
    'stock'      => ['stock.php', 'fa-boxes-stacked', 'Stock'],
    'orders'     => ['orders.php', 'fa-receipt', 'Orders'],
    'reviews'    => ['reviews.php', 'fa-star', 'Reviews'],
    'users'      => ['users.php', 'fa-users', 'Customers'],
    'admins'     => ['admins.php', 'fa-user-shield', 'Admin Accounts'],
    'reports'    => ['reports.php', 'fa-chart-line', 'Sales Report'],
    'settings'   => ['settings.php', 'fa-gear', 'Store Settings'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($adminTitle) ?> &middot; Bloomify Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/dialog.css')) ?>">
</head>
<body>
<div class="admin-shell" id="adminShell">
    <aside class="admin-sidebar">
        <a class="admin-logo" href="<?= e(url('admin/index.php')) ?>">Bloomify</a>
        <p class="admin-logo-sub">Flower Studio Admin</p>

        <nav class="admin-nav" aria-label="Admin navigation">
            <?php foreach ($adminNavigation as $key => $item): ?>
                <a href="<?= e(url('admin/' . $item[0])) ?>"
                   class="<?= $activeAdmin === $key ? 'active' : '' ?>">
                    <i class="fa-solid <?= e($item[1]) ?>"></i>
                    <span><?= e($item[2]) ?></span>
                    <?php if ($key === 'orders' && $pendingOrderCount > 0): ?>
                        <span class="nav-badge"><?= $pendingOrderCount ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>

            <div class="divider"></div>

            <a href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                <span>View Store</span>
            </a>
        </nav>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <div class="admin-topbar-start">
                <button type="button" class="admin-toggle" id="adminSidebarToggle" aria-label="Toggle menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h1><?= e($adminTitle) ?></h1>
            </div>

            <div class="admin-user">
                <span>Hi, <strong><?= e($admin['full_name'] ?? 'Admin') ?></strong></span>
                <form method="post" action="<?= e(url('admin/logout.php')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-sm">Logout</button>
                </form>
            </div>
        </header>

        <main class="admin-content">
            <?php foreach (['success', 'error', 'info'] as $flashType): ?>
                <?php $flashMessage = flash_get($flashType); ?>
                <?php if ($flashMessage): ?>
                    <div class="alert alert-<?= e($flashType) ?>">
                        <?= e(is_array($flashMessage) ? implode(' ', $flashMessage) : $flashMessage) ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
