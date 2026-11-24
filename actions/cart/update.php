<?php
/**
 * Update an existing cart item configuration.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_login();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('cart.php');
}

require_csrf();

$user = current_user();
$cart = get_cart((int) $user['id']);

if (!$cart) {
    flash_set('error', 'Your cart is empty.');
    redirect('cart.php');
}

$cartItemId = (int) ($_POST['cart_item_id'] ?? 0);

$statement = db()->prepare(
    'SELECT ci.* FROM cart_items ci
     JOIN carts c ON c.id = ci.cart_id
     WHERE ci.id = ? AND c.user_id = ?
     LIMIT 1'
);
$statement->execute([$cartItemId, $user['id']]);
$item = $statement->fetch();

if (!$item) {
    flash_set('error', 'Cart item not found.');
    redirect('cart.php');
}

$product = get_product((int) $item['product_id']);

if (!$product) {
    flash_set('error', 'This product is no longer available. Please remove it from your cart.');
    redirect('cart.php');
}

$mode = $product['pricing_mode'];
$itemType = $mode;
$chosen = [];
$quantity = 0;
$units = 0;

if ($mode === 'fixed') {
    $units = (int) ($_POST['quantity'] ?? 0);

    if ($units < 1) {
        flash_set('error', 'Please keep at least 1 box, or remove the item.');
        redirect('cart.php');
    }

    if ($units > (int) $product['stock']) {
        flash_set('error', 'Only ' . (int) $product['stock'] . ' item(s) available.');
        redirect('cart.php');
    }

    $quantity = $units;
} else {
    $inputFlowers = $_POST['flowers'] ?? [];
    if (!is_array($inputFlowers)) {
        $inputFlowers = [];
    }

    $allowed = [];
    foreach (get_flower_types_for_product((int) $product['id']) as $flower) {
        $allowed[(int) $flower['id']] = [
            'id' => (int) $flower['id'],
            'name' => $flower['name'] . ($flower['color'] !== '' ? ' — ' . $flower['color'] : ''),
            'stock' => (int) $flower['stock'],
        ];
    }
    if (!$allowed) {
        flash_set('error', 'This product has no flower options configured yet.');
        redirect('cart.php');
    }

    foreach ($allowed as $flowerId => $flower) {
        $requested = isset($inputFlowers[$flowerId]) ? (int) $inputFlowers[$flowerId] : 0;

        if ($requested < 0) {
            $requested = 0;
        }

        if ($requested > $flower['stock']) {
            flash_set('error', 'Only ' . $flower['stock'] . ' ' . $flower['name'] . ' stems available.');
            redirect('cart.php');
        }

        if ($requested > 0) {
            $chosen[$flowerId] = $requested;
        }
    }

    $quantity = array_sum($chosen);

    if ($quantity < MIN_STEMS) {
        flash_set('error', 'Please choose at least ' . MIN_STEMS . ' stems in total.');
        redirect('cart.php');
    }
}

$configHash = cart_config_hash((int) $product['id'], $itemType, $chosen);

$pdo = db();

try {
    $pdo->beginTransaction();

    $lock = $pdo->prepare('SELECT * FROM cart_items WHERE id = ? FOR UPDATE');
    $lock->execute([$cartItemId]);
    $lockedItem = $lock->fetch();

    if (!$lockedItem) {
        throw new RuntimeException('Cart item not found.');
    }

    $duplicate = $pdo->prepare(
        'SELECT id FROM cart_items WHERE cart_id = ? AND config_hash = ? AND id <> ? LIMIT 1'
    );
    $duplicate->execute([$cart['id'], $configHash, $cartItemId]);

    if ($duplicate->fetch()) {
        throw new RuntimeException(
            'An identical configuration is already in your cart. Please remove the other item first.'
        );
    }

    if ($mode === 'fixed') {
        $update = $pdo->prepare(
            'UPDATE cart_items SET item_type = ?, config_hash = ?, quantity = ? WHERE id = ?'
        );
        $update->execute([$itemType, $configHash, $units, $cartItemId]);
    } else {
        $delete = $pdo->prepare('DELETE FROM cart_item_details WHERE cart_item_id = ?');
        $delete->execute([$cartItemId]);

        $insert = $pdo->prepare(
            'INSERT INTO cart_item_details (cart_item_id, flower_type_id, stem_quantity)
             VALUES (?, ?, ?)'
        );

        foreach ($chosen as $flowerId => $requested) {
            $insert->execute([$cartItemId, $flowerId, $requested]);
        }

        $update = $pdo->prepare(
            'UPDATE cart_items SET item_type = ?, config_hash = ?, quantity = ? WHERE id = ?'
        );
        $update->execute([$itemType, $configHash, $quantity, $cartItemId]);
    }

    $pdo->commit();
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash_set('error', $exception->getMessage());
    redirect('cart.php');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash_set('error', 'Could not update your cart. Please try again.');
    redirect('cart.php');
}

flash_set('success', 'Your cart has been updated.');
redirect('cart.php');
