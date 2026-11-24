<?php
/**
 * Bloomify - admin dashboard.
 */

require_once __DIR__ . '/includes/auth.php';

$adminTitle = 'Dashboard';
$activeAdmin = 'dashboard';

$pdo = db();

$totalOrders = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$pendingOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Pending'")->fetchColumn();
$processOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Proses'")->fetchColumn();
$completedOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Selesai'")->fetchColumn();
$totalCustomers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalProducts = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$revenue = (float) $pdo->query(
    "SELECT COALESCE(SUM(grand_total), 0) FROM orders
     WHERE payment_status = 'Paid' AND order_status <> 'Rejected'"
)->fetchColumn();

$recentOrders = $pdo->query(
    'SELECT o.*, u.full_name AS customer_name, u.profile_photo AS customer_profile_photo
     FROM orders o
     JOIN users u ON u.id = o.user_id
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT 8'
)->fetchAll();

$lowFlowers = $pdo->query(
    'SELECT ft.name, ft.color, ft.id, fs.quantity, fs.low_stock_threshold
     FROM flower_types ft
     JOIN flower_stocks fs ON fs.flower_type_id = ft.id
     WHERE fs.quantity <= fs.low_stock_threshold
     ORDER BY fs.quantity ASC
     LIMIT 6'
)->fetchAll();

$lowProducts = $pdo->query(
    "SELECT p.id, p.name, c.name AS category_name, ps.quantity, ps.low_stock_threshold
     FROM products p
     JOIN categories c ON c.id = p.category_id
     JOIN product_stocks ps ON ps.product_id = p.id
     WHERE COALESCE(p.pricing_mode, c.pricing_mode) = 'fixed'
       AND ps.quantity <= ps.low_stock_threshold
     ORDER BY ps.quantity ASC
     LIMIT 6"
)->fetchAll();

$chartDays = [];
$startDate = date('Y-m-d', strtotime('-6 days'));
for ($i = 6; $i >= 0; $i--) {
    $timestamp = strtotime("-$i days");
    $chartDays[date('Y-m-d', $timestamp)] = [
        'label' => date('d M', $timestamp),
        'orders' => 0,
        'total' => 0.0,
    ];
}

$chartStatement = $pdo->prepare(
    "SELECT DATE(created_at) AS day, COUNT(*) AS orders, COALESCE(SUM(grand_total), 0) AS total
     FROM orders
     WHERE created_at >= ? AND order_status <> 'Rejected'
     GROUP BY DATE(created_at)"
);
$chartStatement->execute([$startDate . ' 00:00:00']);

foreach ($chartStatement->fetchAll() as $row) {
    if (isset($chartDays[$row['day']])) {
        $chartDays[$row['day']]['orders'] = (int) $row['orders'];
        $chartDays[$row['day']]['total'] = (float) $row['total'];
    }
}

$maxChartTotal = 0.0;
foreach ($chartDays as $day) {
    $maxChartTotal = max($maxChartTotal, $day['total']);
}

require __DIR__ . '/includes/header.php';
?>

<div class="stat-grid">
    <div class="stat-card">
        <div class="label">Total Orders</div>
        <div class="value"><?= $totalOrders ?></div>
        <div class="hint"><?= $pendingOrders ?> waiting for confirmation</div>
    </div>
    <div class="stat-card accent-maroon">
        <div class="label">Pending</div>
        <div class="value"><?= $pendingOrders ?></div>
        <div class="hint">Needs stock deduction</div>
    </div>
    <div class="stat-card">
        <div class="label">In Process</div>
        <div class="value"><?= $processOrders ?></div>
        <div class="hint">Being prepared</div>
    </div>
    <div class="stat-card accent-pink">
        <div class="label">Completed</div>
        <div class="value"><?= $completedOrders ?></div>
        <div class="hint">Finished orders</div>
    </div>
    <div class="stat-card accent-pink">
        <div class="label">Revenue (Paid)</div>
        <div class="value" style="font-size: 20px;"><?= e(format_rupiah($revenue)) ?></div>
        <div class="hint">Only paid orders</div>
    </div>
    <div class="stat-card">
        <div class="label">Customers</div>
        <div class="value"><?= $totalCustomers ?></div>
        <div class="hint"><?= $totalProducts ?> products in catalog</div>
    </div>
</div>

<div class="grid-2">
    <div>
        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2>Last 7 Days</h2>
                    <p>Order value per day (all payment statuses).</p>
                </div>
                <a class="btn btn-soft btn-sm" href="<?= e(url('admin/reports.php')) ?>">Open Report</a>
            </div>

            <div class="bar-chart">
                <?php foreach ($chartDays as $day): ?>
                    <?php
                    $height = $maxChartTotal > 0
                        ? max(3, (int) round(($day['total'] / $maxChartTotal) * 100))
                        : 3;
                    ?>
                    <div class="bar-col">
                        <span class="bar-value"><?= (int) $day['orders'] ?></span>
                        <div class="bar-track">
                            <div class="bar <?= $day['total'] > 0 ? 'has-value' : '' ?>"
                                 style="height: <?= $height ?>%;"
                                 title="<?= e(format_rupiah($day['total'])) ?>"></div>
                        </div>
                        <span class="bar-label"><?= e($day['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Recent Orders</h2>
                <a class="btn btn-outline btn-sm" href="<?= e(url('admin/orders.php')) ?>">All Orders</a>
            </div>

            <?php if ($recentOrders): ?>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td class="cell-strong"><?= e($order['order_code']) ?></td>
                                    <td><span class="customer-identity"><?php if (!empty($order['customer_profile_photo'])): ?><img class="customer-avatar" src="<?= e(public_image_url($order['customer_profile_photo'])) ?>" alt="Customer profile picture"><?php else: ?><span class="customer-avatar" style="display:grid;place-items:center;"><?= e(mb_strtoupper(mb_substr($order['customer_name'], 0, 1))) ?></span><?php endif; ?><?= e($order['customer_name']) ?></span></td>
                                    <td class="cell-muted"><?= e(format_datetime($order['created_at'])) ?></td>
                                    <td><?= e(format_rupiah($order['grand_total'])) ?></td>
                                    <td>
                                        <span class="badge <?= e(order_status_class($order['order_status'])) ?>">
                                            <?= e(order_status_label($order['order_status'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <a class="btn btn-soft btn-sm"
                                           href="<?= e(url('admin/order-detail.php?code=' . rawurlencode($order['order_code']))) ?>">
                                            Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding: 30px 10px;">
                    <i class="fa-regular fa-receipt"></i>
                    <h2>No orders yet</h2>
                    <p>New orders will appear here as soon as customers check out.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="panel">
            <div class="panel-head">
                <h2>Low Stock</h2>
                <a class="btn btn-outline btn-sm" href="<?= e(url('admin/stock.php')) ?>">Manage</a>
            </div>

            <?php if ($lowFlowers || $lowProducts): ?>
                <ul class="simple-list">
                    <?php foreach ($lowFlowers as $flower): ?>
                        <li>
                            <span>
                                <i class="fa-solid fa-spa" style="color: var(--pink);"></i>
                                <?= e($flower['name']) ?><?= !empty($flower['color']) ? ' — ' . e($flower['color']) : '' ?>
                                <span class="meta">(flower)</span>
                            </span>
                            <span class="low-stock"><?= (int) $flower['quantity'] ?> left</span>
                        </li>
                    <?php endforeach; ?>

                    <?php foreach ($lowProducts as $product): ?>
                        <li>
                            <span>
                                <i class="fa-solid fa-box" style="color: var(--pink);"></i>
                                <?= e($product['name']) ?>
                                <span class="meta">(<?= e($product['category_name']) ?>)</span>
                            </span>
                            <span class="low-stock"><?= (int) $product['quantity'] ?> left</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="form-help">All stock levels are healthy.</p>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h2>Quick Actions</h2>
            <div class="row-actions" style="flex-direction: column; align-items: stretch;">
                <a class="btn btn-primary btn-block" href="<?= e(url('admin/product-form.php')) ?>">
                    <i class="fa-solid fa-plus"></i> Add Product
                </a>
                <a class="btn btn-soft btn-block" href="<?= e(url('admin/orders.php?status=Pending')) ?>">
                    Review Pending Orders
                </a>
                <a class="btn btn-outline btn-block" href="<?= e(url('admin/stock.php')) ?>">
                    Adjust Stock
                </a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
