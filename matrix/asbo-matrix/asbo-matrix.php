<?php
/**
 * Plugin Name: ASBO Matrix
 * Description: Adds the All Star bulk pricing matrix to normal WooCommerce product templates and hands standard product-page cart items into the existing ASBO tier-pricing engine.
 * Version: 0.1.0
 * Update URI: https://github.com/All-Star-Embroidery/asbo-releases/tree/asbo-matrix
 * Author: All Star Embroidery
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: asbo-matrix
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ASBO_Matrix_Plugin {
    private const VERSION = '0.1.0';
    private const META_PRICING = '_asbo_pricing_matrix';
    private const UPDATE_MANIFEST_URL = 'https://raw.githubusercontent.com/All-Star-Embroidery/asbo-releases/main/matrix.json';
    private const UPDATE_CACHE_KEY = 'asbo_matrix_update_manifest';
    private const UPDATE_CACHE_TTL = 30 * MINUTE_IN_SECONDS;

    public static function boot(): void {
        add_action( 'init', array( __CLASS__, 'register_block' ) );
        add_action( 'admin_notices', array( __CLASS__, 'dependency_notice' ) );

        // Only product-page submissions explicitly marked by the rendered Matrix block
        // receive ASBO cart metadata. Products with no matrix remain completely native.
        add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_product_page_bulk_choice' ), 20, 6 );
        add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'attach_asbo_cart_metadata' ), 20, 4 );

        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_github_update' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'github_plugin_information' ), 20, 3 );
        add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_update_cache_after_upgrade' ), 10, 2 );
    }

    public static function register_block(): void {
        $path = plugin_dir_path( __FILE__ ) . 'block';
        if ( file_exists( $path . '/block.json' ) ) {
            register_block_type(
                $path,
                array(
                    'render_callback' => array( __CLASS__, 'render_block' ),
                )
            );
        }
    }

    public static function dependency_notice(): void {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        if ( ! class_exists( 'WooCommerce' ) ) {
            echo '<div class="notice notice-error"><p><strong>ASBO Matrix:</strong> WooCommerce must be active.</p></div>';
            return;
        }

        if ( ! class_exists( 'ASBO_Plugin' ) ) {
            echo '<div class="notice notice-warning"><p><strong>ASBO Matrix:</strong> the All Star Bulk Order plugin should remain active so normal product-page quantities use the exact same ASBO cart pricing engine.</p></div>';
        }
    }

    /**
     * Product matrix format is intentionally identical to production ASBO:
     * Embroidery|6:30,12:27,24:24
     * Patch|6:33,12:30,24:27
     *
     * WooCommerce Regular Price remains authoritative at 1+.
     *
     * @return array<string,array<int,float>>
     */
    private static function parse_pricing_matrix( string $raw ): array {
        $matrix = array();
        $lines  = preg_split( '/\r\n|\r|\n/', trim( $raw ) );

        if ( ! is_array( $lines ) ) {
            return $matrix;
        }

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( '' === $line || false === strpos( $line, '|' ) ) {
                continue;
            }

            list( $decoration, $tiers_raw ) = array_map( 'trim', explode( '|', $line, 2 ) );
            $decoration = sanitize_text_field( $decoration );
            if ( '' === $decoration ) {
                continue;
            }

            $tiers = array();
            foreach ( explode( ',', $tiers_raw ) as $pair ) {
                if ( false === strpos( $pair, ':' ) ) {
                    continue;
                }

                list( $qty, $price ) = array_map( 'trim', explode( ':', $pair, 2 ) );
                $qty   = absint( $qty );
                $price = (float) wc_format_decimal( $price );

                if ( $qty > 0 && $price >= 0 ) {
                    $tiers[ $qty ] = $price;
                }
            }

            if ( $tiers ) {
                ksort( $tiers, SORT_NUMERIC );
                $matrix[ $decoration ] = $tiers;
            }
        }

        return $matrix;
    }

    private static function product_from_block( $block = null ): ?WC_Product {
        $product_id = 0;

        if ( $block instanceof WP_Block && ! empty( $block->context['postId'] ) ) {
            $product_id = absint( $block->context['postId'] );
        }

        if ( ! $product_id ) {
            $product_id = absint( get_the_ID() );
        }

        if ( ! $product_id && is_singular( 'product' ) ) {
            $product_id = absint( get_queried_object_id() );
        }

        if ( ! $product_id ) {
            return null;
        }

        $product = wc_get_product( $product_id );
        return $product instanceof WC_Product ? $product : null;
    }

    private static function regular_price_range( WC_Product $product ): array {
        $prices = array();

        if ( $product->is_type( 'variable' ) ) {
            foreach ( $product->get_children() as $variation_id ) {
                $variation = wc_get_product( $variation_id );
                if ( ! $variation instanceof WC_Product_Variation || ! $variation->exists() ) {
                    continue;
                }
                $raw = (string) $variation->get_regular_price( 'edit' );
                if ( '' !== $raw && is_numeric( $raw ) ) {
                    $prices[] = (float) $raw;
                }
            }
        } else {
            $raw = (string) $product->get_regular_price( 'edit' );
            if ( '' !== $raw && is_numeric( $raw ) ) {
                $prices[] = (float) $raw;
            }
        }

        if ( empty( $prices ) ) {
            return array( null, null );
        }

        return array( min( $prices ), max( $prices ) );
    }

    private static function regular_price_display( WC_Product $product ): string {
        list( $minimum, $maximum ) = self::regular_price_range( $product );
        if ( null === $minimum ) {
            return '—';
        }

        if ( abs( (float) $maximum - (float) $minimum ) < 0.00001 ) {
            return wc_price( $minimum );
        }

        return wc_price( $minimum ) . '<span class="asbo-matrix__price-range-separator">–</span>' . wc_price( $maximum );
    }

    private static function has_embroidery_method( array $matrix ): bool {
        foreach ( array_keys( $matrix ) as $method ) {
            if ( false !== stripos( (string) $method, 'embro' ) ) {
                return true;
            }
        }
        return false;
    }

    public static function render_block( array $attributes = array(), string $content = '', $block = null ): string {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return '';
        }

        $product = self::product_from_block( $block );
        if ( ! $product ) {
            return '';
        }

        // This is the hard behavior requested for the block: no filled pricing matrix
        // means no markup, no scripts, no hidden cart fields and no pricing changes.
        $raw_matrix = trim( (string) $product->get_meta( self::META_PRICING, true ) );
        if ( '' === $raw_matrix ) {
            return '';
        }

        $matrix = self::parse_pricing_matrix( $raw_matrix );
        if ( empty( $matrix ) ) {
            return '';
        }

        // If production ASBO is unavailable, public shoppers should not be shown a
        // pricing promise that cannot be honored in the cart. Admins get a clear cue.
        if ( ! class_exists( 'ASBO_Plugin' ) ) {
            return current_user_can( 'manage_woocommerce' )
                ? '<div class="asbo-matrix__dependency-warning">ASBO Matrix found pricing for this product, but the production All Star Bulk Order plugin is not active.</div>'
                : '';
        }

        $attributes = wp_parse_args(
            $attributes,
            array(
                'heading'                => __( 'Per-piece pricing by quantity', 'asbo-matrix' ),
                'description'            => __( 'Your decoration choice and quantity determine the unit price.', 'asbo-matrix' ),
                'showDescription'        => true,
                'showStitchAllowance'    => true,
                'showDecorationSelector' => true,
                'showActiveTier'         => true,
            )
        );

        $thresholds = array( 1 );
        foreach ( $matrix as $tiers ) {
            $thresholds = array_unique( array_merge( $thresholds, array_keys( $tiers ) ) );
        }
        sort( $thresholds, SORT_NUMERIC );

        $methods            = array_keys( $matrix );
        $default_decoration = (string) reset( $methods );
        $show_selector      = ! empty( $attributes['showDecorationSelector'] ) && count( $methods ) > 1;
        $show_stitch        = ! empty( $attributes['showStitchAllowance'] ) && self::has_embroidery_method( $matrix );
        $base_display       = self::regular_price_display( $product );

        wp_enqueue_style(
            'asbo-matrix',
            plugins_url( 'assets/matrix.css', __FILE__ ),
            array(),
            self::VERSION
        );
        wp_enqueue_script(
            'asbo-matrix',
            plugins_url( 'assets/matrix.js', __FILE__ ),
            array(),
            self::VERSION,
            true
        );

        $wrapper = get_block_wrapper_attributes(
            array(
                'class'                    => 'asbo-matrix',
                'data-asbo-matrix-product' => (string) $product->get_id(),
                'data-default-decoration'  => $default_decoration,
                'data-currency-code'       => get_woocommerce_currency(),
            )
        );

        ob_start();
        ?>
        <section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <script type="application/json" class="asbo-matrix__data"><?php echo wp_json_encode( $matrix ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>

            <div class="asbo-matrix__heading-row">
                <div class="asbo-matrix__heading-copy">
                    <h3><?php echo esc_html( (string) $attributes['heading'] ); ?></h3>
                    <?php if ( ! empty( $attributes['showDescription'] ) && '' !== trim( (string) $attributes['description'] ) ) : ?>
                        <p><?php echo esc_html( (string) $attributes['description'] ); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ( ! empty( $attributes['showActiveTier'] ) ) : ?>
                    <div class="asbo-matrix__active-tier" data-asbo-matrix-active-tier><?php esc_html_e( 'Enter quantity to see your pricing tier', 'asbo-matrix' ); ?></div>
                <?php endif; ?>
            </div>

            <?php if ( $show_stitch ) : ?>
                <p class="asbo-matrix__stitch-note">
                    <strong><?php esc_html_e( '10K stitch allowance:', 'asbo-matrix' ); ?></strong>
                    <?php esc_html_e( 'Includes embroidery up to 10,000 stitches. Additional charges may apply for larger or more complex designs.', 'asbo-matrix' ); ?>
                </p>
            <?php endif; ?>

            <div class="asbo-matrix__table-scroll">
                <table class="asbo-matrix__table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e( 'Decoration method', 'asbo-matrix' ); ?></th>
                            <?php foreach ( $thresholds as $threshold ) : ?>
                                <th scope="col" data-threshold="<?php echo esc_attr( $threshold ); ?>"><?php echo esc_html( $threshold ); ?>+</th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $matrix as $decoration => $tiers ) : ?>
                            <tr data-decoration-row="<?php echo esc_attr( $decoration ); ?>">
                                <th scope="row"><?php echo esc_html( $decoration ); ?></th>
                                <?php foreach ( $thresholds as $threshold ) : ?>
                                    <td data-threshold="<?php echo esc_attr( $threshold ); ?>">
                                        <?php
                                        if ( 1 === (int) $threshold ) {
                                            echo wp_kses_post( $base_display );
                                        } elseif ( isset( $tiers[ $threshold ] ) ) {
                                            echo wp_kses_post( wc_price( $tiers[ $threshold ] ) );
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ( $show_selector ) : ?>
                <fieldset class="asbo-matrix__decoration" data-asbo-matrix-decoration-control>
                    <legend><?php esc_html_e( 'Choose decoration method', 'asbo-matrix' ); ?></legend>
                    <div class="asbo-matrix__decoration-options">
                        <?php $first = true; ?>
                        <?php foreach ( $methods as $method ) : ?>
                            <label>
                                <input type="radio" name="asbo-matrix-decoration-<?php echo esc_attr( $product->get_id() ); ?>" value="<?php echo esc_attr( $method ); ?>" <?php checked( $first ); ?>>
                                <span><?php echo esc_html( $method ); ?></span>
                            </label>
                            <?php $first = false; ?>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function marked_product_page_request(): bool {
        if ( empty( $_REQUEST['asbo_matrix_enabled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return false;
        }
        return '1' === sanitize_text_field( wp_unslash( $_REQUEST['asbo_matrix_enabled'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    private static function requested_decoration( WC_Product $parent ): string {
        $matrix = self::parse_pricing_matrix( (string) $parent->get_meta( self::META_PRICING, true ) );
        if ( empty( $matrix ) ) {
            return '';
        }

        $requested = isset( $_REQUEST['asbo_matrix_decoration'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            ? sanitize_text_field( wp_unslash( $_REQUEST['asbo_matrix_decoration'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            : '';

        if ( '' !== $requested && isset( $matrix[ $requested ] ) ) {
            return $requested;
        }

        if ( 1 === count( $matrix ) ) {
            return (string) array_key_first( $matrix );
        }

        return '';
    }

    public static function validate_product_page_bulk_choice( bool $passed, int $product_id, int $quantity, int $variation_id = 0, array $variations = array(), array $cart_item_data = array() ): bool {
        if ( ! $passed || ! self::marked_product_page_request() ) {
            return $passed;
        }

        $parent = wc_get_product( $product_id );
        if ( ! $parent instanceof WC_Product ) {
            return $passed;
        }

        $matrix = self::parse_pricing_matrix( (string) $parent->get_meta( self::META_PRICING, true ) );
        if ( empty( $matrix ) ) {
            // Matrix was removed after the page loaded: fall back to native Woo pricing.
            return $passed;
        }

        $decoration = self::requested_decoration( $parent );
        if ( '' === $decoration ) {
            wc_add_notice( __( 'Choose a decoration method before adding this bulk-priced product to your cart.', 'asbo-matrix' ), 'error' );
            return false;
        }

        return $passed;
    }

    public static function attach_asbo_cart_metadata( array $cart_item_data, int $product_id, int $variation_id, int $quantity ): array {
        if ( ! self::marked_product_page_request() ) {
            return $cart_item_data;
        }

        $parent = wc_get_product( $product_id );
        if ( ! $parent instanceof WC_Product ) {
            return $cart_item_data;
        }

        $matrix = self::parse_pricing_matrix( (string) $parent->get_meta( self::META_PRICING, true ) );
        if ( empty( $matrix ) ) {
            return $cart_item_data;
        }

        $decoration = self::requested_decoration( $parent );
        if ( '' === $decoration || ! isset( $matrix[ $decoration ] ) ) {
            return $cart_item_data;
        }

        $sellable = $variation_id > 0 ? wc_get_product( $variation_id ) : $parent;
        if ( ! $sellable instanceof WC_Product ) {
            return $cart_item_data;
        }

        $regular = (string) $sellable->get_regular_price( 'edit' );
        $base_unit_price = '' !== $regular && is_numeric( $regular ) ? (float) $regular : null;

        // These keys intentionally match production ASBO. Its existing
        // woocommerce_before_calculate_totals hook will group quantities across
        // variations of the same parent product + decoration and apply the exact
        // matrix tier. Its existing cart/order display hooks also carry Decoration.
        $cart_item_data['asbo'] = array(
            'parent_product_id' => $product_id,
            'decoration'        => $decoration,
            'order_group'       => 'product-page-matrix',
            'base_unit_price'   => $base_unit_price,
            'source'            => 'asbo-matrix',
        );

        return $cart_item_data;
    }

    private static function fetch_update_manifest( bool $force = false ): ?array {
        if ( ! $force ) {
            $cached = get_site_transient( self::UPDATE_CACHE_KEY );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $response = wp_remote_get(
            self::UPDATE_MANIFEST_URL,
            array(
                'timeout'    => 8,
                'sslverify'  => true,
                'user-agent' => 'ASBO-Matrix/' . self::VERSION . '; ' . home_url( '/' ),
                'headers'    => array( 'Accept' => 'application/json' ),
            )
        );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return null;
        }

        $manifest = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $manifest ) || empty( $manifest['version'] ) || empty( $manifest['download_url'] ) ) {
            return null;
        }

        set_site_transient( self::UPDATE_CACHE_KEY, $manifest, self::UPDATE_CACHE_TTL );
        return $manifest;
    }

    public static function inject_github_update( $transient ) {
        if ( ! is_object( $transient ) ) {
            return $transient;
        }

        $manifest = self::fetch_update_manifest();
        if ( ! is_array( $manifest ) || version_compare( self::VERSION, (string) $manifest['version'], '>=' ) ) {
            return $transient;
        }

        $plugin_file = plugin_basename( __FILE__ );
        $transient->response[ $plugin_file ] = (object) array(
            'slug'        => 'asbo-matrix',
            'plugin'      => $plugin_file,
            'new_version' => sanitize_text_field( (string) $manifest['version'] ),
            'url'         => 'https://github.com/All-Star-Embroidery/asbo-releases/tree/asbo-matrix',
            'package'     => esc_url_raw( (string) $manifest['download_url'] ),
        );

        return $transient;
    }

    public static function github_plugin_information( $result, string $action, $args ) {
        if ( 'plugin_information' !== $action || empty( $args->slug ) || 'asbo-matrix' !== $args->slug ) {
            return $result;
        }

        $manifest = self::fetch_update_manifest();
        if ( ! is_array( $manifest ) ) {
            return $result;
        }

        return (object) array(
            'name'          => 'ASBO Matrix',
            'slug'          => 'asbo-matrix',
            'version'       => $manifest['version'] ?? self::VERSION,
            'author'        => 'All Star Embroidery',
            'homepage'      => 'https://github.com/All-Star-Embroidery/asbo-releases/tree/asbo-matrix',
            'download_link' => $manifest['download_url'] ?? '',
            'sections'      => array(
                'description' => 'All Star bulk quantity pricing matrix for normal WooCommerce product templates.',
                'changelog'   => $manifest['changelog'] ?? '',
            ),
        );
    }

    public static function clear_update_cache_after_upgrade( $upgrader, array $hook_extra ): void {
        if ( 'plugin' !== ( $hook_extra['type'] ?? '' ) ) {
            return;
        }
        delete_site_transient( self::UPDATE_CACHE_KEY );
    }
}

ASBO_Matrix_Plugin::boot();
