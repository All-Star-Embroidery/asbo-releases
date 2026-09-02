from pathlib import Path
import json

php_path = Path('matrix/asbo-matrix/asbo-matrix.php')
php = php_path.read_text()
php = php.replace(' * Version: 0.1.1', ' * Version: 0.1.2', 1)
php = php.replace("private const VERSION = '0.1.1';", "private const VERSION = '0.1.2';", 1)

hook_anchor = "        add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'attach_asbo_cart_metadata' ), 20, 4 );\n"
store_hook = "        add_filter( 'woocommerce_store_api_add_to_cart_data', array( __CLASS__, 'store_api_add_to_cart_data' ), 20, 2 );\n"
if store_hook not in php:
    if hook_anchor not in php:
        raise SystemExit('cart metadata hook anchor not found')
    php = php.replace(hook_anchor, hook_anchor + store_hook, 1)

method_anchor = "    private static function fetch_update_manifest( bool $force = false ): ?array {\n"
store_method = '''    /**
     * WooCommerce's Add to Cart + Options (Beta) block uses the Store API rather
     * than submitting form.cart. Attach the same ASBO metadata to that request so
     * production ASBO's existing tier engine prices it identically.
     */
    public static function store_api_add_to_cart_data( array $add_to_cart_data, \\WP_REST_Request $request ): array {
        $requested_id = absint( $add_to_cart_data['id'] ?? $request->get_param( 'id' ) );
        if ( ! $requested_id ) {
            return $add_to_cart_data;
        }

        $requested_product = wc_get_product( $requested_id );
        if ( ! $requested_product instanceof WC_Product ) {
            return $add_to_cart_data;
        }

        $variation_id = 0;
        $parent       = $requested_product;
        if ( $requested_product instanceof WC_Product_Variation ) {
            $variation_id = $requested_product->get_id();
            $parent = wc_get_product( $requested_product->get_parent_id() );
            if ( ! $parent instanceof WC_Product ) {
                return $add_to_cart_data;
            }
        }

        $matrix = self::parse_pricing_matrix( (string) $parent->get_meta( self::META_PRICING, true ) );
        if ( empty( $matrix ) ) {
            return $add_to_cart_data;
        }

        if ( ! isset( $add_to_cart_data['cart_item_data'] ) || ! is_array( $add_to_cart_data['cart_item_data'] ) ) {
            $add_to_cart_data['cart_item_data'] = array();
        }

        if ( ! empty( $add_to_cart_data['cart_item_data']['asbo']['parent_product_id'] ) ) {
            return $add_to_cart_data;
        }

        $decoration = '';
        $explicit = $request->get_param( 'asbo_matrix_decoration' );
        if ( is_string( $explicit ) ) {
            $explicit = sanitize_text_field( $explicit );
            if ( isset( $matrix[ $explicit ] ) ) {
                $decoration = $explicit;
            }
        }

        if ( '' === $decoration ) {
            $cookie_key = 'asbo_matrix_decoration_' . $parent->get_id();
            if ( isset( $_COOKIE[ $cookie_key ] ) ) {
                $cookie_value = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_key ] ) );
                if ( isset( $matrix[ $cookie_value ] ) ) {
                    $decoration = $cookie_value;
                }
            }
        }

        if ( '' === $decoration ) {
            $methods = array_keys( $matrix );
            $decoration = isset( $methods[0] ) ? (string) $methods[0] : '';
        }

        if ( '' === $decoration || ! isset( $matrix[ $decoration ] ) ) {
            return $add_to_cart_data;
        }

        $sellable = $variation_id > 0 ? $requested_product : $parent;
        $regular  = (string) $sellable->get_regular_price( 'edit' );
        $base_unit_price = '' !== $regular && is_numeric( $regular ) ? (float) $regular : null;

        $add_to_cart_data['cart_item_data']['asbo'] = array(
            'parent_product_id' => $parent->get_id(),
            'decoration'        => $decoration,
            'order_group'       => 'product-page-matrix',
            'base_unit_price'   => $base_unit_price,
            'source'            => 'asbo-matrix-store-api',
        );

        return $add_to_cart_data;
    }

'''
if 'public static function store_api_add_to_cart_data' not in php:
    if method_anchor not in php:
        raise SystemExit('update manifest method anchor not found')
    php = php.replace(method_anchor, store_method + method_anchor, 1)
php_path.write_text(php)

js_path = Path('matrix/asbo-matrix/assets/matrix.js')
js = js_path.read_text()
helper_anchor = "    function ensureHidden(form, name, value) {\n"
helper = '''    function setDecorationCookie(productId, value) {
        if (!productId || !value) return;
        var key = 'asbo_matrix_decoration_' + String(productId);
        document.cookie = key + '=' + encodeURIComponent(value) + '; path=/; max-age=7200; SameSite=Lax';
    }

    function findBetaQuantityInput() {
        return document.querySelector('.wp-block-woocommerce-add-to-cart-with-options-quantity-selector input.qty, .wp-block-woocommerce-add-to-cart-with-options-quantity-selector input[type="number"]');
    }

'''
if 'function setDecorationCookie' not in js:
    if helper_anchor not in js:
        raise SystemExit('JS helper anchor not found')
    js = js.replace(helper_anchor, helper + helper_anchor, 1)

old_sync = '''        function syncForm() {
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
'''
new_sync = '''        function syncForm() {
            setDecorationCookie(productId, selectedDecoration);
            form = findCartForm(productId);
            if (!form) return false;
            ensureHidden(form, 'asbo_matrix_enabled', '1');
            ensureHidden(form, 'asbo_matrix_product_id', productId);
            ensureHidden(form, 'asbo_matrix_decoration', selectedDecoration);
            return true;
        }

        function quantityInput() {
            if (!form) syncForm();
            if (form) {
                var classicQty = form.querySelector('input.qty, input[name="quantity"]');
                if (classicQty) return classicQty;
            }
            return findBetaQuantityInput();
        }

        function currentQuantity() {
            var qty = quantityInput();
            if (!qty) return 1;
            return Math.max(0, parseInt(qty.value || '0', 10) || 0);
        }
'''
if old_sync not in js:
    raise SystemExit('sync/currentQuantity JS block not found')
js = js.replace(old_sync, new_sync, 1)

old_bind = '''        function bindForm() {
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
'''
new_bind = '''        function bindForm() {
            syncForm();
            var qty = quantityInput();
            if (qty && !qty.dataset.asboMatrixBound) {
                qty.dataset.asboMatrixBound = '1';
                qty.addEventListener('input', updateTier);
                qty.addEventListener('change', updateTier);
            }
            if (form && !form.dataset.asboMatrixSubmitBound) {
                form.dataset.asboMatrixSubmitBound = '1';
                form.addEventListener('submit', syncForm);
            }
            return !!(form || qty);
        }
'''
if old_bind not in js:
    raise SystemExit('bindForm JS block not found')
js = js.replace(old_bind, new_bind, 1)

select_anchor = "        var selectedDecoration = block.getAttribute('data-default-decoration') || Object.keys(matrix)[0];\n"
if "setDecorationCookie(productId, selectedDecoration);" not in js.split('function syncForm()', 1)[0]:
    if select_anchor not in js:
        raise SystemExit('selected decoration anchor not found')
    js = js.replace(select_anchor, select_anchor + "        setDecorationCookie(productId, selectedDecoration);\n", 1)
js_path.write_text(js)

block_path = Path('matrix/asbo-matrix/block/block.json')
data = json.loads(block_path.read_text())
data['version'] = '0.1.2'
data['category'] = 'all-star-embroidery'
block_path.write_text(json.dumps(data, indent=2) + '\n')
