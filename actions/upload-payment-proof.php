<?php

require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('orders.php');
}

if (!verify_csrf()) {
    flash_set('error', 'Your session expired. Please try again.');
    redirect('orders.php');
}

$user = current_user();
$orderCode = trim((string) ($_POST['order_code'] ?? ''));
$order = $orderCode !== '' ? get_order_by_code($orderCode, (int) $user['id']) : null;

if (!$order) {
    flash_set('error', 'Order not found.');
    redirect('orders.php');
}

$redirectUrl = 'order-detail.php?code=' . rawurlencode($order['order_code']);

if (!payment_method_is_digital($order['payment_method'])) {
    flash_set('error', 'Payment proof is only required for digital payments.');
    redirect($redirectUrl);
}

if (!in_array($order['payment_status'], ['Unpaid', 'Rejected'], true)
    || in_array($order['order_status'], ['Selesai', 'Rejected'], true)) {
    flash_set('error', 'This order cannot accept a new payment proof.');
    redirect($redirectUrl);
}

try {
    $proofPath = save_payment_proof($_FILES['payment_proof'] ?? [], (int) $user['id']);

    $pdo = db();
    $update = $pdo->prepare(
        'UPDATE orders
         SET payment_proof = ?,
             payment_proof_uploaded_at = NOW(),
             payment_status = "Pending Verification",
             payment_verified_at = NULL,
             payment_verified_by = NULL
         WHERE id = ?
           AND user_id = ?
           AND payment_status IN ("Unpaid", "Rejected")
           AND order_status NOT IN ("Selesai", "Rejected")'
    );
    $update->execute([$proofPath, $order['id'], $user['id']]);

    if ($update->rowCount() === 0) {
        delete_payment_proof($proofPath);
        flash_set('error', 'This order cannot accept a new payment proof.');
        redirect($redirectUrl);
    }

    $oldProof = (string) ($order['payment_proof'] ?? '');
    if ($oldProof !== '' && $oldProof !== $proofPath) {
        delete_payment_proof($oldProof);
    }

    $log = $pdo->prepare('INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)');
    $log->execute([$order['id'], 'Pending', 'Payment proof uploaded. Waiting for admin verification.']);

    flash_set('success', 'Payment proof uploaded. Please wait for admin verification.');
} catch (RuntimeException $exception) {
    flash_set('error', $exception->getMessage());
} catch (Throwable $exception) {
    flash_set('error', 'Could not upload your payment proof. Please try again.');
}

redirect($redirectUrl);
