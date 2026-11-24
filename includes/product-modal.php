<?php
/**
 * Product detail popup. Data is loaded by assets/js/product-modal.js
 * from api/product.php so prices and stock always come from the server.
 */
?>
<div class="product-modal" id="productModal" aria-hidden="true">
    <div class="product-detail" role="dialog" aria-modal="true" aria-labelledby="detailName">
        <button type="button" class="close-modal" id="closeModal" aria-label="Close">&times;</button>

        <div class="detail-image">
            <img id="detailImage" src="<?= e(url('assets/images/Flower.png')) ?>" alt="Product image">
        </div>

        <div class="detail-content">
            <p class="detail-type" id="detailType"></p>
            <p class="modal-rating" id="detailRating"></p>
            <h2 id="detailName">Loading...</h2>
            <p class="detail-description" id="detailDescription"></p>

            <h3 class="variant-title" id="detailVariantTitle" hidden>Choose Your Flowers</h3>
            <div class="flower-variant-grid" id="detailFlowers" hidden></div>
            <p class="variant-total" id="detailVariantTotal" hidden>
                Total stems: <strong id="detailStemCount">0</strong>
            </p>

            <div id="detailFixedQuantity" hidden></div>

            <p class="minimum" id="detailMinimum"></p>

            <div class="detail-summary" id="detailSummary"></div>

            <div class="detail-bottom">
                <strong id="totalPrice"></strong>
                <button type="button" class="add-to-cart" id="addToCart">Add to Cart</button>
            </div>

            <p id="cartMessage" class="cart-message" role="status"></p>
            <p class="detail-link-wrap">
                <a id="detailPageLink" class="text-link" href="#">View full product page</a>
            </p>
        </div>
    </div>
</div>

<?php $modalScript = __DIR__ . '/../assets/js/product-modal.js'; ?>
<script src="<?= e(url('assets/js/product-modal.js') . '?v=' . filemtime($modalScript)) ?>"></script>
