<?php
/**
 * Bloomify - administrator accounts.
 *
 * Rules:
 * - Prevent deactivating your own account.
 * - Prevent deactivating the last active administrator.
 */

require_once __DIR__ . '/includes/auth.php';

$adminTitle = 'Admin Accounts';
$activeAdmin = 'admins';

$currentAdmin = current_admin();
$selfId = (int) $currentAdmin['id'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        $errors = [];

        if ($fullName === '' || mb_strlen($fullName) > 100) {
            $errors[] = 'Full name is required (maximum 100 characters).';
        }

        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            $errors[] = 'Username must be 3-50 characters and use only letters, numbers, dots, dashes, or underscores.';
        }

        if (!password_is_valid($password)) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        if ($password !== $confirmation) {
            $errors[] = 'Password confirmation does not match.';
        }

        if (!$errors) {
            $duplicate = db()->prepare('SELECT COUNT(*) FROM admin_accounts WHERE username = ?');
            $duplicate->execute([$username]);

            if ((int) $duplicate->fetchColumn() > 0) {
                $errors[] = 'That username is already in use.';
            }
        }

        if ($errors) {
            flash_set('error', implode(' ', $errors));
            redirect('admin/admins.php');
        }

        $insert = db()->prepare(
            'INSERT INTO admin_accounts (full_name, username, password_hash, is_active)
             VALUES (?, ?, ?, 1)'
        );
        $insert->execute([$fullName, $username, password_hash($password, PASSWORD_DEFAULT)]);

        flash_set('success', 'Administrator account created.');
        redirect('admin/admins.php');
    }

    if ($action === 'update') {
        $targetId = (int) ($_POST['id'] ?? 0);
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $errors = [];

        $lookup = db()->prepare('SELECT * FROM admin_accounts WHERE id = ? LIMIT 1');
        $lookup->execute([$targetId]);
        $target = $lookup->fetch();

        if (!$target) {
            flash_set('error', 'Administrator not found.');
            redirect('admin/admins.php');
        }

        if ($fullName === '' || mb_strlen($fullName) > 100) {
            $errors[] = 'Full name is required (maximum 100 characters).';
        }

        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            $errors[] = 'Username must be 3-50 characters and use only letters, numbers, dots, dashes, or underscores.';
        }

        if (!$errors) {
            $duplicate = db()->prepare(
                'SELECT COUNT(*) FROM admin_accounts WHERE username = ? AND id <> ?'
            );
            $duplicate->execute([$username, $targetId]);

            if ((int) $duplicate->fetchColumn() > 0) {
                $errors[] = 'That username is already in use.';
            }
        }

        if ($targetId === $selfId && $isActive === 0) {
            $errors[] = 'You cannot deactivate your own account.';
        }

        if ((int) $target['is_active'] === 1 && $isActive === 0) {
            $activeCount = db()->prepare(
                'SELECT COUNT(*) FROM admin_accounts WHERE is_active = 1 AND id <> ?'
            );
            $activeCount->execute([$targetId]);

            if ((int) $activeCount->fetchColumn() === 0) {
                $errors[] = 'At least one active administrator is required.';
            }
        }

        if ($errors) {
            flash_set('error', implode(' ', $errors));
            redirect('admin/admins.php?edit=' . $targetId);
        }

        $update = db()->prepare(
            'UPDATE admin_accounts SET full_name = ?, username = ?, is_active = ? WHERE id = ?'
        );
        $update->execute([$fullName, $username, $isActive, $targetId]);

        flash_set('success', 'Administrator account updated.');
        redirect('admin/admins.php');
    }

    if ($action === 'password') {
        $targetId = (int) ($_POST['id'] ?? 0);
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        $errors = [];

        $lookup = db()->prepare('SELECT id FROM admin_accounts WHERE id = ? LIMIT 1');
        $lookup->execute([$targetId]);

        if (!$lookup->fetch()) {
            flash_set('error', 'Administrator not found.');
            redirect('admin/admins.php');
        }

        if (!password_is_valid($password)) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        if ($password !== $confirmation) {
            $errors[] = 'Password confirmation does not match.';
        }

        if ($errors) {
            flash_set('error', implode(' ', $errors));
            redirect('admin/admins.php?edit=' . $targetId);
        }

        $update = db()->prepare('UPDATE admin_accounts SET password_hash = ? WHERE id = ?');
        $update->execute([password_hash($password, PASSWORD_DEFAULT), $targetId]);

        flash_set('success', 'Administrator password has been reset.');
        redirect('admin/admins.php');
    }

    flash_set('error', 'Unknown action.');
    redirect('admin/admins.php');
}

$editId = (int) ($_GET['edit'] ?? 0);
$editingAdmin = null;

if ($editId > 0) {
    $lookup = db()->prepare('SELECT * FROM admin_accounts WHERE id = ? LIMIT 1');
    $lookup->execute([$editId]);
    $editingAdmin = $lookup->fetch() ?: null;

    if (!$editingAdmin) {
        flash_set('error', 'Administrator not found.');
        redirect('admin/admins.php');
    }
}

$admins = db()->query(
    'SELECT id, full_name, username, is_active, created_at
     FROM admin_accounts
     ORDER BY is_active DESC, id ASC'
)->fetchAll();

$activeCount = 0;
foreach ($admins as $adminRow) {
    if ((int) $adminRow['is_active'] === 1) {
        $activeCount++;
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="grid-2">
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Administrators</h2>
                <p><?= $activeCount ?> active &middot; <?= count($admins) ?> total.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admins as $adminRow): ?>
                        <tr>
                            <td>
                                <div class="cell-strong">
                                    <?= e($adminRow['full_name']) ?>
                                    <?php if ((int) $adminRow['id'] === $selfId): ?>
                                        <span class="cell-muted">(you)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>@<?= e($adminRow['username']) ?></td>
                            <td>
                                <?php if ((int) $adminRow['is_active'] === 1): ?>
                                    <span class="badge status-visible">Active</span>
                                <?php else: ?>
                                    <span class="badge status-hidden">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-muted"><?= e(format_date($adminRow['created_at'])) ?></td>
                            <td class="text-right">
                                <a class="btn btn-soft btn-sm"
                                   href="<?= e(url('admin/admins.php?edit=' . (int) $adminRow['id'])) ?>">
                                    Manage
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="alert alert-info" style="margin: 18px 0 0;">
            Deactivated administrators cannot log in. At least one active administrator
            must always remain, and you cannot deactivate your own account.
        </div>
    </div>

    <div>
        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2><?= $editingAdmin ? 'Edit Administrator' : 'New Administrator' ?></h2>
                    <p><?= $editingAdmin ? 'Update account details or activation.' : 'Create another admin account.' ?></p>
                </div>
                <?php if ($editingAdmin): ?>
                    <a class="btn btn-outline btn-sm" href="<?= e(url('admin/admins.php')) ?>">New</a>
                <?php endif; ?>
            </div>

            <form method="post" action="<?= e(url('admin/admins.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editingAdmin ? 'update' : 'create' ?>">
                <?php if ($editingAdmin): ?>
                    <input type="hidden" name="id" value="<?= (int) $editingAdmin['id'] ?>">
                <?php endif; ?>

                <div class="form-field">
                    <label for="full_name">Full name</label>
                    <input type="text" id="full_name" name="full_name" class="form-control"
                           value="<?= e($editingAdmin['full_name'] ?? '') ?>" maxlength="100" required>
                </div>

                <div class="form-field" style="margin-top: 14px;">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control"
                           value="<?= e($editingAdmin['username'] ?? '') ?>" maxlength="50" required>
                </div>

                <?php if (!$editingAdmin): ?>
                    <div class="form-field" style="margin-top: 14px;">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control"
                               autocomplete="new-password" required>
                        <span class="form-help">Minimum 8 characters.</span>
                    </div>

                    <div class="form-field" style="margin-top: 14px;">
                        <label for="password_confirmation">Confirm password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation"
                               class="form-control" autocomplete="new-password" required>
                    </div>
                <?php else: ?>
                    <div class="form-field" style="margin-top: 14px;">
                        <label class="checkbox-row">
                            <input type="checkbox" name="is_active" value="1"
                                <?= (int) $editingAdmin['is_active'] === 1 ? 'checked' : '' ?>
                                <?= (int) $editingAdmin['id'] === $selfId ? 'disabled' : '' ?>>
                            Active (can log in)
                        </label>
                        <?php if ((int) $editingAdmin['id'] === $selfId): ?>
                            <input type="hidden" name="is_active" value="1">
                            <span class="form-help">You cannot deactivate your own account.</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?= $editingAdmin ? 'Save Changes' : 'Create Administrator' ?>
                    </button>
                </div>
            </form>
        </div>

        <?php if ($editingAdmin): ?>
            <div class="panel">
                <div class="panel-head">
                    <h2>Reset Password</h2>
                </div>

                <form method="post" action="<?= e(url('admin/admins.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <input type="hidden" name="id" value="<?= (int) $editingAdmin['id'] ?>">

                    <div class="form-field">
                        <label for="new_password">New password</label>
                        <input type="password" id="new_password" name="password" class="form-control"
                               autocomplete="new-password" required>
                        <span class="form-help">Minimum 8 characters.</span>
                    </div>

                    <div class="form-field" style="margin-top: 14px;">
                        <label for="new_password_confirmation">Confirm new password</label>
                        <input type="password" id="new_password_confirmation" name="password_confirmation"
                               class="form-control" autocomplete="new-password" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-soft">Reset Password</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
