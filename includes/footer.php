<?php
/**
 * Public site footer.
 */
$storeName = setting('store_name', 'Bloomify');
$storeAddress = setting('store_address');
$storeWhatsapp = setting('store_whatsapp');
$storeHours = setting('store_hours');
$storeInfo = setting('store_info');
?>
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <a href="<?= e(url('index.php')) ?>" class="footer-logo"><?= e($storeName) ?></a>
            <p><?= e($storeInfo !== '' ? $storeInfo : 'Fresh flowers for every moment.') ?></p>
        </div>

        <div class="footer-column">
            <h4>Explore</h4>
            <a href="<?= e(url('index.php')) ?>">Home</a>
            <a href="<?= e(url('catalog.php')) ?>">Shop</a>
            <a href="<?= e(url('catalog.php?category=hand-bouquet')) ?>">Hand Bouquet</a>
            <a href="<?= e(url('catalog.php?category=mix-bouquet')) ?>">Mix Bouquet</a>
            <a href="<?= e(url('catalog.php?category=flower-box')) ?>">Flower Box</a>
        </div>

        <div class="footer-column">
            <h4>Visit Us</h4>
            <p><?= e($storeAddress) ?></p>
            <p><?= e($storeHours) ?></p>
        </div>

        <div class="footer-column">
            <h4>Contact</h4>
            <p><?= e($storeWhatsapp) ?></p>
            <p>bloomifies@gmail.com</p>
            <p>@bloomifies</p>
        </div>
    </div>

    <div class="footer-bottom">
        <span>&copy; <?= date('Y') ?> <?= e($storeName) ?>. All rights reserved.</span>
    </div>
</footer>

<script src="<?= e(url('assets/js/dialog.js')) ?>"></script>
<script src="<?= e(url('assets/js/main.js')) ?>"></script>
</body>
</html>
