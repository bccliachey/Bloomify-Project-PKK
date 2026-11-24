<?php
/**
 * Bloomify - Homepage.
 * Visually based on the original Homepage.html, now backed by the database.
 */

require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Flowers For Every Moment';
$activeNav = 'home';

$allProducts = get_public_products();
$featuredProducts = array_slice($allProducts, 0, 6);

require __DIR__ . '/includes/header.php';
?>

<main>

    <section class="hero">
        <div class="hero-content">
            <p class="hero-small-text">FRESH FLOWERS, MADE WITH LOVE</p>

            <h1>
                Flowers For Every
                <span>Moment.</span>
            </h1>

            <p class="hero-description">
                Contact <?= e(setting('store_whatsapp', '+62 856 9167 4330')) ?> |
                bloomifies@gmail.com |
                @bloomifies on Instagram and Twitter
            </p>

            <a href="#products" class="hero-button">
                Explore Flowers
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <div class="hero-image">
            <img src="<?= e(url('assets/images/Flower-transparent.png')) ?>"
                 alt="Beautiful Bloomify flower bouquet">
        </div>
    </section>

    <section class="products" id="products">
        <div class="products-heading">
            <p>OUR COLLECTION</p>
            <h2>Bloomify's Bouquet</h2>
        </div>

        <?php if ($featuredProducts): ?>
            <div class="product-grid">
                <?php foreach ($featuredProducts as $product): ?>
                    <?php require __DIR__ . '/includes/product-card.php'; ?>
                <?php endforeach; ?>
            </div>

            <div class="home-view-all">
                <a href="<?= e(url('catalog.php')) ?>" class="btn btn-outline">
                    View all flowers
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-spa"></i>
                <h2>Our flowers are being arranged</h2>
                <p>Please check back soon for our latest bouquets.</p>
            </div>
        <?php endif; ?>
    </section>

</main>

<?php
require __DIR__ . '/includes/product-modal.php';
require __DIR__ . '/includes/footer.php';
