<?php
/**
 * Remove one item from the logged-in customer's cart.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_login();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('cart.php');
}

require_csrf();

$user = current_user();
$cartItemId = (int) ($_POST['cart_item_id'] ?? 0);

$delete = db()->prepare(
    'DELETE ci FROM cart_items ci
     JOIN carts c ON c.id = ci.cart_id
     WHERE ci.id = ? AND c.user_id = ?'
);
$delete->execute([$cartItemId, $user['id']]);

if ($delete->rowCount() > 0) {
    flash_set('success', 'Item removed from your cart.');
} else {
    flash_set('error', 'Cart item not found.');
}

redirect('cart.php');
