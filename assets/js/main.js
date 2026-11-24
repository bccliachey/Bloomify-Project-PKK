/* Bloomify - shared interactions */
(function () {
    'use strict';

    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    onReady(function () {
        var navbar = document.getElementById('siteNavbar');
        var navToggle = document.getElementById('navToggle');

        if (navbar && navToggle) {
            navToggle.addEventListener('click', function () {
                var open = navbar.classList.toggle('menu-open');
                navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                navToggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
            });

            document.addEventListener('click', function (event) {
                if (!navbar.classList.contains('menu-open')) {
                    return;
                }
                if (!navbar.contains(event.target)) {
                    navbar.classList.remove('menu-open');
                    navToggle.setAttribute('aria-expanded', 'false');
                }
            });
        }

        document.querySelectorAll('.flash-close').forEach(function (button) {
            button.addEventListener('click', function () {
                var flash = button.closest('.flash');
                if (flash) {
                    flash.remove();
                }
            });
        });

        var fulfillmentRadios = document.querySelectorAll('input[name="fulfillment_method"]');
        var deliveryBlocks = document.querySelectorAll('[data-delivery-only]');
        var pickupBlocks = document.querySelectorAll('[data-pickup-only]');

        function updateFulfillment() {
            var selected = document.querySelector('input[name="fulfillment_method"]:checked');
            var method = selected ? selected.value : 'delivery';

            deliveryBlocks.forEach(function (block) {
                block.classList.toggle('hidden', method !== 'delivery');
                block.querySelectorAll('input, textarea, select').forEach(function (input) {
                    input.required = method === 'delivery' && input.hasAttribute('data-required-delivery');
                });
            });

            pickupBlocks.forEach(function (block) {
                block.classList.toggle('hidden', method !== 'pickup');
            });

            document.querySelectorAll('[data-delivery-fee]').forEach(function (element) {
                element.textContent = method === 'delivery' ? element.getAttribute('data-delivery-fee') : 'Rp0';
            });

            document.querySelectorAll('[data-grand-total]').forEach(function (element) {
                element.textContent = method === 'delivery'
                    ? element.getAttribute('data-delivery')
                    : element.getAttribute('data-pickup');
            });
        }

        if (fulfillmentRadios.length) {
            fulfillmentRadios.forEach(function (radio) {
                radio.addEventListener('change', updateFulfillment);
            });
            updateFulfillment();
        }

        var checkoutForm = document.getElementById('checkoutForm');

        if (checkoutForm) {
            var paymentRadios = checkoutForm.querySelectorAll('input[name="payment_method"]');
            var digitalPanel = checkoutForm.querySelector('[data-payment-digital-panel]');
            var proofInput = document.getElementById('payment_proof');
            var proofWarning = checkoutForm.querySelector('[data-payment-proof-warning]');
            var codNote = checkoutForm.querySelector('[data-payment-cod-note]');

            var selectedPayment = function () {
                return checkoutForm.querySelector('input[name="payment_method"]:checked');
            };

            var isDigital = function () {
                var selected = selectedPayment();
                return Boolean(selected && selected.getAttribute('data-payment-type') === 'digital');
            };

            var validateProofFile = function () {
                if (!isDigital()) {
                    return '';
                }

                var file = proofInput && proofInput.files ? proofInput.files[0] : null;

                if (!file) {
                    return 'Please upload your payment proof before placing the order.';
                }

                if (!/\.(jpe?g|png|webp)$/i.test(file.name)) {
                    return 'Only JPG, PNG, or WEBP images are allowed.';
                }

                if (file.size > 2 * 1024 * 1024) {
                    return 'The payment proof must be 2 MB or smaller.';
                }

                return '';
            };

            var showProofWarning = function (message) {
                if (!proofWarning) {
                    return;
                }

                proofWarning.textContent = message;
                proofWarning.classList.remove('hidden');
            };

            var clearProofWarning = function () {
                if (proofWarning) {
                    proofWarning.classList.add('hidden');
                }
            };

            var updatePaymentPanel = function () {
                if (!digitalPanel) {
                    return;
                }

                var selected = selectedPayment();
                var digital = isDigital();

                digitalPanel.classList.toggle('hidden', !digital);

                if (codNote) {
                    codNote.classList.toggle('hidden', digital);
                }

                if (proofInput) {
                    proofInput.required = digital;
                }

                if (!digital || !selected) {
                    clearProofWarning();
                    return;
                }

                digitalPanel.querySelectorAll('[data-payment-label]').forEach(function (element) {
                    element.textContent = selected.getAttribute('data-payment-label') || '';
                });

                digitalPanel.querySelectorAll('[data-payment-description]').forEach(function (element) {
                    element.textContent = selected.getAttribute('data-payment-description') || '';
                });

                digitalPanel.querySelectorAll('[data-payment-number]').forEach(function (element) {
                    element.textContent = selected.getAttribute('data-payment-number') || '';
                });

                digitalPanel.querySelectorAll('[data-payment-holder]').forEach(function (element) {
                    element.textContent = selected.getAttribute('data-payment-holder') || '';
                });
            };

            paymentRadios.forEach(function (radio) {
                radio.addEventListener('change', function () {
                    if (proofInput) {
                        proofInput.value = '';
                    }

                    clearProofWarning();
                    updatePaymentPanel();
                });
            });

            if (proofInput) {
                proofInput.addEventListener('change', function () {
                    var message = validateProofFile();

                    if (message !== '') {
                        showProofWarning(message);
                    } else {
                        clearProofWarning();
                    }
                });

                proofInput.addEventListener('invalid', function (event) {
                    event.preventDefault();

                    if (isDigital()) {
                        showProofWarning(validateProofFile());
                    }
                });
            }

            checkoutForm.addEventListener('bloomify:beforeconfirm', function (event) {
                if (!isDigital()) {
                    return;
                }

                var message = validateProofFile();

                if (message !== '') {
                    event.preventDefault();
                    showProofWarning(message);

                    if (proofInput) {
                        proofInput.focus();
                    }
                } else {
                    clearProofWarning();
                }
            });

            updatePaymentPanel();
        }

        var sortSelect = document.querySelector('[data-auto-submit]');
        if (sortSelect) {
            sortSelect.addEventListener('change', function () {
                var form = sortSelect.closest('form');
                if (form) {
                    form.submit();
                }
            });
        }
    });
})();
