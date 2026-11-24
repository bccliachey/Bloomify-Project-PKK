<?php
/**
 * Bloomify - one-time first administrator setup.
 * This page stops working as soon as an admin account exists.
 */

require_once __DIR__ . '/../includes/auth.php';

if (is_admin_logged_in()) {
    redirect('admin/index.php');
}

$adminCount = (int) db()->query('SELECT COUNT(*) FROM admin_accounts')->fetchColumn();

if ($adminCount > 0) {
    flash_set('info', 'Administrator setup has already been completed. Please login.');
    redirect('login.php');
}

$fullName = '';
$username = '';
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    if ($fullName === '' || mb_strlen($fullName) > 100) {
        $errors[] = 'Full name is required (maximum 100 characters).';
    }

    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3-50 characters and use only letters, numbers, dots, dashes, or underscores.';
    }

    if (!password_is_valid($password)) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $passwordConfirmation) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!$errors) {
        $check = db()->prepare('SELECT COUNT(*) FROM admin_accounts WHERE username = ?');
        $check->execute([$username]);

        if ((int) $check->fetchColumn() > 0) {
            $errors[] = 'That username is already taken.';
        }
    }

    if (!$errors) {
        $insert = db()->prepare(
            'INSERT INTO admin_accounts (full_name, username, password_hash, is_active)
             VALUES (?, ?, ?, 1)'
        );
        $insert->execute([
            $fullName,
            $username,
            password_hash($password, PASSWORD_DEFAULT),
        ]);

        flash_set('success', 'Administrator account created. Please sign in.');
        redirect('login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Setup &middot; Bloomify</title>
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

        <h1>Create the First Admin</h1>
        <p class="lead">
            This one-time setup creates the administrator account for your Bloomify store.
        </p>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <ul class="error-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('admin/setup.php')) ?>" autocomplete="off">
            <?= csrf_field() ?>

            <div class="form-field">
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" class="form-control"
                       value="<?= e($fullName) ?>" maxlength="100" required>
            </div>

            <div class="form-field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control"
                       value="<?= e($username) ?>" maxlength="50" autocomplete="username" required>
                <span class="form-help">Letters, numbers, dots, dashes, underscores (3-50).</span>
            </div>

            <div class="form-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       autocomplete="new-password" required>
                <span class="form-help">Minimum 8 characters.</span>
            </div>

            <div class="form-field">
                <label for="password_confirmation">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation"
                       class="form-control" autocomplete="new-password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 10px;">
                Create Admin Account
            </button>
        </form>

        <div class="admin-auth-footer">
            <a href="<?= e(url('login.php')) ?>">Already set up? Sign in</a>
        </div>
    </div>
</body>
</html>
