<?php
/**
 * Place an order.
 *
 * Prices and stock are validated on the server. Stock is NOT deducted here;
 * it is deducted when an admin confirms the order.
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('checkout.php');
}

require_csrf();

$user = current_user();

$token = (string) ($_POST['checkout_token'] ?? '');
if (empty($_SESSION['checkout_token']) || !hash_equals($_SESSION['checkout_token'], $token)) {
    unset($_SESSION['checkout_token']);
    flash_set('error', 'This checkout session has expired. Please review your order again.');
    redirect('checkout.php');
}
unset($_SESSION['checkout_token']);

$recipientName = trim((string) ($_POST['recipient_name'] ?? ''));
$recipientPhone = trim((string) ($_POST['recipient_phone'] ?? ''));
$fulfillment = (string) ($_POST['fulfillment_method'] ?? 'delivery');
$deliveryAddress = trim((string) ($_POST['delivery_address'] ?? ''));
$addressDetail = trim((string) ($_POST['address_detail'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? ''));
$paymentMethod = (string) ($_POST['payment_method'] ?? '');
$saveProfile = isset($_POST['save_profile']) && $_POST['save_profile'] === '1';
$confirmed = isset($_POST['confirm_order']) && $_POST['confirm_order'] === '1';
$paymentProofFile = $_FILES['payment_proof'] ?? [];
$paymentProofError = '';
$paymentIsDigital = payment_method_is_digital($paymentMethod);

$errors = [];

if (mb_strlen($recipientName) < 2 || mb_strlen($recipientName) > 100) {
    $errors[] = 'Recipient name must be between 2 and 100 characters.';
}

if (!preg_match('/^[0-9+\-\s]{8,20}$/', $recipientPhone)) {
    $errors[] = 'Enter a valid WhatsApp or phone number.';
}

if (!in_array($fulfillment, ['delivery', 'pickup'], true)) {
    $errors[] = 'Please choose a valid fulfillment method.';
}

$paymentMethods = payment_methods();
if (!array_key_exists($paymentMethod, $paymentMethods)) {
    $errors[] = 'Please choose a valid payment method.';
} elseif (payment_method_is_digital($paymentMethod) && trim((string) $paymentMethods[$paymentMethod]['number']) === '') {
    $errors[] = 'This payment method is not configured yet. Please choose another method.';
}

if ($fulfillment === 'delivery' && (mb_strlen($deliveryAddress) < 5 || mb_strlen($deliveryAddress) > 500)) {
    $errors[] = 'Please enter a delivery address (5-500 characters).';
}

if (mb_strlen($addressDetail) > 255) {
    $errors[] = 'Address details must be at most 255 characters.';
}

if (mb_strlen($notes) > 1000) {
    $errors[] = 'Order notes must be at most 1000 characters.';
}

if (!$confirmed) {
    $errors[] = 'Please confirm that you have reviewed your order.';
}

if (isset($paymentMethods[$paymentMethod])
    && $paymentIsDigital
    && (int) ($paymentProofFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    $paymentProofError = 'Please upload your payment proof before placing the order.';
    $errors[] = $paymentProofError;
}

$checkoutOldData = [
    'recipient_name'       => $recipientName,
    'recipient_phone'      => $recipientPhone,
    'fulfillment_method'   => $fulfillment,
    'delivery_address'     => $deliveryAddress,
    'address_detail'       => $addressDetail,
    'notes'                => $notes,
    'payment_method'       => $paymentMethod,
    'save_profile'         => $saveProfile ? '1' : '',
];

if ($errors) {
    flash_set('checkout_errors', $errors);
    flash_set('checkout_old', $checkoutOldData);

    if ($paymentProofError !== '') {
        flash_set('checkout_proof_error', $paymentProofError);
    }

    redirect('checkout.php');
}

$cart = get_cart((int) $user['id']);

if (!$cart) {
    flash_set('error', 'Your cart is empty.');
    redirect('cart.php');
}

$pdo = db();
$paymentProofPath = null;

try {
    $pdo->beginTransaction();

    $items = get_cart_items((int) $cart['id']);

    if (!$items) {
        $pdo->rollBack();
        flash_set('error', 'Your cart is empty.');
        redirect('cart.php');
    }

    foreach ($items as $item) {
        if (!$item['product_available'] || !$item['stock_ok']) {
            $pdo->rollBack();
            flash_set('error', 'Some items are no longer available in the requested quantity. Please review your cart.');
            redirect('cart.php');
        }
    }

    $flowerRequirements = [];
    $boxRequirements = [];
    foreach ($items as $item) {
        if ($item['item_type'] === 'fixed') {
            $productId = (int) $item['product_id'];
            if (!isset($boxRequirements[$productId])) {
                $boxRequirements[$productId] = [
                    'name' => $item['product_name'],
                    'quantity' => 0,
                ];
            }
            $boxRequirements[$productId]['quantity'] += (int) $item['units'];
            continue;
        }

        foreach ($item['details'] as $detail) {
            $flowerId = (int) $detail['flower_type_id'];
            if (!isset($flowerRequirements[$flowerId])) {
                $flowerRequirements[$flowerId] = [
                    'name' => $detail['flower_type_name'],
                    'quantity' => 0,
                ];
            }
            $flowerRequirements[$flowerId]['quantity'] += (int) $detail['stem_quantity'];
        }
    }

    foreach ($boxRequirements as $productId => $requirement) {
        $lock = $pdo->prepare('SELECT quantity FROM product_stocks WHERE product_id = ? FOR UPDATE');
        $lock->execute([$productId]);
        $stock = $lock->fetchColumn();

        if ($stock === false || (int) $stock < $requirement['quantity']) {
            $pdo->rollBack();
            flash_set('error', 'Not enough stock for ' . $requirement['name'] . '. Please adjust your cart.');
            redirect('cart.php');
        }
    }

    foreach ($flowerRequirements as $flowerId => $requirement) {
        $lock = $pdo->prepare('SELECT quantity FROM flower_stocks WHERE flower_type_id = ? FOR UPDATE');
        $lock->execute([$flowerId]);
        $stock = $lock->fetchColumn();

        if ($stock === false || (int) $stock < $requirement['quantity']) {
            $pdo->rollBack();
            flash_set('error', 'Not enough ' . $requirement['name'] . ' stems in total. Please adjust your cart.');
            redirect('cart.php');
        }
    }

    if ($paymentIsDigital) {
        try {
            $paymentProofPath = save_payment_proof($paymentProofFile, (int) $user['id']);
        } catch (RuntimeException $exception) {
            $pdo->rollBack();
            $paymentProofError = $exception->getMessage();
            flash_set('checkout_errors', [$paymentProofError]);
            flash_set('checkout_proof_error', $paymentProofError);
            flash_set('checkout_old', $checkoutOldData);
            redirect('checkout.php');
        }
    }

    $paymentStatus = $paymentIsDigital ? 'Pending Verification' : 'Unpaid';

    $totals = cart_totals($items);
    $deliveryFee = $fulfillment === 'delivery' ? DELIVERY_FEE : 0.0;
    $grandTotal = $totals['total'] + $deliveryFee;

    $orderCode = generate_order_code();
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $check = $pdo->prepare('SELECT id FROM orders WHERE order_code = ? LIMIT 1');
        $check->execute([$orderCode]);
        if (!$check->fetch()) {
            break;
        }
        $orderCode = generate_order_code();
    }

    $insertOrder = $pdo->prepare(
        'INSERT INTO orders
            (order_code, user_id, recipient_name, recipient_phone, fulfillment_method,
             delivery_address, address_detail, notes, payment_method, payment_status,
             order_status, payment_proof, payment_proof_uploaded_at,
             product_subtotal, wrapping_total, delivery_fee, grand_total)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Pending", ?, ?, ?, ?, ?, ?)'
    );

    $insertOrder->execute([
        $orderCode,
        $user['id'],
        $recipientName,
        $recipientPhone,
        $fulfillment,
        $fulfillment === 'delivery' ? $deliveryAddress : null,
        $fulfillment === 'delivery' ? ($addressDetail !== '' ? $addressDetail : null) : null,
        $notes !== '' ? $notes : null,
        $paymentMethod,
        $paymentStatus,
        $paymentProofPath,
        $paymentProofPath !== null ? date('Y-m-d H:i:s') : null,
        $totals['product_subtotal'],
        $totals['wrapping_total'],
        $deliveryFee,
        $grandTotal,
    ]);

    $orderId = (int) $pdo->lastInsertId();

    $insertItem = $pdo->prepare(
        'INSERT INTO order_items
            (order_id, product_id, product_name, category_name, product_image, item_type,
             unit_price, stem_total, quantity, flower_subtotal, wrapping_fee, item_subtotal)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $insertDetail = $pdo->prepare(
        'INSERT INTO order_item_details
            (order_item_id, flower_type_id, flower_type_name, price_per_stem, stem_quantity, line_total)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    foreach ($items as $item) {
        $unitPrice = null;
        $stemTotal = 0;
        $units = 0;

        if ($item['item_type'] === 'fixed') {
            $unitPrice = (float) $item['fixed_price'];
            $units = (int) $item['units'];
        } else {
            $stemTotal = (int) $item['stems'];
            if ($item['item_type'] === 'per_stem' && !empty($item['details'])) {
                $unitPrice = (float) $item['details'][0]['price_per_stem'];
            }
        }

        $insertItem->execute([
            $orderId,
            $item['product_id'],
            $item['product_name'],
            $item['category_name'],
            $item['product_image'],
            $item['item_type'],
            $unitPrice,
            $stemTotal,
            $units,
            $item['flower_subtotal'],
            $item['wrapping_fee'],
            $item['item_subtotal'],
        ]);

        $orderItemId = (int) $pdo->lastInsertId();

        foreach ($item['details'] as $detail) {
            $lineTotal = (float) $detail['price_per_stem'] * (int) $detail['stem_quantity'];

            $insertDetail->execute([
                $orderItemId,
                $detail['flower_type_id'],
                $detail['flower_type_name'],
                $detail['price_per_stem'],
                $detail['stem_quantity'],
                $lineTotal,
            ]);
        }
    }

    add_status_history($orderId, 'Pending', 'Order placed by customer.', null);

    if ($paymentIsDigital) {
        add_status_history($orderId, 'Pending', 'Payment proof uploaded. Waiting for admin verification.', null);
    }

    if ($saveProfile) {
        if ($fulfillment === 'delivery') {
            $updateUser = $pdo->prepare('UPDATE users SET phone = ?, address = ? WHERE id = ?');
            $updateUser->execute([$recipientPhone, $deliveryAddress, $user['id']]);
        } else {
            $updateUser = $pdo->prepare('UPDATE users SET phone = ? WHERE id = ?');
            $updateUser->execute([$recipientPhone, $user['id']]);
        }
    }

    $clearCart = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = ?');
    $clearCart->execute([$cart['id']]);

    $pdo->commit();
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($paymentProofPath !== null) {
        delete_payment_proof($paymentProofPath);
    }

    flash_set('error', $exception->getMessage());
    redirect('checkout.php');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($paymentProofPath !== null) {
        delete_payment_proof($paymentProofPath);
    }

    flash_set('error', 'We could not place your order. Please try again.');
    redirect('checkout.php');
}

$successMessage = payment_method_is_digital($paymentMethod)
    ? 'Your order has been placed. Your payment proof is waiting for admin verification.'
    : 'Your order has been placed. Please wait for admin confirmation.';
flash_set('success', $successMessage);
redirect('order-confirmation.php?code=' . rawurlencode($orderCode));
