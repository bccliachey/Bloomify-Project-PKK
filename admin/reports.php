<?php
/**
 * Bloomify - sales reports.
 */

require_once __DIR__ . '/includes/auth.php';

$adminTitle = 'Sales Report';
$activeAdmin = 'reports';

$period = (string) ($_GET['period'] ?? 'monthly');

if (!in_array($period, ['daily', 'weekly', 'monthly', 'custom'], true)) {
    $period = 'monthly';
}

$today = date('Y-m-d');
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));

if ($period === 'daily') {
    $dateFrom = $today;
    $dateTo = $today;
} elseif ($period === 'weekly') {
    $dateFrom = date('Y-m-d', strtotime('-6 days'));
    $dateTo = $today;
} elseif ($period === 'monthly') {
    $dateFrom = date('Y-m-01');
    $dateTo = $today;
} else {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $dateFrom = date('Y-m-01');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $dateTo = $today;
    }

    if (strtotime($dateFrom) > strtotime($dateTo)) {
        [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    }
}

$start = $dateFrom . ' 00:00:00';
$end = $dateTo . ' 23:59:59';

$summaryStatement = db()->prepare(
    "SELECT COUNT(*) AS orders,
            COALESCE(SUM(grand_total), 0) AS total_value,
            COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN grand_total ELSE 0 END), 0) AS revenue,
            COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN 1 ELSE 0 END), 0) AS paid_orders,
            COALESCE(SUM(delivery_fee), 0) AS delivery_total
     FROM orders
     WHERE created_at BETWEEN ? AND ? AND order_status <> 'Rejected'"
);
$summaryStatement->execute([$start, $end]);
$summary = $summaryStatement->fetch();

$unitsStatement = db()->prepare(
    "SELECT COALESCE(SUM(CASE WHEN oi.item_type = 'fixed' THEN oi.quantity ELSE oi.stem_total END), 0) AS units
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE o.created_at BETWEEN ? AND ? AND o.order_status <> 'Rejected'"
);
$unitsStatement->execute([$start, $end]);
$unitsSold = (int) $unitsStatement->fetchColumn();

$bestSellersStatement = db()->prepare(
    "SELECT oi.product_name, oi.item_type,
            SUM(CASE WHEN oi.item_type = 'fixed' THEN oi.quantity ELSE oi.stem_total END) AS units,
            SUM(oi.item_subtotal) AS sales,
            COUNT(DISTINCT oi.order_id) AS orders
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE o.created_at BETWEEN ? AND ? AND o.order_status <> 'Rejected'
     GROUP BY oi.product_name, oi.item_type
     ORDER BY units DESC, sales DESC
     LIMIT 8"
);
$bestSellersStatement->execute([$start, $end]);
$bestSellers = $bestSellersStatement->fetchAll();

$statusStatement = db()->prepare(
    'SELECT order_status, COUNT(*) AS total
     FROM orders
     WHERE created_at BETWEEN ? AND ?
     GROUP BY order_status'
);
$statusStatement->execute([$start, $end]);
$statusCounts = [];
foreach ($statusStatement->fetchAll() as $row) {
    $statusCounts[$row['order_status']] = (int) $row['total'];
}

$chartStatement = db()->prepare(
    "SELECT DATE(created_at) AS day,
            COUNT(*) AS orders,
            COALESCE(SUM(grand_total), 0) AS total,
            COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN grand_total ELSE 0 END), 0) AS revenue
     FROM orders
     WHERE created_at BETWEEN ? AND ? AND order_status <> 'Rejected'
     GROUP BY DATE(created_at)
     ORDER BY day ASC"
);
$chartStatement->execute([$start, $end]);
$chartRows = $chartStatement->fetchAll();

$maxChartTotal = 0.0;
foreach ($chartRows as $row) {
    $maxChartTotal = max($maxChartTotal, (float) $row['total']);
}

$periodLabels = [
    'daily'   => 'Today',
    'weekly'  => 'Last 7 Days',
    'monthly' => 'This Month',
    'custom'  => 'Custom Range',
];

$statuses = ['Pending', 'Proses', 'Dikirim', 'Siap Diambil', 'Selesai', 'Rejected'];

require __DIR__ . '/includes/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2><?= e($periodLabels[$period]) ?></h2>
            <p>
                <?= e(format_date($dateFrom)) ?> &ndash; <?= e(format_date($dateTo)) ?>
                &middot; Revenue counts only paid orders.
            </p>
        </div>
    </div>

    <form class="filter-form" method="get" action="<?= e(url('admin/reports.php')) ?>">
        <div class="form-field">
            <label for="reportPeriod">Period</label>
            <select id="reportPeriod" name="period" class="form-control">
                <?php foreach ($periodLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $period === $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field <?= $period === 'custom' ? '' : 'hidden' ?>" data-custom-range>
            <label for="date_from">From</label>
            <input type="date" id="date_from" name="date_from" class="form-control"
                   value="<?= e($dateFrom) ?>">
        </div>

        <div class="form-field <?= $period === 'custom' ? '' : 'hidden' ?>" data-custom-range>
            <label for="date_to">To</label>
            <input type="date" id="date_to" name="date_to" class="form-control"
                   value="<?= e($dateTo) ?>">
        </div>

        <button type="submit" class="btn btn-soft">Apply</button>
    </form>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="label">Orders</div>
        <div class="value"><?= (int) $summary['orders'] ?></div>
        <div class="hint"><?= (int) $summary['paid_orders'] ?> paid</div>
    </div>
    <div class="stat-card accent-green">
        <div class="label">Revenue (Paid)</div>
        <div class="value" style="font-size: 20px;"><?= e(format_rupiah($summary['revenue'])) ?></div>
        <div class="hint">Paid orders only</div>
    </div>
    <div class="stat-card">
        <div class="label">Order Value</div>
        <div class="value" style="font-size: 20px;"><?= e(format_rupiah($summary['total_value'])) ?></div>
        <div class="hint">All payment statuses</div>
    </div>
    <div class="stat-card">
        <div class="label">Units Sold</div>
        <div class="value"><?= $unitsSold ?></div>
        <div class="hint">Fixed-price items + stems</div>
    </div>
    <div class="stat-card">
        <div class="label">Delivery Fees</div>
        <div class="value" style="font-size: 20px;"><?= e(format_rupiah($summary['delivery_total'])) ?></div>
        <div class="hint">Collected with orders</div>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Daily Order Value</h2>
    </div>

    <?php if ($chartRows): ?>
        <div class="bar-chart">
            <?php foreach ($chartRows as $row): ?>
                <?php
                $height = $maxChartTotal > 0
                    ? max(3, (int) round(((float) $row['total'] / $maxChartTotal) * 100))
                    : 3;
                ?>
                <div class="bar-col">
                    <span class="bar-value"><?= (int) $row['orders'] ?></span>
                    <div class="bar-track">
                        <div class="bar has-value" style="height: <?= $height ?>%;"
                             title="<?= e(format_rupiah($row['total'])) ?> total, <?= e(format_rupiah($row['revenue'])) ?> paid"></div>
                    </div>
                    <span class="bar-label"><?= e(format_date($row['day'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state" style="padding: 28px 10px;">
            <i class="fa-solid fa-chart-line"></i>
            <h2>No data for this period</h2>
            <p>Try choosing a different date range.</p>
        </div>
    <?php endif; ?>
</div>

<div class="grid-2">
    <div class="panel">
        <div class="panel-head">
            <h2>Best Sellers</h2>
        </div>

        <?php if ($bestSellers): ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Units</th>
                            <th>Orders</th>
                            <th>Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bestSellers as $seller): ?>
                            <tr>
                                <td>
                                    <div class="cell-strong"><?= e($seller['product_name']) ?></div>
                                    <div class="cell-muted"><?= e($seller['item_type']) ?></div>
                                </td>
                                <td><?= (int) $seller['units'] ?></td>
                                <td><?= (int) $seller['orders'] ?></td>
                                <td><?= e(format_rupiah($seller['sales'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="form-help">No products sold in this period.</p>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2>Orders by Status</h2>
        </div>

        <ul class="simple-list">
            <?php foreach ($statuses as $status): ?>
                <li>
                    <span class="badge <?= e(order_status_class($status)) ?>"><?= e(order_status_label($status)) ?></span>
                    <span class="cell-strong"><?= $statusCounts[$status] ?? 0 ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
