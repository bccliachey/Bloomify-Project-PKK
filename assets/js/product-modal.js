/* Bloomify - product detail popup (data loaded from api/product.php) */
(function () {
    'use strict';

    var modal = document.getElementById('productModal');
    if (!modal) {
        return;
    }

    var closeButton = document.getElementById('closeModal');
    var detailName = document.getElementById('detailName');
    var detailType = document.getElementById('detailType');
    var detailRating = document.getElementById('detailRating');
    var detailImage = document.getElementById('detailImage');
    var detailDescription = document.getElementById('detailDescription');
    var detailFlowers = document.getElementById('detailFlowers');
    var detailFixedQuantity = document.getElementById('detailFixedQuantity');
    var detailVariantTitle = document.getElementById('detailVariantTitle');
    var detailVariantTotal = document.getElementById('detailVariantTotal');
    var detailStemCount = document.getElementById('detailStemCount');
    var detailMinimum = document.getElementById('detailMinimum');
    var detailSummary = document.getElementById('detailSummary');
    var totalPrice = document.getElementById('totalPrice');
    var addButton = document.getElementById('addToCart');
    var cartMessage = document.getElementById('cartMessage');
    var detailPageLink = document.getElementById('detailPageLink');

    var currentProduct = null;
    var quantities = new Map();
    var quantityNodes = new Map();
    var loadRequestId = 0;
    var lastFocused = null;

    function variantKey(flowerId) {
        return String(flowerId);
    }

    function getQuantity(flowerId) {
        return quantities.get(variantKey(flowerId)) || 0;
    }

    function setQuantity(flowerId, value) {
        quantities.set(variantKey(flowerId), value);
    }

    var config = window.BLOOMIFY || {};
    var baseUrl = config.baseUrl || '';

    function formatRupiah(value) {
        return 'Rp' + Number(value).toLocaleString('id-ID');
    }

    function wrappingFee(stems) {
        if (stems <= 5) {
            return 0;
        }
        return Math.ceil((stems - 5) / 5) * 10000;
    }

    function element(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function openModal() {
        lastFocused = document.activeElement;
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if (closeButton) {
            closeButton.focus();
        }
    }

    function closeModal() {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    function resetState() {
        currentProduct = null;
        quantities = new Map();
        quantityNodes = new Map();
        detailFlowers.innerHTML = '';
        detailFlowers.hidden = true;
        detailFixedQuantity.innerHTML = '';
        detailFixedQuantity.hidden = true;
        detailVariantTitle.hidden = true;
        detailVariantTotal.hidden = true;
        detailStemCount.textContent = '0';
        detailSummary.innerHTML = '';
        detailMinimum.textContent = '';
        totalPrice.textContent = '';
        cartMessage.textContent = '';
        addButton.disabled = true;
        addButton.textContent = 'Add to Cart';
    }

    function loadProduct(productId) {
        var requestId = ++loadRequestId;

        resetState();
        detailName.textContent = 'Loading...';
        detailType.textContent = '';
        detailRating.textContent = '';
        detailDescription.textContent = '';

        fetch(baseUrl + '/api/product.php?id=' + encodeURIComponent(productId), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (requestId !== loadRequestId) {
                    return;
                }

                if (!data.ok) {
                    detailName.textContent = data.message || 'Product unavailable.';
                    return;
                }
                renderProduct(data.product);
            })
            .catch(function () {
                if (requestId !== loadRequestId) {
                    return;
                }
                detailName.textContent = 'Could not load this product.';
            });

        openModal();
    }

    function renderProduct(product) {
        currentProduct = product;
        quantities = new Map();
        quantityNodes = new Map();
        detailFlowers.innerHTML = '';
        detailFixedQuantity.innerHTML = '';

        detailName.textContent = product.name;
        detailType.textContent = product.category;
        detailRating.textContent = Number(product.rating_count) > 0
            ? '★ ' + Number(product.rating_average).toFixed(1) + ' (' + product.rating_count + ' reviews)'
            : 'No reviews yet';
        detailDescription.textContent = product.description || '';
        detailImage.src = product.image;
        detailImage.alt = product.name;

        if (detailPageLink) {
            detailPageLink.href = product.product_url;
        }

        if (product.pricing_mode === 'fixed') {
            detailVariantTitle.hidden = true;
            detailFlowers.hidden = true;
            detailVariantTotal.hidden = true;
            detailFixedQuantity.hidden = false;

            quantities.set('fixed', 0);
            renderFixedRow();
        } else {
            detailVariantTitle.hidden = false;
            detailVariantTitle.textContent = 'Choose Your Flowers';
            detailFlowers.hidden = false;
            detailVariantTotal.hidden = false;
            detailFixedQuantity.hidden = true;

            product.flower_types.forEach(function (flower) {
                setQuantity(flower.id, 0);
            });
            product.flower_types.forEach(function (flower) {
                renderFlowerCard(flower);
            });
        }

        if (product.pricing_mode === 'fixed') {
            detailMinimum.textContent = product.stock > 0
                ? 'Ready-made product. ' + product.stock + ' item(s) available.'
                : 'This product is sold out.';
        } else {
            detailMinimum.textContent = 'Choose any flower colors. Minimum ' + product.min_stems
                + ' stems in total. Wrapping is free for the first 5 stems, then Rp10.000 per additional 5 stems.';
        }

        addButton.disabled = !product.can_buy;
        addButton.textContent = product.can_buy ? 'Add to Cart' : 'Sold Out';

        updateTotal();
    }

    function renderFlowerCard(flower) {
        var card = element('div', 'flower-card');
        var key = variantKey(flower.id);
        card.dataset.flowerId = key;
        card.dataset.price = flower.price;
        card.dataset.stock = flower.stock;

        var flowerLabel = flower.name + (flower.color ? ' — ' + flower.color : '');
        card.appendChild(element('span', 'flower-card-name', flowerLabel));
        card.appendChild(element('span', 'flower-card-price', formatRupiah(flower.price) + '/stem'));

        var quantity = element('div', 'flower-card-controls');
        var minus = element('button', 'minus', '−');
        minus.type = 'button';
        minus.setAttribute('aria-label', 'Remove one ' + flowerLabel + ' stem');
        minus.disabled = true;

        var value = element('span', 'quantity-value', '0');
        var plus = element('button', 'plus', '+');
        plus.type = 'button';
        plus.setAttribute('aria-label', 'Add one ' + flowerLabel + ' stem');
        plus.disabled = flower.stock <= 0;

        minus.addEventListener('click', function () {
            changeQuantity(flower.id, -1);
        });
        plus.addEventListener('click', function () {
            changeQuantity(flower.id, 1);
        });

        quantity.appendChild(minus);
        quantity.appendChild(value);
        quantity.appendChild(plus);
        quantity.appendChild(element('span', 'stem-label', 'stems'));
        card.appendChild(quantity);

        card.appendChild(element('small', 'quantity-note flower-card-stock', flower.stock > 0
            ? flower.stock + ' in stock'
            : 'Out of stock'));

        quantityNodes.set(key, { value: value, minus: minus, plus: plus });
        detailFlowers.appendChild(card);
    }

    function renderFixedRow() {
        var product = currentProduct;
        var row = element('div', 'flower-row fixed-quantity-row');
        row.dataset.fixed = '1';
        row.dataset.price = product.fixed_price;
        row.dataset.stock = product.stock;

        var info = element('span', '', 'Quantity (item) — ' + formatRupiah(product.fixed_price) + '/item');
        info.appendChild(document.createElement('br'));
        info.appendChild(
            element('small', 'quantity-note', product.stock > 0
                ? product.stock + ' item(s) in stock'
                : 'Out of stock')
        );

        var quantity = element('div', 'quantity');
        var minus = element('button', 'minus', '−');
        minus.type = 'button';
        var value = element('span', 'quantity-value', '0');
        var plus = element('button', 'plus', '+');
        plus.type = 'button';

        if (product.stock <= 0) {
            plus.disabled = true;
        }

        minus.addEventListener('click', function () {
            changeFixedQuantity(-1);
        });
        plus.addEventListener('click', function () {
            changeFixedQuantity(1);
        });

        quantity.appendChild(minus);
        quantity.appendChild(value);
        quantity.appendChild(plus);

        row.appendChild(info);
        row.appendChild(quantity);
        detailFixedQuantity.appendChild(row);
    }

    function changeFixedQuantity(delta) {
        if (!currentProduct) {
            return;
        }

        var next = Math.max(0, Math.min(currentProduct.stock, (quantities.get('fixed') || 0) + delta));
        quantities.set('fixed', next);
        var row = detailFixedQuantity.querySelector('[data-fixed="1"]');
        if (row) {
            row.querySelector('.quantity-value').textContent = next;
        }
        updateTotal();
    }

    function changeQuantity(flowerId, delta) {
        if (!currentProduct) {
            return;
        }

        var flower = currentProduct.flower_types.find(function (item) {
            return variantKey(item.id) === variantKey(flowerId);
        });

        if (!flower) {
            return;
        }

        var key = variantKey(flower.id);
        var stock = Number(flower.stock);
        var next = Math.max(0, Math.min(stock, getQuantity(flower.id) + delta));

        setQuantity(flower.id, next);

        var nodes = quantityNodes.get(key);
        if (nodes) {
            nodes.value.textContent = String(next);
            nodes.minus.disabled = next <= 0;
            nodes.plus.disabled = next >= stock;
        }

        updateTotal();
    }

    function currentTotals() {
        if (!currentProduct) {
            return { stems: 0, flowerSubtotal: 0, wrapping: 0, total: 0, units: 0 };
        }

        if (currentProduct.pricing_mode === 'fixed') {
            var units = quantities.get('fixed') || 0;
            var fixedTotal = units * Number(currentProduct.fixed_price);
            return {
                stems: units,
                units: units,
                flowerSubtotal: fixedTotal,
                wrapping: 0,
                total: fixedTotal
            };
        }

        var stems = 0;
        var subtotal = 0;

        currentProduct.flower_types.forEach(function (flower) {
            var quantity = getQuantity(flower.id);
            stems += quantity;
            subtotal += quantity * Number(flower.price);
        });

        var wrapping = wrappingFee(stems);

        return {
            stems: stems,
            units: 0,
            flowerSubtotal: subtotal,
            wrapping: wrapping,
            total: subtotal + wrapping
        };
    }

    function updateTotal() {
        if (!currentProduct) {
            return;
        }

        var totals = currentTotals();
        detailSummary.innerHTML = '';

        if (currentProduct.pricing_mode === 'fixed') {
            appendSummaryLine('Subtotal', formatRupiah(totals.flowerSubtotal));
        } else {
            appendSummaryLine('Flower subtotal', formatRupiah(totals.flowerSubtotal));
            appendSummaryLine('Wrapping fee', formatRupiah(totals.wrapping));
            detailStemCount.textContent = String(totals.stems);
        }

        totalPrice.textContent = formatRupiah(totals.total);

        cartMessage.textContent = '';

        if (!currentProduct.can_buy) {
            return;
        }

        if (currentProduct.pricing_mode === 'fixed') {
            addButton.disabled = (quantities.get('fixed') || 0) < 1;
        } else {
            addButton.disabled = totals.stems < Number(currentProduct.min_stems);
        }
    }

    function appendSummaryLine(label, value) {
        var line = element('div', 'summary-line');
        line.appendChild(element('span', '', label));
        line.appendChild(element('strong', '', value));
        detailSummary.appendChild(line);
    }

    function addToCart() {
        if (!currentProduct) {
            return;
        }

        var totals = currentTotals();

        if (currentProduct.pricing_mode === 'fixed') {
            if ((quantities.get('fixed') || 0) < 1) {
                cartMessage.textContent = 'Please choose at least 1 item.';
                return;
            }
        } else if (totals.stems < Number(currentProduct.min_stems)) {
            cartMessage.textContent = 'Please choose at least ' + currentProduct.min_stems + ' stems in total.';
            return;
        }

        var formData = new FormData();
        formData.append('product_id', currentProduct.id);
        formData.append('_token', config.csrfToken || '');

        if (currentProduct.pricing_mode === 'fixed') {
            formData.append('quantity', quantities.get('fixed') || 0);
        } else {
            quantities.forEach(function (quantity, flowerId) {
                formData.append('flowers[' + flowerId + ']', quantity);
            });
        }

        addButton.disabled = true;
        addButton.textContent = 'Adding...';

        fetch(baseUrl + '/actions/cart/add.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }

                addButton.disabled = false;
                addButton.textContent = 'Add to Cart';

                if (!data.ok) {
                    cartMessage.textContent = data.message || 'Could not add this product to the cart.';
                    return;
                }

                cartMessage.textContent = data.message || 'Added to your cart.';

                var badge = document.getElementById('cartBadge');
                if (badge) {
                    badge.textContent = data.cart_count;
                    badge.hidden = !data.cart_count;
                }
            })
            .catch(function () {
                addButton.disabled = false;
                addButton.textContent = 'Add to Cart';
                cartMessage.textContent = 'Something went wrong. Please try again.';
            });
    }

    function bindCards() {
        document.querySelectorAll('.js-open-product').forEach(function (card) {
            card.addEventListener('click', function (event) {
                if (event.target.closest('a, button')) {
                    return;
                }
                event.preventDefault();
                loadProduct(card.dataset.productId);
            });

            card.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    loadProduct(card.dataset.productId);
                }
            });
        });
    }

    if (closeButton) {
        closeButton.addEventListener('click', closeModal);
    }

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('show')) {
            closeModal();
        }
    });

    addButton.addEventListener('click', addToCart);
    bindCards();
})();
