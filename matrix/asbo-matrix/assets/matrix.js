(function () {
    'use strict';

    function money(value, currency) {
        try {
            return new Intl.NumberFormat(undefined, {
                style: 'currency',
                currency: currency || 'USD'
            }).format(Number(value || 0));
        } catch (e) {
            return '$' + Number(value || 0).toFixed(2);
        }
    }

    function parseMatrix(block) {
        var node = block.querySelector('.asbo-matrix__data');
        if (!node) return null;
        try {
            return JSON.parse(node.textContent || '{}');
        } catch (e) {
            return null;
        }
    }

    function findCartForm(productId) {
        var forms = document.querySelectorAll('form.cart');
        if (!forms.length) return null;

        for (var i = 0; i < forms.length; i++) {
            var form = forms[i];
            var add = form.querySelector('[name="add-to-cart"], .single_add_to_cart_button');
            if (!add) continue;
            var value = add.value || add.getAttribute('value') || '';
            if (!value || String(value) === String(productId)) return form;
        }

        return forms[0];
    }

    function ensureHidden(form, name, value) {
        var input = form.querySelector('input[name="' + name + '"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            form.appendChild(input);
        }
        input.value = value;
        return input;
    }

    function initBlock(block) {
        var matrix = parseMatrix(block);
        if (!matrix || !Object.keys(matrix).length) return;

        var productId = block.getAttribute('data-asbo-matrix-product') || '';
        var currency = block.getAttribute('data-currency-code') || 'USD';
        var selectedDecoration = block.getAttribute('data-default-decoration') || Object.keys(matrix)[0];
        var activeTier = block.querySelector('[data-asbo-matrix-active-tier]');
        var form = null;

        function syncForm() {
            form = findCartForm(productId);
            if (!form) return false;
            ensureHidden(form, 'asbo_matrix_enabled', '1');
            ensureHidden(form, 'asbo_matrix_product_id', productId);
            ensureHidden(form, 'asbo_matrix_decoration', selectedDecoration);
            return true;
        }

        function currentQuantity() {
            if (!form) syncForm();
            if (!form) return 0;
            var qty = form.querySelector('input.qty, input[name="quantity"]');
            if (!qty) return 1;
            return Math.max(0, parseInt(qty.value || '0', 10) || 0);
        }

        function thresholdsFor(decoration) {
            var tiers = matrix[decoration] || {};
            var thresholds = [1];
            Object.keys(tiers).forEach(function (key) {
                var n = parseInt(key, 10);
                if (n > 0 && thresholds.indexOf(n) === -1) thresholds.push(n);
            });
            thresholds.sort(function (a, b) { return a - b; });
            return thresholds;
        }

        function resolveTier(decoration, qty) {
            var thresholds = thresholdsFor(decoration);
            var selected = 1;
            thresholds.forEach(function (threshold) {
                if (threshold > 1 && qty >= threshold) selected = threshold;
            });
            return selected;
        }

        function updateRows() {
            block.querySelectorAll('[data-decoration-row]').forEach(function (row) {
                row.classList.toggle('is-selected-decoration', row.getAttribute('data-decoration-row') === selectedDecoration);
            });
        }

        function updateTier() {
            syncForm();
            var qty = currentQuantity();
            var tier = resolveTier(selectedDecoration, qty);

            block.querySelectorAll('[data-threshold]').forEach(function (cell) {
                cell.classList.toggle('is-active-tier', Number(cell.getAttribute('data-threshold')) === Number(tier));
            });
            updateRows();

            if (!activeTier) return;
            if (qty < 1) {
                activeTier.textContent = 'Enter quantity to see your pricing tier';
                return;
            }

            if (tier <= 1) {
                activeTier.textContent = '1+ pricing · WooCommerce regular price';
                return;
            }

            var tiers = matrix[selectedDecoration] || {};
            var price = tiers[String(tier)] != null ? tiers[String(tier)] : tiers[tier];
            activeTier.textContent = tier + '+ tier · ' + money(price, currency) + ' per piece';
        }

        block.querySelectorAll('[data-asbo-matrix-decoration-control] input[type="radio"]').forEach(function (input) {
            input.addEventListener('change', function () {
                if (!input.checked) return;
                selectedDecoration = input.value;
                block.setAttribute('data-default-decoration', selectedDecoration);
                syncForm();
                updateTier();
            });
        });

        function bindForm() {
            if (!syncForm()) return false;
            var qty = form.querySelector('input.qty, input[name="quantity"]');
            if (qty && !qty.dataset.asboMatrixBound) {
                qty.dataset.asboMatrixBound = '1';
                qty.addEventListener('input', updateTier);
                qty.addEventListener('change', updateTier);
            }
            form.addEventListener('submit', syncForm);
            return true;
        }

        bindForm();
        updateTier();

        // Some themes/Woo variation scripts replace or delay the cart form. Rebind
        // briefly without keeping a permanent polling loop on the storefront.
        var tries = 0;
        var timer = window.setInterval(function () {
            tries++;
            bindForm();
            updateTier();
            if (tries >= 8) window.clearInterval(timer);
        }, 400);
    }

    function boot() {
        document.querySelectorAll('.asbo-matrix').forEach(initBlock);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
