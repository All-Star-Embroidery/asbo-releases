<?php
/**
 * All Star My Account experience layered on top of WooCommerce.
 *
 * Keeps the native WooCommerce endpoints and functionality intact while
 * improving information architecture, artwork discoverability and styling.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ASBO_Account_Experience {
    private const ENDPOINT = 'artwork';
    private const ENDPOINT_VERSION_OPTION = 'asbo_account_endpoint_version';
    private const ENDPOINT_VERSION = '1.1.0';

    public static function boot(): void {
        if ( did_action( 'plugins_loaded' ) ) {
            self::init();
            return;
        }

        add_action( 'plugins_loaded', array( __CLASS__, 'init' ), 25 );
    }

    public static function init(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        add_action( 'init', array( __CLASS__, 'register_endpoint' ), 7 );
        add_action( 'init', array( __CLASS__, 'maybe_flush_endpoint_rules' ), 99 );
        add_action( 'wp_loaded', array( __CLASS__, 'prepare_artwork_fallback_view' ), 30 );
        add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu_items' ), 20 );
        add_filter( 'woocommerce_get_endpoint_url', array( __CLASS__, 'account_endpoint_url' ), 20, 4 );
        add_filter( 'woocommerce_account_menu_item_classes', array( __CLASS__, 'account_menu_item_classes' ), 20, 2 );
        add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render_artwork_hub' ) );
        add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( __CLASS__, 'artwork_endpoint_title' ), 10, 2 );

        add_filter( 'body_class', array( __CLASS__, 'body_classes' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ), 30 );
        remove_action( 'woocommerce_account_navigation', 'woocommerce_account_navigation', 10 );
        add_action( 'woocommerce_account_navigation', array( __CLASS__, 'render_account_sidebar' ), 10 );
        add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'render_dashboard' ), 5 );
        remove_action( 'woocommerce_account_dashboard', 'woocommerce_account_dashboard', 10 );
        add_action( 'woocommerce_view_order', array( __CLASS__, 'render_view_order_header' ), 1 );

        add_filter( 'woocommerce_my_account_my_orders_columns', array( __CLASS__, 'orders_columns' ), 20 );
        add_action( 'woocommerce_my_account_my_orders_column_asbo_artwork', array( __CLASS__, 'render_orders_artwork_column' ) );
        add_filter( 'woocommerce_my_account_my_orders_actions', array( __CLASS__, 'orders_actions' ), 20, 2 );
    }

    public static function register_endpoint(): void {
        add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
    }

    public static function maybe_flush_endpoint_rules(): void {
        if ( self::ENDPOINT_VERSION === get_option( self::ENDPOINT_VERSION_OPTION ) ) {
            return;
        }

        flush_rewrite_rules( false );
        update_option( self::ENDPOINT_VERSION_OPTION, self::ENDPOINT_VERSION, false );
    }

    private static function is_artwork_fallback_request(): bool {
        return is_user_logged_in()
            && isset( $_GET['asbo-view'] )
            && self::ENDPOINT === sanitize_key( wp_unslash( $_GET['asbo-view'] ) );
    }

    public static function prepare_artwork_fallback_view(): void {
        if ( ! self::is_artwork_fallback_request() ) {
            return;
        }

        // Query-string fallback routes through the dashboard endpoint. Swap the dashboard
        // renderer for the Artwork hub so the fallback behaves like the pretty endpoint.
        remove_action( 'woocommerce_account_dashboard', array( __CLASS__, 'render_dashboard' ), 5 );
        remove_action( 'woocommerce_account_dashboard', 'woocommerce_account_dashboard', 10 );
        add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'render_artwork_hub' ), 5 );
    }

    public static function account_endpoint_url( string $url, string $endpoint, $value, string $permalink ): string {
        if ( self::ENDPOINT !== $endpoint ) {
            return $url;
        }

        // Query-string fallback makes Artwork work even before permalink rewrite rules
        // have been refreshed by the host/cache layer. The pretty endpoint remains registered.
        return add_query_arg( 'asbo-view', self::ENDPOINT, wc_get_page_permalink( 'myaccount' ) );
    }

    public static function account_menu_item_classes( array $classes, string $endpoint ): array {
        if ( self::ENDPOINT === $endpoint && self::artwork_attention_count() > 0 ) {
            $classes[] = 'asbo-artwork-has-attention';
        }

        if ( self::is_artwork_fallback_request() ) {
            if ( self::ENDPOINT === $endpoint ) {
                $classes[] = 'is-active';
            } elseif ( 'dashboard' === $endpoint ) {
                $classes = array_values( array_diff( $classes, array( 'is-active' ) ) );
            }
        }

        return array_values( array_unique( $classes ) );
    }

    public static function account_menu_items( array $items ): array {
        if ( isset( $items[ self::ENDPOINT ] ) ) {
            return $items;
        }

        $result = array();
        foreach ( $items as $key => $label ) {
            $result[ $key ] = $label;
            if ( 'orders' === $key ) {
                $result[ self::ENDPOINT ] = __( 'Artwork', 'all-star-bulk-order' );
            }
        }

        if ( ! isset( $result[ self::ENDPOINT ] ) ) {
            $result[ self::ENDPOINT ] = __( 'Artwork', 'all-star-bulk-order' );
        }

        return $result;
    }

    public static function artwork_endpoint_title( string $title, string $endpoint ): string {
        return self::ENDPOINT === $endpoint ? __( 'Artwork', 'all-star-bulk-order' ) : $title;
    }

    public static function body_classes( array $classes ): array {
        if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
            return $classes;
        }

        $classes[] = 'asbo-account-page';
        if ( is_user_logged_in() ) {
            $classes[] = 'asbo-account-logged-in';
            $endpoint = function_exists( 'WC' ) && WC()->query ? (string) WC()->query->get_current_endpoint() : '';
            if ( self::is_artwork_fallback_request() ) {
                $endpoint = self::ENDPOINT;
            }
            $classes[] = $endpoint ? 'asbo-account-endpoint-' . sanitize_html_class( $endpoint ) : 'asbo-account-dashboard';
        } else {
            $classes[] = 'asbo-account-auth';
        }

        return $classes;
    }

    public static function enqueue_styles(): void {
        if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
            return;
        }

        $plugin_file = dirname( __DIR__ ) . '/all-star-bulk-order-block.php';
        wp_enqueue_style(
            'asbo-account-experience',
            plugins_url( 'assets/asbo-account.css', $plugin_file ),
            array(),
            '1.3.1-rc.2'
        );
    }

    public static function render_account_identity(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user = wp_get_current_user();
        $name = $user->display_name ?: $user->user_login;
        $email = sanitize_email( $user->user_email );
        $initials = self::initials( $name );
        ?>
        <div class="asbo-account-identity">
            <div class="asbo-account-avatar" aria-hidden="true"><?php echo esc_html( $initials ); ?></div>
            <div class="asbo-account-identity__copy">
                <span><?php esc_html_e( 'My Account', 'all-star-bulk-order' ); ?></span>
                <strong><?php echo esc_html( $name ); ?></strong>
                <?php if ( $email ) : ?><small><?php echo esc_html( $email ); ?></small><?php endif; ?>
            </div>
            <button type="button" class="asbo-account-menu-toggle" aria-expanded="false" aria-controls="asbo-account-navigation">
                <span><?php esc_html_e( 'Account menu', 'all-star-bulk-order' ); ?></span>
                <span class="asbo-account-menu-toggle__icon" aria-hidden="true"></span>
            </button>
        </div>
        <script>
        (function () {
            function initAsboAccountMenu() {
                var shell = document.querySelector('.asbo-account-page .woocommerce');
                if (!shell) return;
                var nav = shell.querySelector('.woocommerce-MyAccount-navigation');
                var toggle = shell.querySelector('.asbo-account-menu-toggle');
                if (!nav || !toggle || toggle.dataset.asboReady === '1') return;
                toggle.dataset.asboReady = '1';
                nav.id = 'asbo-account-navigation';

                function closeMenu() {
                    shell.classList.remove('asbo-account-menu-open');
                    toggle.setAttribute('aria-expanded', 'false');
                }

                toggle.addEventListener('click', function () {
                    var opening = !shell.classList.contains('asbo-account-menu-open');
                    shell.classList.toggle('asbo-account-menu-open', opening);
                    toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
                });

                nav.addEventListener('click', function (event) {
                    if (event.target.closest('a') && window.innerWidth < 768) closeMenu();
                });

                window.addEventListener('resize', function () {
                    if (window.innerWidth >= 768) closeMenu();
                }, { passive: true });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initAsboAccountMenu);
            } else {
                initAsboAccountMenu();
            }
        }());
        </script>
        <?php
    }

    public static function render_account_sidebar(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }
        ?>
        <aside class="asbo-account-sidebar" aria-label="<?php esc_attr_e( 'Account navigation', 'all-star-bulk-order' ); ?>">
            <?php self::render_account_identity(); ?>
            <?php wc_get_template( 'myaccount/navigation.php' ); ?>
        </aside>
        <?php
    }

    private static function customer_orders( int $limit = 50 ): array {
        static $cache = array();

        if ( ! is_user_logged_in() ) {
            return array();
        }

        $user_id = get_current_user_id();
        $limit = max( 1, min( 50, $limit ) );
        $cache_key = $user_id . '|' . $limit;
        if ( isset( $cache[ $cache_key ] ) ) {
            return $cache[ $cache_key ];
        }

        $cache[ $cache_key ] = wc_get_orders(
            array(
                'customer' => $user_id,
                'limit'    => $limit,
                'orderby'  => 'date',
                'order'    => 'DESC',
            )
        );
        return $cache[ $cache_key ];
    }

    public static function render_dashboard(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user = wp_get_current_user();
        $display_name = trim( (string) $user->first_name );
        if ( '' === $display_name ) {
            $display_name = trim( (string) $user->display_name );
        }
        if ( '' === $display_name ) {
            $display_name = trim( (string) $user->user_login );
        }

        $orders = array_slice( self::customer_orders( 50 ), 0, 12 );

        $active_order     = null;
        $attention_orders = array();
        $terminal_statuses = array( 'completed', 'cancelled', 'refunded', 'failed' );

        foreach ( $orders as $order ) {
            if ( ! $order instanceof WC_Order ) {
                continue;
            }

            if ( null === $active_order && ! in_array( $order->get_status(), $terminal_statuses, true ) ) {
                $active_order = $order;
            }

            if ( self::order_has_artwork_context( $order ) ) {
                $artwork_status = self::artwork_status( $order );
                if ( in_array( $artwork_status, array( 'needed', 'changes_requested' ), true ) ) {
                    $attention_orders[] = $order;
                }
            }
        }

        $recent_orders = array();
        $active_id = $active_order instanceof WC_Order ? $active_order->get_id() : 0;
        foreach ( $orders as $order ) {
            if ( ! $order instanceof WC_Order ) {
                continue;
            }
            if ( $active_id && $order->get_id() === $active_id ) {
                continue;
            }
            $recent_orders[] = $order;
            if ( count( $recent_orders ) >= 4 ) {
                break;
            }
        }

        if ( empty( $recent_orders ) && $active_order instanceof WC_Order ) {
            $recent_orders[] = $active_order;
        }

        $bulk_page = get_page_by_path( 'bulk-order' );
        $start_url = $bulk_page instanceof WP_Post ? get_permalink( $bulk_page ) : wc_get_page_permalink( 'shop' );
        ?>
        <div class="asbo-dashboard-project-hub">
            <script>
            (function () {
                var script = document.currentScript;
                var dashboard = script ? script.closest('.asbo-dashboard-project-hub') : null;
                if (!dashboard) return;
                var node = dashboard.previousElementSibling;
                while (node && node.tagName === 'P') {
                    node.hidden = true;
                    node = node.previousElementSibling;
                }
            }());
            </script>

            <header class="asbo-project-hub__welcome">
                <span class="asbo-account-kicker"><?php esc_html_e( 'All Star Embroidery', 'all-star-bulk-order' ); ?></span>
                <h2><?php echo esc_html( sprintf( __( 'Welcome back, %s.', 'all-star-bulk-order' ), $display_name ) ); ?></h2>
                <p><?php esc_html_e( 'Here’s what’s happening with your All Star projects.', 'all-star-bulk-order' ); ?></p>
            </header>

            <?php if ( ! empty( $attention_orders ) ) : ?>
                <section class="asbo-project-attention" aria-labelledby="asbo-project-attention-title">
                    <div class="asbo-project-attention__heading">
                        <span class="asbo-account-kicker"><?php esc_html_e( 'Needs your attention', 'all-star-bulk-order' ); ?></span>
                        <h3 id="asbo-project-attention-title"><?php esc_html_e( 'Artwork action required', 'all-star-bulk-order' ); ?></h3>
                    </div>
                    <div class="asbo-project-attention__items">
                        <?php foreach ( array_slice( $attention_orders, 0, 3 ) as $attention_order ) : ?>
                            <?php
                            $attention_status = self::artwork_status( $attention_order );
                            $attention_url = wc_get_endpoint_url( 'view-order', (string) $attention_order->get_id(), wc_get_page_permalink( 'myaccount' ) ) . '#asbo-artwork';
                            $attention_title = 'changes_requested' === $attention_status
                                ? __( 'Changes requested', 'all-star-bulk-order' )
                                : __( 'Artwork needed', 'all-star-bulk-order' );
                            $attention_copy = 'changes_requested' === $attention_status
                                ? __( 'We need a revision before this order can move forward.', 'all-star-bulk-order' )
                                : __( 'Please add artwork so this order can move forward.', 'all-star-bulk-order' );
                            ?>
                            <div class="asbo-project-attention__item asbo-project-attention__item--<?php echo esc_attr( $attention_status ); ?>">
                                <div>
                                    <strong><?php echo esc_html( sprintf( __( 'Order #%s — %s', 'all-star-bulk-order' ), $attention_order->get_order_number(), $attention_title ) ); ?></strong>
                                    <span><?php echo esc_html( $attention_copy ); ?></span>
                                </div>
                                <a href="<?php echo esc_url( $attention_url ); ?>"><?php esc_html_e( 'Review artwork', 'all-star-bulk-order' ); ?> <span aria-hidden="true">→</span></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ( $active_order instanceof WC_Order ) : ?>
                <?php
                $order_id = $active_order->get_id();
                $order_url = wc_get_endpoint_url( 'view-order', (string) $order_id, wc_get_page_permalink( 'myaccount' ) );
                $artwork_url = $order_url . '#asbo-artwork';
                $has_artwork = self::order_has_artwork_context( $active_order );
                $artwork_status = $has_artwork ? self::artwork_status( $active_order ) : '';
                $stage = 1;
                $stage_label = wc_get_order_status_name( $active_order->get_status() );
                $summary = __( 'Your order has been received and is in our workflow.', 'all-star-bulk-order' );
                $primary_url = $order_url;
                $primary_label = __( 'View order', 'all-star-bulk-order' );

                if ( $has_artwork ) {
                    if ( 'needed' === $artwork_status ) {
                        $stage = 2;
                        $stage_label = __( 'Artwork needed', 'all-star-bulk-order' );
                        $summary = __( 'Artwork is needed before this order can move forward.', 'all-star-bulk-order' );
                        $primary_url = $artwork_url;
                        $primary_label = __( 'Add artwork', 'all-star-bulk-order' );
                    } elseif ( 'changes_requested' === $artwork_status ) {
                        $stage = 2;
                        $stage_label = __( 'Changes requested', 'all-star-bulk-order' );
                        $summary = __( 'We requested an artwork revision before production.', 'all-star-bulk-order' );
                        $primary_url = $artwork_url;
                        $primary_label = __( 'Review artwork', 'all-star-bulk-order' );
                    } elseif ( 'awaiting_review' === $artwork_status ) {
                        $stage = 2;
                        $stage_label = __( 'Artwork in review', 'all-star-bulk-order' );
                        $summary = __( 'Your artwork is with our review team. No action is needed right now.', 'all-star-bulk-order' );
                    } elseif ( 'approved' === $artwork_status ) {
                        $stage = 3;
                        $stage_label = __( 'Artwork approved', 'all-star-bulk-order' );
                        $summary = __( 'Artwork is approved and this order is moving toward production and fulfillment.', 'all-star-bulk-order' );
                    }
                } elseif ( 'processing' === $active_order->get_status() ) {
                    $stage = 3;
                    $stage_label = __( 'Processing', 'all-star-bulk-order' );
                    $summary = __( 'Your order is being prepared for fulfillment.', 'all-star-bulk-order' );
                }

                $shipping_items = $active_order->get_items( 'shipping' );
                $fulfillment = __( 'Order fulfillment', 'all-star-bulk-order' );
                if ( ! empty( $shipping_items ) ) {
                    $first_shipping = reset( $shipping_items );
                    if ( $first_shipping instanceof WC_Order_Item_Shipping && $first_shipping->get_method_title() ) {
                        $fulfillment = $first_shipping->get_method_title();
                    }
                }

                $created = $active_order->get_date_created();
                $date_text = $created ? wc_format_datetime( $created, get_option( 'date_format' ) ) : '—';
                $item_count = (int) $active_order->get_item_count();
                $steps = array(
                    1 => __( 'Order received', 'all-star-bulk-order' ),
                    2 => __( 'Artwork', 'all-star-bulk-order' ),
                    3 => __( 'Production', 'all-star-bulk-order' ),
                    4 => __( 'Ready', 'all-star-bulk-order' ),
                );
                ?>
                <section class="asbo-current-project" aria-labelledby="asbo-current-project-title">
                    <div class="asbo-current-project__top">
                        <div>
                            <span class="asbo-account-kicker"><?php esc_html_e( 'Current project', 'all-star-bulk-order' ); ?></span>
                            <h3 id="asbo-current-project-title"><?php echo esc_html( sprintf( __( 'Order #%d', 'all-star-bulk-order' ), $order_id ) ); ?></h3>
                        </div>
                        <span class="asbo-current-project__state asbo-current-project__state--<?php echo esc_attr( $artwork_status ? $artwork_status : $active_order->get_status() ); ?>"><?php echo esc_html( $stage_label ); ?></span>
                    </div>

                    <p class="asbo-current-project__summary"><?php echo esc_html( $summary ); ?></p>

                    <div class="asbo-project-progress" aria-label="<?php esc_attr_e( 'Order progress', 'all-star-bulk-order' ); ?>">
                        <?php foreach ( $steps as $step_number => $step_name ) : ?>
                            <?php
                            $step_class = '';
                            if ( $step_number < $stage ) {
                                $step_class = ' is-complete';
                            } elseif ( $step_number === $stage ) {
                                $step_class = ' is-current';
                            }
                            ?>
                            <div class="asbo-project-progress__step<?php echo esc_attr( $step_class ); ?>">
                                <span class="asbo-project-progress__number"><?php echo esc_html( (string) $step_number ); ?></span>
                                <strong><?php echo esc_html( $step_name ); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <dl class="asbo-current-project__facts">
                        <div>
                            <dt><?php esc_html_e( 'Ordered', 'all-star-bulk-order' ); ?></dt>
                            <dd><?php echo esc_html( $date_text ); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Items', 'all-star-bulk-order' ); ?></dt>
                            <dd><?php echo esc_html( sprintf( _n( '%d item', '%d items', $item_count, 'all-star-bulk-order' ), $item_count ) ); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Fulfillment', 'all-star-bulk-order' ); ?></dt>
                            <dd><?php echo esc_html( $fulfillment ); ?></dd>
                        </div>
                    </dl>

                    <a class="asbo-current-project__action" href="<?php echo esc_url( $primary_url ); ?>"><?php echo esc_html( $primary_label ); ?> <span aria-hidden="true">→</span></a>
                </section>
            <?php endif; ?>

            <section class="asbo-project-orders" aria-labelledby="asbo-project-orders-title">
                <div class="asbo-project-section-heading">
                    <div>
                        <span class="asbo-account-kicker"><?php esc_html_e( 'Recent activity', 'all-star-bulk-order' ); ?></span>
                        <h3 id="asbo-project-orders-title"><?php esc_html_e( 'Recent orders', 'all-star-bulk-order' ); ?></h3>
                    </div>
                    <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>"><?php esc_html_e( 'View all', 'all-star-bulk-order' ); ?> <span aria-hidden="true">→</span></a>
                </div>

                <?php if ( ! empty( $recent_orders ) ) : ?>
                    <div class="asbo-project-order-list">
                        <?php foreach ( $recent_orders as $recent_order ) : ?>
                            <?php
                            if ( ! $recent_order instanceof WC_Order ) {
                                continue;
                            }
                            $recent_created = $recent_order->get_date_created();
                            $recent_date = $recent_created ? wc_format_datetime( $recent_created, get_option( 'date_format' ) ) : '—';
                            $recent_url = wc_get_endpoint_url( 'view-order', (string) $recent_order->get_id(), wc_get_page_permalink( 'myaccount' ) );
                            $recent_count = (int) $recent_order->get_item_count();
                            $recent_artwork = self::order_has_artwork_context( $recent_order ) ? self::artwork_status( $recent_order ) : '';
                            $artwork_labels = array(
                                'needed'            => __( 'Artwork needed', 'all-star-bulk-order' ),
                                'awaiting_review'   => __( 'Awaiting review', 'all-star-bulk-order' ),
                                'changes_requested' => __( 'Changes requested', 'all-star-bulk-order' ),
                                'approved'          => __( 'Approved', 'all-star-bulk-order' ),
                            );
                            ?>
                            <a class="asbo-project-order-row" href="<?php echo esc_url( $recent_url ); ?>">
                                <div class="asbo-project-order-row__order">
                                    <strong><?php echo esc_html( sprintf( __( 'Order #%d', 'all-star-bulk-order' ), $recent_order->get_id() ) ); ?></strong>
                                    <span><?php echo esc_html( $recent_date ); ?></span>
                                </div>
                                <span class="asbo-project-order-row__status"><?php echo esc_html( wc_get_order_status_name( $recent_order->get_status() ) ); ?></span>
                                <span class="asbo-project-order-row__items"><?php echo esc_html( sprintf( _n( '%d item', '%d items', $recent_count, 'all-star-bulk-order' ), $recent_count ) ); ?></span>
                                <span class="asbo-project-order-row__artwork<?php echo $recent_artwork ? ' asbo-project-order-row__artwork--' . esc_attr( $recent_artwork ) : ''; ?>">
                                    <?php echo $recent_artwork && isset( $artwork_labels[ $recent_artwork ] ) ? esc_html( $artwork_labels[ $recent_artwork ] ) : esc_html__( 'No artwork action', 'all-star-bulk-order' ); ?>
                                </span>
                                <span class="asbo-project-order-row__arrow" aria-hidden="true">→</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="asbo-project-orders__empty">
                        <p><?php esc_html_e( 'You do not have any orders yet.', 'all-star-bulk-order' ); ?></p>
                    </div>
                <?php endif; ?>
            </section>

            <section class="asbo-next-project">
                <div>
                    <span class="asbo-account-kicker"><?php esc_html_e( 'Next project', 'all-star-bulk-order' ); ?></span>
                    <h3><?php esc_html_e( 'Ready for another project?', 'all-star-bulk-order' ); ?></h3>
                    <p><?php esc_html_e( 'Start a new custom apparel or headwear order whenever you’re ready.', 'all-star-bulk-order' ); ?></p>
                </div>
                <a href="<?php echo esc_url( $start_url ); ?>"><?php esc_html_e( 'Start an order', 'all-star-bulk-order' ); ?> <span aria-hidden="true">→</span></a>
            </section>
        </div>
        <?php
    }

    private static function quick_card( string $title, string $value, string $description, string $url, string $index, bool $has_notice = false ): void {
        $classes = 'asbo-account-quick-card' . ( $has_notice ? ' asbo-account-quick-card--notice' : '' );
        ?>
        <a class="<?php echo esc_attr( $classes ); ?>" href="<?php echo esc_url( $url ); ?>">
            <span class="asbo-account-quick-card__index"><?php echo esc_html( $index ); ?></span>
            <strong><?php echo esc_html( $title ); ?></strong>
            <?php if ( $has_notice ) : ?><span class="asbo-account-quick-card__notice" aria-label="<?php esc_attr_e( 'Artwork activity', 'all-star-bulk-order' ); ?>">!</span><?php endif; ?>
            <b><?php echo esc_html( $value ); ?></b>
            <p><?php echo esc_html( $description ); ?></p>
            <span class="asbo-account-quick-card__arrow" aria-hidden="true">→</span>
        </a>
        <?php
    }

    private static function render_dashboard_order_card( WC_Order $order ): void {
        $date = $order->get_date_created();
        $artwork = self::artwork_status( $order );
        ?>
        <a class="asbo-account-order-card" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
            <div class="asbo-account-order-card__top">
                <strong><?php echo esc_html( sprintf( __( 'Order #%s', 'all-star-bulk-order' ), $order->get_order_number() ) ); ?></strong>
                <span class="asbo-account-order-status"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
            </div>
            <div class="asbo-account-order-card__meta">
                <span><?php echo $date ? esc_html( wc_format_datetime( $date ) ) : ''; ?></span>
                <span><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
            </div>
            <?php if ( self::order_has_artwork_context( $order ) ) : ?>
                <div class="asbo-account-order-card__artwork">
                    <span><?php esc_html_e( 'Artwork', 'all-star-bulk-order' ); ?></span>
                    <?php self::status_badge( $artwork ); ?>
                </div>
            <?php endif; ?>
        </a>
        <?php
    }

    public static function render_artwork_hub(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $orders = self::customer_orders( 50 );

        $artwork_orders = array_values(
            array_filter(
                $orders,
                static function ( $order ) {
                    return $order instanceof WC_Order && self::order_has_artwork_context( $order );
                }
            )
        );
        ?>
        <section class="asbo-artwork-hub">
            <div class="asbo-account-page-header">
                <div>
                    <span class="asbo-account-kicker"><?php esc_html_e( 'Project files', 'all-star-bulk-order' ); ?></span>
                    <h2><?php esc_html_e( 'Artwork', 'all-star-bulk-order' ); ?></h2>
                    <p><?php esc_html_e( 'See exactly where artwork stands for each order. Upload revisions, review requested changes, and confirm when artwork is approved for production.', 'all-star-bulk-order' ); ?></p>
                </div>
            </div>

            <div class="asbo-artwork-status-guide" aria-label="<?php esc_attr_e( 'Artwork status guide', 'all-star-bulk-order' ); ?>">
                <span><?php esc_html_e( 'Artwork Needed', 'all-star-bulk-order' ); ?></span>
                <span><?php esc_html_e( 'Awaiting Review', 'all-star-bulk-order' ); ?></span>
                <span><?php esc_html_e( 'Changes Requested', 'all-star-bulk-order' ); ?></span>
                <span><?php esc_html_e( 'Approved', 'all-star-bulk-order' ); ?></span>
            </div>

            <?php if ( ! $artwork_orders ) : ?>
                <div class="asbo-account-empty-state">
                    <span aria-hidden="true">✦</span>
                    <h3><?php esc_html_e( 'No artwork activity yet', 'all-star-bulk-order' ); ?></h3>
                    <p><?php esc_html_e( 'Orders that use the All Star artwork workflow will appear here automatically.', 'all-star-bulk-order' ); ?></p>
                    <a class="asbo-account-primary-link" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>"><?php esc_html_e( 'View your orders', 'all-star-bulk-order' ); ?> →</a>
                </div>
            <?php else : ?>
                <div class="asbo-artwork-hub__list">
                    <?php foreach ( $artwork_orders as $order ) : self::render_artwork_hub_card( $order ); endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    private static function render_artwork_hub_card( WC_Order $order ): void {
        $status = self::artwork_status( $order );
        $files = self::artwork_files( $order );
        $date = $order->get_date_created();
        $cta = 'changes_requested' === $status ? __( 'Upload revision', 'all-star-bulk-order' ) : ( 'needed' === $status ? __( 'Add artwork', 'all-star-bulk-order' ) : __( 'View artwork', 'all-star-bulk-order' ) );
        ?>
        <article class="asbo-artwork-hub-card asbo-artwork-hub-card--<?php echo esc_attr( $status ); ?>">
            <div class="asbo-artwork-hub-card__accent" aria-hidden="true"></div>
            <div class="asbo-artwork-hub-card__body">
                <div class="asbo-artwork-hub-card__heading">
                    <div>
                        <span><?php echo esc_html( sprintf( __( 'Order #%s', 'all-star-bulk-order' ), $order->get_order_number() ) ); ?></span>
                        <h3><?php echo esc_html( self::artwork_status_headline( $status ) ); ?></h3>
                    </div>
                    <?php self::status_badge( $status ); ?>
                </div>
                <p><?php echo esc_html( self::artwork_status_description( $status ) ); ?></p>
                <div class="asbo-artwork-hub-card__meta">
                    <span><b><?php esc_html_e( 'Order date', 'all-star-bulk-order' ); ?></b><?php echo $date ? esc_html( wc_format_datetime( $date ) ) : '—'; ?></span>
                    <span><b><?php esc_html_e( 'Files', 'all-star-bulk-order' ); ?></b><?php echo esc_html( (string) count( $files ) ); ?></span>
                    <span><b><?php esc_html_e( 'Order status', 'all-star-bulk-order' ); ?></b><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
                </div>
            </div>
            <a class="asbo-artwork-hub-card__action" href="<?php echo esc_url( $order->get_view_order_url() . '#asbo-artwork' ); ?>"><?php echo esc_html( $cta ); ?> <span aria-hidden="true">→</span></a>
        </article>
        <?php
    }

    public static function render_view_order_header( $order_id ): void {
        $order = wc_get_order( absint( $order_id ) );
        if ( ! $order instanceof WC_Order ) {
            return;
        }
        ?>
        <section class="asbo-view-order-overview">
            <div>
                <span class="asbo-account-kicker"><?php esc_html_e( 'Order overview', 'all-star-bulk-order' ); ?></span>
                <h2><?php echo esc_html( sprintf( __( 'Order #%s', 'all-star-bulk-order' ), $order->get_order_number() ) ); ?></h2>
            </div>
            <div class="asbo-view-order-overview__statuses">
                <span class="asbo-account-order-status"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
                <?php if ( self::order_has_artwork_context( $order ) ) : self::status_badge( self::artwork_status( $order ) ); endif; ?>
            </div>
        </section>
        <?php
    }

    public static function orders_columns( array $columns ): array {
        if ( isset( $columns['asbo_artwork'] ) ) {
            return $columns;
        }

        $result = array();
        foreach ( $columns as $key => $label ) {
            $result[ $key ] = $label;
            if ( 'order-status' === $key ) {
                $result['asbo_artwork'] = __( 'Artwork', 'all-star-bulk-order' );
            }
        }
        return $result;
    }

    public static function render_orders_artwork_column( WC_Order $order ): void {
        if ( ! self::order_has_artwork_context( $order ) ) {
            echo '<span class="asbo-artwork-order-none">—</span>';
            return;
        }
        self::status_badge( self::artwork_status( $order ) );
    }

    public static function orders_actions( array $actions, WC_Order $order ): array {
        if ( ! self::order_has_artwork_context( $order ) ) {
            return $actions;
        }

        $status = self::artwork_status( $order );
        $label = 'changes_requested' === $status ? __( 'Fix artwork', 'all-star-bulk-order' ) : ( 'needed' === $status ? __( 'Add artwork', 'all-star-bulk-order' ) : __( 'Artwork', 'all-star-bulk-order' ) );
        $actions['asbo-artwork'] = array(
            'url'  => $order->get_view_order_url() . '#asbo-artwork',
            'name' => $label,
        );
        return $actions;
    }

    private static function order_has_artwork_context( WC_Order $order ): bool {
        return (bool) $order->get_meta( '_asbo_artwork_plan', true )
            || (bool) $order->get_meta( '_ase_artwork_status', true )
            || ! empty( self::artwork_files( $order ) );
    }

    private static function artwork_files( WC_Order $order ): array {
        $files = $order->get_meta( '_ase_order_artwork_files', true );
        return is_array( $files ) ? $files : array();
    }

    private static function artwork_status( WC_Order $order ): string {
        if ( class_exists( 'ASBO_Artwork_Review' ) && method_exists( 'ASBO_Artwork_Review', 'get_order_artwork_status' ) ) {
            return ASBO_Artwork_Review::get_order_artwork_status( $order );
        }

        $status = sanitize_key( (string) $order->get_meta( '_ase_artwork_status', true ) );
        if ( 'received' === $status ) {
            return 'awaiting_review';
        }
        if ( in_array( $status, array( 'awaiting_review', 'changes_requested', 'approved' ), true ) ) {
            return $status;
        }
        return self::artwork_files( $order ) ? 'awaiting_review' : 'needed';
    }

    private static function artwork_attention_count(): int {
        if ( ! is_user_logged_in() ) {
            return 0;
        }

        $orders = self::customer_orders( 50 );

        $count = 0;
        foreach ( $orders as $order ) {
            if ( ! $order instanceof WC_Order || ! self::order_has_artwork_context( $order ) ) {
                continue;
            }
            if ( in_array( self::artwork_status( $order ), array( 'needed', 'changes_requested', 'awaiting_review' ), true ) ) {
                $count++;
            }
        }
        return $count;
    }

    private static function status_badge( string $status ): void {
        $labels = array(
            'needed'            => __( 'Artwork Needed', 'all-star-bulk-order' ),
            'awaiting_review'   => __( 'Awaiting Review', 'all-star-bulk-order' ),
            'changes_requested' => __( 'Changes Requested', 'all-star-bulk-order' ),
            'approved'          => __( 'Approved', 'all-star-bulk-order' ),
        );
        $label = $labels[ $status ] ?? $labels['needed'];
        echo '<span class="asbo-account-artwork-badge asbo-account-artwork-badge--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
    }

    private static function artwork_status_headline( string $status ): string {
        $copy = array(
            'needed'            => __( 'Artwork is needed for this order', 'all-star-bulk-order' ),
            'awaiting_review'   => __( 'Your artwork is with our review team', 'all-star-bulk-order' ),
            'changes_requested' => __( 'A revision is needed before production', 'all-star-bulk-order' ),
            'approved'          => __( 'Artwork is approved for production', 'all-star-bulk-order' ),
        );
        return $copy[ $status ] ?? $copy['needed'];
    }

    private static function artwork_status_description( string $status ): string {
        $copy = array(
            'needed'            => __( 'Upload the artwork you want us to use so we can review file quality, placement, thread matching, and stitch requirements.', 'all-star-bulk-order' ),
            'awaiting_review'   => __( 'No action is needed right now. We are reviewing the submitted artwork and will contact you before production if anything needs to change.', 'all-star-bulk-order' ),
            'changes_requested' => __( 'Open the order to read the exact review note and submit revised artwork. We will review the revision before production.', 'all-star-bulk-order' ),
            'approved'          => __( 'The reviewed artwork has been cleared for production. We will contact you if any other production detail needs confirmation.', 'all-star-bulk-order' ),
        );
        return $copy[ $status ] ?? $copy['needed'];
    }

    private static function initials( string $name ): string {
        $parts = preg_split( '/\s+/', trim( $name ) ) ?: array();
        $initials = '';
        foreach ( array_slice( $parts, 0, 2 ) as $part ) {
            $initials .= function_exists( 'mb_substr' ) ? mb_substr( $part, 0, 1 ) : substr( $part, 0, 1 );
        }
        return strtoupper( $initials ?: 'AS' );
    }

}
