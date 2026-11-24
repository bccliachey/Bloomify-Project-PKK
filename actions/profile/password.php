<?php
/**
 * Change the logged-in customer's password.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_login();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('account.php');
}

require_csrf();

$user = current_user();

$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');

$errors = [];

if (!password_verify($currentPassword, $user['password_hash'])) {
    $errors['current_password'] = 'Your current password is incorrect.';
}

if (!password_is_valid($newPassword)) {
    $errors['new_password'] = 'The new password must be at least 8 characters.';
}

if ($newPassword !== $newPasswordConfirm) {
    $errors['new_password_confirm'] = 'The new password confirmation does not match.';
}

if (!$errors && password_verify($newPassword, $user['password_hash'])) {
    $errors['new_password'] = 'The new password must be different from the current password.';
}

if ($errors) {
    flash_set('password_errors', $errors);
    redirect('account.php#password');
}

$update = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$update->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);

flash_set('success', 'Your password has been updated.');
redirect('account.php#password');
