<?php
/**
 * Handle customer registration.
 */

require_once __DIR__ . '/../../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('register.php');
}

require_csrf();

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
$redirectTarget = normalize_redirect($_POST['redirect'] ?? null);

$errors = [];

if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
    $errors['full_name'] = 'Full name must be between 2 and 100 characters.';
}

if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $username)) {
    $errors['username'] = 'Username must be 3-50 characters (letters, numbers, dot, underscore).';
}

if (!preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) {
    $errors['phone'] = 'Enter a valid phone or WhatsApp number.';
}

if (mb_strlen($address) < 5 || mb_strlen($address) > 500) {
    $errors['address'] = 'Address must be between 5 and 500 characters.';
}

if (!password_is_valid($password)) {
    $errors['password'] = 'Password must be at least 8 characters.';
}

if ($password !== $passwordConfirm) {
    $errors['password_confirm'] = 'Password confirmation does not match.';
}

if (!isset($errors['username'])) {
    $check = db()->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $check->execute([$username]);
    if ($check->fetch()) {
        $errors['username'] = 'This username is already registered.';
    }
}

if ($errors) {
    flash_set('errors', $errors);
    flash_set('old', [
        'full_name' => $fullName,
        'username'  => $username,
        'phone'     => $phone,
        'address'   => $address,
    ]);
    $back = 'register.php' . ($redirectTarget ? '?redirect=' . rawurlencode($redirectTarget) : '');
    redirect($back);
}

$insert = db()->prepare(
    'INSERT INTO users (full_name, username, phone, address, password_hash)
     VALUES (?, ?, ?, ?, ?)'
);
$insert->execute([
    $fullName,
    $username,
    $phone,
    $address,
    password_hash($password, PASSWORD_DEFAULT),
]);

$userId = (int) db()->lastInsertId();
$user = ['id' => $userId];
login_user($user);

flash_set('success', 'Welcome to Bloomify, ' . $fullName . '!');

if ($redirectTarget) {
    unset($_SESSION['intended_url']);
    redirect($redirectTarget);
}

redirect(take_intended_redirect('intended_url', 'index.php'));
