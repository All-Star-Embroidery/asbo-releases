<?php
/**
 * Plugin Name: All Star Bulk Order Block
 * Description: Gutenberg block version of the All Star Embroidery bulk-order workflow.
 * Version: 1.3.1-rc.2
 * Plugin URI: https://github.com/All-Star-Embroidery/asbo-releases
 * Update URI: https://github.com/All-Star-Embroidery/asbo-releases
 * Author: All Star Embroidery
 * Author URI: https://allstarembroidery-ltd.com/
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.3
 * WC tested up to: 11.1
 * Text Domain: all-star-bulk-order
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ASBO_Plugin {
    private const VERSION = '1.3.1-rc.2';
    private const META_ENABLED = '_asbo_enabled';
    private const META_DISPLAY_NAME = '_asbo_display_name';
    private const META_DESCRIPTION = '_asbo_short_description';
    private const META_SIZE_CHART = '_asbo_size_chart';
    private const META_PRICING = '_asbo_pricing_matrix';
    private const SESSION_PROJECT_DETAILS = 'asbo_project_details';
    private const META_RIGHTS_CONFIRMED = '_asbo_artwork_rights_confirmed';
    private const META_RIGHTS_VERSION = '_asbo_artwork_rights_version';
    private const META_RIGHTS_ACCEPTED_AT = '_asbo_artwork_rights_accepted_at';
    private const META_RIGHTS_USER_ID = '_asbo_artwork_rights_user_id';
    private const META_RIGHTS_STATEMENT = '_asbo_artwork_rights_statement';
    private const META_RIGHTS_SOURCE = '_asbo_artwork_rights_source';
    private const ARTWORK_RIGHTS_VERSION = '2026-09-11-v1';

    private const UPDATE_MANIFEST_URL = 'https://raw.githubusercontent.com/All-Star-Embroidery/asbo-releases/main/latest.json';
    private const UPDATE_CACHE_KEY = 'asbo_github_update_manifest';
    private const UPDATE_CACHE_TTL = 1800;
    private const UPDATE_FAILURE_CACHE_TTL = 300;

    public static function boot(): void {
        add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_woocommerce_compatibility' ) );

        if ( did_action( 'plugins_loaded' ) ) {
            self::init();
            return;
        }

        add_action( 'plugins_loaded', array( __CLASS__, 'init' ) );
    }

    public static function init(): void {
        self::register_update_hooks();

        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( __CLASS__, 'woocommerce_required_notice' ) );
            return;
        }

        add_shortcode( 'asbo_bulk_order', array( __CLASS__, 'shortcode' ) );
        add_filter( 'block_categories_all', array( __CLASS__, 'register_block_category' ), 10, 2 );
        add_action( 'init', array( __CLASS__, 'load_textdomain' ), 1 );
        add_action( 'init', array( __CLASS__, 'register_block' ) );
        add_action( 'admin_init', array( __CLASS__, 'repair_catalog_titles' ), 20 );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_frontend_assets' ), 20 );

        add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'product_fields' ) );
        add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product_fields' ) );

        add_action( 'wp_ajax_asbo_get_nonce', array( __CLASS__, 'ajax_get_nonce' ) );
        add_action( 'wp_ajax_nopriv_asbo_get_nonce', array( __CLASS__, 'ajax_get_nonce' ) );
        add_action( 'wp_ajax_asbo_add_bulk_to_cart', array( __CLASS__, 'ajax_add_bulk_to_cart' ) );
        add_action( 'wp_ajax_nopriv_asbo_add_bulk_to_cart', array( __CLASS__, 'ajax_add_bulk_to_cart' ) );

        add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_cart_tier_prices' ), 20 );
        add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_cart_item_data' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'copy_line_item_meta' ), 10, 4 );
        add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'copy_project_details_to_order' ), 10, 2 );
        add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'copy_project_details_to_order' ), 10, 1 );
        add_action( 'woocommerce_thankyou', array( __CLASS__, 'clear_project_details_session' ) );
    }

    private static function register_update_hooks(): void {
        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_github_update' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'github_plugin_information' ), 20, 3 );
        add_filter( 'auto_update_plugin', array( __CLASS__, 'enable_github_auto_update' ), 20, 2 );
        add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_update_cache_after_upgrade' ), 10, 2 );
    }

    private static function plugin_file(): string {
        return __FILE__;
    }

    private static function plugin_basename(): string {
        return plugin_basename( self::plugin_file() );
    }

    public static function declare_woocommerce_compatibility(): void {
        if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            return;
        }

        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', self::plugin_file(), true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', self::plugin_file(), true );
    }

    public static function load_textdomain(): void {
        load_plugin_textdomain(
            'all-star-bulk-order',
            false,
            dirname( self::plugin_basename() ) . '/languages'
        );
    }

    private static function enqueue_frontend_assets(): void {
        wp_enqueue_style(
            'asbo-frontend',
            plugins_url( 'assets/asbo-frontend.css', self::plugin_file() ),
            array(),
            self::VERSION
        );
        wp_enqueue_script(
            'asbo-frontend',
            plugins_url( 'assets/asbo-frontend.js', self::plugin_file() ),
            array(),
            self::VERSION,
            true
        );
    }

    public static function maybe_enqueue_frontend_assets(): void {
        if ( is_admin() || ! is_singular() ) {
            return;
        }

        $post = get_post();
        if ( ! $post instanceof WP_Post ) {
            return;
        }

        $content = (string) $post->post_content;
        if ( has_block( 'all-star/bulk-order', $post ) || has_shortcode( $content, 'asbo_bulk_order' ) ) {
            self::enqueue_frontend_assets();
        }
    }

    public static function ajax_get_nonce(): void {
        nocache_headers();
        wp_send_json_success(
            array(
                'nonce' => wp_create_nonce( 'asbo_bulk_order' ),
            )
        );
    }

    public static function artwork_rights_version(): string {
        return self::ARTWORK_RIGHTS_VERSION;
    }

    public static function artwork_rights_statement(): string {
        return __( 'I confirm that I own this artwork or have permission or a license to reproduce and use it for this order, including any copyrights, trademarks, school/team/business logos, names, images, likenesses, or other protected content. I authorize All Star Embroidery to reproduce the submitted artwork solely to prepare proofs and fulfill this order. I understand All Star Embroidery may pause or decline work if authorization is unclear.', 'all-star-bulk-order' );
    }

    private static function fetch_update_manifest( bool $force = false ): ?array {
        if ( ! $force ) {
            $cached = get_site_transient( self::UPDATE_CACHE_KEY );
            if ( is_array( $cached ) ) {
                if ( ! empty( $cached['_asbo_update_failed'] ) ) {
                    return null;
                }
                return $cached;
            }
        }

        $response = wp_remote_get(
            self::UPDATE_MANIFEST_URL,
            array(
                'timeout'    => 8,
                'sslverify'  => true,
                'user-agent' => 'All-Star-Bulk-Order/' . self::VERSION . '; ' . home_url( '/' ),
                'headers'    => array( 'Accept' => 'application/json' ),
            )
        );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            set_site_transient( self::UPDATE_CACHE_KEY, array( '_asbo_update_failed' => 1 ), self::UPDATE_FAILURE_CACHE_TTL );
            return null;
        }

        $manifest = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $manifest ) || empty( $manifest['version'] ) || empty( $manifest['download_url'] ) ) {
            set_site_transient( self::UPDATE_CACHE_KEY, array( '_asbo_update_failed' => 1 ), self::UPDATE_FAILURE_CACHE_TTL );
            return null;
        }

        $manifest['version']      = sanitize_text_field( $manifest['version'] );
        $manifest['download_url'] = esc_url_raw( $manifest['download_url'] );
        $manifest['homepage']     = isset( $manifest['homepage'] ) ? esc_url_raw( $manifest['homepage'] ) : '';

        set_site_transient( self::UPDATE_CACHE_KEY, $manifest, self::UPDATE_CACHE_TTL );
        return $manifest;
    }

    public static function inject_github_update( $transient ) {
        if ( ! is_object( $transient ) ) {
            return $transient;
        }

        $manifest = self::fetch_update_manifest();
        if ( ! $manifest || version_compare( self::VERSION, $manifest['version'], '>=' ) ) {
            return $transient;
        }

        $update = (object) array(
            'slug'         => 'all-star-bulk-order-block',
            'plugin'       => self::plugin_basename(),
            'new_version'  => $manifest['version'],
            'url'          => $manifest['homepage'] ?? '',
            'package'      => $manifest['download_url'],
            'icons'        => array(),
            'banners'      => array(),
            'tested'       => $manifest['tested'] ?? '',
            'requires_php' => $manifest['requires_php'] ?? '',
        );

        if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
            $transient->response = array();
        }

        $transient->response[ self::plugin_basename() ] = $update;
        return $transient;
    }

    public static function github_plugin_information( $result, string $action, $args ) {
        if ( 'plugin_information' !== $action || empty( $args->slug ) || 'all-star-bulk-order-block' !== $args->slug ) {
            return $result;
        }

        $manifest = self::fetch_update_manifest();
        if ( ! $manifest ) {
            return $result;
        }

        return (object) array(
            'name'          => 'All Star Bulk Order Block',
            'slug'          => 'all-star-bulk-order-block',
            'version'       => $manifest['version'],
            'author'        => '<a href="https://allstaremb.com">All Star Embroidery</a>',
            'homepage'      => $manifest['homepage'] ?? '',
            'requires'      => $manifest['requires'] ?? '',
            'tested'        => $manifest['tested'] ?? '',
            'requires_php'  => $manifest['requires_php'] ?? '',
            'last_updated'  => $manifest['last_updated'] ?? '',
            'download_link' => $manifest['download_url'],
            'sections'      => array(
                'description' => $manifest['description'] ?? 'All Star Embroidery bulk-order workflow.',
                'changelog'   => $manifest['changelog'] ?? '',
            ),
        );
    }

    public static function enable_github_auto_update( $update, $item ): bool {
        $plugin = is_object( $item ) && isset( $item->plugin ) ? (string) $item->plugin : '';
        if ( self::plugin_basename() !== $plugin ) {
            return (bool) $update;
        }
        return (bool) apply_filters( 'asbo_enable_auto_updates', true, $item, $update );
    }

    public static function clear_update_cache_after_upgrade( $upgrader, array $hook_extra ): void {
        if ( 'plugin' !== ( $hook_extra['type'] ?? '' ) ) {
            return;
        }

        $plugins = $hook_extra['plugins'] ?? array();
        if ( isset( $hook_extra['plugin'] ) ) {
            $plugins[] = $hook_extra['plugin'];
        }

        if ( in_array( self::plugin_basename(), (array) $plugins, true ) ) {
            delete_site_transient( self::UPDATE_CACHE_KEY );
        }
    }

    public static function woocommerce_required_notice(): void {
        echo '<div class="notice notice-error"><p><strong>All Star Bulk Order</strong> requires WooCommerce to be installed and active.</p></div>';
    }

    public static function product_fields(): void {
        echo '<div class="options_group">';

        woocommerce_wp_checkbox(
            array(
                'id'          => self::META_ENABLED,
                'label'       => __( 'Show in bulk order page', 'all-star-bulk-order' ),
                'description' => __( 'Displays this product in the accordion bulk-order experience.', 'all-star-bulk-order' ),
            )
        );

        woocommerce_wp_text_input(
            array(
                'id'          => self::META_DISPLAY_NAME,
                'label'       => __( 'Bulk order display name', 'all-star-bulk-order' ),
                'description' => __( 'Optional title override for the bulk-order page. Leave blank to use the WooCommerce product name.', 'all-star-bulk-order' ),
                'desc_tip'    => true,
            )
        );

        woocommerce_wp_textarea_input(
            array(
                'id'          => self::META_DESCRIPTION,
                'label'       => __( 'Bulk order description', 'all-star-bulk-order' ),
                'description' => __( 'A short description shown beside the featured image.', 'all-star-bulk-order' ),
                'desc_tip'    => true,
            )
        );

        woocommerce_wp_textarea_input(
            array(
                'id'          => self::META_SIZE_CHART,
                'label'       => __( 'Size chart / product specs', 'all-star-bulk-order' ),
                'description' => __( 'Basic HTML is allowed. This appears in a collapsible section.', 'all-star-bulk-order' ),
                'desc_tip'    => true,
            )
        );

        woocommerce_wp_textarea_input(
            array(
                'id'          => self::META_PRICING,
                'label'       => __( 'Tiered pricing matrix', 'all-star-bulk-order' ),
                'description' => __( 'One decoration type per line. Example: Embroidery|1:32,12:27,24:24,48:22,96:20', 'all-star-bulk-order' ),
                'desc_tip'    => true,
                'placeholder' => "Embroidery|1:32,12:27,24:24,48:22,96:20\nPatch|1:35,12:30,24:27,48:25,96:23",
            )
        );

        echo '</div>';
    }

    public static function save_product_fields( WC_Product $product ): void {
        if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
            return;
        }

        $enabled = isset( $_POST[ self::META_ENABLED ] ) ? 'yes' : 'no';
        $product->update_meta_data( self::META_ENABLED, $enabled );

        if ( isset( $_POST[ self::META_DISPLAY_NAME ] ) ) {
            $product->update_meta_data(
                self::META_DISPLAY_NAME,
                sanitize_text_field( wp_unslash( $_POST[ self::META_DISPLAY_NAME ] ) )
            );
        }

        if ( isset( $_POST[ self::META_DESCRIPTION ] ) ) {
            $product->update_meta_data(
                self::META_DESCRIPTION,
                sanitize_textarea_field( wp_unslash( $_POST[ self::META_DESCRIPTION ] ) )
            );
        }

        if ( isset( $_POST[ self::META_SIZE_CHART ] ) ) {
            $product->update_meta_data(
                self::META_SIZE_CHART,
                wp_kses_post( wp_unslash( $_POST[ self::META_SIZE_CHART ] ) )
            );
        }

        if ( isset( $_POST[ self::META_PRICING ] ) ) {
            $raw = sanitize_textarea_field( wp_unslash( $_POST[ self::META_PRICING ] ) );
            $product->update_meta_data( self::META_PRICING, $raw );
        }
    }

    public static function repair_catalog_titles(): void {
        $repair_version = '2';
        if ( get_option( 'asbo_catalog_title_repair_version' ) === $repair_version ) {
            return;
        }

        if ( get_transient( 'asbo_catalog_title_repair_lock' ) ) {
            return;
        }
        set_transient( 'asbo_catalog_title_repair_lock', 1, 10 * MINUTE_IN_SECONDS );

        $catalog = array(
            'PC54' => 'Port & Company Core Cotton Tee',
            'PC54LS' => 'Port & Company Long Sleeve Core Cotton Tee',
            'PC61' => 'Port & Company Essential Tee',
            'PC61LS' => 'Port & Company Long Sleeve Essential Tee',
            'K500' => 'Port Authority Silk Touch Polo',
            'L500' => 'Port Authority Ladies Silk Touch Polo',
            'K500P' => 'Port Authority Silk Touch Polo with Pocket',
            'LPC54' => 'Port & Company Ladies Core Cotton Tee',
            'ST350' => 'Sport-Tek Competitor Tee',
            'ST350LS' => 'Sport-Tek Long Sleeve Competitor Tee',
            'LST350' => 'Sport-Tek Ladies Competitor Tee',
            'ST650' => 'Sport-Tek Micropique Sport-Wick Polo',
            'LST650' => 'Sport-Tek Ladies Micropique Sport-Wick Polo',
            'DT104' => 'District Very Important Tee',
            'DT6000' => 'District Perfect Tri Tee',
            'J317' => 'Port Authority Core Soft Shell Jacket',
            'L317' => 'Port Authority Ladies Core Soft Shell Jacket',
            'PC78H' => 'Port & Company Core Fleece Pullover Hooded Sweatshirt',
            'PC78' => 'Port & Company Core Fleece Crewneck Sweatshirt',
            'PC90H' => 'Port & Company Essential Fleece Pullover Hooded Sweatshirt',
            'PC90' => 'Port & Company Essential Fleece Crewneck Sweatshirt',
            'CP80' => 'Port Authority Six-Panel Twill Cap',
            'CP90' => 'Port Authority Knit Cap',
            'K540' => 'Port Authority Easy Care Shirt',
            'S608' => 'Port Authority Easy Care Long Sleeve Shirt',
            'L608' => 'Port Authority Ladies Easy Care Long Sleeve Shirt',
            'S508' => 'Port Authority Short Sleeve Easy Care Shirt',
            'LS508' => 'Port Authority Ladies Short Sleeve Easy Care Shirt',
            'C112' => 'CornerStone Washed Duck Active Jacket',
            'CS410' => 'CornerStone Select Lightweight Snag-Proof Polo',
            'CS412' => 'CornerStone Select Snag-Proof Polo',
            'PC450' => 'Port & Company Fan Favorite Tee',
            'LPC450' => 'Port & Company Ladies Fan Favorite Tee',
            'PC450LS' => 'Port & Company Fan Favorite Long Sleeve Tee',
            'NE200' => 'New Era Structured Stretch Fit Cap',
            'NE1020' => 'New Era Flat Bill Snapback Cap',
            'NE400' => 'New Era Core Fleece Hoodie',
            'DT130' => 'District Featherweight Fleece Hoodie',
            'PC78ZH' => 'Port & Company Core Fleece Full-Zip Hooded Sweatshirt',
            'K100' => 'Port Authority Core Classic Pique Polo',
            'LK100' => 'Port Authority Ladies Core Classic Pique Polo',
            'K110' => 'Port Authority Core Classic Pique Polo with Pocket',
            'K525' => 'Port Authority Jersey Knit Polo',
            'L525' => 'Port Authority Ladies Jersey Knit Polo',
            'LPC54V' => 'Port & Company Ladies Core Cotton V-Neck Tee',
            'PC450V' => 'Port & Company Fan Favorite V-Neck Tee',
            'LPC450V' => 'Port & Company Ladies Fan Favorite V-Neck Tee',
            'ST240' => 'Sport-Tek Sport-Wick Fleece Hooded Pullover',
            'YST350' => 'Sport-Tek Youth Competitor Tee',
            'PC54Y' => 'Port & Company Youth Core Cotton Tee',
            '112TruckerSnapback' => 'Richardson 112 Trucker Snapback',
            '112PMTruckerSnapback' => 'Richardson 112PM - Trucker Snapback',
            '112PFPTruckerSnapback' => 'Richardson 112-PFP Trucker Snapback',
            '112PlusTruckerSnapback' => 'Richardson 112Plus - Trucker Snapback',
            '6606TruckerSnapback' => 'Yupoong Trucker Snapback',
            '6006FlatBillTruckerSnapback' => 'Yupoong Flat Bill Trucker Snapback',
            '6245CMDadCap' => 'Yupoong Dad Cap',
            '6089MFlatBillSnapback' => 'Yupoong Flat Bill Snapback',
            'TraditionalSnapback' => 'Yupoong Traditional Snapback',
            '110MTruckerSnapback' => 'FlexFit 110 Trucker Snapback',
            '6277CurvedBillFitted' => 'FlexFit Original 6277 Fitted',
            'TruckerFitted' => 'FlexFit Trucker Fitted',
            'FlatBillFitted' => 'FlexFit Flat Bill Fitted',
            '104BRRopeTrucker' => 'Pacific Rope Trucker Snapback',
            'USA100TruckerSnapback' => 'USA Trucker Snapback',
        );

        try {
            foreach ( $catalog as $sku => $correct_name ) {
                $product_id = wc_get_product_id_by_sku( $sku );
                if ( ! $product_id ) {
                    continue;
                }

                $product = wc_get_product( $product_id );
                if ( ! $product instanceof WC_Product ) {
                    continue;
                }

                $dirty = false;
                $current_name = trim( (string) $product->get_name( 'edit' ) );
                if ( '' === $current_name || 'product' === strtolower( $current_name ) ) {
                    $product->set_name( $correct_name );
                    $dirty = true;
                }

                $display_name = trim( (string) $product->get_meta( self::META_DISPLAY_NAME, true ) );
                if ( '' === $display_name || 'product' === strtolower( $display_name ) ) {
                    $product->update_meta_data( self::META_DISPLAY_NAME, $correct_name );
                    $dirty = true;
                }

                if ( $dirty ) {
                    $product->save();
                }
            }

            update_option( 'asbo_catalog_title_repair_version', $repair_version, false );
            wc_delete_product_transients();
        } finally {
            delete_transient( 'asbo_catalog_title_repair_lock' );
        }
    }

    private static function display_name( WC_Product $product ): string {
        $display_name = trim( (string) $product->get_meta( self::META_DISPLAY_NAME ) );

        if ( '' === $display_name || 'product' === strtolower( $display_name ) ) {
            $display_name = trim( (string) $product->get_name() );
        }

        if ( '' === $display_name || 'product' === strtolower( $display_name ) ) {
            $sku = trim( (string) $product->get_sku() );
            $display_name = $sku ? $sku : __( 'Unnamed product', 'all-star-bulk-order' );
        }

        return $display_name;
    }

    /**
     * Pricing format:
     * Embroidery|6:30,12:27,24:24
     * Patch|6:33,12:30,24:27
     *
     * WooCommerce Regular Price is authoritative at 1+. Any legacy matrix 1: entry
     * is retained in product metadata for compatibility but ignored by ASBO pricing.
     *
     * @return array<string,array<int,float>>
     */
    private static function parse_pricing_matrix( string $raw ): array {
        static $cache = array();

        $cache_key = md5( $raw );
        if ( isset( $cache[ $cache_key ] ) ) {
            return $cache[ $cache_key ];
        }

        $matrix = array();
        $lines  = preg_split( '/\r\n|\r|\n/', trim( $raw ) );

        if ( ! is_array( $lines ) ) {
            $cache[ $cache_key ] = $matrix;
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

        $cache[ $cache_key ] = $matrix;
        return $matrix;
    }

    private static function tier_price( array $tiers, int $quantity ): ?float {
        if ( $quantity < 1 || empty( $tiers ) ) {
            return null;
        }

        ksort( $tiers, SORT_NUMERIC );
        $selected = null;

        foreach ( $tiers as $minimum => $price ) {
            // WooCommerce Regular Price is the authoritative 1+ storefront price.
            // Legacy 1: entries in the ASBO matrix are intentionally ignored.
            if ( (int) $minimum <= 1 ) {
                continue;
            }

            if ( $quantity >= (int) $minimum ) {
                $selected = (float) $price;
            }
        }

        return $selected;
    }

    public static function register_block_category( array $categories, $editor_context = null ): array {
        foreach ( $categories as $category ) {
            if ( isset( $category['slug'] ) && 'all-star-embroidery' === $category['slug'] ) {
                return $categories;
            }
        }

        $categories[] = array(
            'slug'  => 'all-star-embroidery',
            'title' => __( 'All Star Embroidery', 'all-star-bulk-order' ),
            'icon'  => null,
        );

        return $categories;
    }

    public static function register_block(): void {
        $block_path = plugin_dir_path( __FILE__ ) . 'block';

        if ( file_exists( $block_path . '/block.json' ) ) {
            register_block_type(
                $block_path,
                array(
                    'render_callback' => array( __CLASS__, 'render_block' ),
                )
            );
        }
    }

    public static function render_block( array $attributes = array(), string $content = '', $block = null ): string {
        $defaults = self::block_attribute_defaults();
        $attributes = wp_parse_args( $attributes, $defaults );

        return self::shortcode(
            array(
                'category'                  => $attributes['category'],
                'limit'                     => $attributes['limit'],
                'welcome_title'             => $attributes['welcomeTitle'],
                'welcome_text'              => $attributes['welcomeText'],
                'products_title'            => $attributes['productsTitle'],
                'products_text'             => $attributes['productsText'],
                'artwork_title'             => $attributes['artworkTitle'],
                'artwork_text'              => $attributes['artworkText'],
                'checkout_title'            => $attributes['checkoutTitle'],
                'checkout_text'             => $attributes['checkoutText'],
                'step_items'                => $attributes['stepItemsLabel'],
                'step_artwork'              => $attributes['stepArtworkLabel'],
                'step_checkout'             => $attributes['stepCheckoutLabel'],
                'next_artwork_text'         => $attributes['nextArtworkText'],
                'continue_checkout_text'    => $attributes['continueCheckoutText'],
                'back_text'                 => $attributes['backText'],
                'estimated_total_label'     => $attributes['estimatedTotalLabel'],
                'pieces_selected_label'     => $attributes['piecesSelectedLabel'],
                'total_saved_label'         => $attributes['totalSavedLabel'],
                'product_total_label'        => $attributes['productTotalLabel'],
                'digitizing_incentive_text' => $attributes['digitizingIncentiveText'],
                'shipping_incentive_text'   => $attributes['shippingIncentiveText'],
                'free_art_qty'              => $attributes['freeArtQty'],
                'free_ship_qty'             => $attributes['freeShipQty'],
                'digitizing_savings_amount' => $attributes['digitizingSavingsAmount'],
                'shipping_savings_amount'   => $attributes['shippingSavingsAmount'],
                'primary_color'             => $attributes['primaryColor'],
                'primary_hover_color'       => $attributes['primaryHoverColor'],
                'primary_text_color'        => $attributes['primaryTextColor'],
                'navy_color'                => $attributes['navyColor'],
                'text_color'                => $attributes['textColor'],
                'muted_color'               => $attributes['mutedColor'],
                'border_color'              => $attributes['borderColor'],
                'surface_color'             => $attributes['surfaceColor'],
                'page_background'           => $attributes['pageBackground'],
                'sticky_background'         => $attributes['stickyBackground'],
                'sticky_text_color'         => $attributes['stickyTextColor'],
                'progress_inactive_color'   => $attributes['progressInactiveColor'],
                'button_radius'             => $attributes['buttonRadius'],
                'card_radius'               => $attributes['cardRadius'],
            )
        );
    }

    private static function block_attribute_defaults(): array {
        return array(
            'category'                  => '',
            'limit'                     => 100,
            'welcomeTitle'              => 'New here?',
            'welcomeText'               => 'Pick your garments and colors below — pricing updates live as you add quantities. Artwork and production details come next.',
            'productsTitle'             => 'Select garments and headwear',
            'productsText'              => 'Open a style, choose embroidery or patch, then split the quantity across available colors. Pricing tiers use the combined quantity for that style and decoration method.',
            'artworkTitle'              => 'Artwork plan and production details',
            'artworkText'               => 'Tell us how you’ll provide the logo and share the production details we should review. The actual artwork file is uploaded after checkout, once a secure WooCommerce order number exists.',
            'checkoutTitle'             => 'Review and continue to checkout',
            'checkoutText'              => 'The customer is sent to WooCommerce checkout, where shipping, taxes, payment, and order details are finalized.',
            'stepItemsLabel'            => 'Items',
            'stepArtworkLabel'          => 'Artwork',
            'stepCheckoutLabel'         => 'Checkout',
            'nextArtworkText'           => 'Next: Artwork Plan',
            'continueCheckoutText'      => 'Continue to Checkout',
            'backText'                  => 'Back',
            'estimatedTotalLabel'       => 'Estimated Total',
            'piecesSelectedLabel'       => 'Pieces Selected',
            'totalSavedLabel'           => 'Total Saved',
            'productTotalLabel'          => 'Selected total',
            'digitizingIncentiveText'   => 'pieces · Digitizing + setup included',
            'shippingIncentiveText'     => 'pieces · Standard shipping included',
            'freeArtQty'                => 12,
            'freeShipQty'               => 24,
            'digitizingSavingsAmount'   => 15,
            'shippingSavingsAmount'     => 10,
            'primaryColor'              => '#f3bd2f',
            'primaryHoverColor'         => '#f7c94f',
            'primaryTextColor'          => '#11192d',
            'navyColor'                 => '#11192d',
            'textColor'                 => '#182033',
            'mutedColor'                => '#687184',
            'borderColor'               => '#e2e6ed',
            'surfaceColor'              => '#f7f8fb',
            'pageBackground'            => '#ffffff',
            'stickyBackground'          => '#ffffff',
            'stickyTextColor'           => '#182033',
            'progressInactiveColor'     => '#8a92a2',
            'buttonRadius'              => 9,
            'cardRadius'                => 14,
        );
    }

    /**
     * Return recent eligible orders belonging to the currently logged-in customer.
     * Uses WooCommerce order APIs so it remains compatible with HPOS.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function recent_customer_orders(): array {
        if ( ! is_user_logged_in() || ! function_exists( 'wc_get_orders' ) ) {
            return array();
        }

        $user_id = get_current_user_id();
        if ( $user_id < 1 ) {
            return array();
        }

        $orders = wc_get_orders(
            array(
                'customer_id' => $user_id,
                'limit'       => 25,
                'orderby'     => 'date',
                'order'       => 'DESC',
                'status'      => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
                'return'      => 'objects',
            )
        );

        $choices = array();
        foreach ( $orders as $order ) {
            if ( ! $order instanceof WC_Order || (int) $order->get_customer_id() !== $user_id ) {
                continue;
            }

            $item_names = array();
            $all_items  = $order->get_items();
            foreach ( $all_items as $item ) {
                if ( ! $item instanceof WC_Order_Item_Product ) {
                    continue;
                }
                $item_names[] = $item->get_name();
                if ( count( $item_names ) >= 2 ) {
                    break;
                }
            }

            $product_summary = $item_names ? implode( ', ', $item_names ) : __( 'Custom apparel order', 'all-star-bulk-order' );
            if ( count( $all_items ) > 2 ) {
                $product_summary .= sprintf( __( ' +%d more', 'all-star-bulk-order' ), count( $all_items ) - 2 );
            }

            $date = $order->get_date_created();
            $choices[] = array(
                'id'      => $order->get_id(),
                'number'  => $order->get_order_number(),
                'date'    => $date ? wc_format_datetime( $date, get_option( 'date_format' ) ) : '',
                'summary' => wp_strip_all_tags( $product_summary ),
            );
        }

        return $choices;
    }

    /**
     * Normalize a previous-order reference. A customer-order selector posts values
     * like "order:123"; manual references remain free text for guest/legacy orders.
     *
     * @return array{reference:string,order_id:int}
     */
    private static function normalize_previous_order_reference( string $raw ): array {
        $raw = trim( $raw );
        if ( '' === $raw ) {
            return array( 'reference' => '', 'order_id' => 0 );
        }

        if ( 0 === strpos( $raw, 'order:' ) ) {
            $order_id = absint( substr( $raw, 6 ) );
            $order    = $order_id ? wc_get_order( $order_id ) : false;
            $user_id  = get_current_user_id();

            if ( $order instanceof WC_Order && $user_id > 0 && (int) $order->get_customer_id() === $user_id ) {
                return array(
                    'reference' => sprintf( 'Order #%s', $order->get_order_number() ),
                    'order_id'  => $order->get_id(),
                );
            }

            return array( 'reference' => '', 'order_id' => 0 );
        }

        return array(
            'reference' => sanitize_text_field( $raw ),
            'order_id'  => 0,
        );
    }

    public static function shortcode( array $atts = array() ): string {
        self::enqueue_frontend_assets();

        $atts = shortcode_atts(
            array(
                'category'        => '',
                'limit'           => '100',
                'welcome_title'   => __( 'New here?', 'all-star-bulk-order' ),
                'welcome_text'    => __( 'Pick your garments and colors below — pricing updates live as you add quantities. Artwork and production details come next.', 'all-star-bulk-order' ),
                'products_title'  => __( 'Select garments and headwear', 'all-star-bulk-order' ),
                'products_text'   => __( 'Open a style, choose embroidery or patch, then split the quantity across available colors. Pricing tiers use the combined quantity for that style and decoration method.', 'all-star-bulk-order' ),
                'artwork_title'             => __( 'Artwork plan and production details', 'all-star-bulk-order' ),
                'artwork_text'              => __( 'Tell us how you’ll provide the logo and share the production details we should review. The actual artwork file is uploaded after checkout, once a secure WooCommerce order number exists.', 'all-star-bulk-order' ),
                'checkout_title'            => __( 'Review and continue to checkout', 'all-star-bulk-order' ),
                'checkout_text'             => __( 'The customer is sent to WooCommerce checkout, where shipping, taxes, payment, and order details are finalized.', 'all-star-bulk-order' ),
                'step_items'                => __( 'Items', 'all-star-bulk-order' ),
                'step_artwork'              => __( 'Artwork', 'all-star-bulk-order' ),
                'step_checkout'             => __( 'Checkout', 'all-star-bulk-order' ),
                'next_artwork_text'         => __( 'Next: Artwork Plan', 'all-star-bulk-order' ),
                'continue_checkout_text'    => __( 'Continue to Checkout', 'all-star-bulk-order' ),
                'back_text'                 => __( 'Back', 'all-star-bulk-order' ),
                'estimated_total_label'     => __( 'Estimated Total', 'all-star-bulk-order' ),
                'pieces_selected_label'     => __( 'Pieces Selected', 'all-star-bulk-order' ),
                'total_saved_label'         => __( 'Total Saved', 'all-star-bulk-order' ),
                'product_total_label'        => __( 'Selected total', 'all-star-bulk-order' ),
                'digitizing_incentive_text' => __( 'pieces · Digitizing + setup included', 'all-star-bulk-order' ),
                'shipping_incentive_text'   => __( 'pieces · Standard shipping included', 'all-star-bulk-order' ),
                'free_art_qty'              => 12,
                'free_ship_qty'             => 24,
                'digitizing_savings_amount' => 15,
                'shipping_savings_amount'   => 10,
                'primary_color'             => '#f3bd2f',
                'primary_hover_color'       => '#f7c94f',
                'primary_text_color'        => '#11192d',
                'navy_color'                => '#11192d',
                'text_color'                => '#182033',
                'muted_color'               => '#687184',
                'border_color'              => '#e2e6ed',
                'surface_color'             => '#f7f8fb',
                'page_background'           => '#ffffff',
                'sticky_background'         => '#ffffff',
                'sticky_text_color'         => '#182033',
                'progress_inactive_color'   => '#8a92a2',
                'button_radius'              => 9,
                'card_radius'                => 14,
            ),
            $atts,
            'asbo_bulk_order'
        );

        // Normalize legacy defaults to current store policy.
        if ( abs( (float) $atts['shipping_savings_amount'] - 9.99 ) < 0.001 ) {
            $atts['shipping_savings_amount'] = 10;
        }
        if ( 'pieces · Digitizing included' === trim( (string) $atts['digitizing_incentive_text'] ) ) {
            $atts['digitizing_incentive_text'] = 'pieces · Digitizing + setup included';
        }

        $config = array(
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonceUrl'      => add_query_arg( 'action', 'asbo_get_nonce', admin_url( 'admin-ajax.php' ) ),
            'checkoutUrl'   => wc_get_checkout_url(),
            'currency'      => get_woocommerce_currency(),
            'locale'        => str_replace( '_', '-', determine_locale() ),
            'freeArtQty'    => max( 1, absint( $atts['free_art_qty'] ) ),
            'freeShipQty'   => max( 1, absint( $atts['free_ship_qty'] ) ),
            'digitizingSavingsAmount' => max( 0, (float) wc_format_decimal( $atts['digitizing_savings_amount'] ) ),
            'shippingSavingsAmount'   => max( 0, (float) wc_format_decimal( $atts['shipping_savings_amount'] ) ),
            'buttonTexts'   => array(
                'nextArtwork'      => sanitize_text_field( $atts['next_artwork_text'] ),
                'continueCheckout' => sanitize_text_field( $atts['continue_checkout_text'] ),
            ),
            'storageKey'    => 'asbo_bulk_order_v2',
            'messages'      => array(
                'chooseItem' => __( 'Add at least one garment or hat before continuing.', 'all-star-bulk-order' ),
                'working'    => __( 'Building your production order…', 'all-star-bulk-order' ),
                'error'      => __( 'We could not prepare the embroidery order. Review the quantities and try again.', 'all-star-bulk-order' ),
                'sessionExpired' => __( 'Your secure order session expired. Please try again; your selections are still saved on this page.', 'all-star-bulk-order' ),
                'choosePreviousOrder' => __( 'Choose a previous order or enter a reference before continuing.', 'all-star-bulk-order' ),
                'confirmArtworkRights' => __( 'Confirm that you own the artwork or have permission to reproduce it before continuing.', 'all-star-bulk-order' ),
            ),
        );

        $query_vars = array(
            'status'  => 'publish',
            'limit'   => max( 1, min( 200, absint( $atts['limit'] ) ) ),
            'orderby' => 'menu_order',
            'order'   => 'ASC',
            'return'  => 'objects',
            'asbo_enabled' => true,
        );

        if ( '' !== trim( $atts['category'] ) ) {
            $query_vars['category'] = array( sanitize_title( $atts['category'] ) );
        }

        $query_filter = static function ( array $wp_query_args, array $product_query_vars ): array {
            if ( empty( $product_query_vars['asbo_enabled'] ) ) {
                return $wp_query_args;
            }

            if ( ! isset( $wp_query_args['meta_query'] ) || ! is_array( $wp_query_args['meta_query'] ) ) {
                $wp_query_args['meta_query'] = array();
            }

            $wp_query_args['meta_query'][] = array(
                'key'     => self::META_ENABLED,
                'value'   => array( 'yes', '1', 'on', 'true' ),
                'compare' => 'IN',
            );

            return $wp_query_args;
        };

        add_filter( 'woocommerce_product_data_store_cpt_get_products_query', $query_filter, 10, 2 );
        $products = wc_get_products( $query_vars );
        remove_filter( 'woocommerce_product_data_store_cpt_get_products_query', $query_filter, 10 );

        // If this ASBO block is already scoped to a WooCommerce category (for
        // example Headwear), expose only that category's represented direct
        // children as lightweight storefront filters. This keeps the customer
        // inside the intended product family instead of turning ASBO into a
        // second full-catalog WooCommerce browser.
        $filter_parent_term       = null;
        $filter_categories        = array();
        $product_filter_slugs_map = array();
        if ( '' !== trim( $atts['category'] ) ) {
            $candidate_parent = get_term_by( 'slug', sanitize_title( $atts['category'] ), 'product_cat' );
            if ( $candidate_parent instanceof WP_Term ) {
                $filter_parent_term = $candidate_parent;
                $children = get_terms( array(
                    'taxonomy'   => 'product_cat',
                    'parent'     => (int) $candidate_parent->term_id,
                    'hide_empty' => false,
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                ) );

                if ( ! is_wp_error( $children ) ) {
                    $children_by_id = array();
                    foreach ( $children as $child ) {
                        $children_by_id[ (int) $child->term_id ] = $child;
                    }

                    $represented_child_ids = array();
                    foreach ( $products as $product ) {
                        $product_id = $product->get_id();
                        $term_ids = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
                        if ( is_wp_error( $term_ids ) ) {
                            $product_filter_slugs_map[ $product_id ] = array();
                            continue;
                        }

                        $slugs = array();
                        foreach ( array_map( 'absint', $term_ids ) as $term_id ) {
                            $lineage = array_merge( array( $term_id ), array_map( 'absint', get_ancestors( $term_id, 'product_cat', 'taxonomy' ) ) );
                            foreach ( $lineage as $ancestor_id ) {
                                if ( isset( $children_by_id[ $ancestor_id ] ) ) {
                                    $slugs[] = sanitize_title( $children_by_id[ $ancestor_id ]->slug );
                                    $represented_child_ids[ $ancestor_id ] = true;
                                }
                            }
                        }
                        $product_filter_slugs_map[ $product_id ] = array_values( array_unique( $slugs ) );
                    }

                    foreach ( $children as $child ) {
                        if ( ! empty( $represented_child_ids[ (int) $child->term_id ] ) ) {
                            $filter_categories[] = $child;
                        }
                    }
                }
            }
        }

        $recent_customer_orders = self::recent_customer_orders();
        $my_account_url         = wc_get_page_permalink( 'myaccount' );

        $style_vars = sprintf(
            '--asbo-navy:%1$s;--asbo-gold:%2$s;--asbo-gold-hover:%3$s;--asbo-gold-text:%4$s;--asbo-ink:%5$s;--asbo-muted:%6$s;--asbo-border:%7$s;--asbo-surface:%8$s;--asbo-page-bg:%9$s;--asbo-sticky-bg:%10$s;--asbo-sticky-text:%11$s;--asbo-progress-inactive:%12$s;--asbo-button-radius:%13$dpx;--asbo-card-radius:%14$dpx;',
            sanitize_hex_color( $atts['navy_color'] ) ?: '#11192d',
            sanitize_hex_color( $atts['primary_color'] ) ?: '#f3bd2f',
            sanitize_hex_color( $atts['primary_hover_color'] ) ?: '#f7c94f',
            sanitize_hex_color( $atts['primary_text_color'] ) ?: '#11192d',
            sanitize_hex_color( $atts['text_color'] ) ?: '#182033',
            sanitize_hex_color( $atts['muted_color'] ) ?: '#687184',
            sanitize_hex_color( $atts['border_color'] ) ?: '#e2e6ed',
            sanitize_hex_color( $atts['surface_color'] ) ?: '#f7f8fb',
            sanitize_hex_color( $atts['page_background'] ) ?: '#ffffff',
            sanitize_hex_color( $atts['sticky_background'] ) ?: '#ffffff',
            sanitize_hex_color( $atts['sticky_text_color'] ) ?: '#182033',
            sanitize_hex_color( $atts['progress_inactive_color'] ) ?: '#8a92a2',
            max( 0, min( 40, absint( $atts['button_radius'] ) ) ),
            max( 0, min( 40, absint( $atts['card_radius'] ) ) )
        );

        $step_labels = array(
            1 => sanitize_text_field( $atts['step_items'] ),
            2 => sanitize_text_field( $atts['step_artwork'] ),
            3 => sanitize_text_field( $atts['step_checkout'] ),
        );

        ob_start();
        ?>
        <section class="asbo" data-asbo-root style="<?php echo esc_attr( $style_vars ); ?>">
            <script type="application/json" class="asbo__config"><?php echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
            <div class="asbo__welcome-toast" data-welcome-toast role="status" aria-live="polite" hidden>
                <button type="button" class="asbo__welcome-toast-close" data-welcome-close aria-label="<?php esc_attr_e( 'Dismiss', 'all-star-bulk-order' ); ?>">&times;</button>
                <strong><?php echo esc_html( $atts['welcome_title'] ); ?></strong>
                <p><?php echo esc_html( $atts['welcome_text'] ); ?></p>
            </div>
            <div class="asbo__shell">
                <header class="asbo__topbar">
                    <nav class="asbo__progress" aria-label="Order progress">
                        <?php foreach ( $step_labels as $step => $label ) : ?>
                            <div class="asbo__progress-step<?php echo 1 === $step ? ' is-active' : ''; ?>" data-progress-step="<?php echo esc_attr( $step ); ?>">
                                <span><?php echo esc_html( $step ); ?></span>
                                <strong><?php echo esc_html( $label ); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </nav>
                    <button type="button" class="asbo__button asbo__button--primary" data-next-step>
                        <?php echo esc_html( $atts['next_artwork_text'] ); ?>
                    </button>
                </header>

                <div class="asbo__notice" role="status" aria-live="polite" data-notice hidden></div>

                <div class="asbo__step is-active" data-step-panel="1">
                    <div class="asbo__section-heading">
                        <div>
                            <h2><?php echo esc_html( $atts['products_title'] ); ?></h2>
                        </div>
                        <p><?php echo esc_html( $atts['products_text'] ); ?></p>
                    </div>

                    <?php if ( $filter_parent_term instanceof WP_Term && ! empty( $filter_categories ) ) : ?>
                        <div class="asbo__subcategory-filter" data-subcategory-filter>
                            <div class="asbo__subcategory-filter-copy">
                                <strong><?php esc_html_e( 'Filter styles', 'all-star-bulk-order' ); ?></strong>
                                <small><?php echo esc_html( sprintf( __( 'Showing only %s subcategories.', 'all-star-bulk-order' ), $filter_parent_term->name ) ); ?></small>
                            </div>
                            <div class="asbo__subcategory-filter-options" role="group" aria-label="<?php echo esc_attr( sprintf( __( 'Filter %s products', 'all-star-bulk-order' ), $filter_parent_term->name ) ); ?>">
                                <button type="button" class="is-active" data-subcategory-filter-value="all" aria-pressed="true">
                                    <?php echo esc_html( sprintf( __( 'All %s', 'all-star-bulk-order' ), $filter_parent_term->name ) ); ?>
                                </button>
                                <?php foreach ( $filter_categories as $filter_category ) : ?>
                                    <button type="button" data-subcategory-filter-value="<?php echo esc_attr( $filter_category->slug ); ?>" aria-pressed="false">
                                        <?php echo esc_html( $filter_category->name ); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="asbo__products">
                        <?php if ( empty( $products ) ) : ?>
                            <div class="asbo__empty">
                                <h3><?php esc_html_e( 'No bulk-order products are enabled yet.', 'all-star-bulk-order' ); ?></h3>
                                <p><?php esc_html_e( 'Edit a WooCommerce product and enable “Show in bulk order page” in Product data → General.', 'all-star-bulk-order' ); ?></p>
                            </div>
                        <?php else : ?>
                            <?php foreach ( $products as $product ) : ?>
                                <?php self::render_product( $product, $atts, $product_filter_slugs_map[ $product->get_id() ] ?? array() ); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="asbo__step" data-step-panel="2" hidden>
                    <div class="asbo__section-heading">
                        <div>
                            <h2><?php echo esc_html( $atts['artwork_title'] ); ?></h2>
                        </div>
                        <p><?php echo esc_html( $atts['artwork_text'] ); ?></p>
                    </div>

                    <div class="asbo__artwork-layout">
                        <div class="asbo__artwork-card">
                            <div class="asbo__post-checkout-note">
                                <span class="asbo__post-checkout-badge"><?php esc_html_e( 'After checkout', 'all-star-bulk-order' ); ?></span>
                                <div>
                                    <strong><?php esc_html_e( 'Artwork files stay connected to the final WooCommerce order.', 'all-star-bulk-order' ); ?></strong>
                                    <p><?php esc_html_e( 'If you are uploading a new file, the secure uploader appears immediately after checkout and remains available from My Account → Orders.', 'all-star-bulk-order' ); ?></p>
                                </div>
                            </div>

                            <div class="asbo__stitch-allowance" role="note" aria-label="10K Stitch Allowance">
                                <span class="asbo__stitch-allowance-label"><?php esc_html_e( '10K Stitch Allowance', 'all-star-bulk-order' ); ?></span>
                                <p><?php esc_html_e( 'Standard embroidery pricing includes designs up to 10,000 stitches. Larger or more detailed artwork may exceed this allowance. If an additional embroidery charge is required, we will review your artwork and contact you before production.', 'all-star-bulk-order' ); ?></p>
                            </div>

                            <div class="asbo__artwork-guide">
                                <span class="asbo__artwork-guide-number">1</span>
                                <div>
                                    <strong><?php esc_html_e( 'Choose how we should handle your logo', 'all-star-bulk-order' ); ?></strong>
                                    <p><?php esc_html_e( 'Pick the path that best matches what you already have. You can add production details underneath.', 'all-star-bulk-order' ); ?></p>
                                </div>
                            </div>

                            <fieldset class="asbo__artwork-options">
                                <legend class="screen-reader-text"><?php esc_html_e( 'How will you provide artwork?', 'all-star-bulk-order' ); ?></legend>

                                <label class="asbo__artwork-option is-selected">
                                    <input type="radio" name="asbo-artwork-plan" value="upload_after_checkout" data-artwork-plan checked>
                                    <span class="asbo__artwork-option-icon" aria-hidden="true">↥</span>
                                    <span class="asbo__artwork-option-copy">
                                        <strong><?php esc_html_e( 'Upload a new file', 'all-star-bulk-order' ); ?></strong>
                                        <small><?php esc_html_e( 'Choose this if you already have a JPG, PNG, or PDF logo file.', 'all-star-bulk-order' ); ?></small>
                                    </span>
                                    <span class="asbo__artwork-option-check" aria-hidden="true">✓</span>
                                </label>

                                <label class="asbo__artwork-option">
                                    <input type="radio" name="asbo-artwork-plan" value="previous_order" data-artwork-plan>
                                    <span class="asbo__artwork-option-icon" aria-hidden="true">↺</span>
                                    <span class="asbo__artwork-option-copy">
                                        <strong><?php esc_html_e( 'Reuse approved artwork', 'all-star-bulk-order' ); ?></strong>
                                        <small><?php esc_html_e( 'Choose a previous order below and we will reference the artwork already associated with it.', 'all-star-bulk-order' ); ?></small>
                                    </span>
                                    <span class="asbo__artwork-option-check" aria-hidden="true">✓</span>
                                </label>

                                <label class="asbo__artwork-option">
                                    <input type="radio" name="asbo-artwork-plan" value="design_help" data-artwork-plan>
                                    <span class="asbo__artwork-option-icon" aria-hidden="true">✦</span>
                                    <span class="asbo__artwork-option-copy">
                                        <strong><?php esc_html_e( 'I need design or digitizing help', 'all-star-bulk-order' ); ?></strong>
                                        <small><?php esc_html_e( 'Choose this if your logo needs cleanup, digitizing, stitch-count guidance, or help getting production-ready.', 'all-star-bulk-order' ); ?></small>
                                    </span>
                                    <span class="asbo__artwork-option-check" aria-hidden="true">✓</span>
                                </label>
                            </fieldset>

                            <div class="asbo__previous-order-box" data-previous-order-field hidden>
                                <?php if ( is_user_logged_in() && ! empty( $recent_customer_orders ) ) : ?>
                                    <label class="asbo__field">
                                        <span><?php esc_html_e( 'Choose one of your recent orders', 'all-star-bulk-order' ); ?></span>
                                        <select data-previous-order-select>
                                            <option value=""><?php esc_html_e( 'Select a previous order…', 'all-star-bulk-order' ); ?></option>
                                            <?php foreach ( $recent_customer_orders as $previous_order_choice ) : ?>
                                                <option value="order:<?php echo esc_attr( $previous_order_choice['id'] ); ?>">
                                                    <?php
                                                    echo esc_html(
                                                        sprintf(
                                                            '#%1$s · %2$s · %3$s',
                                                            $previous_order_choice['number'],
                                                            $previous_order_choice['date'],
                                                            $previous_order_choice['summary']
                                                        )
                                                    );
                                                    ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <details class="asbo__manual-order-reference">
                                        <summary><?php esc_html_e( 'Can’t find the order? Enter a reference instead', 'all-star-bulk-order' ); ?></summary>
                                        <label class="asbo__field">
                                            <span><?php esc_html_e( 'Order number or identifying details', 'all-star-bulk-order' ); ?></span>
                                            <input type="text" data-previous-order-manual placeholder="Example: Order #1842 or Newark High School navy hat order">
                                        </label>
                                    </details>
                                <?php else : ?>
                                    <label class="asbo__field">
                                        <span><?php esc_html_e( 'Previous order number or identifying details', 'all-star-bulk-order' ); ?></span>
                                        <input type="text" data-previous-order-manual placeholder="Example: Order #1842 or Newark High School navy hat order">
                                    </label>
                                    <?php if ( ! is_user_logged_in() && $my_account_url ) : ?>
                                        <p class="asbo__previous-order-login">
                                            <?php esc_html_e( 'Have an account?', 'all-star-bulk-order' ); ?>
                                            <a href="<?php echo esc_url( $my_account_url ); ?>"><?php esc_html_e( 'Sign in to choose from your past orders.', 'all-star-bulk-order' ); ?></a>
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <div class="asbo__artwork-guide asbo__artwork-guide--notes">
                                <span class="asbo__artwork-guide-number">2</span>
                                <div>
                                    <strong><?php esc_html_e( 'Tell us what production should look like', 'all-star-bulk-order' ); ?></strong>
                                    <p><?php esc_html_e( 'Placement, thread matching, garment notes, and deadline details help us prepare the proof correctly the first time.', 'all-star-bulk-order' ); ?></p>
                                </div>
                            </div>

                            <label class="asbo__field">
                                <span><?php esc_html_e( 'Production notes and required in-hands date', 'all-star-bulk-order' ); ?></span>
                                <textarea rows="5" data-artwork-notes placeholder="Example: Left-chest logo, match Pantone 186 C, navy polos, needed in hand by September 1."></textarea>
                            </label>


                            <div class="asbo__rights-confirmation" data-artwork-rights-wrap>
                                <label>
                                    <input type="checkbox" value="1" data-artwork-rights aria-describedby="asbo-artwork-rights-help">
                                    <span><strong><?php esc_html_e( 'Artwork-use authorization', 'all-star-bulk-order' ); ?></strong><br><?php echo esc_html( ASBO_Plugin::artwork_rights_statement() ); ?></span>
                                </label>
                                <small id="asbo-artwork-rights-help"><?php esc_html_e( 'Finding an image online does not by itself grant permission to reproduce it. If the artwork belongs to a school, team, business, artist, organization, or another person, make sure you are authorized to use it.', 'all-star-bulk-order' ); ?></small>
                            </div>

                            <p class="asbo__artwork-reminder">
                                <?php esc_html_e( 'We will confirm thread colors, stitch count, proof approval, and final turnaround before production begins.', 'all-star-bulk-order' ); ?>
                            </p>
                        </div>

                        <aside class="asbo__review-card">
                            <h3><?php esc_html_e( 'Production order review', 'all-star-bulk-order' ); ?></h3>
                            <div data-order-review></div>
                            <div class="asbo__review-total">
                                <span><?php esc_html_e( 'Estimated decorated-product total', 'all-star-bulk-order' ); ?></span>
                                <strong data-review-total>$0.00</strong>
                            </div>
                            <p class="asbo__fine-print"><?php esc_html_e( 'Standard embroidery pricing includes up to 10,000 stitches per design. Additional charges may apply for larger or more complex artwork and will be communicated prior to production. Specialty thread, multiple placements, rush production, shipping, and taxes may also affect the final total.', 'all-star-bulk-order' ); ?></p>
                        </aside>
                    </div>
                </div>
            </div>

            <footer class="asbo__sticky is-visible" data-sticky-summary aria-hidden="false">
                <div class="asbo__sticky-inner">
                    <div class="asbo__sticky-stat">
                        <span><?php echo esc_html( $atts['estimated_total_label'] ); ?></span>
                        <strong data-grand-total>$0.00</strong>
                    </div>
                    <div class="asbo__sticky-stat">
                        <span><?php echo esc_html( $atts['pieces_selected_label'] ); ?></span>
                        <strong data-total-items>0</strong>
                    </div>
                    <div class="asbo__sticky-stat asbo__sticky-stat--saved">
                        <span><?php echo esc_html( $atts['total_saved_label'] ); ?></span>
                        <strong data-total-saved>$0.00</strong>
                    </div>
                    <div class="asbo__incentives">
                        <span data-art-incentive><?php echo esc_html( max( 1, absint( $atts['free_art_qty'] ) ) ); ?>+ <?php echo esc_html( $atts['digitizing_incentive_text'] ); ?></span>
                        <span data-ship-incentive><?php echo esc_html( max( 1, absint( $atts['free_ship_qty'] ) ) ); ?>+ <?php echo esc_html( $atts['shipping_incentive_text'] ); ?></span>
                    </div>
                    <div class="asbo__sticky-actions">
                        <span class="asbo__idle-cue" data-idle-cue aria-hidden="true">↓</span>
                        <button type="button" class="asbo__button asbo__button--ghost" data-back-step hidden><?php echo esc_html( $atts['back_text'] ); ?></button>
                        <button type="button" class="asbo__button asbo__button--primary" data-sticky-next><?php echo esc_html( $atts['next_artwork_text'] ); ?></button>
                    </div>
                </div>
            </footer>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function render_product( WC_Product $product, array $atts = array(), array $product_filter_slugs = array() ): void {
        $display_name = self::display_name( $product );
        $matrix = self::parse_pricing_matrix( (string) $product->get_meta( self::META_PRICING ) );
        if ( empty( $matrix ) ) {
            return;
        }

        $has_embroidery = false;
        foreach ( array_keys( $matrix ) as $decoration_method ) {
            if ( false !== stripos( (string) $decoration_method, 'embro' ) ) {
                $has_embroidery = true;
                break;
            }
        }

        $description = (string) $product->get_meta( self::META_DESCRIPTION );
        if ( '' === trim( $description ) ) {
            $description = wp_strip_all_tags( $product->get_short_description() );
        }

        $size_chart = (string) $product->get_meta( self::META_SIZE_CHART );
        $image_id   = $product->get_image_id();
        $image_url  = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : wc_placeholder_img_src( 'large' );
        $thumb      = $image_id ? wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array( 'loading' => 'lazy' ) ) : wc_placeholder_img( 'woocommerce_thumbnail' );
        $variations = self::get_variation_options( $product );

        if ( empty( $variations ) ) {
            return;
        }

        $base_prices = array_values( array_filter( array_map(
            static function ( array $variation ) {
                return isset( $variation['base_price'] ) && null !== $variation['base_price'] ? (float) $variation['base_price'] : null;
            },
            $variations
        ), static function ( $price ) {
            return null !== $price;
        } ) );
        $base_min = $base_prices ? min( $base_prices ) : null;
        $base_max = $base_prices ? max( $base_prices ) : null;
        $base_price_display = null === $base_min
            ? '—'
            : ( abs( (float) $base_max - (float) $base_min ) < 0.00001
                ? wc_price( $base_min )
                : wc_price( $base_min ) . '<span class="asbo__price-range-separator">–</span>' . wc_price( $base_max ) );

        // Collapsed rows show the lowest customer-facing unit price available in
        // the approved ASBO matrix (or Woo Regular Price at 1+) so shoppers have
        // immediate price context before opening the accordion. Supplier cost
        // fields are intentionally never consulted here.
        $starting_price_candidates = $base_prices;
        foreach ( $matrix as $tiers ) {
            foreach ( $tiers as $threshold => $price ) {
                if ( (int) $threshold > 1 && is_numeric( $price ) ) {
                    $starting_price_candidates[] = (float) $price;
                }
            }
        }
        $starting_price = $starting_price_candidates ? min( $starting_price_candidates ) : null;

        $product_filter_slugs = array_values( array_unique( array_map( 'sanitize_title', $product_filter_slugs ) ) );

        $thresholds = array( 1 );
        foreach ( $matrix as $tiers ) {
            $thresholds = array_unique( array_merge( $thresholds, array_keys( $tiers ) ) );
        }
        sort( $thresholds, SORT_NUMERIC );
        ?>
        <article class="asbo__product" data-product data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" data-product-name="<?php echo esc_attr( $display_name ); ?>" data-subcategories="<?php echo esc_attr( implode( ' ', array_unique( $product_filter_slugs ) ) ); ?>">
            <div class="asbo__product-summary">
                <button
                    type="button"
                    class="asbo__product-trigger"
                    aria-expanded="false"
                    aria-label="<?php echo esc_attr( sprintf( __( 'Configure %s', 'all-star-bulk-order' ), $display_name ) ); ?>"
                ></button>
                <span class="asbo__product-thumb"><?php echo wp_kses_post( $thumb ); ?></span>
                <span class="asbo__product-title-wrap">
                    <span class="asbo__product-title-line">
                        <strong><?php echo esc_html( $display_name ); ?></strong>
                        <button
                            type="button"
                            class="asbo__details-chip"
                            data-product-details-open
                            aria-haspopup="dialog"
                            aria-controls="asbo-product-details-<?php echo esc_attr( $product->get_id() ); ?>"
                            aria-label="<?php echo esc_attr( sprintf( __( 'View details and sizing for %s', 'all-star-bulk-order' ), $display_name ) ); ?>"
                        ><?php esc_html_e( 'Details & sizing', 'all-star-bulk-order' ); ?></button>
                    </span>
                    <small class="asbo__product-meta">
                        <span data-product-quantity-label><?php esc_html_e( 'No pieces selected', 'all-star-bulk-order' ); ?></span>
                        <?php if ( null !== $starting_price ) : ?>
                            <span class="asbo__product-meta-separator" aria-hidden="true">•</span>
                            <span class="asbo__starting-price"><?php echo wp_kses_post( sprintf( __( 'Starting at %s', 'all-star-bulk-order' ), wc_price( $starting_price ) ) ); ?></span>
                        <?php endif; ?>
                    </small>
                </span>
                <span class="asbo__product-total-wrap">
                    <small><?php echo esc_html( $atts['product_total_label'] ); ?></small>
                    <strong class="asbo__product-subtotal" data-product-subtotal>$0.00</strong>
                </span>
                <span class="asbo__product-icon" aria-hidden="true">+</span>
            </div>

            <div class="asbo__product-panel" hidden>
                <script type="application/json" class="asbo__pricing-data"><?php echo wp_json_encode( $matrix ); ?></script>

                <div class="asbo__pricing-section">
                    <div class="asbo__section-title-row">
                        <div>
                            <h4><?php esc_html_e( 'Per-piece pricing by quantity', 'all-star-bulk-order' ); ?></h4>
                            <p><?php esc_html_e( 'Your decoration choice and combined color quantity determine the unit price.', 'all-star-bulk-order' ); ?></p>
                        </div>
                        <div class="asbo__active-tier" data-active-tier><?php esc_html_e( 'Enter quantities to calculate your pricing tier', 'all-star-bulk-order' ); ?></div>
                    </div>
                    <?php if ( $has_embroidery ) : ?>
                        <p class="asbo__stitch-policy-note">
                            <strong><?php esc_html_e( '10K stitch allowance:', 'all-star-bulk-order' ); ?></strong>
                            <?php esc_html_e( 'Includes embroidery up to 10,000 stitches. Additional charges may apply for larger or more complex designs.', 'all-star-bulk-order' ); ?>
                        </p>
                    <?php endif; ?>
                    <div class="asbo__table-scroll">
                        <table class="asbo__pricing-table">
                            <thead>
                                <tr>
                                    <th scope="col"><?php esc_html_e( 'Decoration method', 'all-star-bulk-order' ); ?></th>
                                    <?php foreach ( $thresholds as $threshold ) : ?>
                                        <th scope="col"><?php echo esc_html( $threshold ); ?>+</th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $matrix as $decoration => $tiers ) : ?>
                                    <tr data-pricing-row="<?php echo esc_attr( $decoration ); ?>">
                                        <th scope="row"><?php echo esc_html( $decoration ); ?></th>
                                        <?php foreach ( $thresholds as $threshold ) : ?>
                                            <td data-threshold="<?php echo esc_attr( $threshold ); ?>">
                                                <?php
                                                if ( 1 === (int) $threshold ) {
                                                    // 1+ is always the WooCommerce Regular Price.
                                                    echo wp_kses_post( $base_price_display );
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
                </div>

                <div class="asbo__decoration-section">
                    <h4><?php esc_html_e( '1. Choose the decoration method', 'all-star-bulk-order' ); ?></h4>
                    <div class="asbo__decoration-options">
                        <?php $first = true; ?>
                        <?php foreach ( array_keys( $matrix ) as $decoration ) : ?>
                            <label>
                                <input type="radio" name="asbo-decoration-<?php echo esc_attr( $product->get_id() ); ?>" value="<?php echo esc_attr( $decoration ); ?>" data-decoration <?php checked( $first ); ?>>
                                <span><?php echo esc_html( $decoration ); ?></span>
                            </label>
                            <?php $first = false; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="asbo__variants-section">
                    <div class="asbo__section-title-row">
                        <div>
                            <h4><?php esc_html_e( '2. Split quantities by color', 'all-star-bulk-order' ); ?></h4>
                            <p><?php esc_html_e( 'All colors of this style count together toward the same quantity break.', 'all-star-bulk-order' ); ?></p>
                        </div>
                    </div>

                    <div class="asbo__variant-grid">
                        <?php foreach ( $variations as $variation ) : ?>
                            <div class="asbo__variant" data-variation-id="<?php echo esc_attr( $variation['variation_id'] ); ?>">
                                <div class="asbo__variant-image">
                                    <img src="<?php echo esc_url( $variation['image'] ); ?>" alt="<?php echo esc_attr( $variation['label'] ); ?>" loading="lazy">
                                </div>
                                <strong><?php echo esc_html( $variation['label'] ); ?></strong>
                                <?php if ( ! $variation['purchasable'] ) : ?>
                                    <small><?php esc_html_e( 'Unavailable', 'all-star-bulk-order' ); ?></small>
                                <?php else : ?>
                                    <div class="asbo__quantity" aria-label="<?php echo esc_attr( sprintf( __( 'Quantity for %s', 'all-star-bulk-order' ), $variation['label'] ) ); ?>">
                                        <button type="button" data-qty-minus aria-label="<?php esc_attr_e( 'Decrease quantity', 'all-star-bulk-order' ); ?>">−</button>
                                        <input
                                            type="number"
                                            min="0"
                                            max="9999"
                                            step="1"
                                            value="0"
                                            inputmode="numeric"
                                            data-qty-input
                                            data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
                                            data-variation-id="<?php echo esc_attr( $variation['variation_id'] ); ?>"
                                            data-variation-label="<?php echo esc_attr( $variation['label'] ); ?>"
                                            data-base-price="<?php echo esc_attr( null !== $variation['base_price'] ? wc_format_decimal( $variation['base_price'], wc_get_price_decimals() ) : '' ); ?>"
                                        >
                                        <button type="button" data-qty-plus aria-label="<?php esc_attr_e( 'Increase quantity', 'all-star-bulk-order' ); ?>">+</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="asbo__product-modal" id="asbo-product-details-<?php echo esc_attr( $product->get_id() ); ?>" data-product-modal hidden>
                <button type="button" class="asbo__modal-backdrop" data-product-details-close aria-label="<?php esc_attr_e( 'Close product details', 'all-star-bulk-order' ); ?>"></button>
                <div class="asbo__modal-dialog" role="dialog" aria-modal="true" aria-labelledby="asbo-product-details-title-<?php echo esc_attr( $product->get_id() ); ?>">
                    <button type="button" class="asbo__modal-close" data-product-details-close aria-label="<?php esc_attr_e( 'Close product details', 'all-star-bulk-order' ); ?>">×</button>
                    <div class="asbo__modal-grid">
                        <div class="asbo__modal-image">
                            <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $display_name ); ?>" loading="lazy">
                        </div>
                        <div class="asbo__modal-copy">
                            <span class="asbo__modal-kicker"><?php esc_html_e( 'Product details', 'all-star-bulk-order' ); ?></span>
                            <h3 id="asbo-product-details-title-<?php echo esc_attr( $product->get_id() ); ?>"><?php echo esc_html( $display_name ); ?></h3>
                            <?php if ( '' !== trim( $description ) ) : ?>
                                <p><?php echo esc_html( $description ); ?></p>
                            <?php endif; ?>
                            <?php if ( '' !== trim( $size_chart ) ) : ?>
                                <div class="asbo__modal-specs">
                                    <h4><?php esc_html_e( 'Garment specifications and size information', 'all-star-bulk-order' ); ?></h4>
                                    <?php echo wp_kses_post( wpautop( $size_chart ) ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </article>
        <?php
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_variation_options( WC_Product $product ): array {
        $options = array();

        if ( $product->is_type( 'variable' ) ) {
            /** @var WC_Product_Variable $product */
            foreach ( $product->get_children() as $variation_id ) {
                $variation = wc_get_product( $variation_id );
                if ( ! $variation instanceof WC_Product_Variation ) {
                    continue;
                }

                $parts = array();
                foreach ( $variation->get_attributes() as $attribute_name => $attribute_value ) {
                    $taxonomy = str_replace( 'attribute_', '', $attribute_name );
                    $label    = wc_attribute_label( $taxonomy, $product );
                    $value    = $attribute_value;

                    if ( taxonomy_exists( $taxonomy ) ) {
                        $term = get_term_by( 'slug', $attribute_value, $taxonomy );
                        if ( $term && ! is_wp_error( $term ) ) {
                            $value = $term->name;
                        }
                    }

                    $parts[] = count( $variation->get_attributes() ) > 1 ? $label . ': ' . $value : $value;
                }

                $variation_image_id = $variation->get_image_id() ?: $product->get_image_id();
                $options[] = array(
                    'variation_id' => $variation->get_id(),
                    'label'        => $parts ? implode( ' / ', $parts ) : $variation->get_name(),
                    'image'        => $variation_image_id ? wp_get_attachment_image_url( $variation_image_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' ),
                    'purchasable'  => '' !== (string) $variation->get_regular_price( 'edit' ) && $variation->is_purchasable() && $variation->is_in_stock(),
                    'base_price'   => '' !== (string) $variation->get_regular_price( 'edit' ) ? (float) $variation->get_regular_price( 'edit' ) : null,
                );
            }
        } else {
            $image_id = $product->get_image_id();
            $options[] = array(
                'variation_id' => 0,
                'label'        => __( 'Standard', 'all-star-bulk-order' ),
                'image'        => $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' ),
                'purchasable'  => '' !== (string) $product->get_regular_price( 'edit' ) && $product->is_purchasable() && $product->is_in_stock(),
                'base_price'   => '' !== (string) $product->get_regular_price( 'edit' ) ? (float) $product->get_regular_price( 'edit' ) : null,
            );
        }

        return $options;
    }

    public static function ajax_add_bulk_to_cart(): void {
        if ( false === check_ajax_referer( 'asbo_bulk_order', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Your secure order session expired. Please try again.', 'all-star-bulk-order' ) ), 403 );
        }

        if ( ! WC()->cart ) {
            wc_load_cart();
        }

        $raw_selections = isset( $_POST['selections'] ) ? wp_unslash( $_POST['selections'] ) : '';
        $selections     = json_decode( $raw_selections, true );

        if ( ! is_array( $selections ) || empty( $selections ) ) {
            wp_send_json_error( array( 'message' => __( 'No products were selected.', 'all-star-bulk-order' ) ), 400 );
        }

        $rights_confirmed = isset( $_POST['rightsConfirmed'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['rightsConfirmed'] ) );
        if ( ! $rights_confirmed ) {
            wp_send_json_error( array( 'message' => __( 'Confirm that you own the artwork or have permission to reproduce it before continuing.', 'all-star-bulk-order' ) ), 409 );
        }

        $normalized = array();
        $validation_errors = array();

        foreach ( $selections as $selection ) {
            $product_id   = isset( $selection['productId'] ) ? absint( $selection['productId'] ) : 0;
            $variation_id = isset( $selection['variationId'] ) ? absint( $selection['variationId'] ) : 0;
            $quantity     = isset( $selection['quantity'] ) ? min( 9999, absint( $selection['quantity'] ) ) : 0;
            $decoration   = isset( $selection['decoration'] ) ? sanitize_text_field( $selection['decoration'] ) : '';

            if ( $product_id < 1 || $quantity < 1 || '' === $decoration ) {
                continue;
            }

            $product = wc_get_product( $product_id );
            if ( ! $product instanceof WC_Product || 'yes' !== $product->get_meta( self::META_ENABLED ) ) {
                $validation_errors[] = __( 'A selected product is no longer available for bulk ordering. Refresh the page and try again.', 'all-star-bulk-order' );
                continue;
            }

            $matrix = self::parse_pricing_matrix( (string) $product->get_meta( self::META_PRICING ) );
            if ( ! isset( $matrix[ $decoration ] ) ) {
                $validation_errors[] = sprintf(
                    __( '%s no longer has pricing for the selected decoration method.', 'all-star-bulk-order' ),
                    self::display_name( $product )
                );
                continue;
            }

            $sellable_product      = $product;
            $variation_attributes  = array();
            $variation_label       = '';

            if ( $variation_id > 0 ) {
                $variation = wc_get_product( $variation_id );
                if ( ! $variation instanceof WC_Product_Variation || $variation->get_parent_id() !== $product_id ) {
                    $validation_errors[] = sprintf(
                        __( '%s has a variation that changed after this page loaded. Refresh the page and select it again.', 'all-star-bulk-order' ),
                        self::display_name( $product )
                    );
                    continue;
                }

                if ( 'publish' !== $variation->get_status() ) {
                    $validation_errors[] = sprintf(
                        __( '%s — %s is no longer active.', 'all-star-bulk-order' ),
                        self::display_name( $product ),
                        wp_strip_all_tags( wc_get_formatted_variation( $variation, true, false, true ) )
                    );
                    continue;
                }

                // Supplier Sync is the source of truth for the Woo variation. Always
                // rebuild attributes server-side rather than trusting stale browser data.
                $variation_attributes = $variation->get_variation_attributes();
                $variation_label      = wp_strip_all_tags( wc_get_formatted_variation( $variation, true, false, true ) );
                $sellable_product     = $variation;
            } elseif ( $product->is_type( 'variable' ) ) {
                $validation_errors[] = sprintf(
                    __( '%s requires a specific color/size variation.', 'all-star-bulk-order' ),
                    self::display_name( $product )
                );
                continue;
            }

            if ( ! $sellable_product->is_in_stock() ) {
                $validation_errors[] = self::cart_validation_message( $product, $variation_label, __( 'is currently out of stock.', 'all-star-bulk-order' ) );
                continue;
            }

            if ( ! $sellable_product->backorders_allowed() && ! $sellable_product->has_enough_stock( $quantity ) ) {
                $available = $sellable_product->get_stock_quantity();
                $reason = null === $available
                    ? __( 'does not have enough stock for that quantity.', 'all-star-bulk-order' )
                    : sprintf( __( 'only has %d available.', 'all-star-bulk-order' ), max( 0, (int) $available ) );
                $validation_errors[] = self::cart_validation_message( $product, $variation_label, $reason );
                continue;
            }

            $base_price_raw = (string) $sellable_product->get_regular_price( 'edit' );
            $base_unit_price = '' !== $base_price_raw ? (float) $base_price_raw : null;

            $normalized[] = array(
                'product_id'           => $product_id,
                'variation_id'         => $variation_id,
                'quantity'             => $quantity,
                'decoration'           => $decoration,
                'variation_attributes' => $variation_attributes,
                'variation_label'      => $variation_label,
                'base_unit_price'      => $base_unit_price,
            );
        }

        if ( $validation_errors ) {
            wp_send_json_error( array( 'message' => implode( ' ', array_unique( $validation_errors ) ) ), 409 );
        }

        if ( empty( $normalized ) ) {
            wp_send_json_error( array( 'message' => __( 'The selected products could not be validated.', 'all-star-bulk-order' ) ), 400 );
        }

        $group_totals = array();
        foreach ( $normalized as $item ) {
            $group_key = $item['product_id'] . '|' . $item['decoration'];
            $group_totals[ $group_key ] = ( $group_totals[ $group_key ] ?? 0 ) + $item['quantity'];
        }

        // Resolve every ASBO price before mutating the cart. This keeps the cart
        // operation atomic if a matrix was edited while the customer had the page open.
        foreach ( $normalized as $index => $item ) {
            $product    = wc_get_product( $item['product_id'] );
            $matrix     = self::parse_pricing_matrix( (string) $product->get_meta( self::META_PRICING ) );
            $group_key  = $item['product_id'] . '|' . $item['decoration'];
            $unit_price = isset( $matrix[ $item['decoration'] ] )
                ? self::tier_price( $matrix[ $item['decoration'] ], $group_totals[ $group_key ] )
                : null;

            // Quantities below the first configured bulk break use the product's
            // WooCommerce Regular Price. Supplier Sync owns that storefront base price;
            // ASBO never derives it from supplier cost, MAP, MSRP, list price, or price_breaks.
            if ( null === $unit_price ) {
                $unit_price = $item['base_unit_price'];
            }

            if ( null === $unit_price ) {
                wp_send_json_error(
                    array(
                        'message' => self::cart_validation_message(
                            $product,
                            $item['variation_label'],
                            __( 'is missing its WooCommerce Regular Price. Run Supplier Sync → Quick Repair, then retry.', 'all-star-bulk-order' )
                        ),
                    ),
                    409
                );
            }

            $normalized[ $index ]['unit_price'] = (float) $unit_price;
        }

        $order_group = wp_generate_uuid4();
        $added       = array();

        // Supplier Sync guarantees a valid WooCommerce Regular Price. ASBO no longer
        // bypasses WooCommerce purchasability checks when that source-of-truth price is missing.

        foreach ( $normalized as $item ) {
            $product = wc_get_product( $item['product_id'] );
            if ( ! $product ) {
                self::rollback_added_cart_items( $added );
                wp_send_json_error( array( 'message' => __( 'A selected product changed during checkout preparation. Refresh and try again.', 'all-star-bulk-order' ) ), 409 );
            }

            $cart_item_data = array(
                'asbo' => array(
                    'parent_product_id' => $item['product_id'],
                    'decoration'        => $item['decoration'],
                    'order_group'       => $order_group,
                    'unit_price'        => $item['unit_price'],
                    'base_unit_price'   => $item['base_unit_price'],
                ),
                'asbo_unique' => md5( wp_json_encode( $item ) . microtime( true ) . wp_rand() ),
            );

            $errors_before = wc_get_notices( 'error' );

            $cart_key = WC()->cart->add_to_cart(
                $item['product_id'],
                $item['quantity'],
                $item['variation_id'],
                $item['variation_attributes'],
                $cart_item_data
            );

            if ( ! $cart_key ) {
                $errors_after = wc_get_notices( 'error' );
                $new_notices  = array_slice( $errors_after, count( $errors_before ) );
                $reason       = self::notice_text( $new_notices );

                self::rollback_added_cart_items( $added );

                if ( '' === $reason ) {
                    $reason = __( 'WooCommerce rejected this variation. Run Supplier Sync → Quick Repair for this product, then retry.', 'all-star-bulk-order' );
                }

                wp_send_json_error(
                    array(
                        'message' => self::cart_validation_message( $product, $item['variation_label'], $reason ),
                    ),
                    409
                );
            }

            $added[] = $cart_key;
        }

        if ( ! WC()->session ) {
            self::rollback_added_cart_items( $added );
            wp_send_json_error( array( 'message' => __( 'Your WooCommerce session could not be started. Refresh the page and try again.', 'all-star-bulk-order' ) ), 409 );
        }

        foreach ( WC()->cart->get_cart() as $existing_key => $existing_item ) {
            if ( in_array( $existing_key, $added, true ) ) {
                continue;
            }
            if ( ! empty( $existing_item['asbo']['order_group'] ) && $order_group !== (string) $existing_item['asbo']['order_group'] ) {
                WC()->cart->remove_cart_item( $existing_key );
            }
        }

        $notes = isset( $_POST['artworkNotes'] )
            ? sanitize_textarea_field( wp_unslash( $_POST['artworkNotes'] ) )
            : '';

        $artwork_plan = isset( $_POST['artworkPlan'] )
            ? sanitize_key( wp_unslash( $_POST['artworkPlan'] ) )
            : 'upload_after_checkout';

        $allowed_plans = array( 'upload_after_checkout', 'previous_order', 'design_help' );
        if ( ! in_array( $artwork_plan, $allowed_plans, true ) ) {
            $artwork_plan = 'upload_after_checkout';
        }

        $previous_order_raw = isset( $_POST['previousOrder'] )
            ? (string) wp_unslash( $_POST['previousOrder'] )
            : '';
        $previous_order_data = self::normalize_previous_order_reference( $previous_order_raw );

        WC()->session->set(
            self::SESSION_PROJECT_DETAILS,
            array(
                'artwork_plan'      => $artwork_plan,
                'previous_order'    => $previous_order_data['reference'],
                'previous_order_id' => $previous_order_data['order_id'],
                'notes'             => $notes,
                'rights_confirmed'  => true,
                'rights_version'    => self::artwork_rights_version(),
                'rights_statement'  => self::artwork_rights_statement(),
                'rights_accepted_at'=> gmdate( 'c' ),
                'rights_user_id'    => get_current_user_id(),
                'rights_source'     => 'builder',
            )
        );

        WC()->cart->calculate_totals();

        wp_send_json_success(
            array(
                'checkoutUrl' => wc_get_checkout_url(),
                'cartCount'   => WC()->cart->get_cart_contents_count(),
            )
        );
    }

    private static function cart_validation_message( WC_Product $product, string $variation_label, string $reason ): string {
        $name = self::display_name( $product );
        if ( '' !== trim( $variation_label ) ) {
            $name .= ' — ' . trim( $variation_label );
        }
        return trim( $name . ' ' . wp_strip_all_tags( $reason ) );
    }

    private static function rollback_added_cart_items( array $cart_keys ): void {
        if ( ! WC()->cart ) {
            return;
        }
        foreach ( $cart_keys as $cart_key ) {
            WC()->cart->remove_cart_item( $cart_key );
        }
        WC()->cart->calculate_totals();
    }

    private static function notice_text( array $notices ): string {
        $messages = array();
        foreach ( $notices as $notice ) {
            if ( is_array( $notice ) && isset( $notice['notice'] ) ) {
                $messages[] = wp_strip_all_tags( (string) $notice['notice'] );
            } elseif ( is_string( $notice ) ) {
                $messages[] = wp_strip_all_tags( $notice );
            }
        }
        return trim( implode( ' ', array_filter( $messages ) ) );
    }

    public static function apply_cart_tier_prices( WC_Cart $cart ): void {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        $groups = array();

        foreach ( $cart->get_cart() as $cart_key => $cart_item ) {
            if ( empty( $cart_item['asbo']['parent_product_id'] ) || empty( $cart_item['asbo']['decoration'] ) ) {
                continue;
            }

            $parent_id  = absint( $cart_item['asbo']['parent_product_id'] );
            $decoration = sanitize_text_field( $cart_item['asbo']['decoration'] );
            $group_key  = $parent_id . '|' . $decoration;

            if ( ! isset( $groups[ $group_key ] ) ) {
                $groups[ $group_key ] = array(
                    'parent_id'  => $parent_id,
                    'decoration' => $decoration,
                    'quantity'   => 0,
                    'cart_keys'  => array(),
                );
            }

            $groups[ $group_key ]['quantity'] += absint( $cart_item['quantity'] );
            $groups[ $group_key ]['cart_keys'][] = $cart_key;
        }

        foreach ( $groups as $group ) {
            $parent = wc_get_product( $group['parent_id'] );
            if ( ! $parent ) {
                continue;
            }

            $matrix = self::parse_pricing_matrix( (string) $parent->get_meta( self::META_PRICING ) );
            if ( empty( $matrix[ $group['decoration'] ] ) ) {
                continue;
            }

            $price = self::tier_price( $matrix[ $group['decoration'] ], $group['quantity'] );

            foreach ( $group['cart_keys'] as $cart_key ) {
                if ( ! isset( $cart->cart_contents[ $cart_key ]['data'] ) ) {
                    continue;
                }

                if ( null !== $price ) {
                    $cart->cart_contents[ $cart_key ]['data']->set_price( $price );
                    continue;
                }

                // Below the first bulk threshold, preserve each variation's authoritative
                // WooCommerce Regular Price rather than forcing a matrix 1+ value.
                if ( isset( $cart->cart_contents[ $cart_key ]['asbo']['base_unit_price'] )
                    && null !== $cart->cart_contents[ $cart_key ]['asbo']['base_unit_price'] ) {
                    $cart->cart_contents[ $cart_key ]['data']->set_price( (float) $cart->cart_contents[ $cart_key ]['asbo']['base_unit_price'] );
                }
            }
        }
    }

    public static function display_cart_item_data( array $item_data, array $cart_item ): array {
        if ( ! empty( $cart_item['asbo']['decoration'] ) ) {
            $item_data[] = array(
                'key'   => __( 'Decoration', 'all-star-bulk-order' ),
                'value' => sanitize_text_field( $cart_item['asbo']['decoration'] ),
            );
        }

        return $item_data;
    }

    public static function copy_line_item_meta( WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order ): void {
        if ( ! empty( $values['asbo']['decoration'] ) ) {
            $item->add_meta_data( __( 'Decoration', 'all-star-bulk-order' ), sanitize_text_field( $values['asbo']['decoration'] ), true );
        }
    }

    public static function copy_project_details_to_order( WC_Order $order, array $data = array() ): void {
        $details = WC()->session ? WC()->session->get( self::SESSION_PROJECT_DETAILS ) : null;
        if ( ! is_array( $details ) ) {
            return;
        }

        $artwork_plan = sanitize_key( $details['artwork_plan'] ?? 'upload_after_checkout' );
        $plan_labels  = array(
            'upload_after_checkout' => 'Customer will upload artwork after checkout.',
            'previous_order'        => 'Customer wants to reuse artwork from a previous order.',
            'design_help'           => 'Customer requested artwork or digitizing assistance.',
        );

        if ( ! isset( $plan_labels[ $artwork_plan ] ) ) {
            $artwork_plan = 'upload_after_checkout';
        }

        $order->update_meta_data( '_asbo_artwork_plan', $artwork_plan );
        $order->update_meta_data( '_ase_artwork_status', 'needed' );
        $order->add_order_note( 'Artwork plan: ' . $plan_labels[ $artwork_plan ] );

        if ( ! empty( $details['rights_confirmed'] ) ) {
            $order->update_meta_data( self::META_RIGHTS_CONFIRMED, 'yes' );
            $order->update_meta_data( self::META_RIGHTS_VERSION, sanitize_text_field( $details['rights_version'] ?? self::artwork_rights_version() ) );
            $order->update_meta_data( self::META_RIGHTS_ACCEPTED_AT, sanitize_text_field( $details['rights_accepted_at'] ?? gmdate( 'c' ) ) );
            $order->update_meta_data( self::META_RIGHTS_USER_ID, absint( $details['rights_user_id'] ?? 0 ) );
            $order->update_meta_data( self::META_RIGHTS_STATEMENT, sanitize_textarea_field( $details['rights_statement'] ?? self::artwork_rights_statement() ) );
            $order->update_meta_data( self::META_RIGHTS_SOURCE, sanitize_key( $details['rights_source'] ?? 'builder' ) );
            $order->add_order_note( 'Customer confirmed artwork-use authorization (' . sanitize_text_field( $details['rights_version'] ?? self::artwork_rights_version() ) . ').' );
        }

        if ( ! empty( $details['previous_order'] ) ) {
            $previous_order = sanitize_text_field( $details['previous_order'] );
            $order->update_meta_data( '_asbo_previous_order_reference', $previous_order );
            if ( ! empty( $details['previous_order_id'] ) ) {
                $order->update_meta_data( '_asbo_previous_order_id', absint( $details['previous_order_id'] ) );
            }
            $order->add_order_note( 'Previous artwork reference: ' . $previous_order );
        }

        if ( ! empty( $details['notes'] ) ) {
            $notes = sanitize_textarea_field( $details['notes'] );
            $order->update_meta_data( '_asbo_artwork_notes', $notes );
            $order->add_order_note( 'Production and artwork notes: ' . $notes );
        }
    }

    public static function clear_project_details_session(): void {
        if ( WC()->session ) {
            WC()->session->__unset( self::SESSION_PROJECT_DETAILS );
        }
    }


}

require_once __DIR__ . '/includes/class-asbo-artwork-review.php';
require_once __DIR__ . '/includes/class-asbo-account-experience.php';

ASBO_Plugin::boot();
ASBO_Artwork_Review::boot();
ASBO_Account_Experience::boot();
