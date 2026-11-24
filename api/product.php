<?php
/**
 * JSON product endpoint used by the product detail popup.
 */

require_once __DIR__ . '/../includes/functions.php';

$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$product = get_visible_product($productId);

if (!$product) {
    json_response(['ok' => false, 'message' => 'Product not found.'], 404);
}

$reviewSummary = product_review_summary((int) $product['id']);

$flowers = [];
if ($product['pricing_mode'] !== 'fixed') {
    foreach (get_flower_types_for_product((int) $product['id']) as $flower) {
        $flowers[] = [
            'id'    => (int) $flower['id'],
            'name'  => $flower['name'],
            'color' => $flower['color'] ?? '',
            'price' => (float) $flower['price_per_stem'],
            'stock' => (int) $flower['stock'],
        ];
    }
}

json_response([
    'ok'      => true,
    'product' => [
        'id'           => (int) $product['id'],
        'name'         => $product['name'],
        'description'  => $product['description'],
        'image'        => public_image_url($product['image']),
        'category'     => $product['category_name'],
        'pricing_mode' => $product['pricing_mode'],
        'price_label'  => $product['price_label'],
        'fixed_price'  => $product['fixed_price'] !== null ? (float) $product['fixed_price'] : null,
        'stock'        => (int) $product['stock'],
        'can_buy'      => (bool) $product['can_buy'],
        'min_stems'    => MIN_STEMS,
        'product_url'  => url('product.php?id=' . (int) $product['id']),
        'rating_average' => $reviewSummary['average'],
        'rating_count'   => $reviewSummary['count'],
        'flower_types' => $flowers,
    ],
]);
