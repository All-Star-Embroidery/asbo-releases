from pathlib import Path
import json
import re
import sys

root = Path(sys.argv[1]).resolve()
main = root / "all-star-bulk-order-block.php"
account = root / "includes/class-asbo-account-experience.php"
artwork = root / "includes/class-asbo-artwork-review.php"
block_json = root / "block/block.json"
editor_js = root / "block/editor.js"
readme = root / "README.txt"
assets = root / "assets"
assets.mkdir(exist_ok=True)


def read(path):
    return path.read_text(encoding="utf-8")


def write(path, text):
    path.write_text(text, encoding="utf-8")


def replace_once(text, old, new, label):
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected 1 occurrence, found {count}")
    return text.replace(old, new, 1)


s = read(main)

# Extract the large front-end assets first. They remain functionally identical except
# for the targeted JS fixes below, but become browser-cacheable files instead of HTML.
css_match = re.search(r"\n    private static function inline_css\(\): string \{\n        return <<<'CSS'\n(.*?)\nCSS;\n    \}\n", s, re.S)
if not css_match:
    raise SystemExit("frontend CSS heredoc not found")
frontend_css = css_match.group(1).rstrip() + "\n"
s = s[:css_match.start()] + "\n" + s[css_match.end():]

js_match = re.search(r"\n    private static function inline_js\(\): string \{\n        return <<<'JS'\n(.*?)\nJS;\n    \}\n", s, re.S)
if not js_match:
    raise SystemExit("frontend JS heredoc not found")
frontend_js = js_match.group(1)
s = s[:js_match.start()] + "\n" + s[js_match.end():]

# Version + plugin metadata.
s = s.replace("1.3.1-rc.1", "1.3.1-rc.2")
s = replace_once(
    s,
    " * Update URI: https://github.com/All-Star-Embroidery/asbo-releases\n"
    " * Author: All Star Embroidery\n"
    " * Requires Plugins: woocommerce\n"
    " * Text Domain: all-star-bulk-order\n",
    " * Plugin URI: https://github.com/All-Star-Embroidery/asbo-releases\n"
    " * Update URI: https://github.com/All-Star-Embroidery/asbo-releases\n"
    " * Author: All Star Embroidery\n"
    " * Author URI: https://allstarembroidery-ltd.com/\n"
    " * Requires at least: 6.5\n"
    " * Requires PHP: 7.4\n"
    " * Requires Plugins: woocommerce\n"
    " * WC requires at least: 8.3\n"
    " * WC tested up to: 11.1\n"
    " * Text Domain: all-star-bulk-order\n"
    " * Domain Path: /languages\n",
    "plugin metadata",
)
s = replace_once(
    s,
    "    private const UPDATE_CACHE_TTL = 1800;\n",
    "    private const UPDATE_CACHE_TTL = 1800;\n"
    "    private const UPDATE_FAILURE_CACHE_TTL = 300;\n",
    "failure cache constant",
)

# Compatibility declaration is registered while plugins are still loading.
s = replace_once(
    s,
    "    public static function boot(): void {\n"
    "        if ( did_action( 'plugins_loaded' ) ) {\n",
    "    public static function boot(): void {\n"
    "        add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_woocommerce_compatibility' ) );\n\n"
    "        if ( did_action( 'plugins_loaded' ) ) {\n",
    "boot compatibility",
)

# Core hooks: admin-only migration, translation load, cacheable assets, fresh nonce,
# and Checkout Block/Store API order metadata support.
s = replace_once(
    s,
    "        add_shortcode( 'asbo_bulk_order', array( __CLASS__, 'shortcode' ) );\n"
    "        add_filter( 'block_categories_all', array( __CLASS__, 'register_block_category' ), 10, 2 );\n"
    "        add_action( 'init', array( __CLASS__, 'register_block' ) );\n"
    "        add_action( 'init', array( __CLASS__, 'repair_catalog_titles' ) );\n",
    "        add_shortcode( 'asbo_bulk_order', array( __CLASS__, 'shortcode' ) );\n"
    "        add_filter( 'block_categories_all', array( __CLASS__, 'register_block_category' ), 10, 2 );\n"
    "        add_action( 'init', array( __CLASS__, 'load_textdomain' ), 1 );\n"
    "        add_action( 'init', array( __CLASS__, 'register_block' ) );\n"
    "        add_action( 'admin_init', array( __CLASS__, 'repair_catalog_titles' ), 20 );\n"
    "        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_frontend_assets' ), 20 );\n",
    "main init hooks",
)
s = replace_once(
    s,
    "        add_action( 'wp_ajax_asbo_add_bulk_to_cart', array( __CLASS__, 'ajax_add_bulk_to_cart' ) );\n"
    "        add_action( 'wp_ajax_nopriv_asbo_add_bulk_to_cart', array( __CLASS__, 'ajax_add_bulk_to_cart' ) );\n",
    "        add_action( 'wp_ajax_asbo_get_nonce', array( __CLASS__, 'ajax_get_nonce' ) );\n"
    "        add_action( 'wp_ajax_nopriv_asbo_get_nonce', array( __CLASS__, 'ajax_get_nonce' ) );\n"
    "        add_action( 'wp_ajax_asbo_add_bulk_to_cart', array( __CLASS__, 'ajax_add_bulk_to_cart' ) );\n"
    "        add_action( 'wp_ajax_nopriv_asbo_add_bulk_to_cart', array( __CLASS__, 'ajax_add_bulk_to_cart' ) );\n",
    "nonce hooks",
)
s = replace_once(
    s,
    "        add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'copy_project_details_to_order' ), 10, 2 );\n"
    "        add_action( 'woocommerce_thankyou', array( __CLASS__, 'clear_project_details_session' ) );\n",
    "        add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'copy_project_details_to_order' ), 10, 2 );\n"
    "        add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'copy_project_details_to_order' ), 10, 1 );\n"
    "        add_action( 'woocommerce_thankyou', array( __CLASS__, 'clear_project_details_session' ) );\n",
    "store api order meta hook",
)

marker = """    private static function plugin_basename(): string {
        return plugin_basename( self::plugin_file() );
    }

"""
insert = """    private static function plugin_basename(): string {
        return plugin_basename( self::plugin_file() );
    }

    public static function declare_woocommerce_compatibility(): void {
        if ( ! class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
            return;
        }

        \\Automattic\\WooCommerce\\Utilities\\FeaturesUtil::declare_compatibility( 'custom_order_tables', self::plugin_file(), true );
        \\Automattic\\WooCommerce\\Utilities\\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', self::plugin_file(), true );
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

"""
s = replace_once(s, marker, insert, "utility methods")

# Negative-cache GitHub failures so a temporary outage cannot add repeated 8-second calls.
s = replace_once(
    s,
    "            if ( is_array( $cached ) ) {\n"
    "                return $cached;\n"
    "            }\n",
    "            if ( is_array( $cached ) ) {\n"
    "                if ( ! empty( $cached['_asbo_update_failed'] ) ) {\n"
    "                    return null;\n"
    "                }\n"
    "                return $cached;\n"
    "            }\n",
    "manifest cache read",
)
s = replace_once(
    s,
    "        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {\n"
    "            return null;\n"
    "        }\n\n"
    "        $manifest = json_decode( wp_remote_retrieve_body( $response ), true );\n"
    "        if ( ! is_array( $manifest ) || empty( $manifest['version'] ) || empty( $manifest['download_url'] ) ) {\n"
    "            return null;\n"
    "        }\n",
    "        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {\n"
    "            set_site_transient( self::UPDATE_CACHE_KEY, array( '_asbo_update_failed' => 1 ), self::UPDATE_FAILURE_CACHE_TTL );\n"
    "            return null;\n"
    "        }\n\n"
    "        $manifest = json_decode( wp_remote_retrieve_body( $response ), true );\n"
    "        if ( ! is_array( $manifest ) || empty( $manifest['version'] ) || empty( $manifest['download_url'] ) ) {\n"
    "            set_site_transient( self::UPDATE_CACHE_KEY, array( '_asbo_update_failed' => 1 ), self::UPDATE_FAILURE_CACHE_TTL );\n"
    "            return null;\n"
    "        }\n",
    "manifest negative cache",
)

# Preserve the existing auto-update default, but provide an opt-out/filter and avoid
# a TypeError if another callback returns a non-bool value.
s = replace_once(
    s,
    "    public static function enable_github_auto_update( bool $update, $item ): bool {\n"
    "        $plugin = is_object( $item ) && isset( $item->plugin ) ? (string) $item->plugin : '';\n"
    "        return self::plugin_basename() === $plugin ? true : $update;\n"
    "    }\n",
    "    public static function enable_github_auto_update( $update, $item ): bool {\n"
    "        $plugin = is_object( $item ) && isset( $item->plugin ) ? (string) $item->plugin : '';\n"
    "        if ( self::plugin_basename() !== $plugin ) {\n"
    "            return (bool) $update;\n"
    "        }\n"
    "        return (bool) apply_filters( 'asbo_enable_auto_updates', true, $item, $update );\n"
    "    }\n",
    "auto update filter",
)

# Explicit capability defense in the product-save callback.
s = replace_once(
    s,
    "    public static function save_product_fields( WC_Product $product ): void {\n"
    "        $enabled = isset( $_POST[ self::META_ENABLED ] ) ? 'yes' : 'no';\n",
    "    public static function save_product_fields( WC_Product $product ): void {\n"
    "        if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {\n"
    "            return;\n"
    "        }\n\n"
    "        $enabled = isset( $_POST[ self::META_ENABLED ] ) ? 'yes' : 'no';\n",
    "product capability",
)

# The old title repair becomes an admin-only, locked, non-destructive one-off migration.
s = replace_once(
    s,
    "        $catalog = array(\n",
    "        if ( get_transient( 'asbo_catalog_title_repair_lock' ) ) {\n"
    "            return;\n"
    "        }\n"
    "        set_transient( 'asbo_catalog_title_repair_lock', 1, 10 * MINUTE_IN_SECONDS );\n\n"
    "        $catalog = array(\n",
    "repair lock start",
)
old_loop = """        foreach ( $catalog as $sku => $correct_name ) {
            $product_id = wc_get_product_id_by_sku( $sku );
            if ( ! $product_id ) {
                continue;
            }

            $product = wc_get_product( $product_id );
            if ( ! $product instanceof WC_Product ) {
                continue;
            }

            $current_name = trim( (string) $product->get_name( 'edit' ) );
            if ( '' === $current_name || 'product' === strtolower( $current_name ) || $current_name !== $correct_name ) {
                $product->set_name( $correct_name );
            }

            $product->update_meta_data( self::META_DISPLAY_NAME, $correct_name );
            $product->save();
        }

        update_option( 'asbo_catalog_title_repair_version', $repair_version, false );
        wc_delete_product_transients();
"""
new_loop = """        try {
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
"""
s = replace_once(s, old_loop, new_loop, "repair loop")

# Always enqueue as a fallback even if the block/shortcode was injected dynamically.
s = replace_once(
    s,
    "    public static function shortcode( array $atts = array() ): string {\n"
    "        $atts = shortcode_atts(\n",
    "    public static function shortcode( array $atts = array() ): string {\n"
    "        self::enqueue_frontend_assets();\n\n"
    "        $atts = shortcode_atts(\n",
    "shortcode enqueue",
)

# Cached HTML no longer carries the security token. A fresh nonce comes from admin-ajax.
s = replace_once(
    s,
    "            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),\n"
    "            'nonce'         => wp_create_nonce( 'asbo_bulk_order' ),\n"
    "            'checkoutUrl'   => wc_get_checkout_url(),\n",
    "            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),\n"
    "            'nonceUrl'      => add_query_arg( 'action', 'asbo_get_nonce', admin_url( 'admin-ajax.php' ) ),\n"
    "            'checkoutUrl'   => wc_get_checkout_url(),\n",
    "config nonce",
)
s = replace_once(
    s,
    "            'currency'      => get_woocommerce_currency(),\n",
    "            'currency'      => get_woocommerce_currency(),\n"
    "            'locale'        => str_replace( '_', '-', determine_locale() ),\n",
    "locale config",
)
s = replace_once(
    s,
    "                'error'      => __( 'We could not prepare the embroidery order. Review the quantities and try again.', 'all-star-bulk-order' ),\n",
    "                'error'      => __( 'We could not prepare the embroidery order. Review the quantities and try again.', 'all-star-bulk-order' ),\n"
    "                'sessionExpired' => __( 'Your secure order session expired. Please try again; your selections are still saved on this page.', 'all-star-bulk-order' ),\n",
    "session error message",
)

# Resolve category ancestry once per product term, not repeatedly inside child x product loops.
old_filter = """        $filter_parent_term = null;
        $filter_categories  = array();
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
                    $product_term_map = array();
                    foreach ( $products as $product ) {
                        $term_ids = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
                        $product_term_map[ $product->get_id() ] = is_wp_error( $term_ids ) ? array() : array_map( 'absint', $term_ids );
                    }

                    foreach ( $children as $child ) {
                        $represented = false;
                        foreach ( $products as $product ) {
                            foreach ( $product_term_map[ $product->get_id() ] ?? array() as $product_term_id ) {
                                if ( (int) $product_term_id === (int) $child->term_id || term_is_ancestor_of( (int) $child->term_id, (int) $product_term_id, 'product_cat' ) ) {
                                    $represented = true;
                                    break 2;
                                }
                            }
                        }
                        if ( $represented ) {
                            $filter_categories[] = $child;
                        }
                    }
                }
            }
        }
"""
new_filter = """        $filter_parent_term       = null;
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
"""
s = replace_once(s, old_filter, new_filter, "subcategory query optimization")
s = replace_once(
    s,
    "                                <?php self::render_product( $product, $atts, $filter_categories ); ?>\n",
    "                                <?php self::render_product( $product, $atts, $product_filter_slugs_map[ $product->get_id() ] ?? array() ); ?>\n",
    "render product call",
)
s = replace_once(
    s,
    "    private static function render_product( WC_Product $product, array $atts = array(), array $filter_categories = array() ): void {\n",
    "    private static function render_product( WC_Product $product, array $atts = array(), array $product_filter_slugs = array() ): void {\n",
    "render product signature",
)
old_product_filter = """        $product_filter_slugs = array();
        if ( ! empty( $filter_categories ) ) {
            $product_term_ids = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
            if ( ! is_wp_error( $product_term_ids ) ) {
                foreach ( $filter_categories as $filter_category ) {
                    foreach ( $product_term_ids as $product_term_id ) {
                        if ( (int) $product_term_id === (int) $filter_category->term_id || term_is_ancestor_of( (int) $filter_category->term_id, (int) $product_term_id, 'product_cat' ) ) {
                            $product_filter_slugs[] = sanitize_title( $filter_category->slug );
                            break;
                        }
                    }
                }
            }
        }
"""
s = replace_once(
    s,
    old_product_filter,
    "        $product_filter_slugs = array_values( array_unique( array_map( 'sanitize_title', $product_filter_slugs ) ) );\n",
    "remove repeated product terms",
)

# Per-root configuration lets multiple blocks coexist. Assets are loaded once by WordPress.
s = replace_once(
    s,
    "        <style id=\"asbo-inline-styles\"><?php echo self::inline_css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>\n"
    "        <section class=\"asbo\" data-asbo-root style=\"<?php echo esc_attr( $style_vars ); ?>\">\n",
    "        <section class=\"asbo\" data-asbo-root style=\"<?php echo esc_attr( $style_vars ); ?>\">\n"
    "            <script type=\"application/json\" class=\"asbo__config\"><?php echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>\n",
    "root config",
)
s = replace_once(
    s,
    "        </section>\n"
    "        <script>window.ASBO_CONFIG = <?php echo wp_json_encode( $config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;</script>\n"
    "        <script id=\"asbo-inline-script\"><?php echo self::inline_js(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>\n"
    "        <?php\n",
    "        </section>\n"
    "        <?php\n",
    "remove inline assets",
)

# Nonce failures now return a customer-readable JSON error instead of WordPress -1.
s = replace_once(
    s,
    "    public static function ajax_add_bulk_to_cart(): void {\n"
    "        check_ajax_referer( 'asbo_bulk_order', 'nonce' );\n\n",
    "    public static function ajax_add_bulk_to_cart(): void {\n"
    "        if ( false === check_ajax_referer( 'asbo_bulk_order', 'nonce', false ) ) {\n"
    "            wp_send_json_error( array( 'message' => __( 'Your secure order session expired. Please try again.', 'all-star-bulk-order' ) ), 403 );\n"
    "        }\n\n",
    "nonce verification",
)

# Back -> edit -> submit should replace, not duplicate, the existing ASBO group. Old
# ASBO lines remain untouched unless every replacement line was successfully added.
needle = """            $added[] = $cart_key;
        }

        $notes = isset( $_POST['artworkNotes'] )
"""
replacement = """            $added[] = $cart_key;
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
"""
s = replace_once(s, needle, replacement, "cart replacement")

# Checkout Block hook supplies only the WC_Order argument.
s = replace_once(
    s,
    "    public static function copy_project_details_to_order( WC_Order $order, array $data ): void {\n",
    "    public static function copy_project_details_to_order( WC_Order $order, array $data = array() ): void {\n",
    "store api callback signature",
)

# Front-end JS: root-local config, one init pass, no initial forced scroll, fresh nonce.
frontend_js = replace_once(
    frontend_js,
    """  const roots = document.querySelectorAll('[data-asbo-root]');
  if (!roots.length || typeof ASBO_CONFIG === 'undefined') return;

  const money = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: ASBO_CONFIG.currency || 'USD',
  });

  roots.forEach((root) => {
    let currentStep = 1;
""",
    """  const initAll = () => {
  const roots = document.querySelectorAll('[data-asbo-root]');
  if (!roots.length) return;

  roots.forEach((root) => {
    if (root.dataset.asboReady === '1') return;

    const configEl = root.querySelector('.asbo__config');
    let ASBO_CONFIG = {};
    try {
      ASBO_CONFIG = JSON.parse(configEl?.textContent || '{}');
    } catch (error) {
      console.error('Invalid ASBO configuration', error);
      return;
    }
    if (!ASBO_CONFIG.ajaxUrl || !ASBO_CONFIG.nonceUrl) return;
    root.dataset.asboReady = '1';

    const money = new Intl.NumberFormat(ASBO_CONFIG.locale || 'en-US', {
      style: 'currency',
      currency: ASBO_CONFIG.currency || 'USD',
    });

    let currentStep = 1;
""",
    "frontend JS init",
)
frontend_js = replace_once(frontend_js, "    const setStep = (step) => {\n", "    const setStep = (step, shouldScroll = true) => {\n", "setStep signature")
frontend_js = replace_once(
    frontend_js,
    "      updateUI();\n      root.scrollIntoView({ behavior: 'smooth', block: 'start' });\n    };\n",
    "      updateUI();\n      if (shouldScroll) root.scrollIntoView({ behavior: 'smooth', block: 'start' });\n    };\n",
    "conditional step scroll",
)
old_submit = """      setCheckoutProgress();

      const selections = summaries.flatMap((summary) => summary.lines);
      const form = new FormData();
      form.append('action', 'asbo_add_bulk_to_cart');
      form.append('nonce', ASBO_CONFIG.nonce);
      form.append('selections', JSON.stringify(selections));
      form.append('artworkNotes', artworkNotes?.value || '');
      form.append('artworkPlan', selectedArtworkPlan());
      form.append('previousOrder', previousOrderValue());
      form.append('rightsConfirmed', artworkRights?.checked ? '1' : '0');

      submitting = true;
      [topNext, stickyNext].forEach((button) => {
"""
new_submit = """      setCheckoutProgress();

      const selections = summaries.flatMap((summary) => summary.lines);

      submitting = true;
      [topNext, stickyNext].forEach((button) => {
"""
frontend_js = replace_once(frontend_js, old_submit, new_submit, "submit preamble")
frontend_js = replace_once(
    frontend_js,
    """      try {
        const response = await fetch(ASBO_CONFIG.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          body: form,
        });
        const payload = await response.json();

        if (!response.ok || !payload.success) {
          throw new Error(payload?.data?.message || ASBO_CONFIG.messages.error);
        }
""",
    """      try {
        const nonceResponse = await fetch(ASBO_CONFIG.nonceUrl, {
          method: 'GET',
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { 'Accept': 'application/json' },
        });
        const noncePayload = await nonceResponse.json();
        if (!nonceResponse.ok || !noncePayload?.success || !noncePayload?.data?.nonce) {
          throw new Error(ASBO_CONFIG.messages.sessionExpired);
        }

        const form = new FormData();
        form.append('action', 'asbo_add_bulk_to_cart');
        form.append('nonce', noncePayload.data.nonce);
        form.append('selections', JSON.stringify(selections));
        form.append('artworkNotes', artworkNotes?.value || '');
        form.append('artworkPlan', selectedArtworkPlan());
        form.append('previousOrder', previousOrderValue());
        form.append('rightsConfirmed', artworkRights?.checked ? '1' : '0');

        const response = await fetch(ASBO_CONFIG.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          cache: 'no-store',
          body: form,
        });
        const payload = await response.json();

        if (!response.ok || !payload.success) {
          const message = response.status === 403
            ? ASBO_CONFIG.messages.sessionExpired
            : (payload?.data?.message || ASBO_CONFIG.messages.error);
          throw new Error(message);
        }
""",
    "fresh nonce fetch",
)
frontend_js = replace_once(frontend_js, "        setStep(2);\n      }\n    };\n", "        setStep(2, false);\n      }\n    };\n", "submit error no-scroll")
frontend_js = replace_once(
    frontend_js,
    "    setStep(1);\n  });\n\n  function escapeHtml(value) {\n",
    "    setStep(1, false);\n  });\n  };\n\n  if (document.readyState === 'loading') {\n    document.addEventListener('DOMContentLoaded', initAll, { once: true });\n  } else {\n    initAll();\n  }\n\n  function escapeHtml(value) {\n",
    "init all footer",
)

write(assets / "asbo-frontend.css", frontend_css)
write(assets / "asbo-frontend.js", frontend_js.rstrip() + "\n")
write(main, s)

# My Account: the fallback URL must actually render Artwork, and the large CSS is
# moved to a cacheable file. Preserve the existing UI rather than redesigning it here.
a = read(account).replace("1.3.1-rc.1", "1.3.1-rc.2")
a = replace_once(
    a,
    "        // The normal WooCommerce dashboard callback would otherwise render underneath\n"
    "        // the fallback Artwork view. Remove only that default callback for this request.\n"
    "        remove_action( 'woocommerce_account_dashboard', 'woocommerce_account_dashboard', 10 );\n",
    "        // Query-string fallback routes through the dashboard endpoint. Swap the dashboard\n"
    "        // renderer for the Artwork hub so the fallback behaves like the pretty endpoint.\n"
    "        remove_action( 'woocommerce_account_dashboard', array( __CLASS__, 'render_dashboard' ), 5 );\n"
    "        remove_action( 'woocommerce_account_dashboard', 'woocommerce_account_dashboard', 10 );\n"
    "        add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'render_artwork_hub' ), 5 );\n",
    "artwork fallback renderer",
)
old_enqueue = """        wp_register_style( 'asbo-account-experience', false, array(), '1.3.0' );
        wp_enqueue_style( 'asbo-account-experience' );
        wp_add_inline_style( 'asbo-account-experience', self::css() );
"""
new_enqueue = """        $plugin_file = dirname( __DIR__ ) . '/all-star-bulk-order-block.php';
        wp_enqueue_style(
            'asbo-account-experience',
            plugins_url( 'assets/asbo-account.css', $plugin_file ),
            array(),
            '1.3.1-rc.2'
        );
"""
a = replace_once(a, old_enqueue, new_enqueue, "account css enqueue")
a = a.replace(
    "sprintf( __( 'Order #%d — %s', 'all-star-bulk-order' ), $attention_order->get_id(), $attention_title )",
    "sprintf( __( 'Order #%s — %s', 'all-star-bulk-order' ), $attention_order->get_order_number(), $attention_title )",
)
account_css_match = re.search(r"\n    private static function css\(\): string \{\n        return <<<'CSS'\n(.*?)\nCSS;\n    \}\n", a, re.S)
if not account_css_match:
    raise SystemExit("account CSS heredoc not found")
write(assets / "asbo-account.css", account_css_match.group(1).rstrip() + "\n")
a = a[:account_css_match.start()] + "\n" + a[account_css_match.end():]
write(account, a)

# Artwork uploads already avoid persisting public URLs. Fail closed if the protection
# files cannot be created on the current Apache/LiteSpeed-style host.
r = read(artwork)
r = replace_once(
    r,
    "        self::ensure_protected_artwork_directory();\n",
    "        if ( ! self::ensure_protected_artwork_directory() ) {\n"
    "            self::redirect_error( $return_url, __( 'Artwork storage could not be secured. Please contact us before uploading the file.', 'all-star-bulk-order' ) );\n"
    "        }\n",
    "secure artwork directory call",
)
old_secure = """    private static function ensure_protected_artwork_directory(): void {
        $uploads = wp_upload_dir();
        $base = trailingslashit( $uploads['basedir'] ) . 'all-star-artwork';
        if ( ! is_dir( $base ) ) {
            wp_mkdir_p( $base );
        }

        $htaccess = trailingslashit( $base ) . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            $rules = "Options -Indexes\\n<IfModule mod_authz_core.c>\\nRequire all denied\\n</IfModule>\\n<IfModule !mod_authz_core.c>\\nDeny from all\\n</IfModule>\\n";
            file_put_contents( $htaccess, $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }

        $index = trailingslashit( $base ) . 'index.php';
        if ( ! file_exists( $index ) ) {
            file_put_contents( $index, "<?php\\n// Silence is golden.\\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }
    }
"""
new_secure = """    private static function ensure_protected_artwork_directory(): bool {
        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            return false;
        }

        $base = trailingslashit( $uploads['basedir'] ) . 'all-star-artwork';
        if ( ! is_dir( $base ) && ! wp_mkdir_p( $base ) ) {
            return false;
        }

        $htaccess = trailingslashit( $base ) . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            $rules = "Options -Indexes\\n<IfModule mod_authz_core.c>\\nRequire all denied\\n</IfModule>\\n<IfModule !mod_authz_core.c>\\nDeny from all\\n</IfModule>\\n";
            if ( false === file_put_contents( $htaccess, $rules ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                return false;
            }
        }

        $index = trailingslashit( $base ) . 'index.php';
        if ( ! file_exists( $index ) && false === file_put_contents( $index, "<?php\\n// Silence is golden.\\n" ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            return false;
        }

        return is_readable( $htaccess );
    }
"""
r = replace_once(r, old_secure, new_secure, "secure artwork directory implementation")
r = replace_once(
    r,
    "    private static function admin_styles(): void {\n        ?>\n",
    "    private static function admin_styles(): void {\n"
    "        static $printed = false;\n"
    "        if ( $printed ) {\n"
    "            return;\n"
    "        }\n"
    "        $printed = true;\n"
    "        ?>\n",
    "admin style guard",
)
write(artwork, r)

# One block render path. WordPress receives an explicit dependency manifest for editor.js.
data = json.loads(read(block_json))
data["version"] = "1.3.1-rc.2"
data.pop("render", None)
write(block_json, json.dumps(data, indent=2) + "\n")
render_php = root / "block/render.php"
if render_php.exists():
    render_php.unlink()

e = read(editor_js)
e = replace_once(
    e,
    "    BaseControl,\n    __experimentalNumberControl: NumberControl\n  } = components;\n",
    "    BaseControl\n  } = components;\n"
    "  const NumberControl = components.NumberControl || components.__experimentalNumberControl || TextControl;\n",
    "number control fallback",
)
write(editor_js, e)
write(
    root / "block/editor.asset.php",
    "<?php\nreturn array(\n"
    "    'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ),\n"
    "    'version' => '1.3.1-rc.2',\n"
    ");\n",
)

# Directory indexes + future private-plugin translations path.
for folder in [root / "block", root / "includes", root / "assets", root / "languages"]:
    folder.mkdir(exist_ok=True)
    idx = folder / "index.php"
    if not idx.exists():
        write(idx, "<?php\n// Silence is golden.\n")

# Correct the stale repository pointer in bundled docs.
if readme.exists():
    t = read(readme)
    t = t.replace(
        "https://github.com/rolejarczyk/ASE.SupplierSync-Releases/tree/main/asbo",
        "https://github.com/All-Star-Embroidery/asbo-releases",
    )
    write(readme, t)

print("ASBO 1.3.1-rc.2 verified review patch applied")
