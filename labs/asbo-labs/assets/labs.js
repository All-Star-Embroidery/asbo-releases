(function () {
    'use strict';

    var cfg = window.ASBOLabsConfig || {};
    var root = document.querySelector('.asbo-labs-root');
    if (!root) return;

    var STORAGE = 'asboLabsProjectV130';
    var INTRO_KEY = 'asboLabsIntroSeen';
    var state = {
        step: 1,
        products: [],
        activeId: null,
        active: null,
        decoration: 'embroidery',
        quantities: {},
        variantChoice: {},
        showAllColors: false,
        items: [],
        artwork: {
            source: 'upload',
            filename: '',
            placement: 'front-center',
            notes: ''
        },
        loading: true,
        error: '',
        onboarding: sessionStorage.getItem(INTRO_KEY) !== '1',
        toast: ''
    };

    restore();

    function restore() {
        try {
            var saved = JSON.parse(localStorage.getItem(STORAGE) || '{}');
            if (Array.isArray(saved.items)) state.items = saved.items;
            if (saved.artwork && typeof saved.artwork === 'object') {
                state.artwork = Object.assign(state.artwork, saved.artwork);
            }
            if (saved.step && Number(saved.step) >= 1 && Number(saved.step) <= 3) {
                state.step = Number(saved.step);
            }
        } catch (e) {}
    }

    function persist() {
        try {
            localStorage.setItem(STORAGE, JSON.stringify({
                step: state.step,
                items: state.items,
                artwork: state.artwork
            }));
        } catch (e) {}
    }

    function api(path) {
        return fetch(String(cfg.restBase || '').replace(/\/$/, '') + path, {
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': cfg.nonce || '' }
        }).then(function (res) {
            if (!res.ok) throw new Error('Request failed (' + res.status + ')');
            return res.json();
        });
    }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function money(value) {
        var num = Number(value || 0);
        try {
            return new Intl.NumberFormat(undefined, {
                style: 'currency',
                currency: cfg.currencyCode || 'USD'
            }).format(num);
        } catch (e) {
            return (cfg.currencySymbol || '$') + num.toFixed(2);
        }
    }

    function capitalize(value) {
        value = String(value || '');
        return value.charAt(0).toUpperCase() + value.slice(1);
    }

    function totalPieces() {
        return state.items.reduce(function (sum, item) { return sum + Number(item.qty || 0); }, 0);
    }

    function totalValue() {
        return state.items.reduce(function (sum, item) { return sum + Number(item.total || 0); }, 0);
    }

    function activeQty() {
        return Object.keys(state.quantities).reduce(function (sum, id) {
            return sum + Number(state.quantities[id] || 0);
        }, 0);
    }

    function priceMap() {
        var map = {};
        if (!state.active) return map;
        (state.active.variations || []).forEach(function (v) { map[String(v.id)] = Number(v.price || 0); });
        return map;
    }

    function activeTotal() {
        var map = priceMap();
        return Object.keys(state.quantities).reduce(function (sum, id) {
            return sum + (Number(state.quantities[id] || 0) * Number(map[String(id)] || 0));
        }, 0);
    }

    function itemForProduct(id) {
        for (var i = 0; i < state.items.length; i++) {
            if (Number(state.items[i].productId) === Number(id)) return state.items[i];
        }
        return null;
    }

    function loadProducts() {
        state.loading = true;
        render();
        var query = '?limit=' + encodeURIComponent(cfg.limit || 24);
        if (cfg.category) query += '&category=' + encodeURIComponent(cfg.category);
        api('/products' + query).then(function (data) {
            state.products = data.products || [];
            state.loading = false;
            if (state.products.length && !state.activeId) {
                var preferred = state.items.length ? state.items[0].productId : state.products[0].id;
                selectProduct(preferred, false);
            } else {
                render();
            }
        }).catch(function (err) {
            state.loading = false;
            state.error = err.message || 'Could not load products.';
            render();
        });
    }

    function selectProduct(id, focusBuilder) {
        state.activeId = Number(id);
        state.active = null;
        state.quantities = {};
        state.variantChoice = {};
        state.showAllColors = false;
        render();
        api('/product/' + encodeURIComponent(id)).then(function (data) {
            state.active = data;
            restoreActiveConfiguration();
            render();
            if (focusBuilder && window.innerWidth < 760) {
                var builder = root.querySelector('.asbo-labs-configurator');
                if (builder) builder.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }).catch(function (err) {
            state.error = err.message || 'Could not load product.';
            render();
        });
    }

    function restoreActiveConfiguration() {
        buildDefaultChoices();
        var existing = itemForProduct(state.activeId);
        if (!existing) return;
        state.decoration = existing.decoration || 'embroidery';
        (existing.breakdown || []).forEach(function (row) {
            var id = String(row.variationId || '');
            if (!id) return;
            state.quantities[id] = Number(row.qty || 0);
            var match = (state.active.variations || []).filter(function (v) { return String(v.id) === id; })[0];
            if (match) {
                var groupKey = colorOf(match).toLowerCase().replace(/[^a-z0-9]+/g, '-');
                state.variantChoice[groupKey] = id;
            }
        });
    }

    function buildDefaultChoices() {
        groupVariations().forEach(function (group) {
            if (group.variants.length) state.variantChoice[group.key] = String(group.variants[0].id);
        });
    }

    function colorOf(v) {
        var attrs = v.attributes || [];
        for (var i = 0; i < attrs.length; i++) {
            var hay = (String(attrs[i].label || '') + ' ' + String(attrs[i].key || '')).toLowerCase();
            if (hay.indexOf('color') !== -1 || hay.indexOf('colour') !== -1) return String(attrs[i].value || 'Color');
        }
        return String(v.label || 'Option').split(' / ')[0];
    }

    function sizeOf(v) {
        var attrs = v.attributes || [];
        for (var i = 0; i < attrs.length; i++) {
            var hay = (String(attrs[i].label || '') + ' ' + String(attrs[i].key || '')).toLowerCase();
            if (hay.indexOf('size') !== -1) return String(attrs[i].value || '');
        }
        var bits = String(v.label || '').split(' / ');
        return bits.length > 1 ? bits.slice(1).join(' / ') : '';
    }

    function groupVariations() {
        if (!state.active) return [];
        var map = {};
        (state.active.variations || []).forEach(function (v) {
            if (!v.inStock) return;
            var color = colorOf(v);
            var key = color.toLowerCase().replace(/[^a-z0-9]+/g, '-');
            if (!map[key]) map[key] = { key: key, name: color, image: v.image, variants: [] };
            map[key].variants.push(v);
        });
        return Object.keys(map).map(function (k) { return map[k]; });
    }

    function selectedVariant(group) {
        var id = String(state.variantChoice[group.key] || (group.variants[0] && group.variants[0].id) || '');
        return group.variants.filter(function (v) { return String(v.id) === id; })[0] || group.variants[0];
    }

    function render() {
        root.innerHTML = shell();
        bindFileInput();
        scheduleOnboarding();
        scheduleToast();
    }

    function shell() {
        return '<div class="asbo-labs-app">' +
            '<div class="asbo-labs-beta-bar"><strong>ASBO LABS</strong><span>Private beta · interaction sandbox · no cart/order writes</span><span class="asbo-labs-beta-status">' + (cfg.productionDetected ? 'Production ASBO detected' : 'Production ASBO not detected') + '</span></div>' +
            topbar() +
            onboarding() +
            (state.toast ? '<div class="asbo-labs-toast" role="status">' + esc(state.toast) + '</div>' : '') +
            '<main class="asbo-labs-stage">' + (state.step === 1 ? itemsStep() : state.step === 2 ? artworkStep() : checkoutStep()) + '</main>' +
            projectFooter() +
        '</div>';
    }

    function topbar() {
        var steps = [
            { n: 1, label: 'Items' },
            { n: 2, label: 'Artwork' },
            { n: 3, label: 'Checkout' }
        ];
        return '<div class="asbo-labs-topbar">' +
            '<div class="asbo-labs-title"><span class="asbo-labs-mark">✦</span><span>Bulk Order Builder</span></div>' +
            '<nav class="asbo-labs-steps" aria-label="Bulk order steps">' + steps.map(function (s) {
                var cls = s.n === state.step ? ' is-current' : s.n < state.step ? ' is-complete' : '';
                return '<button type="button" data-action="step" data-step="' + s.n + '" class="asbo-labs-step' + cls + '"><span>' + (s.n < state.step ? '✓' : s.n) + '</span>' + esc(s.label) + '</button>';
            }).join('<i></i>') + '</nav>' +
            '<div class="asbo-labs-top-actions"><button type="button" class="asbo-labs-how" data-action="show-intro">How it works</button><button type="button" class="asbo-labs-save" data-action="save">Save beta draft</button></div>' +
        '</div>';
    }

    function onboarding() {
        if (!state.onboarding) return '';
        return '<aside class="asbo-labs-onboarding" role="status">' +
            '<button type="button" data-action="dismiss-intro" aria-label="Dismiss">×</button>' +
            '<strong>New here? Here’s how it works.</strong>' +
            '<div><span><b>1</b> Pick items</span><span><b>2</b> Add artwork</span><span><b>3</b> Check out</span></div>' +
        '</aside>';
    }

    function itemsStep() {
        if (state.loading) return '<div class="asbo-labs-loading">Loading your WooCommerce catalog…</div>';
        if (state.error) return '<div class="asbo-labs-error">' + esc(state.error) + '</div>';
        return '<div class="asbo-labs-workspace">' + productLibrary() + configurator() + '</div>';
    }

    function productLibrary() {
        var rows = state.products.map(function (p) {
            var active = Number(p.id) === Number(state.activeId);
            var item = itemForProduct(p.id);
            return '<button type="button" class="asbo-labs-product-row' + (active ? ' is-active' : '') + (item ? ' is-in-project' : '') + '" data-action="select-product" data-id="' + p.id + '">' +
                '<img src="' + esc(p.image) + '" alt="">' +
                '<span class="asbo-labs-product-copy"><strong>' + esc(p.name) + '</strong><small>' + esc((p.categories || [])[0] || 'Product') + ' · Starting at ' + money(p.startingPrice) + '</small></span>' +
                '<span class="asbo-labs-product-side"><small>' + (item ? item.qty + ' selected' : '0 selected') + '</small><b>' + (item ? money(item.total) : money(0)) + '</b><em>›</em></span>' +
            '</button>';
        }).join('');

        return '<section class="asbo-labs-library">' +
            '<div class="asbo-labs-library-head"><div><span class="asbo-labs-eyebrow">Items</span><h2>Choose your headwear</h2><p>Pick a style, configure it on the right, then keep adding styles without leaving this screen.</p></div></div>' +
            projectMiniSummary() +
            '<div class="asbo-labs-library-tools"><label><span class="screen-reader-text">Search products</span><input type="search" placeholder="Search styles…" data-action="search"></label><span>' + state.products.length + ' styles loaded</span></div>' +
            '<div class="asbo-labs-product-list">' + rows + '</div>' +
            '<div class="asbo-labs-help"><div><strong>Need help choosing?</strong><span>We can help pick the right style and decoration.</span></div><a href="mailto:AllStarEmb@windstream.net">Ask All Star</a></div>' +
        '</section>';
    }

    function projectMiniSummary() {
        if (!state.items.length) return '';
        return '<div class="asbo-labs-mini-summary"><span><b>' + state.items.length + '</b> ' + (state.items.length === 1 ? 'style' : 'styles') + ' in project</span><span><b>' + totalPieces() + '</b> pieces</span><button type="button" data-action="step" data-step="2">Artwork next →</button></div>';
    }

    function configurator() {
        if (!state.active) return '<section class="asbo-labs-configurator"><div class="asbo-labs-loading">Loading product details…</div></section>';

        var groups = groupVariations();
        var visible = state.showAllColors ? groups : groups.slice(0, 8);
        var cards = visible.map(variationCard).join('');
        var existing = itemForProduct(state.active.id);

        return '<section class="asbo-labs-configurator">' +
            '<button type="button" class="asbo-labs-mobile-back" data-action="mobile-back">← Back to styles</button>' +
            '<div class="asbo-labs-product-hero"><div class="asbo-labs-product-hero-copy"><img src="' + esc(state.active.image) + '" alt=""><div><span class="asbo-labs-eyebrow">' + (existing ? 'Editing project item' : 'Configuring') + '</span><h2>' + esc(state.active.name) + '</h2><p>Starting at <strong>' + money(state.active.startingPrice) + '</strong></p></div></div><div class="asbo-labs-pricing-quick"><small>Pricing quick view</small><strong>' + money(state.active.startingPrice) + '</strong><span>WooCommerce regular price</span><button type="button" data-action="pricing-info">About beta pricing</button></div></div>' +
            '<div class="asbo-labs-config-body">' +
                '<section class="asbo-labs-task"><div class="asbo-labs-task-title"><span>1</span><div><h3>Decoration method</h3><p>Choose how you’d like us to decorate this product.</p></div></div><div class="asbo-labs-decoration"><button type="button" data-action="decoration" data-value="embroidery" class="' + (state.decoration === 'embroidery' ? 'is-selected' : '') + '"><b>Embroidery</b><small>Best for most logos</small></button><button type="button" data-action="decoration" data-value="patch" class="' + (state.decoration === 'patch' ? 'is-selected' : '') + '"><b>Patch</b><small>Bold sewn-on finish</small></button></div></section>' +
                '<section class="asbo-labs-task"><div class="asbo-labs-task-title"><span>2</span><div><h3>Split quantities by color</h3><p>Type a quantity or use the controls. Saved project items reopen exactly where you left them.</p></div></div><div class="asbo-labs-color-grid">' + cards + '</div>' + (groups.length > 8 ? '<button type="button" class="asbo-labs-more" data-action="toggle-colors">' + (state.showAllColors ? 'Show fewer colors' : 'Show more colors (' + (groups.length - 8) + ')') + '</button>' : '') + '</section>' +
                '<div class="asbo-labs-totals"><div><small>Total pieces</small><strong>' + activeQty() + '</strong></div><div><small>UX sandbox estimate</small><strong>' + money(activeTotal()) + '</strong></div></div>' +
            '</div>' +
            '<div class="asbo-labs-config-actions"><button type="button" class="asbo-labs-primary-navy" data-action="add-order"' + (activeQty() < 1 ? ' disabled' : '') + '>' + (existing ? 'Update project item' : 'Add to project') + '</button>' + (existing ? '<button type="button" class="asbo-labs-danger-link" data-action="remove-order" data-id="' + state.active.id + '">Remove</button>' : '<button type="button" class="asbo-labs-secondary" data-action="save">Save for later</button>') + '</div>' +
        '</section>';
    }

    function variationCard(group) {
        var v = selectedVariant(group);
        if (!v) return '';
        var qty = Number(state.quantities[String(v.id)] || 0);
        var sizeOptions = group.variants.length > 1 ? '<select data-action="variant-choice" data-group="' + esc(group.key) + '">' + group.variants.map(function (variant) {
            return '<option value="' + variant.id + '"' + (String(variant.id) === String(v.id) ? ' selected' : '') + '>' + esc(sizeOf(variant) || variant.label) + '</option>';
        }).join('') + '</select>' : '<small>' + esc(sizeOf(v) || (v.attributes && v.attributes[0] ? v.attributes[0].value : '')) + '</small>';

        return '<article class="asbo-labs-color-card' + (qty > 0 ? ' has-qty' : '') + '">' +
            '<img src="' + esc(v.image || group.image) + '" alt="">' +
            '<strong>' + esc(group.name) + '</strong>' + sizeOptions +
            '<div class="asbo-labs-stepper"><button type="button" data-action="qty" data-delta="-1" data-id="' + v.id + '" aria-label="Decrease quantity">−</button><input type="number" inputmode="numeric" min="0" max="9999" value="' + qty + '" data-action="qty-input" data-id="' + v.id + '" aria-label="Quantity for ' + esc(group.name) + '"><button type="button" data-action="qty" data-delta="1" data-id="' + v.id + '" aria-label="Increase quantity">+</button></div>' +
            '<div class="asbo-labs-quick-qty"><button type="button" data-action="qty-add" data-delta="6" data-id="' + v.id + '">+6</button><button type="button" data-action="qty-add" data-delta="12" data-id="' + v.id + '">+12</button></div>' +
            '<div class="asbo-labs-line-price"><span>' + money(v.price) + ' ea</span><b>' + money(qty * Number(v.price || 0)) + '</b></div>' +
        '</article>';
    }

    function artworkStep() {
        var sources = [
            ['upload', 'Upload a new file', 'Fastest option'],
            ['library', 'Use artwork already on file', 'Choose from a prior order later'],
            ['later', 'Send it later', 'We’ll follow up']
        ];
        var placements = [
            ['front-center', 'Front center'], ['left-side', 'Left side'], ['right-side', 'Right side'], ['back-center', 'Back center'], ['other', 'Other']
        ];

        return '<div class="asbo-labs-artwork-layout">' +
            '<section class="asbo-labs-artwork-main">' +
                '<header class="asbo-labs-artwork-head"><span class="asbo-labs-eyebrow">Artwork</span><h2>Artwork & <em>production details.</em></h2><p>Three quick choices. We’ll handle the production review after checkout.</p></header>' +
                '<section class="asbo-labs-art-group"><h3>1. How will you provide your logo?</h3><div class="asbo-labs-source-grid">' + sources.map(function (s) {
                    return '<button type="button" data-action="art-source" data-value="' + s[0] + '" class="' + (state.artwork.source === s[0] ? 'is-selected' : '') + '"><b>' + esc(s[1]) + '</b><small>' + esc(s[2]) + '</small></button>';
                }).join('') + '</div>' + (state.artwork.source === 'upload' ? '<label class="asbo-labs-upload"><input type="file" data-art-file accept=".ai,.eps,.pdf,.svg,.png,.jpg,.jpeg"><strong>' + (state.artwork.filename ? esc(state.artwork.filename) : 'Tap or click to choose a file') + '</strong><span>Beta preview only: the filename stays in this browser; no file is uploaded to WordPress yet.</span></label>' : '') + '</section>' +
                '<section class="asbo-labs-art-group"><h3>2. Where should we place it?</h3><div class="asbo-labs-placement-grid">' + placements.map(function (p) {
                    return '<button type="button" data-action="placement" data-value="' + p[0] + '" class="' + (state.artwork.placement === p[0] ? 'is-selected' : '') + '"><span class="asbo-labs-placement-icon">✦</span><b>' + esc(p[1]) + '</b></button>';
                }).join('') + '</div></section>' +
                '<section class="asbo-labs-art-group"><h3>3. Production notes <small>(optional)</small></h3><textarea data-action="art-notes" rows="4" maxlength="500" placeholder="Thread colors, size preference, match a previous order, or anything else we should know…">' + esc(state.artwork.notes) + '</textarea></section>' +
                '<div class="asbo-labs-art-actions"><button type="button" class="asbo-labs-secondary" data-action="step" data-step="1">← Back to items</button><button type="button" class="asbo-labs-primary-gold" data-action="step" data-step="3">Continue to review →</button></div>' +
            '</section>' + orderSummaryRail() +
        '</div>';
    }

    function orderSummaryRail() {
        var rows = state.items.map(function (item) {
            return '<button type="button" class="asbo-labs-summary-item" data-action="edit-item" data-id="' + item.productId + '"><img src="' + esc(item.image) + '" alt=""><div><strong>' + esc(item.name) + '</strong><span>' + esc(capitalize(item.decoration)) + ' · ' + item.qty + ' pieces</span></div><b>' + money(item.total) + '</b></button>';
        }).join('');
        return '<aside class="asbo-labs-order-rail"><span class="asbo-labs-eyebrow">Your project</span><h3>' + state.items.length + ' ' + (state.items.length === 1 ? 'style' : 'styles') + ' selected</h3><div class="asbo-labs-summary-list">' + (rows || '<p>No styles added yet.</p>') + '</div><dl><div><dt>Total pieces</dt><dd>' + totalPieces() + '</dd></div><div><dt>Estimated total</dt><dd>' + money(totalValue()) + '</dd></div></dl><div class="asbo-labs-rail-note"><strong>Artwork status</strong><span>' + (state.artwork.filename ? 'File selected for beta preview' : 'No artwork file selected') + '</span></div><button type="button" class="asbo-labs-rail-edit" data-action="step" data-step="1">Edit items</button></aside>';
    }

    function checkoutStep() {
        return '<div class="asbo-labs-review-layout"><section class="asbo-labs-review-main"><header><span class="asbo-labs-eyebrow">Checkout</span><h2>Review your beta project.</h2><p>This review screen is the final safety stop before we connect V2 to the live ASBO cart and checkout services.</p></header>' +
            '<div class="asbo-labs-review-items">' + state.items.map(function (item) {
                return '<article><img src="' + esc(item.image) + '" alt=""><div><strong>' + esc(item.name) + '</strong><span>' + esc(capitalize(item.decoration)) + ' · ' + item.qty + ' pieces</span></div><b>' + money(item.total) + '</b></article>';
            }).join('') + '</div>' +
            '<section class="asbo-labs-review-art"><h3>Artwork plan</h3><dl><div><dt>Source</dt><dd>' + esc(capitalize(state.artwork.source)) + '</dd></div><div><dt>Placement</dt><dd>' + esc(state.artwork.placement.replace(/-/g, ' ')) + '</dd></div><div><dt>File</dt><dd>' + esc(state.artwork.filename || 'None selected') + '</dd></div></dl>' + (state.artwork.notes ? '<p><strong>Notes:</strong> ' + esc(state.artwork.notes) + '</p>' : '') + '</section>' +
            '<div class="asbo-labs-beta-stop"><strong>Integration safety stop</strong><p>No cart, order, stock, artwork, or product data is written by ASBO Labs. The next integration phase will connect this approved V2 interface to existing production services rather than re-create them.</p></div>' +
            '<div class="asbo-labs-art-actions"><button type="button" class="asbo-labs-secondary" data-action="step" data-step="2">← Back to artwork</button><button type="button" class="asbo-labs-primary-navy" disabled>Live checkout not connected yet</button></div></section>' + orderSummaryRail() + '</div>';
    }

    function projectFooter() {
        return '<footer class="asbo-labs-project-footer"><div><small>Project summary</small><strong>' + state.items.length + ' styles</strong></div><div><small>Total pieces</small><strong>' + totalPieces() + '</strong></div><div><small>Estimated total</small><strong>' + money(totalValue()) + '</strong></div><span class="asbo-labs-footer-spacer"></span>' + (state.step === 1 ? '<button type="button" class="asbo-labs-footer-link" data-action="step" data-step="2"' + (state.items.length < 1 ? ' disabled' : '') + '>Continue to Artwork →</button>' : state.step === 2 ? '<button type="button" class="asbo-labs-footer-link" data-action="step" data-step="3">Review beta project →</button>' : '<button type="button" class="asbo-labs-footer-link" data-action="reset">Reset Labs session</button>') + '</footer>';
    }

    function addActiveToOrder() {
        if (!state.active || activeQty() < 1) return;
        var breakdown = [];
        (state.active.variations || []).forEach(function (v) {
            var qty = Number(state.quantities[String(v.id)] || 0);
            if (qty > 0) breakdown.push({ variationId: v.id, label: v.label, qty: qty, price: Number(v.price || 0) });
        });
        var item = {
            productId: state.active.id,
            name: state.active.name,
            image: state.active.image,
            decoration: state.decoration,
            qty: activeQty(),
            total: activeTotal(),
            breakdown: breakdown
        };
        state.items = state.items.filter(function (i) { return Number(i.productId) !== Number(item.productId); });
        state.items.push(item);
        state.toast = item.name + ' saved to your beta project.';
        persist();
        render();
    }

    function removeItem(id) {
        var existing = itemForProduct(id);
        state.items = state.items.filter(function (item) { return Number(item.productId) !== Number(id); });
        state.quantities = {};
        state.toast = existing ? existing.name + ' removed from your beta project.' : 'Item removed.';
        persist();
        render();
    }

    function scheduleOnboarding() {
        if (!state.onboarding) return;
        clearTimeout(scheduleOnboarding.timer);
        scheduleOnboarding.timer = setTimeout(function () {
            state.onboarding = false;
            sessionStorage.setItem(INTRO_KEY, '1');
            render();
        }, 10000);
    }

    function scheduleToast() {
        if (!state.toast) return;
        clearTimeout(scheduleToast.timer);
        scheduleToast.timer = setTimeout(function () {
            state.toast = '';
            render();
        }, 2600);
    }

    function bindFileInput() {
        var input = root.querySelector('[data-art-file]');
        if (!input) return;
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            state.artwork.filename = file ? file.name : '';
            persist();
            render();
        });
    }

    function setQuantity(id, value) {
        var clean = Math.max(0, Math.min(9999, parseInt(value, 10) || 0));
        state.quantities[String(id)] = clean;
    }

    root.addEventListener('click', function (event) {
        var target = event.target.closest('[data-action]');
        if (!target) return;
        var action = target.getAttribute('data-action');

        if (action === 'select-product') selectProduct(target.getAttribute('data-id'), true);
        if (action === 'edit-item') { state.step = 1; persist(); selectProduct(target.getAttribute('data-id'), true); }
        if (action === 'decoration') { state.decoration = target.getAttribute('data-value'); render(); }
        if (action === 'qty' || action === 'qty-add') {
            var id = String(target.getAttribute('data-id'));
            var delta = Number(target.getAttribute('data-delta') || 0);
            setQuantity(id, Number(state.quantities[id] || 0) + delta);
            render();
        }
        if (action === 'toggle-colors') { state.showAllColors = !state.showAllColors; render(); }
        if (action === 'add-order') addActiveToOrder();
        if (action === 'remove-order') removeItem(target.getAttribute('data-id'));
        if (action === 'step') {
            var step = Number(target.getAttribute('data-step') || 1);
            if (step === 2 && state.items.length < 1) return;
            state.step = Math.min(3, Math.max(1, step));
            persist();
            render();
            window.scrollTo({ top: root.getBoundingClientRect().top + window.scrollY - 24, behavior: 'smooth' });
        }
        if (action === 'dismiss-intro') { state.onboarding = false; sessionStorage.setItem(INTRO_KEY, '1'); render(); }
        if (action === 'show-intro') { state.onboarding = true; render(); }
        if (action === 'save') { persist(); state.toast = 'Beta draft saved on this device.'; render(); }
        if (action === 'art-source') { state.artwork.source = target.getAttribute('data-value'); persist(); render(); }
        if (action === 'placement') { state.artwork.placement = target.getAttribute('data-value'); persist(); render(); }
        if (action === 'reset') {
            localStorage.removeItem(STORAGE);
            sessionStorage.removeItem(INTRO_KEY);
            location.reload();
        }
        if (action === 'pricing-info') {
            alert('ASBO Labs currently uses customer-visible WooCommerce regular prices for its UX estimate. Production quantity-tier pricing has not been duplicated or guessed. It will be connected to the existing ASBO pricing service during the integration beta.');
        }
        if (action === 'mobile-back') {
            var library = root.querySelector('.asbo-labs-library');
            if (library) library.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    root.addEventListener('change', function (event) {
        var target = event.target;
        if (target.getAttribute('data-action') === 'variant-choice') {
            var group = target.getAttribute('data-group');
            var old = state.variantChoice[group];
            var oldQty = Number(state.quantities[String(old)] || 0);
            if (old) delete state.quantities[String(old)];
            state.variantChoice[group] = String(target.value);
            if (oldQty > 0) state.quantities[String(target.value)] = oldQty;
            render();
        }
        if (target.getAttribute('data-action') === 'qty-input') {
            setQuantity(target.getAttribute('data-id'), target.value);
            render();
        }
    });

    root.addEventListener('input', function (event) {
        var target = event.target;
        if (target.getAttribute('data-action') === 'search') {
            var q = target.value.toLowerCase();
            root.querySelectorAll('.asbo-labs-product-row').forEach(function (row) {
                row.hidden = row.textContent.toLowerCase().indexOf(q) === -1;
            });
        }
        if (target.getAttribute('data-action') === 'qty-input') {
            setQuantity(target.getAttribute('data-id'), target.value);
            var card = target.closest('.asbo-labs-color-card');
            if (card) card.classList.toggle('has-qty', Number(target.value || 0) > 0);
        }
        if (target.getAttribute('data-action') === 'art-notes') {
            state.artwork.notes = target.value;
            persist();
        }
    });

    loadProducts();
})();
