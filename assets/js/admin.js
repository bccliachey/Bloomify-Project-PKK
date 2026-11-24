/* Bloomify admin - small UI helpers (no framework). */
(function () {
    'use strict';

    var shell = document.getElementById('adminShell');
    var toggle = document.getElementById('adminSidebarToggle');

    if (shell && toggle) {
        toggle.addEventListener('click', function () {
            shell.classList.toggle('sidebar-open');
        });
    }

    var categorySelect = document.getElementById('category_id');
    var pricingSelect = document.getElementById('pricing_mode');

    if (categorySelect || pricingSelect) {
        var modeLabels = {
            per_stem: 'Per Stem',
            mixed: 'Mixed',
            fixed: 'Fixed Price'
        };

        var syncFields = function () {
            var option = categorySelect ? categorySelect.options[categorySelect.selectedIndex] : null;
            var categoryMode = option ? option.getAttribute('data-mode') : 'per_stem';
            var overrideMode = pricingSelect ? pricingSelect.value : '';
            var mode = overrideMode || categoryMode;

            if (pricingSelect) {
                var defaultOption = pricingSelect.querySelector('option[value=""]');
                if (defaultOption) {
                    defaultOption.textContent = 'Category default (' + (modeLabels[categoryMode] || categoryMode) + ')';
                }
            }

            document.querySelectorAll('[data-field]').forEach(function (field) {
                var fieldMode = field.getAttribute('data-field');
                var visible = fieldMode === 'fixed' ? mode === 'fixed' : fieldMode === 'flowers' ? mode !== 'fixed' : fieldMode === mode;
                field.classList.toggle('hidden', !visible);
            });
        };

        if (categorySelect) {
            categorySelect.addEventListener('change', syncFields);
        }

        if (pricingSelect) {
            pricingSelect.addEventListener('change', syncFields);
        }

        syncFields();
    }

    var reportPeriod = document.getElementById('reportPeriod');
    if (reportPeriod) {
        var syncRange = function () {
            var isCustom = reportPeriod.value === 'custom';
            document.querySelectorAll('[data-custom-range]').forEach(function (field) {
                field.classList.toggle('hidden', !isCustom);
            });
        };

        reportPeriod.addEventListener('change', syncRange);
        syncRange();
    }

    document.querySelectorAll('[data-image-input]').forEach(function (input) {
        input.addEventListener('change', function () {
            var preview = document.getElementById(input.getAttribute('data-image-input'));
            if (!preview || !input.files || !input.files[0]) {
                return;
            }

            preview.src = URL.createObjectURL(input.files[0]);
            preview.classList.remove('hidden');
        });
    });
})();
