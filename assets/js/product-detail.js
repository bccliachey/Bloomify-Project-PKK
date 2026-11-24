/* Bloomify - live total for the product detail page */
(function () {
    'use strict';

    var form = document.getElementById('productDetailForm');
    if (!form) {
        return;
    }

    var inputs = Array.prototype.slice.call(form.querySelectorAll('.detail-qty'));
    var subtotalElement = document.getElementById('detailSubtotal');
    var wrappingElement = document.getElementById('detailWrapping');
    var stemsElement = document.getElementById('detailStems');
    var totalElement = document.getElementById('detailTotal');
    var addButton = document.getElementById('detailAddButton');
    var message = document.getElementById('detailMessage');
    var wrappingRow = document.getElementById('wrappingRow');
    var stemsRow = document.getElementById('stemsRow');
    var minStems = Number(form.getAttribute('data-min-stems') || 3);

    function formatRupiah(value) {
        return 'Rp' + Number(value).toLocaleString('id-ID');
    }

    function wrappingFee(stems) {
        if (stems <= 5) {
            return 0;
        }
        return Math.ceil((stems - 5) / 5) * 10000;
    }

    function calculate() {
        var subtotal = 0;
        var quantity = 0;
        var mode = inputs.length ? inputs[0].getAttribute('data-mode') : 'variable';

        inputs.forEach(function (input) {
            var value = Math.max(0, Number(input.value) || 0);
            var price = Number(input.getAttribute('data-price')) || 0;
            quantity += value;
            subtotal += value * price;
        });

        var isFixed = mode === 'fixed';
        var wrapping = isFixed ? 0 : wrappingFee(quantity);
        var total = subtotal + wrapping;

        subtotalElement.textContent = formatRupiah(subtotal);
        wrappingElement.textContent = formatRupiah(wrapping);
        stemsElement.textContent = String(quantity);
        totalElement.textContent = formatRupiah(total);

        if (isFixed) {
            wrappingRow.style.display = 'none';
            stemsRow.querySelector('span').textContent = 'Total items';
        }

        var valid = isFixed ? quantity >= 1 : quantity >= minStems;
        if (addButton) {
            addButton.disabled = !valid;
        }

        return { quantity: quantity, isFixed: isFixed, valid: valid };
    }

    inputs.forEach(function (input) {
        input.addEventListener('input', function () {
            var max = Number(input.getAttribute('max'));
            if (max >= 0 && Number(input.value) > max) {
                input.value = String(max);
            }
            if (message) {
                message.textContent = '';
            }
            calculate();
        });
    });

    form.addEventListener('submit', function (event) {
        var result = calculate();

        if (!result.valid) {
            event.preventDefault();
            if (message) {
                message.textContent = result.isFixed
                    ? 'Please choose at least 1 item.'
                    : 'Please choose at least ' + minStems + ' stems in total.';
            }
        }
    });

    calculate();
})();
