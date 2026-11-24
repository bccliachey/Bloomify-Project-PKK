<?php
/**
 * Update the logged-in customer's profile.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_login();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('account.php');
}

require_csrf();

$user = current_user();

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$description = trim((string) ($_POST['profile_description'] ?? ''));

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

if (mb_strlen($description) > 1000) {
    $errors['profile_description'] = 'Profile description must be at most 1000 characters.';
}

if (!isset($errors['username'])) {
    $check = db()->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
    $check->execute([$username, $user['id']]);
    if ($check->fetch()) {
        $errors['username'] = 'This username is already used by another account.';
    }
}

$photoPath = $user['profile_photo'];

if (isset($_FILES['profile_photo'])
    && ($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    try {
        $photoPath = save_uploaded_image($_FILES['profile_photo'], 'profiles', 'user');
    } catch (RuntimeException $exception) {
        $errors['profile_photo'] = $exception->getMessage();
    }
}

if ($errors) {
    flash_set('profile_errors', $errors);
    flash_set('profile_old', [
        'full_name'           => $fullName,
        'username'            => $username,
        'phone'               => $phone,
        'address'             => $address,
        'profile_description' => $description,
    ]);
    redirect('account.php#profile');
}

$update = db()->prepare(
    'UPDATE users
     SET full_name = ?, username = ?, phone = ?, address = ?, profile_description = ?, profile_photo = ?
     WHERE id = ?'
);
$update->execute([
    $fullName,
    $username,
    $phone,
    $address,
    $description,
    $photoPath,
    $user['id'],
]);

flash_set('success', 'Your profile has been updated.');
redirect('account.php#profile');
