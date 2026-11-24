<?php
/**
 * Handle customer login.
 */

require_once __DIR__ . '/../../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('login.php');
}

require_csrf();

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$redirectTarget = normalize_redirect($_POST['redirect'] ?? null);

if ($username === '' || $password === '') {
    flash_set('error', 'Please enter your username and password.');
    $back = 'login.php' . ($redirectTarget ? '?redirect=' . rawurlencode($redirectTarget) : '');
    redirect($back);
}

$adminLookup = db()->prepare('SELECT * FROM admin_accounts WHERE username = ? AND is_active = 1 LIMIT 1');
$adminLookup->execute([$username]);
$admin = $adminLookup->fetch();

if ($admin && password_verify($password, $admin['password_hash'])) {
    login_admin($admin);
    flash_set('success', 'Welcome back, ' . $admin['full_name'] . '!');
    unset($_SESSION['intended_url']);
    redirect(take_intended_redirect('admin_intended_url', 'admin/index.php'));
}

$statement = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
$statement->execute([$username]);
$user = $statement->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    flash_set('error', 'Invalid username or password.');
    $back = 'login.php' . ($redirectTarget ? '?redirect=' . rawurlencode($redirectTarget) : '');
    redirect($back);
}

login_user($user);
flash_set('success', 'Welcome back, ' . $user['full_name'] . '!');

if ($redirectTarget) {
    unset($_SESSION['intended_url']);
    redirect($redirectTarget);
}

redirect(take_intended_redirect('intended_url', 'index.php'));
