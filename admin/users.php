<?php
/**
 * Bloomify - customer list and read-only detail.
 */

require_once __DIR__ . '/includes/auth.php';

$adminTitle = 'Customers';
$activeAdmin = 'users';

$search = trim((string) ($_GET['q'] ?? ''));
$viewId = (int) ($_GET['id'] ?? 0);

$viewedUser = null;
$viewedOrders = [];

if ($viewId > 0) {
    $userStatement = db()->prepare(
        'SELECT id, full_name, username, phone, address, profile_photo,
                profile_description, created_at
         FROM users
         WHERE id = ?
         LIMIT 1'
    );
    $userStatement->execute([$viewId]);
    $viewedUser = $userStatement->fetch() ?: null;

    if (!$viewedUser) {
        flash_set('error', 'Customer not found.');
        redirect('admin/users.php');
    }

    $orderStatement = db()->prepare(
        'SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC'
    );
    $orderStatement->execute([$viewId]);
    $viewedOrders = $orderStatement->fetchAll();
}

$sql = 'SELECT u.id, u.full_name, u.username, u.phone, u.profile_photo, u.created_at,
               COUNT(o.id) AS order_count,
               COALESCE(SUM(CASE WHEN o.payment_status = \'Paid\' AND o.order_status <> \'Rejected\' THEN o.grand_total ELSE 0 END), 0) AS total_paid
        FROM users u
        LEFT JOIN orders o ON o.user_id = u.id';
$parameters = [];

if ($search !== '') {
    $sql .= ' WHERE u.full_name LIKE ? OR u.username LIKE ? OR u.phone LIKE ?';
    $like = '%' . $search . '%';
    $parameters[] = $like;
    $parameters[] = $like;
    $parameters[] = $like;
}

$sql .= ' GROUP BY u.id ORDER BY u.created_at DESC, u.id DESC';

$statement = db()->prepare($sql);
$statement->execute($parameters);
$users = $statement->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<?php if ($viewedUser): ?>
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2><?= e($viewedUser['full_name']) ?></h2>
                <p>Customer since <?= e(format_date($viewedUser['created_at'])) ?>.</p>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= e(url('admin/users.php')) ?>">&larr; All Customers</a>
        </div>

        <div class="grid-2">
            <div>
                <div class="info-card">
                    <div class="customer-identity" style="margin-bottom: 16px;">
                        <?php if (!empty($viewedUser['profile_photo'])): ?>
                            <img class="customer-avatar large" src="<?= e(public_image_url($viewedUser['profile_photo'])) ?>" alt="<?= e($viewedUser['full_name']) ?> profile picture">
                        <?php else: ?>
                            <div class="customer-avatar large" style="display:grid;place-items:center;font-size:28px;color:var(--maroon);"><?= e(mb_strtoupper(mb_substr($viewedUser['full_name'], 0, 1))) ?></div>
                        <?php endif; ?>
                        <div><h2 style="margin:0;">Customer Details</h2><p class="cell-muted">Profile picture and account information</p></div>
                    </div>
                    <ul class="info-list">
                        <li><span class="label">Username</span> @<?= e($viewedUser['username']) ?></li>
                        <li><span class="label">Phone</span> <?= e($viewedUser['phone']) ?></li>
                        <li><span class="label">Address</span> <?= e($viewedUser['address']) ?></li>
                        <li><span class="label">Profile note</span> <?= e($viewedUser['profile_description'] ?: '-') ?></li>
                        <li><span class="label">Joined</span> <?= e(format_datetime($viewedUser['created_at'])) ?></li>
                    </ul>
                </div>
            </div>

            <div>
                <div class="info-card">
                    <h2>Order Summary</h2>
                    <?php
                    $paidTotal = 0.0;
                    $paidCount = 0;
                    foreach ($viewedOrders as $customerOrder) {
                        if ($customerOrder['payment_status'] === 'Paid' && $customerOrder['order_status'] !== 'Rejected') {
                            $paidTotal += (float) $customerOrder['grand_total'];
                            $paidCount++;
                        }
                    }
                    ?>
                    <ul class="info-list">
                        <li><span class="label">Orders</span> <?= count($viewedOrders) ?></li>
                        <li><span class="label">Paid orders</span> <?= $paidCount ?></li>
                        <li><span class="label">Paid total</span> <?= e(format_rupiah($paidTotal)) ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="panel" style="margin-bottom: 0; box-shadow: none; padding: 0;">
            <div class="panel-head">
                <h2>Order History</h2>
            </div>

            <?php if ($viewedOrders): ?>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Date</th>
                                <th>Fulfillment</th>
                                <th>Total</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th class="text-right"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($viewedOrders as $customerOrder): ?>
                                <tr>
                                    <td class="cell-strong"><?= e($customerOrder['order_code']) ?></td>
                                    <td class="cell-muted"><?= e(format_datetime($customerOrder['created_at'])) ?></td>
                                    <td><?= $customerOrder['fulfillment_method'] === 'delivery' ? 'Delivery' : 'Pickup' ?></td>
                                    <td><?= e(format_rupiah($customerOrder['grand_total'])) ?></td>
                                    <td>
                                        <span class="badge <?= e(payment_status_class($customerOrder['payment_status'])) ?>">
                                            <?= e(payment_status_label($customerOrder['payment_status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= e(order_status_class($customerOrder['order_status'])) ?>">
                                            <?= e(order_status_label($customerOrder['order_status'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <a class="btn btn-soft btn-sm"
                                           href="<?= e(url('admin/order-detail.php?code=' . rawurlencode($customerOrder['order_code']))) ?>">
                                            Open
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="form-help">This customer has not placed any orders yet.</p>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Customers</h2>
                <p><?= count($users) ?> customer(s) found. Customer data is read-only.</p>
            </div>
        </div>

        <form class="filter-form" method="get" action="<?= e(url('admin/users.php')) ?>">
            <div class="form-field search">
                <label for="q">Search</label>
                <input type="text" id="q" name="q" class="form-control"
                       value="<?= e($search) ?>" placeholder="Name, username, or phone...">
            </div>
            <button type="submit" class="btn btn-soft">Search</button>
            <a href="<?= e(url('admin/users.php')) ?>" class="btn btn-outline">Reset</a>
        </form>

        <?php if ($users): ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Orders</th>
                            <th>Paid Total</th>
                            <th>Joined</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="customer-identity">
                                        <?php if (!empty($user['profile_photo'])): ?>
                                            <img class="customer-avatar" src="<?= e(public_image_url($user['profile_photo'])) ?>" alt="<?= e($user['full_name']) ?> profile picture">
                                        <?php else: ?>
                                            <div class="customer-avatar" style="display:grid;place-items:center;color:var(--maroon);font-weight:700;"><?= e(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?></div>
                                        <?php endif; ?>
                                        <div><div class="cell-strong"><?= e($user['full_name']) ?></div><div class="cell-muted">@<?= e($user['username']) ?></div></div>
                                    </div>
                                </td>
                                <td><?= e($user['phone']) ?></td>
                                <td><?= (int) $user['order_count'] ?></td>
                                <td><?= e(format_rupiah($user['total_paid'])) ?></td>
                                <td class="cell-muted"><?= e(format_date($user['created_at'])) ?></td>
                                <td class="text-right">
                                    <a class="btn btn-soft btn-sm"
                                       href="<?= e(url('admin/users.php?id=' . (int) $user['id'])) ?>">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-users"></i>
                <h2>No customers found</h2>
                <p>Customer accounts will appear here after registration.</p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
