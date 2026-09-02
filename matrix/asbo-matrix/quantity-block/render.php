<?php
/**
 * Server render for the All Star Single Product Quantity block.
 *
 * The control intentionally lives inside WooCommerce's Add to Cart + Options
 * context so its frontend module can reuse the currently selected variation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$product_id = isset( $block->context['postId'] ) ? absint( $block->context['postId'] ) : 0;
if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
    return '';
}

$product = wc_get_product( $product_id );
if ( ! $product instanceof WC_Product ) {
    return '';
}

$raw_matrix         = $product->get_meta( '_asbo_pricing_matrix', true );
$decoded_matrix     = is_array( $raw_matrix ) ? $raw_matrix : json_decode( (string) $raw_matrix, true );
$has_matrix         = is_array( $decoded_matrix ) && ! empty( $decoded_matrix );
$default_decoration = '';

if ( $has_matrix ) {
    $methods = array_keys( $decoded_matrix );
    if ( isset( $methods[0] ) ) {
        $default_decoration = sanitize_text_field( (string) $methods[0] );
    }
}

$module_path = __DIR__ . '/view.js';
if ( function_exists( 'wp_enqueue_script_module' ) && file_exists( $module_path ) ) {
    wp_enqueue_script_module(
        'asbo-matrix/single-product-quantity',
        plugins_url( 'view.js', __FILE__ ),
        array(
            '@wordpress/interactivity',
            '@woocommerce/stores/woocommerce/products',
            '@woocommerce/stores/woocommerce/cart',
        ),
        (string) filemtime( $module_path )
    );
}

$interactive_context = array(
    'parentProductId'    => $product->get_id(),
    'hasMatrix'          => $has_matrix,
    'decoration'         => $default_decoration,
    'optimisticQuantity' => null,
    'busy'               => false,
    'error'              => '',
);

$wrapper = get_block_wrapper_attributes(
    array(
        'class'                     => 'asbo-single-product-quantity',
        'data-wp-interactive'       => 'asbo-matrix/quantity',
        'data-wp-init'              => 'callbacks.init',
        'data-wp-class--is-busy'    => 'state.isBusy',
        'data-asbo-parent-product'   => (string) $product->get_id(),
    )
);

ob_start();
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo wp_interactivity_data_wp_context( $interactive_context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
    <div class="asbo-single-product-quantity__control" role="group" aria-label="<?php esc_attr_e( 'Quantity in cart', 'asbo-matrix' ); ?>">
        <button
            type="button"
            class="asbo-single-product-quantity__button"
            aria-label="<?php esc_attr_e( 'Decrease quantity in cart', 'asbo-matrix' ); ?>"
            data-wp-on--click="actions.decrease"
            data-wp-bind--disabled="state.disableDecrease"
        >−</button>

        <output
            class="asbo-single-product-quantity__value"
            aria-label="<?php esc_attr_e( 'Current quantity in cart', 'asbo-matrix' ); ?>"
            data-wp-text="state.quantity"
        >0</output>

        <button
            type="button"
            class="asbo-single-product-quantity__button"
            aria-label="<?php esc_attr_e( 'Increase quantity in cart', 'asbo-matrix' ); ?>"
            data-wp-on--click="actions.increase"
            data-wp-bind--disabled="state.disableIncrease"
        >+</button>
    </div>

    <span
        class="asbo-single-product-quantity__hint"
        data-wp-bind--hidden="state.hideHint"
        data-wp-text="state.hint"
    ></span>

    <span
        class="asbo-single-product-quantity__sr"
        aria-live="polite"
        aria-atomic="true"
        data-wp-text="state.status"
    ></span>
</div>
<?php
return (string) ob_get_clean();
