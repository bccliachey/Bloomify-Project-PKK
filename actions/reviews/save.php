<?php
/**
 * Create or edit a product review.
 *
 * Reviews are only accepted for a product that belongs to the customer's own
 * Completed order. Editing is restricted to the original reviewer.
 */

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_login();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('orders.php');
}

require_csrf();

$user = current_user();
$action = (string) ($_POST['review_action'] ?? 'create');
$reviewId = (int) ($_POST['review_id'] ?? 0);
$orderCode = trim((string) ($_POST['order_code'] ?? ''));
$productId = (int) ($_POST['product_id'] ?? 0);
$commentInput = (string) ($_POST['comment'] ?? '');

[$rating, $comment, $errors] = validate_review_submission($_POST['rating'] ?? null, $commentInput);

$formPath = $action === 'update'
    ? 'review-form.php?review=' . $reviewId
    : 'review-form.php?order=' . rawurlencode($orderCode) . '&product=' . $productId;

$pdo = db();
$productForRedirect = $productId;

try {
    if ($action !== 'create' && $action !== 'update') {
        throw new RuntimeException('Unknown review action.');
    }

    if ($errors) {
        throw new RuntimeException(implode(' ', $errors));
    }

    $pdo->beginTransaction();

    if ($action === 'update') {
        $lock = $pdo->prepare(
            'SELECT * FROM product_reviews WHERE id = ? AND user_id = ? LIMIT 1 FOR UPDATE'
        );
        $lock->execute([$reviewId, $user['id']]);
        $review = $lock->fetch();

        if (!$review) {
            throw new RuntimeException('You can only edit reviews you submitted yourself.');
        }

        $productForRedirect = (int) $review['product_id'];

        $update = $pdo->prepare(
            'UPDATE product_reviews
             SET rating = ?, comment = ?
             WHERE id = ? AND user_id = ?'
        );
        $update->execute([$rating, $comment, $reviewId, $user['id']]);

        $message = 'Your review has been updated.';
    } else {
        if ($orderCode === '' || $productId <= 0) {
            throw new RuntimeException('Choose a purchased product to review.');
        }

        $lock = $pdo->prepare(
            'SELECT * FROM orders
             WHERE order_code = ? AND user_id = ?
             LIMIT 1 FOR UPDATE'
        );
        $lock->execute([$orderCode, $user['id']]);
        $order = $lock->fetch();

        if (!$order) {
            throw new RuntimeException('This order was not found in your order history.');
        }

        if ($order['order_status'] !== 'Selesai') {
            throw new RuntimeException('You can only review products from a completed order.');
        }

        $item = $pdo->prepare(
            'SELECT id FROM order_items
             WHERE order_id = ? AND product_id = ?
             LIMIT 1'
        );
        $item->execute([$order['id'], $productId]);
        $orderItem = $item->fetch();

        if (!$orderItem) {
            throw new RuntimeException('This product is not part of that order.');
        }

        $existing = $pdo->prepare(
            'SELECT id FROM product_reviews
             WHERE order_id = ? AND product_id = ?
             LIMIT 1'
        );
        $existing->execute([$order['id'], $productId]);

        if ($existing->fetch()) {
            throw new RuntimeException('You have already reviewed this product for that order.');
        }

        $insert = $pdo->prepare(
            'INSERT INTO product_reviews
                (user_id, product_id, order_id, order_item_id, rating, comment)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $user['id'],
            $productId,
            $order['id'],
            (int) $orderItem['id'],
            $rating,
            $comment,
        ]);

        $message = 'Thank you. Your review has been published.';
    }

    $pdo->commit();
    flash_set('success', $message);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $message = $exception->getMessage();

    if ($exception instanceof PDOException) {
        $message = ((string) $exception->getCode() === '23000')
            ? 'You have already reviewed this product for that order.'
            : 'The review could not be saved because of a database error. Please try again.';
    }

    flash_set('error', $message ?: 'The review could not be saved.');
    flash_set('review_old', [
        'rating'  => $rating,
        'comment' => $commentInput,
    ]);

    redirect($formPath);
}

redirect('product.php?id=' . $productForRedirect . '#reviews');
