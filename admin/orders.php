<?php
/**
 * Bloomify - admin order list with filters.
 */

require_once __DIR__ . '/includes/auth.php';

$adminTitle = 'Orders';
$activeAdmin = 'orders';

$statusOptions = ['Pending', 'Proses', 'Dikirim', 'Siap Diambil', 'Selesai', 'Rejected'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();
    if ((string) ($_POST['action'] ?? '') === 'delete_completed') {
        $deleteId = (int) ($_POST['id'] ?? 0);
        try {
            db()->beginTransaction();
            $lock = db()->prepare('SELECT id, order_code, order_status FROM orders WHERE id = ? FOR UPDATE');
            $lock->execute([$deleteId]);
            $target = $lock->fetch();
            if (!$target || $target['order_status'] !== 'Selesai') {
                throw new RuntimeException('Only completed orders can be deleted.');
            }
            $delete = db()->prepare("DELETE FROM orders WHERE id = ? AND order_status = 'Selesai'");
            $delete->execute([$deleteId]);
            db()->commit();
            flash_set('success', 'Completed order ' . $target['order_code'] . ' was removed from both order histories.');
        } catch (Throwable $exception) {
            if (db()->inTransaction()) db()->rollBack();
            flash_set('error', $exception->getMessage() ?: 'The order could not be deleted.');
        }
        redirect('admin/orders.php');
    }
    flash_set('error', 'Unknown action.');
    redirect('admin/orders.php');
}

$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$fulfillment = (string) ($_GET['fulfillment'] ?? '');
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));

if (!in_array($status, $statusOptions, true)) {
    $status = '';
}

if (!in_array($fulfillment, ['delivery', 'pickup'], true)) {
    $fulfillment = '';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

$sql = 'SELECT o.*, u.full_name AS customer_name, u.username AS customer_username, u.profile_photo AS customer_profile_photo
        FROM orders o
        JOIN users u ON u.id = o.user_id
        WHERE 1 = 1';
$parameters = [];

if ($search !== '') {
    $sql .= ' AND (o.order_code LIKE ? OR u.full_name LIKE ? OR o.recipient_name LIKE ?)';
    $like = '%' . $search . '%';
    $parameters[] = $like;
    $parameters[] = $like;
    $parameters[] = $like;
}

if ($status !== '') {
    $sql .= ' AND o.order_status = ?';
    $parameters[] = $status;
}

if ($fulfillment !== '') {
    $sql .= ' AND o.fulfillment_method = ?';
    $parameters[] = $fulfillment;
}

if ($dateFrom !== '') {
    $sql .= ' AND o.created_at >= ?';
    $parameters[] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $sql .= ' AND o.created_at <= ?';
    $parameters[] = $dateTo . ' 23:59:59';
}

$sql .= ' ORDER BY o.created_at DESC, o.id DESC LIMIT 300';

$statement = db()->prepare($sql);
$statement->execute($parameters);
$orders = $statement->fetchAll();

$pendingCount = (int) db()
    ->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Pending'")
    ->fetchColumn();

require __DIR__ . '/includes/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Orders</h2>
            <p><?= count($orders) ?> order(s) shown &middot; <?= $pendingCount ?> pending confirmation.</p>
        </div>
        <?php if ($pendingCount > 0): ?>
            <a class="btn btn-soft btn-sm" href="<?= e(url('admin/orders.php?status=Pending')) ?>">
                View Pending Only
            </a>
        <?php endif; ?>
    </div>

    <form class="filter-form" method="get" action="<?= e(url('admin/orders.php')) ?>">
        <div class="form-field search">
            <label for="q">Search</label>
            <input type="text" id="q" name="q" class="form-control"
                   value="<?= e($search) ?>" placeholder="Order code, customer, recipient...">
        </div>

        <div class="form-field">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control">
                <option value="">All statuses</option>
                <?php foreach ($statusOptions as $option): ?>
                    <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>>
                        <?= e(order_status_label($option)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field">
            <label for="fulfillment">Fulfillment</label>
            <select id="fulfillment" name="fulfillment" class="form-control">
                <option value="">All methods</option>
                <option value="delivery" <?= $fulfillment === 'delivery' ? 'selected' : '' ?>>Delivery</option>
                <option value="pickup" <?= $fulfillment === 'pickup' ? 'selected' : '' ?>>Self Pickup</option>
            </select>
        </div>

        <div class="form-field">
            <label for="date_from">From</label>
            <input type="date" id="date_from" name="date_from" class="form-control"
                   value="<?= e($dateFrom) ?>">
        </div>

        <div class="form-field">
            <label for="date_to">To</label>
            <input type="date" id="date_to" name="date_to" class="form-control"
                   value="<?= e($dateTo) ?>">
        </div>

        <button type="submit" class="btn btn-soft">Filter</button>
        <a href="<?= e(url('admin/orders.php')) ?>" class="btn btn-outline">Reset</a>
    </form>

    <?php if ($orders): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Recipient</th>
                        <th>Fulfillment</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <div class="cell-strong"><?= e($order['order_code']) ?></div>
                                <div class="cell-muted"><?= e(format_datetime($order['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="customer-identity">
                                    <?php if (!empty($order['customer_profile_photo'])): ?>
                                        <img class="customer-avatar" src="<?= e(public_image_url($order['customer_profile_photo'])) ?>" alt="<?= e($order['customer_name']) ?> profile picture">
                                    <?php else: ?>
                                        <span class="customer-avatar" style="display:grid;place-items:center;color:var(--maroon);font-weight:700;"><?= e(mb_strtoupper(mb_substr($order['customer_name'], 0, 1))) ?></span>
                                    <?php endif; ?>
                                    <div><?= e($order['customer_name']) ?><div class="cell-muted">@<?= e($order['customer_username']) ?></div></div>
                                </div>
                            </td>
                            <td>
                                <?= e($order['recipient_name']) ?>
                                <div class="cell-muted"><?= e($order['recipient_phone']) ?></div>
                            </td>
                            <td>
                                <?= $order['fulfillment_method'] === 'delivery' ? 'Delivery' : 'Pickup' ?>
                            </td>
                            <td><?= e(format_rupiah($order['grand_total'])) ?></td>
                            <td>
                                <span class="badge <?= e(payment_status_class($order['payment_status'])) ?>">
                                    <?= e(payment_status_label($order['payment_status'])) ?>
                                </span>
                                <div class="cell-muted"><?= e(payment_method_label($order['payment_method'])) ?></div>
                            </td>
                            <td>
                                <span class="badge <?= e(order_status_class($order['order_status'])) ?>">
                                    <?= e(order_status_label($order['order_status'])) ?>
                                </span>
                                <?php if ($order['order_status'] === 'Rejected' && !empty($order['rejection_reason'])): ?>
                                    <div class="cell-muted"><?= e($order['rejection_reason']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <a class="btn btn-soft btn-sm"
                                   href="<?= e(url('admin/order-detail.php?code=' . rawurlencode($order['order_code']))) ?>">
                                    Manage
                                </a>
                                <?php if ($order['order_status'] === 'Selesai'): ?>
                                    <form method="post" action="<?= e(url('admin/orders.php')) ?>" style="display:inline;" data-confirm="Permanently delete this completed order from both Admin and customer history?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_completed">
                                        <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                                        <button type="submit" class="btn btn-outline btn-sm">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-receipt"></i>
            <h2>No orders found</h2>
            <p>Try clearing the filters or wait for a new checkout.</p>
            <a class="btn btn-outline" href="<?= e(url('admin/orders.php')) ?>">Reset Filters</a>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
