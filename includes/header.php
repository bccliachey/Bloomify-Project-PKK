<?php
/**
 * Public site header.
 *
 * Expected (optional) variables set by the page before including:
 *   $pageTitle   string
 *   $activeNav   string  home|shop|cart|account
 *   $bodyClass   string
 *   $hideNavbar  bool
 */

if (!function_exists('e')) {
    require_once __DIR__ . '/functions.php';
}

$storeName = setting('store_name', 'Bloomify');
$pageTitle = $pageTitle ?? $storeName;
$activeNav = $activeNav ?? '';
$bodyClass = $bodyClass ?? '';
$hideNavbar = $hideNavbar ?? false;
$extraStyles = $extraStyles ?? [];

$flashMessages = [];
foreach (['success', 'error', 'info'] as $flashType) {
    $message = flash_get($flashType);
    if ($message !== null && $message !== '') {
        $flashMessages[] = ['type' => $flashType, 'message' => $message];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e($storeName) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">

    <link rel="stylesheet" href="<?= e(url('Homepage Style.CSS')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/pages.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/dialog.css')) ?>">
    <?php foreach ($extraStyles as $style): ?>
        <link rel="stylesheet" href="<?= e(url($style)) ?>">
    <?php endforeach; ?>

    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <script>
        window.BLOOMIFY = {
            baseUrl: <?= json_encode(BASE_URL) ?>,
            csrfToken: <?= json_encode(csrf_token()) ?>,
            loggedIn: <?= is_logged_in() ? 'true' : 'false' ?>
        };
    </script>
</head>
<body class="<?= e($bodyClass) ?>">

<?php if (!$hideNavbar): ?>
    <?php require __DIR__ . '/navbar.php'; ?>
<?php endif; ?>

<?php if ($flashMessages): ?>
    <div class="flash-stack">
        <?php foreach ($flashMessages as $flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?>">
                <span><?= e($flash['message']) ?></span>
                <button type="button" class="flash-close" aria-label="Dismiss">&times;</button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
