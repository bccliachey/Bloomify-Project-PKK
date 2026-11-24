<?php
/**
 * Bloomify - serve a private payment proof to the order owner or an admin.
 */

require_once __DIR__ . '/includes/auth.php';

$orderCode = trim((string) ($_GET['code'] ?? ''));

$admin = current_admin();
$user = current_user();

if (!$admin && !$user) {
    require_login('orders.php');
}

$order = null;
if ($orderCode !== '') {
    $order = $admin
        ? get_order_by_code($orderCode)
        : get_order_by_code($orderCode, (int) $user['id']);
}

if (!$order || empty($order['payment_proof'])) {
    http_response_code(404);
    exit('Payment proof not found.');
}

$absolutePath = payment_proof_absolute_path((string) $order['payment_proof']);

if ($absolutePath === null || !is_file($absolutePath)) {
    http_response_code(404);
    exit('Payment proof not found.');
}

$mime = '';
if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($absolutePath);
}

if ($mime === '' && function_exists('mime_content_type')) {
    $mime = (string) mime_content_type($absolutePath);
}

if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
    $mime = $extension === 'png' ? 'image/png'
        : ($extension === 'webp' ? 'image/webp' : 'image/jpeg');
}

$filename = 'payment-proof-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $order['order_code']);

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($absolutePath));
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

readfile($absolutePath);
exit;
