<?php
/**
 * Bloomify - admin login.
 */

require_once __DIR__ . '/../includes/auth.php';

if (is_admin_logged_in()) {
    redirect('admin/index.php');
}

$adminCount = (int) db()->query('SELECT COUNT(*) FROM admin_accounts')->fetchColumn();

$username = '';
$errors = [];
$flashMessages = [];

foreach (['success', 'error', 'info'] as $flashType) {
    $flashMessage = flash_get($flashType);
    if ($flashMessage) {
        $flashMessages[$flashType] = is_array($flashMessage)
            ? implode(' ', $flashMessage)
            : $flashMessage;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $errors[] = 'Please enter your username and password.';
    } else {
        $statement = db()->prepare('SELECT * FROM admin_accounts WHERE username = ? LIMIT 1');
        $statement->execute([$username]);
        $admin = $statement->fetch();

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            $errors[] = 'Invalid username or password.';
        } elseif ((int) $admin['is_active'] !== 1) {
            $errors[] = 'This administrator account has been deactivated.';
        } else {
            login_admin($admin);
            flash_set('success', 'Welcome back, ' . $admin['full_name'] . '.');
            redirect(take_intended_redirect('admin_intended_url', 'admin/index.php'));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login &middot; Bloomify</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
</head>
<body class="admin-auth">
    <div class="admin-auth-card">
        <a href="<?= e(url('index.php')) ?>" class="admin-auth-logo">Bloomify</a>
        <p class="admin-auth-sub">Flower Studio Admin</p>

        <h1>Administrator Login</h1>
        <p class="lead">Sign in to manage products, stock, and orders.</p>

        <?php foreach ($flashMessages as $flashType => $flashMessage): ?>
            <div class="alert alert-<?= e($flashType) ?>"><?= e($flashMessage) ?></div>
        <?php endforeach; ?>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <ul class="error-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($adminCount === 0): ?>
            <div class="alert alert-info">
                No administrator account exists yet.
                <a href="<?= e(url('admin/setup.php')) ?>">Create the first admin</a>.
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('admin/login.php')) ?>" autocomplete="off">
            <?= csrf_field() ?>

            <div class="form-field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control"
                       value="<?= e($username) ?>" autocomplete="username" required>
            </div>

            <div class="form-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 10px;">
                Sign In
            </button>
        </form>

        <div class="admin-auth-footer">
            <a href="<?= e(url('index.php')) ?>">&larr; Back to Bloomify</a>
            <?php if ($adminCount > 0): ?>
                &middot; <a href="<?= e(url('admin/setup.php')) ?>">Setup</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
