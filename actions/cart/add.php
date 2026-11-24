<?php
/**
 * Add a product configuration to the logged-in customer's cart.
 *
 * Stock is validated but never deducted here. Prices are recomputed
 * from the database on every page that displays the cart.
 */

require_once __DIR__ . '/../../includes/auth.php';

$returnTo = normalize_redirect($_POST['return_to'] ?? null) ?? url('catalog.php');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('catalog.php');
}

if (!is_logged_in()) {
    if (is_ajax()) {
        json_response([
            'ok'       => false,
            'message'  => 'Please login first.',
            'redirect' => url('login.php?redirect=' . rawurlencode($returnTo)),
        ], 401);
    }
    require_login($returnTo);
}

require_csrf();

$user = current_user();

$fail = function (string $message, int $status = 422) use ($returnTo): void {
    if (is_ajax()) {
        json_response(['ok' => false, 'message' => $message], $status);
    }
    flash_set('error', $message);
    redirect($returnTo);
};

$succeed = function (string $message) use ($user): void {
    if (is_ajax()) {
        json_response([
            'ok'         => true,
            'message'    => $message,
            'cart_count' => cart_item_count((int) $user['id']),
        ]);
    }
    flash_set('success', $message);
    redirect('cart.php');
};

$productId = (int) ($_POST['product_id'] ?? 0);
$product = get_product($productId);

if (!$product || (int) $product['is_visible'] !== 1) {
    $fail('This product is not available.', 404);
}

$mode = $product['pricing_mode'];
$chosen = [];
$quantity = 0;
$itemType = $mode;
$configHash = '';
$units = 0;

if ($mode === 'fixed') {
    $units = (int) ($_POST['quantity'] ?? 0);

    if ($units < 1) {
        $fail('Please choose at least 1 box.');
    }

    if ($units > (int) $product['stock']) {
        $fail('Only ' . (int) $product['stock'] . ' item(s) available.');
    }

    $itemType = 'fixed';
    $quantity = $units;
    $configHash = cart_config_hash($productId, 'fixed', []);
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
        $fail('This product has no flower options configured yet.');
    }

    foreach ($allowed as $flowerId => $flower) {
        $requested = isset($inputFlowers[$flowerId]) ? (int) $inputFlowers[$flowerId] : 0;

        if ($requested < 0) {
            $requested = 0;
        }

        if ($requested > $flower['stock']) {
            $fail('Only ' . $flower['stock'] . ' ' . $flower['name'] . ' stems available.');
        }

        if ($requested > 0) {
            $chosen[$flowerId] = $requested;
        }
    }

    $quantity = array_sum($chosen);

    if ($quantity < MIN_STEMS) {
        $fail('Please choose at least ' . MIN_STEMS . ' stems in total.');
    }

    $configHash = cart_config_hash($productId, $itemType, $chosen);
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $cart = get_cart((int) $user['id'], true);

    $lookup = $pdo->prepare('SELECT * FROM cart_items WHERE cart_id = ? AND config_hash = ? FOR UPDATE');
    $lookup->execute([$cart['id'], $configHash]);
    $existing = $lookup->fetch();

    if ($existing) {
        if ($mode === 'fixed') {
            $newUnits = (int) $existing['quantity'] + $units;

            if ($newUnits > (int) $product['stock']) {
                throw new RuntimeException('Only ' . (int) $product['stock'] . ' item(s) available in total.');
            }

            $update = $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
            $update->execute([$newUnits, $existing['id']]);
        } else {
            $detailQuery = $pdo->prepare('SELECT * FROM cart_item_details WHERE cart_item_id = ? FOR UPDATE');
            $detailQuery->execute([$existing['id']]);

            $existingDetails = [];
            foreach ($detailQuery->fetchAll() as $detail) {
                $existingDetails[(int) $detail['flower_type_id']] = (int) $detail['stem_quantity'];
            }

            foreach ($chosen as $flowerId => $requested) {
                $combined = ($existingDetails[$flowerId] ?? 0) + $requested;

                if ($combined > $allowed[$flowerId]['stock']) {
                    throw new RuntimeException(
                        'Only ' . $allowed[$flowerId]['stock'] . ' ' . $allowed[$flowerId]['name']
                        . ' stems available in total.'
                    );
                }

                if (isset($existingDetails[$flowerId])) {
                    $update = $pdo->prepare(
                        'UPDATE cart_item_details SET stem_quantity = ?
                         WHERE cart_item_id = ? AND flower_type_id = ?'
                    );
                    $update->execute([$combined, $existing['id'], $flowerId]);
                } else {
                    $insert = $pdo->prepare(
                        'INSERT INTO cart_item_details (cart_item_id, flower_type_id, stem_quantity)
                         VALUES (?, ?, ?)'
                    );
                    $insert->execute([$existing['id'], $flowerId, $requested]);
                }
            }

            $sum = $pdo->prepare('SELECT COALESCE(SUM(stem_quantity), 0) FROM cart_item_details WHERE cart_item_id = ?');
            $sum->execute([$existing['id']]);
            $totalStems = (int) $sum->fetchColumn();

            $update = $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
            $update->execute([$totalStems, $existing['id']]);
        }
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO cart_items (cart_id, product_id, item_type, config_hash, quantity)
             VALUES (?, ?, ?, ?, ?)'
        );
        $insert->execute([$cart['id'], $productId, $itemType, $configHash, $quantity]);
        $newItemId = (int) $pdo->lastInsertId();

        if ($mode !== 'fixed') {
            $insertDetail = $pdo->prepare(
                'INSERT INTO cart_item_details (cart_item_id, flower_type_id, stem_quantity)
                 VALUES (?, ?, ?)'
            );

            foreach ($chosen as $flowerId => $requested) {
                $insertDetail->execute([$newItemId, $flowerId, $requested]);
            }
        }
    }

    $pdo->commit();
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $fail($exception->getMessage());
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $fail('Could not add this product to your cart. Please try again.', 500);
}

$succeed('Added to your cart.');
