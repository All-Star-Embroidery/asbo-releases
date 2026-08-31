<?php
/**
 * Plugin Name: ASBO Labs
 * Description: Private beta workspace for the next All Star Bulk Order experience. Reads WooCommerce catalog data but does not write carts or orders.
 * Version: 1.3.0-beta.2
 * Author: All Star Embroidery
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Text Domain: asbo-labs
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ASBO_Labs {
    public const VERSION = '1.3.0-beta.2';
    private const REST_NS = 'asbo-labs/v1';
    private const BETA_FEED = 'https://raw.githubusercontent.com/All-Star-Embroidery/asbo-releases/asbo-labs/beta.json';

    public static function boot(): void {
        add_action( 'init', array( __CLASS__, 'register_block' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
        add_shortcode( 'asbo_labs', array( __CLASS__, 'shortcode' ) );

        // Labs has its own beta update feed. It never reads or writes production latest.json.
        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_beta_update' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'plugins_api' ), 10, 3 );
    }

    public static function register_block(): void {
        register_block_type(
            __DIR__ . '/block',
            array(
                'render_callback' => array( __CLASS__, 'render_block' ),
            )
        );
    }

    public static function shortcode( $atts = array() ): string {
        $atts = shortcode_atts(
            array(
                'category' => '',
                'limit'    => 24,
            ),
            is_array( $atts ) ? $atts : array(),
            'asbo_labs'
        );

        return self::render_builder(
            array(
                'category' => sanitize_title( (string) $atts['category'] ),
                'limit'    => max( 6, min( 60, absint( $atts['limit'] ) ) ),
            )
        );
    }

    public static function render_block( array $attributes = array() ): string {
        return self::render_builder( $attributes );
    }

    private static function render_builder( array $attributes ): string {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return '<div class="asbo-labs-missing">ASBO Labs requires WooCommerce.</div>';
        }

        $category = isset( $attributes['category'] ) ? sanitize_title( (string) $attributes['category'] ) : '';
        $limit    = isset( $attributes['limit'] ) ? max( 6, min( 60, absint( $attributes['limit'] ) ) ) : 24;

        wp_enqueue_style(
            'asbo-labs',
            plugins_url( 'assets/labs.css', __FILE__ ),
            array(),
            self::VERSION
        );
        wp_enqueue_style(
            'asbo-labs-beta2',
            plugins_url( 'assets/labs-beta2.css', __FILE__ ),
            array( 'asbo-labs' ),
            self::VERSION
        );
        wp_enqueue_script(
            'asbo-labs',
            plugins_url( 'assets/labs.js', __FILE__ ),
            array(),
            self::VERSION,
            true
        );

        $config = array(
            'version'            => self::VERSION,
            'restBase'           => esc_url_raw( rest_url( self::REST_NS ) ),
            'nonce'              => wp_create_nonce( 'wp_rest' ),
            'category'           => $category,
            'limit'              => $limit,
            'currencySymbol'     => get_woocommerce_currency_symbol(),
            'currencyCode'       => get_woocommerce_currency(),
            'productionDetected' => shortcode_exists( 'asbo_bulk_order' ),
            'phase'              => 'ux-sandbox-2',
        );

        wp_add_inline_script(
            'asbo-labs',
            'window.ASBOLabsConfig = ' . wp_json_encode( $config ) . ';',
            'before'
        );

        return '<div class="asbo-labs-root" data-asbo-labs-version="' . esc_attr( self::VERSION ) . '"><div class="asbo-labs-loading">Loading ASBO Labs…</div></div>';
    }

    public static function register_rest_routes(): void {
        register_rest_route(
            self::REST_NS,
            '/products',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'rest_products' ),
                'permission_callback' => array( __CLASS__, 'rest_permission' ),
                'args'                => array(
                    'search'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
                    'category' => array( 'sanitize_callback' => 'sanitize_title' ),
                    'limit'    => array( 'sanitize_callback' => 'absint' ),
                ),
            )
        );

        register_rest_route(
            self::REST_NS,
            '/product/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'rest_product' ),
                'permission_callback' => array( __CLASS__, 'rest_permission' ),
                'args'                => array(
                    'id' => array( 'sanitize_callback' => 'absint' ),
                ),
            )
        );
    }

    public static function rest_permission(): bool {
        // The page itself can be hidden however the site owner prefers. The API still
        // requires a trusted logged-in editor/admin so the beta catalog is not public.
        return is_user_logged_in() && current_user_can( 'edit_pages' );
    }

    public static function rest_products( WP_REST_Request $request ): WP_REST_Response {
        $limit = max( 6, min( 60, absint( $request->get_param( 'limit' ) ?: 24 ) ) );
        $args  = array(
            'status'  => 'publish',
            'limit'   => $limit,
            'orderby' => 'menu_order',
            'order'   => 'ASC',
            'return'  => 'objects',
        );

        $search = trim( (string) $request->get_param( 'search' ) );
        if ( '' !== $search ) {
            $args['s'] = $search;
        }

        $category = sanitize_title( (string) $request->get_param( 'category' ) );
        if ( '' !== $category ) {
            $args['category'] = array( $category );
        }

        $products = wc_get_products( $args );
        $payload  = array();
        foreach ( $products as $product ) {
            if ( ! $product instanceof WC_Product || ! $product->is_visible() ) {
                continue;
            }
            $payload[] = self::product_card_payload( $product );
        }

        return rest_ensure_response(
            array(
                'products' => $payload,
                'count'    => count( $payload ),
            )
        );
    }

    public static function rest_product( WP_REST_Request $request ) {
        $product = wc_get_product( absint( $request['id'] ) );
        if ( ! $product instanceof WC_Product || 'publish' !== $product->get_status() ) {
            return new WP_Error( 'asbo_labs_product_missing', 'Product not found.', array( 'status' => 404 ) );
        }

        return rest_ensure_response( self::product_detail_payload( $product ) );
    }

    private static function product_card_payload( WC_Product $product ): array {
        $regular = self::safe_regular_price( $product );
        $terms   = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );

        return array(
            'id'            => $product->get_id(),
            'name'          => wp_strip_all_tags( $product->get_name() ),
            'image'         => self::image_url( $product ),
            'startingPrice' => $regular,
            'priceHtml'     => wp_strip_all_tags( $product->get_price_html() ),
            'type'          => $product->get_type(),
            'categories'    => is_wp_error( $terms ) ? array() : array_values( $terms ),
        );
    }

    private static function product_detail_payload( WC_Product $product ): array {
        $variations = array();

        if ( $product->is_type( 'variable' ) ) {
            foreach ( $product->get_children() as $variation_id ) {
                $variation = wc_get_product( $variation_id );
                if ( ! $variation instanceof WC_Product_Variation || ! $variation->exists() ) {
                    continue;
                }

                $attributes = array();
                foreach ( $variation->get_variation_attributes() as $key => $value ) {
                    $taxonomy = str_replace( 'attribute_', '', (string) $key );
                    $label    = wc_attribute_label( $taxonomy );
                    $pretty   = $value;
                    if ( taxonomy_exists( $taxonomy ) ) {
                        $term = get_term_by( 'slug', $value, $taxonomy );
                        if ( $term && ! is_wp_error( $term ) ) {
                            $pretty = $term->name;
                        }
                    }
                    $attributes[] = array(
                        'key'   => $taxonomy,
                        'label' => $label ?: ucfirst( str_replace( array( 'pa_', '-', '_' ), array( '', ' ', ' ' ), $taxonomy ) ),
                        'value' => $pretty,
                    );
                }

                $variations[] = array(
                    'id'         => $variation->get_id(),
                    'image'      => self::image_url( $variation, $product ),
                    'attributes' => $attributes,
                    'label'      => self::variation_label( $attributes ),
                    'price'      => self::safe_regular_price( $variation, $product ),
                    'inStock'    => $variation->is_in_stock(),
                );
            }
        } else {
            $variations[] = array(
                'id'         => $product->get_id(),
                'image'      => self::image_url( $product ),
                'attributes' => array(),
                'label'      => 'Default',
                'price'      => self::safe_regular_price( $product ),
                'inStock'    => $product->is_in_stock(),
            );
        }

        $payload = array(
            'id'            => $product->get_id(),
            'name'          => wp_strip_all_tags( $product->get_name() ),
            'image'         => self::image_url( $product ),
            'startingPrice' => self::safe_regular_price( $product ),
            'short'         => wp_strip_all_tags( $product->get_short_description() ),
            'variations'    => $variations,
            'pricing'       => self::safe_pricing_matrix( $product ),
        );

        /**
         * Lets production ASBO expose an exact, customer-safe pricing adapter later
         * without Labs ever reading supplier cost/reference fields.
         */
        return apply_filters( 'asbo_labs_product_payload', $payload, $product );
    }

    private static function safe_regular_price( WC_Product $product, ?WC_Product $fallback = null ): float {
        $value = $product->get_regular_price();
        if ( '' === $value && $fallback instanceof WC_Product ) {
            $value = $fallback->get_regular_price();
        }
        if ( '' === $value ) {
            $value = $product->get_price();
        }
        return is_numeric( $value ) ? round( (float) $value, wc_get_price_decimals() ) : 0.0;
    }

    private static function safe_pricing_matrix( WC_Product $product ): array {
        // Deliberately whitelist only ASBO customer-facing candidate keys. Never scan
        // arbitrary product meta and never expose supplier buy/MAP/MSRP/reference data.
        $keys = array(
            '_asbo_pricing_matrix',
            'asbo_pricing_matrix',
            '_asbo_bulk_pricing',
            'asbo_bulk_pricing',
            '_asbo_customer_pricing',
        );

        foreach ( $keys as $key ) {
            $raw = $product->get_meta( $key, true );
            if ( empty( $raw ) ) {
                continue;
            }
            if ( is_string( $raw ) ) {
                $decoded = json_decode( $raw, true );
                if ( is_array( $decoded ) ) {
                    $raw = $decoded;
                }
            }
            if ( ! is_array( $raw ) ) {
                continue;
            }

            $safe = self::sanitize_numeric_matrix( $raw );
            if ( ! empty( $safe ) ) {
                return $safe;
            }
        }

        return array();
    }

    private static function sanitize_numeric_matrix( array $raw ): array {
        $safe = array();
        foreach ( $raw as $key => $value ) {
            if ( is_numeric( $value ) ) {
                $safe[ sanitize_key( (string) $key ) ] = (float) $value;
            } elseif ( is_array( $value ) ) {
                $nested = self::sanitize_numeric_matrix( $value );
                if ( ! empty( $nested ) ) {
                    $safe[ sanitize_key( (string) $key ) ] = $nested;
                }
            }
        }
        return $safe;
    }

    private static function image_url( WC_Product $product, ?WC_Product $fallback = null ): string {
        $image_id = $product->get_image_id();
        if ( ! $image_id && $fallback instanceof WC_Product ) {
            $image_id = $fallback->get_image_id();
        }
        $url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
        return $url ? esc_url_raw( $url ) : '';
    }

    private static function variation_label( array $attributes ): string {
        if ( empty( $attributes ) ) {
            return 'Default';
        }
        $bits = array();
        foreach ( $attributes as $attribute ) {
            if ( ! empty( $attribute['value'] ) ) {
                $bits[] = (string) $attribute['value'];
            }
        }
        return implode( ' / ', $bits );
    }

    public static function inject_beta_update( $transient ) {
        if ( ! is_object( $transient ) ) {
            return $transient;
        }

        $info = self::fetch_beta_feed();
        if ( ! is_array( $info ) || empty( $info['version'] ) || empty( $info['download_url'] ) ) {
            return $transient;
        }

        if ( version_compare( self::VERSION, (string) $info['version'], '>=' ) ) {
            return $transient;
        }

        $plugin_file = plugin_basename( __FILE__ );
        $transient->response[ $plugin_file ] = (object) array(
            'slug'        => 'asbo-labs',
            'plugin'      => $plugin_file,
            'new_version' => sanitize_text_field( (string) $info['version'] ),
            'url'         => 'https://github.com/All-Star-Embroidery/asbo-releases/tree/asbo-labs',
            'package'     => esc_url_raw( (string) $info['download_url'] ),
        );

        return $transient;
    }

    public static function plugins_api( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || empty( $args->slug ) || 'asbo-labs' !== $args->slug ) {
            return $result;
        }

        $info = self::fetch_beta_feed();
        if ( ! is_array( $info ) ) {
            return $result;
        }

        return (object) array(
            'name'          => 'ASBO Labs',
            'slug'          => 'asbo-labs',
            'version'       => isset( $info['version'] ) ? $info['version'] : self::VERSION,
            'author'        => 'All Star Embroidery',
            'homepage'      => 'https://github.com/All-Star-Embroidery/asbo-releases/tree/asbo-labs',
            'download_link' => isset( $info['download_url'] ) ? $info['download_url'] : '',
            'sections'      => array(
                'description' => 'Private UX beta lane for the next ASBO builder.',
                'changelog'   => isset( $info['changelog'] ) ? (string) $info['changelog'] : '',
            ),
        );
    }

    private static function fetch_beta_feed(): ?array {
        $cache_key = 'asbo_labs_beta_feed';
        $cached    = get_site_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $response = wp_remote_get( self::BETA_FEED, array( 'timeout' => 5 ) );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return null;
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $decoded ) ) {
            return null;
        }

        set_site_transient( $cache_key, $decoded, 30 * MINUTE_IN_SECONDS );
        return $decoded;
    }
}

ASBO_Labs::boot();
