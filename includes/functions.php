<?php
/**
 * Bloomify - shared helper functions.
 *
 * Keep this file free of HTML output. It is loaded by pages and actions.
 */

require_once __DIR__ . '/../config/database.php';

/* ---------------------------------------------------------------------
 * Output & URL helpers
 * ------------------------------------------------------------------- */

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function bloomify_logo_url(): string
{
    return url('assets/images/logo-bloomify.png');
}

function redirect(string $path): void
{
    if (preg_match('~^https?://~i', $path) || (isset($path[0]) && $path[0] === '/')) {
        header('Location: ' . $path);
        exit;
    }

    header('Location: ' . url($path));
    exit;
}

/**
 * Accept only local paths. Used for "intended destination" redirects.
 */
function normalize_redirect(?string $target): ?string
{
    if ($target === null) {
        return null;
    }

    $target = trim($target);
    if ($target === '' || preg_match('~^(https?:)?//~i', $target)) {
        return null;
    }

    if ($target[0] !== '/') {
        $target = url($target);
    }

    if (BASE_URL !== '' && strpos($target, BASE_URL) !== 0) {
        return null;
    }

    return $target;
}

function is_ajax(): bool
{
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }

    return isset($_SERVER['HTTP_ACCEPT'])
        && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function format_rupiah($amount): string
{
    return 'Rp' . number_format((float) $amount, 0, ',', '.');
}

function format_datetime(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }

    $timestamp = strtotime($datetime);
    return $timestamp ? date('d M Y, H:i', $timestamp) : '-';
}

function format_date(?string $date): string
{
    if (!$date) {
        return '-';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d M Y', $timestamp) : '-';
}

/* ---------------------------------------------------------------------
 * Flash messages
 * ------------------------------------------------------------------- */

function flash_set(string $key, $value): void
{
    $_SESSION['_flash'][$key] = $value;
}

function flash_get(string $key, $default = null)
{
    if (isset($_SESSION['_flash'][$key])) {
        $value = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    return $default;
}

function flash_has(string $key): bool
{
    return isset($_SESSION['_flash'][$key]);
}

/* ---------------------------------------------------------------------
 * CSRF
 * ------------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    return is_string($token)
        && $token !== ''
        && !empty($_SESSION['_csrf'])
        && hash_equals($_SESSION['_csrf'], $token);
}

function require_csrf(): void
{
    if (verify_csrf()) {
        return;
    }

    if (is_ajax()) {
        json_response(['ok' => false, 'message' => 'Invalid security token. Please refresh the page.'], 419);
    }

    http_response_code(419);
    exit('Invalid security token. Please refresh the page and try again.');
}

/* ---------------------------------------------------------------------
 * Authentication state
 * ------------------------------------------------------------------- */

function current_user(): ?array
{
    static $user = false;

    if ($user !== false) {
        return $user;
    }

    if (empty($_SESSION['user_id'])) {
        $user = null;
        return null;
    }

    $statement = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $statement->execute([$_SESSION['user_id']]);
    $found = $statement->fetch();

    if (!$found) {
        unset($_SESSION['user_id']);
        $user = null;
        return null;
    }

    $user = $found;
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function order_status_label(string $status): string
{
    return [
        'Pending' => 'Pending', 'Proses' => 'Processing', 'Dikirim' => 'On the Way',
        'Siap Diambil' => 'Ready for Pickup', 'Selesai' => 'Completed',
        'Rejected' => 'Rejected',
    ][$status] ?? $status;
}

function payment_status_label(string $status): string
{
    return [
        'Belum Dibayar'      => 'Unpaid',
        'Lunas'              => 'Paid',
        'Unpaid'             => 'Unpaid',
        'Pending Verification' => 'Pending Verification',
        'Paid'               => 'Paid',
        'Rejected'           => 'Rejected',
    ][$status] ?? $status;
}

function payment_methods(): array
{
    static $methods = null;

    if ($methods === null) {
        $methods = [
            'COD' => [
                'label'       => 'COD',
                'name'        => 'Cash on Delivery',
                'type'        => 'cod',
                'number'      => '',
                'holder'      => '',
                'description' => setting('payment_cod_note', 'Pay in cash when your order is delivered or collected at the store.'),
            ],
            'DANA' => [
                'label'       => 'DANA',
                'name'        => 'DANA',
                'type'        => 'digital',
                'number'      => setting('payment_dana_number'),
                'holder'      => setting('payment_dana_name'),
                'description' => 'Transfer to the DANA account below, then upload your payment proof.',
            ],
            'GoPay' => [
                'label'       => 'GoPay',
                'name'        => 'GoPay',
                'type'        => 'digital',
                'number'      => setting('payment_gopay_number'),
                'holder'      => setting('payment_gopay_name'),
                'description' => 'Transfer to the GoPay account below, then upload your payment proof.',
            ],
            'OVO' => [
                'label'       => 'OVO',
                'name'        => 'OVO',
                'type'        => 'digital',
                'number'      => setting('payment_ovo_number'),
                'holder'      => setting('payment_ovo_name'),
                'description' => 'Transfer to the OVO account below, then upload your payment proof.',
            ],
            'ShopeePay' => [
                'label'       => 'ShopeePay',
                'name'        => 'ShopeePay',
                'type'        => 'digital',
                'number'      => setting('payment_shopeepay_number'),
                'holder'      => setting('payment_shopeepay_name'),
                'description' => 'Transfer to the ShopeePay account below, then upload your payment proof.',
            ],
            'Bank BRI' => [
                'label'       => 'Bank BRI',
                'name'        => 'Bank BRI Transfer',
                'type'        => 'digital',
                'number'      => setting('payment_bank_bri_account'),
                'holder'      => setting('payment_bank_bri_holder'),
                'description' => 'Transfer to the Bank BRI account below, then upload your payment proof.',
            ],
        ];
    }

    return $methods;
}

function payment_method_label(string $method): string
{
    $methods = payment_methods();

    return $methods[$method]['label'] ?? $method;
}

function payment_method_is_digital(string $method): bool
{
    $methods = payment_methods();

    return ($methods[$method]['type'] ?? '') === 'digital';
}

function payment_status_options(string $method): array
{
    if (payment_method_is_digital($method)) {
        return ['Unpaid', 'Pending Verification', 'Paid'];
    }

    return ['Unpaid', 'Paid'];
}

function current_admin(): ?array
{
    static $admin = false;

    if ($admin !== false) {
        return $admin;
    }

    if (empty($_SESSION['admin_id'])) {
        $admin = null;
        return null;
    }

    $statement = db()->prepare('SELECT * FROM admin_accounts WHERE id = ? AND is_active = 1 LIMIT 1');
    $statement->execute([$_SESSION['admin_id']]);
    $found = $statement->fetch();

    if (!$found) {
        unset($_SESSION['admin_id']);
        $admin = null;
        return null;
    }

    $admin = $found;
    return $admin;
}

function is_admin_logged_in(): bool
{
    return current_admin() !== null;
}

function require_login(?string $intended = null): void
{
    if (is_logged_in()) {
        return;
    }

    if ($intended !== null) {
        $_SESSION['intended_url'] = normalize_redirect($intended);
    } elseif (empty($_SESSION['intended_url']) && isset($_SERVER['REQUEST_URI'])) {
        $_SESSION['intended_url'] = normalize_redirect($_SERVER['REQUEST_URI']);
    }

    flash_set('info', 'Please login first to continue.');
    redirect('login.php');
}

function require_admin(): void
{
    if (is_admin_logged_in()) {
        return;
    }

    if (isset($_SERVER['REQUEST_URI'])) {
        $_SESSION['admin_intended_url'] = normalize_redirect($_SERVER['REQUEST_URI']);
    }

    flash_set('info', 'Please log in with an administrator account.');
    redirect('login.php');
}

/* ---------------------------------------------------------------------
 * Store settings
 * ------------------------------------------------------------------- */

function setting(string $key, string $default = ''): string
{
    static $settings = null;

    if ($settings === null) {
        $settings = [];

        try {
            foreach (db()->query('SELECT setting_key, setting_value FROM store_settings') as $row) {
                $settings[$row['setting_key']] = (string) $row['setting_value'];
            }
        } catch (Throwable $exception) {
            $settings = [];
        }
    }

    return $settings[$key] ?? $default;
}

function public_image_url(?string $path): string
{
    if ($path === null || trim($path) === '') {
        return url('assets/images/Flower.png');
    }

    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }

    return url($path);
}

/* ---------------------------------------------------------------------
 * Pricing & product helpers
 * ------------------------------------------------------------------- */

function wrapping_fee(int $totalStems): int
{
    if ($totalStems <= WRAPPING_FREE_STEMS) {
        return 0;
    }

    $paidSteps = intdiv($totalStems - WRAPPING_FREE_STEMS - 1, WRAPPING_STEMS_PER_STEP) + 1;

    return $paidSteps * WRAPPING_STEP;
}

function get_flower_types(): array
{
    $sql = 'SELECT ft.id, ft.name, ft.color, ft.price_per_stem,
                   COALESCE(fs.quantity, 0) AS stock,
                   COALESCE(fs.low_stock_threshold, ?) AS low_stock_threshold
            FROM flower_types ft
            LEFT JOIN flower_stocks fs ON fs.flower_type_id = ft.id
            ORDER BY ft.name ASC, ft.color ASC';

    $statement = db()->prepare($sql);
    $statement->execute([LOW_STOCK_FALLBACK]);
    return $statement->fetchAll();
}

/**
 * Decorate a product row (must contain pricing_mode, flower_stock,
 * product_stock, total_flower_stock, min_flower_price, fixed_price,
 * price_per_stem).
 */
function get_flower_types_for_category(int $categoryId): array
{
    $statement = db()->prepare(
        'SELECT ft.id, ft.name, ft.color, ft.price_per_stem,
                COALESCE(fs.quantity, 0) AS stock,
                COALESCE(fs.low_stock_threshold, ?) AS low_stock_threshold
         FROM category_flower_types cft
         JOIN flower_types ft ON ft.id = cft.flower_type_id
         LEFT JOIN flower_stocks fs ON fs.flower_type_id = ft.id
         WHERE cft.category_id = ?
         ORDER BY ft.name ASC, ft.color ASC'
    );
    $statement->execute([LOW_STOCK_FALLBACK, $categoryId]);
    return $statement->fetchAll();
}

/** Flower choices configured individually for one product. */
function get_flower_types_for_product(int $productId): array
{
    $statement = db()->prepare(
        'SELECT ft.id, ft.name, ft.color, ft.price_per_stem,
                COALESCE(fs.quantity, 0) AS stock,
                COALESCE(fs.low_stock_threshold, ?) AS low_stock_threshold
         FROM product_flower_types pft
         JOIN flower_types ft ON ft.id = pft.flower_type_id
         LEFT JOIN flower_stocks fs ON fs.flower_type_id = ft.id
         WHERE pft.product_id = ?
         ORDER BY ft.name ASC, ft.color ASC'
    );
    $statement->execute([LOW_STOCK_FALLBACK, $productId]);
    return $statement->fetchAll();
}

function decorate_product(array $product): array
{
    $mode = $product['effective_pricing_mode'] ?? $product['pricing_mode'] ?? 'per_stem';
    $product['pricing_mode'] = $mode;

    if ($mode === 'fixed') {
        $stock = (int) ($product['product_stock'] ?? 0);
        $product['unit_label'] = 'item';
        $product['price_value'] = (float) ($product['fixed_price'] ?? 0);
    } elseif ($mode === 'mixed') {
        $stock = (int) ($product['total_flower_stock'] ?? 0);
        $product['unit_label'] = 'stem';
        $product['price_value'] = (float) ($product['min_flower_price'] ?? 0);
    } else {
        $stock = (int) ($product['total_flower_stock'] ?? 0);
        $product['unit_label'] = 'stem';
        $product['price_value'] = (float) ($product['min_flower_price'] ?? $product['price_per_stem'] ?? 0);
    }

    $product['stock'] = $stock;
    $product['is_sold_out'] = $stock <= 0;
    $product['can_buy'] = $mode === 'fixed' ? $stock >= 1 : $stock >= MIN_STEMS;
    $product['price_label'] = product_price_label($product);

    return $product;
}

function product_price_label(array $product): string
{
    $mode = $product['effective_pricing_mode'] ?? $product['pricing_mode'] ?? 'per_stem';

    if ($mode === 'fixed') {
        return format_rupiah($product['fixed_price'] ?? 0);
    }

    if ($mode === 'mixed') {
        $minimum = (float) ($product['min_flower_price'] ?? 0);
        return 'From ' . format_rupiah($minimum) . ' / stem';
    }

    return 'From ' . format_rupiah($product['min_flower_price'] ?? $product['price_per_stem'] ?? 0) . ' / stem';
}

function get_product(int $id): ?array
{
    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                   COALESCE(p.pricing_mode, c.pricing_mode) AS effective_pricing_mode,
                   ft.name AS flower_type_name, ft.price_per_stem,
                   fs.quantity AS flower_stock,
                   ps.quantity AS product_stock,
                   (SELECT COALESCE(SUM(fs2.quantity), 0) FROM product_flower_types pft2 JOIN flower_stocks fs2 ON fs2.flower_type_id = pft2.flower_type_id WHERE pft2.product_id = p.id) AS total_flower_stock,
                   (SELECT MIN(ft2.price_per_stem) FROM product_flower_types pft3 JOIN flower_types ft2 ON ft2.id = pft3.flower_type_id WHERE pft3.product_id = p.id) AS min_flower_price
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN flower_types ft ON ft.id = p.flower_type_id
            LEFT JOIN flower_stocks fs ON fs.flower_type_id = p.flower_type_id
            LEFT JOIN product_stocks ps ON ps.product_id = p.id
            WHERE p.id = ?
            LIMIT 1';

    $statement = db()->prepare($sql);
    $statement->execute([$id]);
    $product = $statement->fetch();

    return $product ? decorate_product($product) : null;
}

function get_visible_product(int $id): ?array
{
    $product = get_product($id);

    if (!$product || (int) $product['is_visible'] !== 1) {
        return null;
    }

    return $product;
}

function get_categories(): array
{
    return db()->query('SELECT * FROM categories ORDER BY id ASC')->fetchAll();
}

function get_public_products(string $search = '', string $categorySlug = '', string $sort = 'newest'): array
{
    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                   COALESCE(p.pricing_mode, c.pricing_mode) AS effective_pricing_mode,
                   ft.name AS flower_type_name, ft.price_per_stem,
                   fs.quantity AS flower_stock,
                   ps.quantity AS product_stock,
                   (SELECT COALESCE(SUM(fs2.quantity), 0) FROM product_flower_types pft2 JOIN flower_stocks fs2 ON fs2.flower_type_id = pft2.flower_type_id WHERE pft2.product_id = p.id) AS total_flower_stock,
                   (SELECT MIN(ft2.price_per_stem) FROM product_flower_types pft3 JOIN flower_types ft2 ON ft2.id = pft3.flower_type_id WHERE pft3.product_id = p.id) AS min_flower_price
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN flower_types ft ON ft.id = p.flower_type_id
            LEFT JOIN flower_stocks fs ON fs.flower_type_id = p.flower_type_id
            LEFT JOIN product_stocks ps ON ps.product_id = p.id
            WHERE p.is_visible = 1';

    $parameters = [];

    if ($search !== '') {
        $sql .= ' AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)';
        $like = '%' . $search . '%';
        $parameters[] = $like;
        $parameters[] = $like;
        $parameters[] = $like;
    }

    if ($categorySlug !== '') {
        $sql .= ' AND c.slug = ?';
        $parameters[] = $categorySlug;
    }

    if ($sort === 'price_low') {
        $sql .= " ORDER BY CASE COALESCE(p.pricing_mode, c.pricing_mode)
                    WHEN 'fixed' THEN p.fixed_price
                    WHEN 'mixed' THEN (SELECT MIN(ft3.price_per_stem) FROM product_flower_types pft3 JOIN flower_types ft3 ON ft3.id = pft3.flower_type_id WHERE pft3.product_id = p.id)
                    ELSE ft.price_per_stem END ASC";
    } elseif ($sort === 'price_high') {
        $sql .= " ORDER BY CASE COALESCE(p.pricing_mode, c.pricing_mode)
                    WHEN 'fixed' THEN p.fixed_price
                    WHEN 'mixed' THEN (SELECT MIN(ft3.price_per_stem) FROM product_flower_types pft3 JOIN flower_types ft3 ON ft3.id = pft3.flower_type_id WHERE pft3.product_id = p.id)
                    ELSE ft.price_per_stem END DESC";
    } elseif ($sort === 'name') {
        $sql .= ' ORDER BY p.name ASC';
    } else {
        $sql .= ' ORDER BY p.created_at DESC, p.id DESC';
    }

    $statement = db()->prepare($sql);
    $statement->execute($parameters);

    return array_map('decorate_product', $statement->fetchAll());
}

/* ---------------------------------------------------------------------
 * Cart helpers
 * ------------------------------------------------------------------- */

function get_cart(int $userId, bool $create = false): ?array
{
    $statement = db()->prepare('SELECT * FROM carts WHERE user_id = ? LIMIT 1');
    $statement->execute([$userId]);
    $cart = $statement->fetch();

    if (!$cart && $create) {
        $insert = db()->prepare('INSERT INTO carts (user_id) VALUES (?)');
        $insert->execute([$userId]);
        $statement->execute([$userId]);
        $cart = $statement->fetch();
    }

    return $cart ?: null;
}

function cart_config_hash(int $productId, string $itemType, array $flowerQuantities): string
{
    ksort($flowerQuantities);
    $payload = $productId . '|' . $itemType . '|' . json_encode($flowerQuantities);

    return md5($payload);
}

function get_cart_items(int $cartId): array
{
    $sql = 'SELECT ci.*, p.name AS product_name, p.image AS product_image, p.fixed_price,
                   p.is_visible,
                   c.name AS category_name,
                   COALESCE(p.pricing_mode, c.pricing_mode) AS pricing_mode,
                   ft.name AS flower_type_name, ft.price_per_stem,
                   ps.quantity AS product_stock
            FROM cart_items ci
            JOIN products p ON p.id = ci.product_id
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN flower_types ft ON ft.id = p.flower_type_id
            LEFT JOIN product_stocks ps ON ps.product_id = p.id
            WHERE ci.cart_id = ?
            ORDER BY ci.id DESC';

    $statement = db()->prepare($sql);
    $statement->execute([$cartId]);
    $items = $statement->fetchAll();

    if (!$items) {
        return [];
    }

    $itemIds = array_column($items, 'id');
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));

    $detailSql = "SELECT cid.*, CONCAT(ft.name, CASE WHEN ft.color <> '' THEN CONCAT(' — ', ft.color) ELSE '' END) AS flower_type_name, ft.price_per_stem,
                         COALESCE(fs.quantity, 0) AS stock
                  FROM cart_item_details cid
                  JOIN flower_types ft ON ft.id = cid.flower_type_id
                  LEFT JOIN flower_stocks fs ON fs.flower_type_id = cid.flower_type_id
                  WHERE cid.cart_item_id IN ($placeholders)
                  ORDER BY ft.name ASC";

    $detailStatement = db()->prepare($detailSql);
    $detailStatement->execute($itemIds);
    $details = $detailStatement->fetchAll();

    $grouped = [];
    foreach ($details as $detail) {
        $grouped[$detail['cart_item_id']][] = $detail;
    }

    foreach ($items as &$item) {
        $itemDetails = $grouped[$item['id']] ?? [];
        $item['details'] = $itemDetails;
        $item['stems'] = 0;
        $item['flower_subtotal'] = 0.0;
        $item['wrapping_fee'] = 0.0;
        $item['item_subtotal'] = 0.0;
        $item['stock_ok'] = true;

        if ($item['item_type'] === 'fixed') {
            $item['units'] = (int) $item['quantity'];
            $unitPrice = (float) ($item['fixed_price'] ?? 0);
            $item['flower_subtotal'] = $unitPrice * $item['units'];
            $item['item_subtotal'] = $item['flower_subtotal'];

            if ($item['units'] > (int) ($item['product_stock'] ?? 0)) {
                $item['stock_ok'] = false;
            }
        } else {
            foreach ($itemDetails as $detail) {
                $line = (float) $detail['price_per_stem'] * (int) $detail['stem_quantity'];
                $item['stems'] += (int) $detail['stem_quantity'];
                $item['flower_subtotal'] += $line;

                if ((int) $detail['stem_quantity'] > (int) $detail['stock']) {
                    $item['stock_ok'] = false;
                }
            }

            $item['wrapping_fee'] = wrapping_fee((int) $item['stems']);
            $item['item_subtotal'] = $item['flower_subtotal'] + $item['wrapping_fee'];

            if ($item['stems'] < MIN_STEMS) {
                $item['stock_ok'] = false;
            }
        }

        $item['product_available'] = (int) $item['is_visible'] === 1;
    }
    unset($item);

    // A flower type may appear in several cart configurations. Validate the
    // combined demand, not just each individual row, so checkout cannot accept
    // a cart that the admin would later be unable to confirm.
    $flowerDemand = [];
    foreach ($items as $item) {
        if ($item['item_type'] === 'fixed') {
            continue;
        }

        foreach ($item['details'] as $detail) {
            $flowerId = (int) $detail['flower_type_id'];
            if (!isset($flowerDemand[$flowerId])) {
                $flowerDemand[$flowerId] = [
                    'quantity' => 0,
                    'stock' => (int) $detail['stock'],
                ];
            }
            $flowerDemand[$flowerId]['quantity'] += (int) $detail['stem_quantity'];
        }
    }

    $overStockFlowers = [];
    foreach ($flowerDemand as $flowerId => $demand) {
        if ($demand['quantity'] > $demand['stock']) {
            $overStockFlowers[(int) $flowerId] = true;
        }
    }

    if ($overStockFlowers) {
        foreach ($items as &$item) {
            if ($item['item_type'] === 'fixed') {
                continue;
            }
            foreach ($item['details'] as $detail) {
                if (isset($overStockFlowers[(int) $detail['flower_type_id']])) {
                    $item['stock_ok'] = false;
                    break;
                }
            }
        }
        unset($item);
    }

    return $items;
}

function cart_totals(array $items): array
{
    $productSubtotal = 0.0;
    $wrappingTotal = 0.0;
    $stems = 0;

    foreach ($items as $item) {
        $productSubtotal += (float) $item['flower_subtotal'];
        $wrappingTotal += (float) $item['wrapping_fee'];

        if ($item['item_type'] === 'fixed') {
            $stems += (int) $item['units'];
        } else {
            $stems += (int) $item['stems'];
        }
    }

    return [
        'product_subtotal' => $productSubtotal,
        'wrapping_total'   => $wrappingTotal,
        'total'            => $productSubtotal + $wrappingTotal,
        'item_count'       => count($items),
        'stem_count'       => $stems,
    ];
}

function cart_item_count(?int $userId): int
{
    if (!$userId) {
        return 0;
    }

    $cart = get_cart($userId);
    if (!$cart) {
        return 0;
    }

    $statement = db()->prepare('SELECT COUNT(*) FROM cart_items WHERE cart_id = ?');
    $statement->execute([$cart['id']]);

    return (int) $statement->fetchColumn();
}

/* ---------------------------------------------------------------------
 * Notifications & orders
 * ------------------------------------------------------------------- */

function unread_notification_count(?int $userId): int
{
    if (!$userId) {
        return 0;
    }

    $statement = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $statement->execute([$userId]);

    return (int) $statement->fetchColumn();
}

function create_notification(int $userId, ?int $orderId, string $title, string $message): void
{
    $statement = db()->prepare(
        'INSERT INTO notifications (user_id, order_id, title, message) VALUES (?, ?, ?, ?)'
    );
    $statement->execute([$userId, $orderId, $title, $message]);
}

function add_status_history(int $orderId, string $status, ?string $note, ?int $adminId): void
{
    $statement = db()->prepare(
        'INSERT INTO order_status_history (order_id, status, note, admin_id) VALUES (?, ?, ?, ?)'
    );
    $statement->execute([$orderId, $status, $note, $adminId]);
}

function get_order_by_code(string $code, ?int $userId = null): ?array
{
    $sql = 'SELECT o.*, u.full_name AS customer_name, u.username AS customer_username,
                   u.phone AS customer_phone, u.profile_photo AS customer_profile_photo
            FROM orders o
            JOIN users u ON u.id = o.user_id
            WHERE o.order_code = ?';
    $parameters = [$code];

    if ($userId !== null) {
        $sql .= ' AND o.user_id = ?';
        $parameters[] = $userId;
    }

    $sql .= ' LIMIT 1';

    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    $order = $statement->fetch();

    return $order ?: null;
}

function get_order_items(int $orderId): array
{
    $statement = db()->prepare(
        'SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC'
    );
    $statement->execute([$orderId]);
    $items = $statement->fetchAll();

    if (!$items) {
        return [];
    }

    $itemIds = array_column($items, 'id');
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));

    $detailStatement = db()->prepare(
        "SELECT * FROM order_item_details WHERE order_item_id IN ($placeholders) ORDER BY id ASC"
    );
    $detailStatement->execute($itemIds);
    $details = $detailStatement->fetchAll();

    $grouped = [];
    foreach ($details as $detail) {
        $grouped[$detail['order_item_id']][] = $detail;
    }

    foreach ($items as &$item) {
        $item['details'] = $grouped[$item['id']] ?? [];
    }
    unset($item);

    return $items;
}

function get_order_history(int $orderId): array
{
    $statement = db()->prepare(
        'SELECT h.*, a.full_name AS admin_name
         FROM order_status_history h
         LEFT JOIN admin_accounts a ON a.id = h.admin_id
         WHERE h.order_id = ?
         ORDER BY h.created_at ASC, h.id ASC'
    );
    $statement->execute([$orderId]);
    return $statement->fetchAll();
}

/**
 * Statuses that the customer is allowed to complete. Completion is never an
 * admin action; these are the final delivery/handover states.
 */
function customer_completable_statuses(): array
{
    return ['Dikirim', 'Siap Diambil'];
}

function order_is_awaiting_customer_confirmation(array $order): bool
{
    return in_array($order['order_status'] ?? '', customer_completable_statuses(), true)
        && !empty($order['user_id']);
}

function order_status_class(string $status): string
{
    $map = [
        'Pending'      => 'status-pending',
        'Proses'       => 'status-process',
        'Dikirim'      => 'status-shipped',
        'Siap Diambil' => 'status-ready',
        'Selesai'      => 'status-done',
        'Rejected'     => 'status-rejected',
    ];

    return $map[$status] ?? 'status-pending';
}

function payment_status_class(string $status): string
{
    return [
        'Belum Dibayar'        => 'status-unpaid',
        'Lunas'                => 'status-paid',
        'Unpaid'               => 'status-unpaid',
        'Pending Verification' => 'status-pending-verification',
        'Paid'                 => 'status-paid',
        'Rejected'             => 'status-rejected',
    ][$status] ?? 'status-unpaid';
}

function next_order_statuses(string $currentStatus, string $fulfillmentMethod): array
{
    if ($currentStatus === 'Pending') {
        return ['Proses'];
    }

    if ($currentStatus === 'Proses') {
        return $fulfillmentMethod === 'pickup' ? ['Siap Diambil'] : ['Dikirim'];
    }

    // Completion belongs to the customer. Admins stop at the handover state.
    if ($currentStatus === 'Dikirim' || $currentStatus === 'Siap Diambil') {
        return [];
    }

    return [];
}

/* ---------------------------------------------------------------------
 * Product reviews
 * ------------------------------------------------------------------- */

function product_review_summary(int $productId): array
{
    $statement = db()->prepare(
        'SELECT rating_avg, rating_count FROM products WHERE id = ? LIMIT 1'
    );
    $statement->execute([$productId]);
    $product = $statement->fetch();

    if (!$product) {
        return ['average' => 0.0, 'count' => 0];
    }

    return [
        'average' => round((float) $product['rating_avg'], 1),
        'count'   => (int) $product['rating_count'],
    ];
}

function get_product_reviews(int $productId): array
{
    $statement = db()->prepare(
        'SELECT r.*, u.full_name AS customer_name, u.username AS customer_username
         FROM product_reviews r
         JOIN users u ON u.id = r.user_id
         WHERE r.product_id = ?
         ORDER BY r.created_at DESC, r.id DESC'
    );
    $statement->execute([$productId]);

    return $statement->fetchAll();
}

function get_review_by_id(int $reviewId): ?array
{
    $statement = db()->prepare(
        'SELECT r.*, p.name AS product_name, o.order_code
         FROM product_reviews r
         JOIN products p ON p.id = r.product_id
         JOIN orders o ON o.id = r.order_id
         WHERE r.id = ?
         LIMIT 1'
    );
    $statement->execute([$reviewId]);
    $review = $statement->fetch();

    return $review ?: null;
}

function get_order_product_review(int $userId, int $orderId, int $productId): ?array
{
    $statement = db()->prepare(
        'SELECT * FROM product_reviews
         WHERE user_id = ? AND order_id = ? AND product_id = ?
         LIMIT 1'
    );
    $statement->execute([$userId, $orderId, $productId]);
    $review = $statement->fetch();

    return $review ?: null;
}

/**
 * Find a completed, purchased product that the customer has not reviewed yet.
 * The row-level check is repeated by the save action inside a transaction.
 */
function find_reviewable_order_item(int $userId, int $productId): ?array
{
    $statement = db()->prepare(
        'SELECT o.id AS order_id, o.order_code, oi.id AS order_item_id, oi.product_name
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         LEFT JOIN product_reviews pr
                ON pr.order_id = o.id
               AND pr.product_id = oi.product_id
               AND pr.user_id = ?
         WHERE o.user_id = ?
           AND o.order_status = \'Selesai\'
           AND oi.product_id = ?
           AND pr.id IS NULL
         ORDER BY o.completed_at DESC, o.id DESC
         LIMIT 1'
    );
    $statement->execute([$userId, $userId, $productId]);
    $orderItem = $statement->fetch();

    return $orderItem ?: null;
}

function validate_review_submission($rating, $comment): array
{
    $errors = [];

    $ratingValue = filter_var($rating, FILTER_VALIDATE_INT);
    if ($ratingValue === false || $ratingValue < 1 || $ratingValue > 5) {
        $errors[] = 'Choose a star rating between 1 and 5.';
    }

    $text = trim((string) $comment);
    if (mb_strlen($text) < 5 || mb_strlen($text) > 1000) {
        $errors[] = 'Write a review between 5 and 1000 characters.';
    }

    return [$ratingValue === false ? 0 : (int) $ratingValue, $text, $errors];
}

function generate_order_code(): string
{
    return 'BLM-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Restore stock that was deducted when an order was confirmed.
 * The caller must already hold a row lock on the order and a transaction.
 */
function restore_order_stock(int $orderId, array $order, ?int $adminId): void
{
    $pdo = db();

    foreach (get_order_items($orderId) as $item) {
        if ($item['item_type'] === 'fixed') {
            $units = (int) $item['quantity'];
            $productId = (int) ($item['product_id'] ?? 0);

            if ($units <= 0 || $productId <= 0) {
                continue;
            }

            $lock = $pdo->prepare('SELECT quantity FROM product_stocks WHERE product_id = ? FOR UPDATE');
            $lock->execute([$productId]);
            $current = $lock->fetchColumn();

            if ($current === false) {
                $insert = $pdo->prepare(
                    'INSERT INTO product_stocks (product_id, quantity) VALUES (?, ?)'
                );
                $insert->execute([$productId, $units]);
            } else {
                $update = $pdo->prepare(
                    'UPDATE product_stocks SET quantity = quantity + ? WHERE product_id = ?'
                );
                $update->execute([$units, $productId]);
            }

            $newQuantity = ($current === false ? 0 : (int) $current) + $units;

            $movement = $pdo->prepare(
                'INSERT INTO stock_movements
                    (item_type, flower_type_id, product_id, change_qty, stock_after, note, admin_id)
                 VALUES (?, NULL, ?, ?, ?, ?, ?)'
            );
            $movement->execute([
                'product',
                $productId,
                $units,
                $newQuantity,
                'Order rejection ' . $order['order_code'],
                $adminId,
            ]);

            continue;
        }

        foreach ($item['details'] as $detail) {
            $stems = (int) $detail['stem_quantity'];
            $flowerId = (int) ($detail['flower_type_id'] ?? 0);

            if ($stems <= 0 || $flowerId <= 0) {
                continue;
            }

            $lock = $pdo->prepare('SELECT quantity FROM flower_stocks WHERE flower_type_id = ? FOR UPDATE');
            $lock->execute([$flowerId]);
            $current = $lock->fetchColumn();

            if ($current === false) {
                $insert = $pdo->prepare(
                    'INSERT INTO flower_stocks (flower_type_id, quantity) VALUES (?, ?)'
                );
                $insert->execute([$flowerId, $stems]);
            } else {
                $update = $pdo->prepare(
                    'UPDATE flower_stocks SET quantity = quantity + ? WHERE flower_type_id = ?'
                );
                $update->execute([$stems, $flowerId]);
            }

            $newQuantity = ($current === false ? 0 : (int) $current) + $stems;

            $movement = $pdo->prepare(
                'INSERT INTO stock_movements
                    (item_type, flower_type_id, product_id, change_qty, stock_after, note, admin_id)
                 VALUES (?, ?, NULL, ?, ?, ?, ?)'
            );
            $movement->execute([
                'flower',
                $flowerId,
                $stems,
                $newQuantity,
                'Order rejection ' . $order['order_code'],
                $adminId,
            ]);
        }
    }
}

/**
 * Reject an order, optionally restoring stock, and notify the customer.
 */
function reject_order(int $orderId, int $adminId, string $reason): void
{
    $reason = trim($reason);

    if ($reason === '') {
        throw new RuntimeException('Enter a rejection reason before rejecting this order.');
    }

    if (mb_strlen($reason) > 255) {
        $reason = mb_substr($reason, 0, 255);
    }

    $pdo = db();
    $ownsTransaction = !$pdo->inTransaction();

    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $lock = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        $lock->execute([$orderId]);
        $order = $lock->fetch();

        if (!$order) {
            throw new RuntimeException('Order not found.');
        }

        if ($order['order_status'] === 'Rejected') {
            throw new RuntimeException('This order has already been rejected.');
        }

        if ($order['order_status'] === 'Selesai') {
            throw new RuntimeException('Completed orders cannot be rejected.');
        }

        $alreadyRestored = !empty($order['stock_restored_at']);
        if (!$alreadyRestored && !empty($order['confirmed_at'])) {
            restore_order_stock($orderId, $order, $adminId);
        }

        $update = $pdo->prepare(
            "UPDATE orders
             SET order_status = 'Rejected',
                 rejection_reason = ?,
                 rejected_at = NOW(),
                 rejected_by = ?,
                 stock_restored_at = CASE
                     WHEN stock_restored_at IS NOT NULL THEN stock_restored_at
                     WHEN confirmed_at IS NOT NULL THEN NOW()
                     ELSE NULL
                 END,
                 updated_at = NOW()
             WHERE id = ?
               AND order_status <> 'Rejected'"
        );
        $update->execute([$reason, $adminId, $orderId]);

        if ($update->rowCount() === 0) {
            throw new RuntimeException('This order has already been rejected.');
        }

        if (payment_method_is_digital($order['payment_method'])
            && in_array($order['payment_status'], ['Unpaid', 'Pending Verification', 'Rejected'], true)) {
            $paymentUpdate = $pdo->prepare(
                "UPDATE orders
                 SET payment_status = 'Rejected',
                     updated_at = NOW()
                 WHERE id = ?"
            );
            $paymentUpdate->execute([$orderId]);
        }

        add_status_history(
            $orderId,
            'Rejected',
            mb_substr('Order rejected by Admin: ' . $reason, 0, 255),
            $adminId
        );

        $message = 'Your order ' . $order['order_code'] . ' was rejected by the Admin. Reason: ' . $reason;
        create_notification(
            (int) $order['user_id'],
            $orderId,
            'Order rejected',
            mb_substr($message, 0, 255)
        );

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

/* ---------------------------------------------------------------------
 * Uploads
 * ------------------------------------------------------------------- */

function save_uploaded_image(array $file, string $subdirectory, string $prefix = 'img'): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('No file was uploaded.');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The upload failed. Please try again.');
    }

    if ((int) $file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('The image must be 2 MB or smaller.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $mime = null;
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
    } else {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = $extension === 'png' ? 'image/png'
            : ($extension === 'webp' ? 'image/webp' : 'image/jpeg');
    }

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, or WEBP images are allowed.');
    }

    $directory = APP_ROOT . '/assets/uploads/' . trim($subdirectory, '/');
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create the upload directory.');
    }

    $filename = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];

    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }

    return 'assets/uploads/' . trim($subdirectory, '/') . '/' . $filename;
}

/**
 * Save a payment proof outside the public web root.
 * Returns a storage-relative path such as "payment-proofs/proof-u1-....jpg".
 */
function save_payment_proof(array $file, int $userId): string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Please upload your payment proof.');
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The payment proof upload failed. Please try again.');
    }

    $size = (int) ($file['size'] ?? 0);

    if ($size <= 0) {
        throw new RuntimeException('The uploaded payment proof is empty.');
    }

    if ($size > 2 * 1024 * 1024) {
        throw new RuntimeException('The payment proof must be 2 MB or smaller.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new RuntimeException('The payment proof must be a valid JPG, PNG, or WEBP image.');
    }

    if ($mime === '' && !empty($imageInfo['mime'])) {
        $mime = (string) $imageInfo['mime'];
    }

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, or WEBP images are allowed.');
    }

    $directory = rtrim(STORAGE_ROOT, '/\\') . '/payment-proofs';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create the payment proof storage directory.');
    }

    $filename = 'proof-u' . $userId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];

    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('Could not save the uploaded payment proof.');
    }

    return 'payment-proofs/' . $filename;
}

function payment_proof_absolute_path(string $storedPath): ?string
{
    $storedPath = str_replace('\\', '/', trim($storedPath));

    if ($storedPath === '' || strpos($storedPath, '..') !== false || preg_match('~^[a-z]+://~i', $storedPath)) {
        return null;
    }

    if (strpos($storedPath, 'assets/uploads/') === 0) {
        return APP_ROOT . '/' . ltrim($storedPath, '/');
    }

    return rtrim(STORAGE_ROOT, '/\\') . '/' . ltrim($storedPath, '/');
}

function delete_payment_proof(string $storedPath): void
{
    $absolutePath = payment_proof_absolute_path($storedPath);

    if ($absolutePath !== null && is_file($absolutePath)) {
        unlink($absolutePath);
    }
}

function payment_proof_url(string $orderCode): string
{
    return url('payment-proof.php?code=' . rawurlencode($orderCode));
}
