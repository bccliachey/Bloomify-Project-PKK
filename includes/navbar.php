<?php
/**
 * Public navbar. Uses the existing Bloomify navbar classes.
 */

$user = current_user();
$cartCount = $user ? cart_item_count((int) $user['id']) : 0;
$unreadCount = $user ? unread_notification_count((int) $user['id']) : 0;
$searchValue = isset($_GET['q']) ? (string) $_GET['q'] : '';

$navItems = [
    'home'    => ['label' => 'Home', 'href' => 'index.php'],
    'shop'    => ['label' => 'Shop', 'href' => 'catalog.php'],
    'orders'  => ['label' => 'Order History', 'href' => $user ? 'orders.php' : 'login.php'],
    'cart'    => ['label' => 'Cart', 'href' => 'cart.php'],
    'account' => ['label' => 'Account', 'href' => $user ? 'account.php' : 'login.php'],
];
?>
<header class="navbar" id="siteNavbar">
    <a href="<?= e(url('index.php')) ?>" class="logo">
        <img src="<?= e(bloomify_logo_url()) ?>" alt="" aria-hidden="true" class="site-logo-image">
        <span><?= e(setting('store_name', 'Bloomify')) ?></span>
    </a>

    <nav aria-label="Main navigation">
        <ul class="nav-links" id="navLinks">
            <?php foreach ($navItems as $key => $item): ?>
                <li>
                    <a href="<?= e(url($item['href'])) ?>"
                       class="<?= $activeNav === $key ? 'active' : '' ?>">
                        <?= e($item['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>

            <li class="nav-search-mobile">
                <form action="<?= e(url('catalog.php')) ?>" method="get" role="search">
                    <input type="search" name="q" placeholder="Search flowers..."
                           value="<?= e($searchValue) ?>" aria-label="Search products">
                    <button type="submit" aria-label="Search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </form>
            </li>
        </ul>
    </nav>

    <div class="nav-actions">
        <form class="nav-search" action="<?= e(url('catalog.php')) ?>" method="get" role="search">
            <input type="search" name="q" placeholder="Search..."
                   value="<?= e($searchValue) ?>" aria-label="Search products">
            <button type="submit" aria-label="Search">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
        </form>

        <a href="<?= e(url('cart.php')) ?>" class="nav-icon-link" aria-label="Shopping cart">
            <i class="fa-solid fa-bag-shopping"></i>
            <?php if ($cartCount > 0): ?>
                <span class="nav-badge" id="cartBadge"><?= $cartCount ?></span>
            <?php else: ?>
                <span class="nav-badge" id="cartBadge" hidden>0</span>
            <?php endif; ?>
        </a>

        <?php if ($user): ?>
            <a href="<?= e(url('notifications.php')) ?>" class="nav-icon-link" aria-label="Notifications">
                <i class="fa-regular fa-bell"></i>
                <?php if ($unreadCount > 0): ?>
                    <span class="nav-badge" id="notificationBadge"><?= $unreadCount ?></span>
                <?php else: ?>
                    <span class="nav-badge" id="notificationBadge" hidden>0</span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <a href="<?= e(url($user ? 'account.php' : 'login.php')) ?>" class="nav-icon-link" aria-label="Account">
            <i class="fa-regular fa-user"></i>
        </a>

        <button type="button" class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>
</header>
