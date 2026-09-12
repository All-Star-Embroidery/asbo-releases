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

        // The normal WooCommerce dashboard callback would otherwise render underneath
        // the fallback Artwork view. Remove only that default callback for this request.
        remove_action( 'woocommerce_account_dashboard', 'woocommerce_account_dashboard', 10 );
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

        wp_register_style( 'asbo-account-experience', false, array(), '1.3.1-rc.1' );
        wp_enqueue_style( 'asbo-account-experience' );
        wp_add_inline_style( 'asbo-account-experience', self::css() );
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
                                    <strong><?php echo esc_html( sprintf( __( 'Order #%d — %s', 'all-star-bulk-order' ), $attention_order->get_id(), $attention_title ) ); ?></strong>
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

    private static function css(): string {
        return <<<'CSS'
:root{--asbo-navy:#11192d;--asbo-navy-2:#1b2947;--asbo-gold:#d7aa32;--asbo-gold-soft:#fff8e7;--asbo-ink:#182033;--asbo-muted:#6f7786;--asbo-line:#e5e7eb;--asbo-line-strong:#d4d8df;--asbo-soft:#f7f8fa;--asbo-soft-2:#fbfbfc;--asbo-green:#24734a;--asbo-green-soft:#edf8f2;--asbo-red:#a04435;--asbo-red-soft:#fff3ef;--asbo-warning:#7a5a00;--asbo-warning-soft:#fff8df}
.asbo-account-page .woocommerce{position:relative;left:50%;width:min(1260px,calc(100vw - 48px));max-width:none!important;margin:clamp(34px,5vw,64px) 0 72px!important;transform:translateX(-50%);font-size:15px;color:var(--asbo-ink)}
.asbo-account-page.asbo-account-logged-in .woocommerce{display:grid;grid-template-columns:220px minmax(0,1fr);column-gap:38px;align-items:start}
.asbo-account-page .woocommerce-notices-wrapper,.asbo-account-page .woocommerce-message,.asbo-account-page .woocommerce-error,.asbo-account-page .woocommerce-info{grid-column:1/-1}
.asbo-account-page .woocommerce-MyAccount-navigation{float:none!important;width:auto!important;margin:0!important;border-right:1px solid var(--asbo-line);padding-right:26px}
.asbo-account-page .woocommerce-MyAccount-content{float:none!important;width:auto!important;min-width:0;margin:0!important;padding:0 0 40px;background:transparent!important;border:0!important;border-radius:0!important;box-shadow:none!important}
.asbo-account-identity{grid-column:1/-1;display:flex;align-items:center;gap:12px;margin:0 0 30px;padding:0 0 24px;border-bottom:1px solid var(--asbo-line);background:transparent;color:var(--asbo-ink)}
.asbo-account-avatar{display:grid;place-items:center;flex:0 0 38px;width:38px;height:38px;border-radius:999px;background:var(--asbo-gold-soft);color:#765800;font-size:13px;font-weight:900;border:1px solid #edd892}
.asbo-account-identity__copy{display:grid;gap:1px}.asbo-account-identity__copy>span{color:var(--asbo-muted);font-size:9px;font-weight:850;letter-spacing:.12em;text-transform:uppercase}.asbo-account-identity__copy strong{color:var(--asbo-navy);font-size:14px}.asbo-account-identity__copy small{color:var(--asbo-muted);font-size:11px}
.asbo-account-page .woocommerce-MyAccount-navigation ul{position:sticky;top:140px;display:grid;gap:2px;margin:0!important;padding:0!important;list-style:none!important;background:transparent}
.asbo-account-page .woocommerce-MyAccount-navigation li{margin:0!important;list-style:none!important}
.asbo-account-page .woocommerce-MyAccount-navigation li a{position:relative;display:flex;align-items:center;min-height:39px;padding:8px 12px;border-radius:10px;color:#515b6a!important;font-size:13px;font-weight:700;text-decoration:none!important;transition:background .15s ease,color .15s ease,transform .15s ease}
.asbo-account-page .woocommerce-MyAccount-navigation li a:hover{background:var(--asbo-soft);color:var(--asbo-navy)!important;transform:translateX(2px)}
.asbo-account-page .woocommerce-MyAccount-navigation li.is-active a{background:var(--asbo-gold-soft);color:var(--asbo-navy)!important;font-weight:850}.asbo-account-page .woocommerce-MyAccount-navigation li.is-active a:before{content:"";position:absolute;left:-27px;top:9px;bottom:9px;width:3px;border-radius:99px;background:var(--asbo-gold)}
.asbo-account-kicker{display:block;margin-bottom:7px;color:#8b6a13;font-size:10px;font-weight:900;letter-spacing:.11em;text-transform:uppercase}.asbo-account-page h2,.asbo-account-page h3,.asbo-account-page h4{color:var(--asbo-navy);letter-spacing:-.025em}.asbo-account-page h2{margin:0;font-size:clamp(30px,3.5vw,46px);line-height:1.06}.asbo-account-page h3{font-size:22px}.asbo-account-page p{line-height:1.62}
.asbo-account-dashboard-hero,.asbo-account-page-header,.asbo-account-section-heading,.asbo-view-order-overview{display:flex;align-items:flex-end;justify-content:space-between;gap:28px}
.asbo-account-dashboard-hero{padding:0 0 30px;border-bottom:1px solid var(--asbo-line)}.asbo-account-dashboard-hero>div{max-width:720px}.asbo-account-dashboard-hero p,.asbo-account-page-header p{max-width:720px;margin:11px 0 0;color:var(--asbo-muted);font-size:15px}.asbo-account-primary-link{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:40px;padding:8px 14px;border-radius:11px;background:var(--asbo-navy);color:#fff!important;font-size:12px;font-weight:800;text-decoration:none!important;box-shadow:0 4px 12px rgba(17,25,45,.12)}.asbo-account-primary-link:hover{background:var(--asbo-navy-2);transform:translateY(-1px)}
.asbo-account-quick-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));margin:0 0 42px;border-bottom:1px solid var(--asbo-line)}.asbo-account-quick-card{position:relative;display:grid;grid-template-columns:1fr auto;grid-template-rows:auto auto;gap:5px 12px;min-height:auto;padding:20px 20px 22px 0;border:0!important;border-right:1px solid var(--asbo-line)!important;border-radius:0!important;background:transparent!important;color:var(--asbo-ink)!important;text-decoration:none!important;box-shadow:none!important}.asbo-account-quick-card:last-child{border-right:0!important;padding-left:20px}.asbo-account-quick-card:not(:first-child){padding-left:20px}.asbo-account-quick-card:hover{background:linear-gradient(to bottom,transparent,rgba(247,248,250,.72))!important}.asbo-account-quick-card__index{display:none}.asbo-account-quick-card strong{grid-column:1;color:var(--asbo-muted);font-size:11px;font-weight:800}.asbo-account-quick-card b{grid-column:1;color:var(--asbo-navy);font-size:22px;font-weight:780;letter-spacing:-.035em}.asbo-account-quick-card p{display:none}.asbo-account-quick-card__arrow{grid-column:2;grid-row:1/3;align-self:center;color:#9ca3af;font-size:18px}.asbo-account-quick-card:hover .asbo-account-quick-card__arrow{color:var(--asbo-gold)}
.asbo-account-section-heading{align-items:center;margin:0 0 12px}.asbo-account-section-heading h3{margin:0}.asbo-account-section-heading a{color:var(--asbo-navy)!important;font-size:12px;font-weight:800;text-decoration:none!important}.asbo-account-order-cards{display:grid;border-top:1px solid var(--asbo-line)}.asbo-account-order-card{display:grid;grid-template-columns:minmax(190px,1fr) auto;gap:8px 22px;padding:18px 2px;border:0!important;border-bottom:1px solid var(--asbo-line)!important;border-radius:0!important;background:transparent!important;color:var(--asbo-ink)!important;text-decoration:none!important;box-shadow:none!important}.asbo-account-order-card:hover{background:linear-gradient(90deg,var(--asbo-soft-2),transparent)!important}.asbo-account-order-card__top{display:flex;align-items:center;gap:10px}.asbo-account-order-card__top strong{color:var(--asbo-navy);font-size:14px}.asbo-account-order-card__meta{grid-row:2;display:flex;gap:14px;color:var(--asbo-muted);font-size:11px}.asbo-account-order-card__artwork{grid-column:2;grid-row:1/3;display:flex;align-items:center;gap:10px}.asbo-account-order-card__artwork>span{color:var(--asbo-muted);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.asbo-account-order-status,.asbo-account-artwork-badge{display:inline-flex;align-items:center;min-height:26px;padding:4px 9px;border-radius:999px;font-size:10px;font-weight:800;white-space:nowrap}.asbo-account-order-status{background:var(--asbo-soft);color:#586273;border:1px solid var(--asbo-line)}.asbo-account-artwork-badge--needed{background:var(--asbo-soft);color:#596575}.asbo-account-artwork-badge--awaiting_review{background:var(--asbo-warning-soft);color:var(--asbo-warning)}.asbo-account-artwork-badge--changes_requested{background:var(--asbo-red-soft);color:var(--asbo-red)}.asbo-account-artwork-badge--approved{background:var(--asbo-green-soft);color:var(--asbo-green)}
.asbo-account-page-header{align-items:flex-start;margin:0 0 22px;padding:0 0 22px;border-bottom:1px solid var(--asbo-line)}.asbo-artwork-status-guide{display:flex;gap:7px;flex-wrap:wrap;margin:0 0 16px}.asbo-artwork-status-guide span{padding:5px 9px;border:1px solid var(--asbo-line);border-radius:999px;background:#fff;color:var(--asbo-muted);font-size:10px;font-weight:750}.asbo-artwork-hub__list{display:grid;border-top:1px solid var(--asbo-line)}.asbo-artwork-hub-card{position:relative;display:grid;grid-template-columns:5px minmax(0,1fr) auto;border:0!important;border-bottom:1px solid var(--asbo-line)!important;border-radius:0!important;background:transparent!important;overflow:visible!important}.asbo-artwork-hub-card__accent{background:#c6cbd3;border-radius:99px;margin:18px 0}.asbo-artwork-hub-card--awaiting_review .asbo-artwork-hub-card__accent{background:var(--asbo-gold)}.asbo-artwork-hub-card--changes_requested .asbo-artwork-hub-card__accent{background:#c8694f}.asbo-artwork-hub-card--approved .asbo-artwork-hub-card__accent{background:#399063}.asbo-artwork-hub-card__body{padding:18px 20px}.asbo-artwork-hub-card__heading{display:flex;align-items:flex-start;justify-content:space-between;gap:20px}.asbo-artwork-hub-card__heading>div>span{color:var(--asbo-muted);font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.asbo-artwork-hub-card__heading h3{margin:3px 0 0!important;font-size:18px}.asbo-artwork-hub-card__body>p{max-width:710px;margin:9px 0 14px;color:var(--asbo-muted);font-size:13px}.asbo-artwork-hub-card__meta{display:flex;gap:22px;flex-wrap:wrap}.asbo-artwork-hub-card__meta span{display:flex;flex-direction:column;gap:2px;color:var(--asbo-ink);font-size:11px}.asbo-artwork-hub-card__meta b{color:#9aa1ad;font-size:9px;letter-spacing:.07em;text-transform:uppercase}.asbo-artwork-hub-card__action{display:flex;align-items:center;gap:7px;padding:0 2px 0 20px;color:var(--asbo-navy)!important;font-size:12px;font-weight:850;text-decoration:none!important;white-space:nowrap}.asbo-artwork-hub-card__action:hover{color:#8b6a13!important}.asbo-account-empty-state{padding:44px 0;text-align:left;border-top:1px solid var(--asbo-line);border-bottom:1px solid var(--asbo-line);background:transparent}.asbo-account-empty-state>span{display:none}.asbo-account-empty-state h3{margin:0 0 6px!important}.asbo-account-empty-state p{margin:0 0 16px;max-width:500px;color:var(--asbo-muted)}
.asbo-view-order-overview{align-items:center;margin:0 0 24px;padding:0 0 20px;border-bottom:1px solid var(--asbo-line);background:transparent}.asbo-view-order-overview h2{font-size:26px!important}.asbo-view-order-overview__statuses{display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
.asbo-account-page table.shop_table,.asbo-account-page table.woocommerce-table{width:100%!important;border:0!important;border-collapse:collapse!important;border-spacing:0!important;border-radius:0!important;overflow:visible;background:transparent!important}.asbo-account-page table.shop_table thead,.asbo-account-page table.woocommerce-table thead{border-bottom:1px solid var(--asbo-line-strong)}.asbo-account-page table.shop_table th,.asbo-account-page table.woocommerce-table th{padding:11px 10px!important;background:transparent!important;color:#7d8592!important;font-size:10px!important;font-weight:850!important;letter-spacing:.07em;text-transform:uppercase}.asbo-account-page table.shop_table td,.asbo-account-page table.woocommerce-table td{padding:15px 10px!important;border-top:1px solid var(--asbo-line)!important;font-size:13px;vertical-align:middle}.asbo-account-page table.shop_table tbody tr:hover,.asbo-account-page table.woocommerce-table tbody tr:hover{background:var(--asbo-soft-2)}.asbo-account-page .woocommerce-orders-table__cell-order-number a{color:var(--asbo-navy)!important;font-weight:850}.asbo-account-page .woocommerce-orders-table__cell-order-actions .button{margin:2px!important;padding:7px 10px!important;border:1px solid var(--asbo-line-strong)!important;border-radius:9px!important;background:#fff!important;color:var(--asbo-navy)!important;font-size:11px!important;font-weight:750!important}.asbo-account-page .woocommerce-orders-table__cell-order-actions .button:hover{background:var(--asbo-soft)!important}.asbo-account-page .woocommerce-orders-table__cell-order-actions .button.asbo-artwork{border-color:#ead58f!important;background:var(--asbo-gold-soft)!important;color:#6e5205!important}
.asbo-account-page .woocommerce-Addresses{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:0!important;width:100%!important;margin-top:24px!important;align-items:start!important}.asbo-account-page .woocommerce-Addresses:before,.asbo-account-page .woocommerce-Addresses:after{display:none!important}.asbo-account-page .woocommerce-Address,.asbo-account-page .woocommerce-Addresses>.u-column1,.asbo-account-page .woocommerce-Addresses>.u-column2,.asbo-account-page .woocommerce-Addresses>.col-1,.asbo-account-page .woocommerce-Addresses>.col-2{float:none!important;clear:none!important;position:static!important;display:block!important;width:100%!important;max-width:none!important;min-width:0!important;margin:0!important;box-sizing:border-box!important;padding:0 32px 0 0!important;border:0!important;border-radius:0!important;background:transparent!important}.asbo-account-page .woocommerce-Addresses>.u-column2,.asbo-account-page .woocommerce-Addresses>.col-2,.asbo-account-page .woocommerce-Address.col-2{padding:0 0 0 32px!important;border-left:1px solid var(--asbo-line)!important}.asbo-account-page .woocommerce-Address-title{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:15px}.asbo-account-page .woocommerce-Address-title h3{margin:0!important;font-size:24px!important}.asbo-account-page .woocommerce-Address-title a{display:inline-flex;align-items:center;min-height:32px;padding:5px 9px;border-radius:9px;background:var(--asbo-gold-soft);color:#765800!important;font-size:11px;font-weight:800;text-decoration:none!important}.asbo-account-page address{margin:0!important;padding:0!important;border:0!important;border-radius:0!important;background:transparent!important;color:var(--asbo-ink)!important;font-style:normal!important;line-height:1.7}
.asbo-account-page form .form-row{margin-bottom:15px}.asbo-account-page form .form-row label{margin-bottom:6px;color:var(--asbo-navy);font-size:12px;font-weight:800}.asbo-account-page form input.input-text,.asbo-account-page form textarea,.asbo-account-page form select,.asbo-account-page .select2-selection{min-height:44px!important;border:1px solid transparent!important;border-radius:11px!important;background:var(--asbo-soft)!important;box-shadow:inset 0 0 0 1px rgba(17,25,45,.03)!important}.asbo-account-page form input.input-text:hover,.asbo-account-page form textarea:hover,.asbo-account-page form select:hover{background:#f3f4f6!important}.asbo-account-page form input.input-text:focus,.asbo-account-page form textarea:focus,.asbo-account-page form select:focus{border-color:#d9bd69!important;background:#fff!important;outline:none!important;box-shadow:0 0 0 3px rgba(215,170,50,.13)!important}.asbo-account-page button.button,.asbo-account-page a.button,.asbo-account-page input.button{min-height:40px;padding:8px 14px!important;border:0!important;border-radius:10px!important;background:var(--asbo-navy)!important;color:#fff!important;font-weight:800!important;box-shadow:0 3px 8px rgba(17,25,45,.12)!important}.asbo-account-page button.button:hover,.asbo-account-page a.button:hover,.asbo-account-page input.button:hover{background:var(--asbo-navy-2)!important}.asbo-account-page fieldset{margin-top:28px;padding:20px 0 0;border:0;border-top:1px solid var(--asbo-line);border-radius:0}.asbo-account-page legend{padding:0 12px 0 0;color:var(--asbo-navy);font-weight:850}
.asbo-account-auth .woocommerce{width:min(980px,calc(100vw - 40px));max-width:none!important}.asbo-account-auth #customer_login{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0;border-top:1px solid var(--asbo-line)}.asbo-account-auth #customer_login>.u-column1,.asbo-account-auth #customer_login>.u-column2{float:none!important;width:auto!important;margin:0!important;padding:30px 36px 0 0!important;border:0!important;border-radius:0!important;background:transparent!important;box-shadow:none!important}.asbo-account-auth #customer_login>.u-column2{padding-left:36px!important;padding-right:0!important;border-left:1px solid var(--asbo-line)!important}
.asbo-account-page .woocommerce-order-details,.asbo-account-page .woocommerce-customer-details{margin-top:30px;padding-top:6px}.asbo-account-page .woocommerce-order-details__title,.asbo-account-page .woocommerce-column__title{font-size:21px!important}.asbo-account-page .woocommerce-customer-details .woocommerce-columns{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:0!important;border-top:1px solid var(--asbo-line)!important}.asbo-account-page .woocommerce-customer-details .woocommerce-column{float:none!important;clear:none!important;width:100%!important;max-width:none!important;margin:0!important;padding:22px 30px 0 0!important;box-sizing:border-box!important}.asbo-account-page .woocommerce-customer-details .woocommerce-column.col-2{padding-left:30px!important;padding-right:0!important;border-left:1px solid var(--asbo-line)!important}
#asbo-artwork{scroll-margin-top:140px}
@media(max-width:980px){.asbo-account-page.asbo-account-logged-in .woocommerce{grid-template-columns:1fr;column-gap:0}.asbo-account-identity{margin-bottom:12px}.asbo-account-page .woocommerce-MyAccount-navigation{border-right:0;padding-right:0;border-bottom:1px solid var(--asbo-line);padding-bottom:12px;margin-bottom:24px!important}.asbo-account-page .woocommerce-MyAccount-navigation ul{position:static;display:flex;gap:4px;overflow-x:auto;padding:0 0 3px!important;scrollbar-width:thin}.asbo-account-page .woocommerce-MyAccount-navigation li{flex:0 0 auto}.asbo-account-page .woocommerce-MyAccount-navigation li a{min-height:36px;padding:7px 10px;white-space:nowrap}.asbo-account-page .woocommerce-MyAccount-navigation li.is-active a:before{left:10px;right:10px;top:auto;bottom:-13px;width:auto;height:2px}.asbo-account-quick-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.asbo-account-quick-card:nth-child(2){border-right:0!important}.asbo-account-quick-card:nth-child(3){padding-left:0;border-top:1px solid var(--asbo-line)!important}.asbo-account-quick-card:nth-child(4){border-top:1px solid var(--asbo-line)!important}.asbo-artwork-hub-card{grid-template-columns:5px minmax(0,1fr)}.asbo-artwork-hub-card__action{grid-column:2;padding:0 20px 15px}.asbo-account-page .woocommerce-Addresses,.asbo-account-page .woocommerce-customer-details .woocommerce-columns{grid-template-columns:1fr!important}.asbo-account-page .woocommerce-Addresses>.u-column1,.asbo-account-page .woocommerce-Addresses>.u-column2,.asbo-account-page .woocommerce-Addresses>.col-1,.asbo-account-page .woocommerce-Addresses>.col-2,.asbo-account-page .woocommerce-Address{padding:0 0 24px!important;border-left:0!important;border-bottom:1px solid var(--asbo-line)!important}.asbo-account-page .woocommerce-Addresses>.u-column2,.asbo-account-page .woocommerce-Addresses>.col-2,.asbo-account-page .woocommerce-Address.col-2{padding-top:24px!important;border-bottom:0!important}.asbo-account-page .woocommerce-customer-details .woocommerce-column,.asbo-account-page .woocommerce-customer-details .woocommerce-column.col-2{padding:20px 0!important;border-left:0!important;border-bottom:1px solid var(--asbo-line)!important}.asbo-account-page .woocommerce-customer-details .woocommerce-column.col-2{border-bottom:0!important}}
@media(max-width:650px){.asbo-account-page .woocommerce{width:calc(100vw - 24px);margin-top:24px!important}.asbo-account-dashboard-hero,.asbo-account-section-heading,.asbo-account-page-header,.asbo-view-order-overview{align-items:flex-start;flex-direction:column}.asbo-account-dashboard-hero .asbo-account-primary-link{width:auto}.asbo-account-quick-grid{grid-template-columns:1fr}.asbo-account-quick-card,.asbo-account-quick-card:not(:first-child),.asbo-account-quick-card:last-child{padding:14px 0!important;border-right:0!important;border-top:1px solid var(--asbo-line)!important}.asbo-account-quick-card:first-child{border-top:0!important}.asbo-account-order-card{grid-template-columns:1fr}.asbo-account-order-card__artwork{grid-column:1;grid-row:auto;justify-content:flex-start}.asbo-artwork-hub-card__heading{flex-direction:column;gap:8px}.asbo-artwork-hub-card__meta{gap:12px}.asbo-artwork-hub-card__meta span{min-width:42%}.asbo-account-page table.shop_table,.asbo-account-page table.woocommerce-table{display:block}.asbo-account-page table.shop_table thead,.asbo-account-page table.woocommerce-table thead{display:none}.asbo-account-page table.shop_table tbody,.asbo-account-page table.woocommerce-table tbody{display:grid}.asbo-account-page table.shop_table tr,.asbo-account-page table.woocommerce-table tr{display:block;padding:12px 0;border-bottom:1px solid var(--asbo-line);background:transparent}.asbo-account-page table.shop_table td,.asbo-account-page table.woocommerce-table td{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:6px 0!important;border:0!important;text-align:right!important}.asbo-account-page table.shop_table td:before,.asbo-account-page table.woocommerce-table td:before{float:none!important;color:var(--asbo-muted);font-size:10px;font-weight:850;letter-spacing:.05em;text-transform:uppercase}.asbo-account-page .woocommerce-orders-table__cell-order-actions{display:block!important;text-align:left!important}.asbo-account-auth #customer_login{grid-template-columns:1fr}.asbo-account-auth #customer_login>.u-column1,.asbo-account-auth #customer_login>.u-column2{padding:22px 0!important;border-left:0!important;border-bottom:1px solid var(--asbo-line)!important}.asbo-account-auth #customer_login>.u-column2{border-bottom:0!important}}

/* ========================================================================== 
   ASBO 1.2.2 BRAND SYSTEM — All Star Embroidery 2026
   Mobile-first, production/editorial account UI. This intentionally replaces
   the previous HeroUI/SaaS visual language without replacing WooCommerce.
   ========================================================================== */
:root{
  --ase-navy:#080F1F;
  --ase-gold:#D2A952;
  --ase-cream:#F3EEE7;
  --ase-white:#FFFFFF;
  --ase-steel:#B8B6B5;
  --ase-red:#B4383D;
  --ase-ink:#151A24;
  --ase-muted:#626872;
  --ase-line:#DEDAD4;
  --ase-line-dark:#BDB8B1;
  --ase-focus:rgba(210,169,82,.28);
  --ase-radius:6px;
}

/* Never break out of the WordPress/WooCommerce content width. The old 100vw +
   translate shell is intentionally neutralized because it caused mobile/theme
   overflow and made the account page dependent on its parent container. */
.asbo-account-page .woocommerce,
.asbo-account-page .woocommerce *{box-sizing:border-box}
.asbo-account-page .woocommerce{
  position:static!important;
  left:auto!important;
  width:100%!important;
  max-width:1180px!important;
  min-width:0!important;
  margin:clamp(28px,4vw,64px) auto 76px!important;
  padding:0 clamp(20px,3vw,40px)!important;
  transform:none!important;
  color:var(--ase-ink);
  font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  font-size:16px;
  line-height:1.55;
}
.asbo-account-page.asbo-account-logged-in .woocommerce{
  display:grid!important;
  grid-template-columns:minmax(0,1fr)!important;
  gap:0!important;
  align-items:start;
}
.asbo-account-page .woocommerce-MyAccount-content,
.asbo-account-page .woocommerce-MyAccount-navigation,
.asbo-account-page .asbo-account-identity{min-width:0!important;max-width:100%}
.asbo-account-page .woocommerce-MyAccount-content{
  float:none!important;
  width:100%!important;
  margin:0!important;
  padding:24px 0 0!important;
  border:0!important;
  border-top:2px solid var(--ase-gold)!important;
  border-radius:0!important;
  background:transparent!important;
  box-shadow:none!important;
  overflow-wrap:anywhere;
}
.asbo-account-page .woocommerce-MyAccount-content img{max-width:100%;height:auto}
.asbo-account-page .woocommerce-MyAccount-content table{max-width:100%}

/* Account identity + navigation: one crafted navy rail, not stacked cards. */
.asbo-account-identity{
  display:grid!important;
  grid-template-columns:48px minmax(0,1fr) auto;
  align-items:center;
  gap:13px;
  margin:0!important;
  padding:16px!important;
  border:0!important;
  border-radius:var(--ase-radius)!important;
  background:var(--ase-navy)!important;
  color:var(--ase-white)!important;
  box-shadow:none!important;
}
.asbo-account-avatar{
  display:grid;
  place-items:center;
  width:48px!important;
  height:48px!important;
  min-width:48px;
  border:1px solid var(--ase-gold)!important;
  border-radius:50%!important;
  background:transparent!important;
  color:var(--ase-gold)!important;
  font-size:13px;
  font-weight:800;
  letter-spacing:.04em;
}
.asbo-account-identity__copy{min-width:0}
.asbo-account-identity__copy>span{
  display:block;
  margin:0 0 2px;
  color:var(--ase-gold)!important;
  font-size:10px!important;
  font-weight:800!important;
  letter-spacing:.14em!important;
  text-transform:uppercase;
}
.asbo-account-identity__copy strong{
  display:block;
  margin:0!important;
  color:var(--ase-white)!important;
  font-size:15px!important;
  font-weight:750;
  line-height:1.25;
}
.asbo-account-identity__copy small{
  display:block;
  margin-top:3px;
  max-width:100%;
  overflow:visible!important;
  color:#C7CBD3!important;
  font-size:12px!important;
  line-height:1.35;
  text-overflow:clip!important;
  white-space:normal!important;
  overflow-wrap:anywhere;
}
.asbo-account-menu-toggle{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:9px;
  min-width:44px;
  min-height:44px;
  padding:0 4px 0 10px;
  border:0;
  border-left:1px solid rgba(255,255,255,.16);
  border-radius:0;
  background:transparent;
  color:var(--ase-white);
  font:inherit;
  font-size:13px;
  font-weight:700;
  cursor:pointer;
}
.asbo-account-menu-toggle__icon{
  width:9px;
  height:9px;
  border-right:2px solid var(--ase-gold);
  border-bottom:2px solid var(--ase-gold);
  transform:rotate(45deg) translateY(-2px);
  transition:transform .18s ease;
}
.asbo-account-menu-toggle[aria-expanded="true"] .asbo-account-menu-toggle__icon{
  transform:rotate(225deg) translate(-2px,-1px);
}
.asbo-account-page .woocommerce-MyAccount-navigation{
  float:none!important;
  width:100%!important;
  margin:0!important;
  padding:0!important;
  border:0!important;
  background:transparent!important;
}
.asbo-account-page .woocommerce-MyAccount-navigation ul{
  position:static!important;
  display:grid!important;
  gap:0!important;
  margin:0!important;
  padding:8px 14px 16px!important;
  overflow:visible!important;
  border:0!important;
  border-radius:0 0 var(--ase-radius) var(--ase-radius)!important;
  background:var(--ase-navy)!important;
  box-shadow:none!important;
  list-style:none!important;
}
.asbo-account-page .woocommerce-MyAccount-navigation li{
  margin:0!important;
  padding:0!important;
  border:0!important;
}
.asbo-account-page .woocommerce-MyAccount-navigation li a{
  position:relative;
  display:flex!important;
  align-items:center;
  min-height:48px!important;
  padding:11px 12px 11px 16px!important;
  border:0!important;
  border-top:1px solid rgba(255,255,255,.08)!important;
  border-radius:0!important;
  background:transparent!important;
  box-shadow:none!important;
  color:#D7DAE0!important;
  font-size:15px!important;
  font-weight:600!important;
  line-height:1.2;
  text-decoration:none!important;
  transform:none!important;
  white-space:normal!important;
}
.asbo-account-page .woocommerce-MyAccount-navigation li:first-child a{border-top:0!important}
.asbo-account-page .woocommerce-MyAccount-navigation li a:hover,
.asbo-account-page .woocommerce-MyAccount-navigation li a:focus-visible{
  background:rgba(255,255,255,.055)!important;
  color:var(--ase-white)!important;
  transform:none!important;
}
.asbo-account-page .woocommerce-MyAccount-navigation li.is-active a{
  background:transparent!important;
  color:var(--ase-gold)!important;
  font-weight:800!important;
  box-shadow:none!important;
}
.asbo-account-page .woocommerce-MyAccount-navigation li.is-active a:before{
  content:""!important;
  position:absolute!important;
  left:0!important;
  top:10px!important;
  bottom:10px!important;
  width:2px!important;
  height:auto!important;
  border-radius:0!important;
  background:var(--ase-gold)!important;
}
.asbo-account-page .woocommerce-MyAccount-navigation-link--customer-logout{
  margin-top:8px!important;
  padding-top:8px!important;
  border-top:1px solid rgba(210,169,82,.34)!important;
}

/* Editorial type hierarchy. */
.asbo-account-page .woocommerce-MyAccount-content h1,
.asbo-account-page .woocommerce-MyAccount-content h2,
.asbo-account-page .woocommerce-MyAccount-content h3{
  color:var(--ase-navy)!important;
  letter-spacing:-.02em!important;
}
.asbo-account-page .woocommerce-MyAccount-content h2,
.asbo-account-page .asbo-account-page-header h2{
  font-family:"Roboto Slab",Georgia,serif!important;
  font-size:clamp(32px,4vw,46px)!important;
  font-weight:700!important;
  line-height:1.08!important;
}
.asbo-account-page .woocommerce-MyAccount-content h3{font-size:21px!important;line-height:1.25}
.asbo-account-kicker{
  display:block;
  margin-bottom:8px;
  color:#876A2A!important;
  font-size:11px!important;
  font-weight:800!important;
  letter-spacing:.14em!important;
  text-transform:uppercase;
}
.asbo-account-page .woocommerce-MyAccount-content>p,
.asbo-account-page .asbo-account-page-header p,
.asbo-account-page .asbo-account-dashboard-hero p{
  color:var(--ase-muted)!important;
  font-size:16px!important;
  line-height:1.6!important;
}

/* Dashboard: information strip, not a grid of chatbot cards. */
.asbo-account-dashboard-hero{
  display:flex!important;
  align-items:flex-end!important;
  justify-content:space-between!important;
  gap:28px!important;
  padding:4px 0 30px!important;
  border:0!important;
  border-bottom:1px solid var(--ase-line)!important;
  background:transparent!important;
}
.asbo-account-dashboard-hero h2{margin:0 0 12px!important}
.asbo-account-primary-link{
  display:inline-flex!important;
  align-items:center;
  justify-content:center;
  min-height:46px!important;
  padding:10px 17px!important;
  border:1px solid var(--ase-navy)!important;
  border-radius:5px!important;
  background:var(--ase-navy)!important;
  color:var(--ase-white)!important;
  box-shadow:none!important;
  font-size:14px!important;
  font-weight:750!important;
  text-decoration:none!important;
  transform:none!important;
}
.asbo-account-primary-link:hover{background:#111A2C!important;transform:none!important}
.asbo-account-quick-grid{
  display:grid!important;
  grid-template-columns:repeat(4,minmax(0,1fr))!important;
  gap:0!important;
  margin:30px 0 44px!important;
  border-top:1px solid var(--ase-line)!important;
  border-bottom:1px solid var(--ase-line)!important;
}
.asbo-account-quick-card{
  position:relative;
  display:flex!important;
  min-height:166px!important;
  flex-direction:column;
  padding:22px!important;
  border:0!important;
  border-left:1px solid var(--ase-line)!important;
  border-radius:0!important;
  background:transparent!important;
  box-shadow:none!important;
  color:var(--ase-ink)!important;
  text-decoration:none!important;
  transform:none!important;
}
.asbo-account-quick-card:first-child{border-left:0!important}
.asbo-account-quick-card:hover{background:var(--ase-cream)!important;box-shadow:none!important;transform:none!important}
.asbo-account-quick-card__index{margin-bottom:20px!important;color:#8A6C2A!important;font-size:10px!important;font-weight:800!important;letter-spacing:.14em!important}
.asbo-account-quick-card strong{color:var(--ase-navy)!important;font-size:14px!important}
.asbo-account-quick-card b{margin:6px 0 9px!important;color:var(--ase-navy)!important;font-size:28px!important;line-height:1!important}
.asbo-account-quick-card p{margin:0!important;color:var(--ase-muted)!important;font-size:13px!important;line-height:1.45!important}
.asbo-account-quick-card__arrow{right:18px!important;bottom:17px!important;color:#8A6C2A!important}

/* Natural lists and production records. */
.asbo-account-section-heading,
.asbo-account-page-header,
.asbo-view-order-overview{
  display:flex!important;
  align-items:flex-end!important;
  justify-content:space-between!important;
  gap:24px!important;
}
.asbo-account-page-header{
  align-items:flex-start!important;
  padding:0 0 28px!important;
  border:0!important;
  border-bottom:1px solid var(--ase-line)!important;
}
.asbo-account-order-cards{display:grid!important;gap:0!important;border-top:1px solid var(--ase-line)!important}
.asbo-account-order-card{
  display:block!important;
  padding:19px 4px!important;
  border:0!important;
  border-bottom:1px solid var(--ase-line)!important;
  border-radius:0!important;
  background:transparent!important;
  box-shadow:none!important;
  color:inherit!important;
  text-decoration:none!important;
}
.asbo-account-order-card:hover{background:var(--ase-cream)!important;border-color:var(--ase-line)!important}
.asbo-account-order-card__top strong{color:var(--ase-navy)!important;font-size:15px!important}
.asbo-account-order-card__meta{color:var(--ase-muted)!important;font-size:13px!important}
.asbo-account-order-card__artwork{border-top:1px solid #ECE8E2!important;color:var(--ase-muted)!important;font-size:11px!important}

/* Statuses are production labels, not rounded app chips. */
.asbo-account-order-status,
.asbo-account-artwork-badge{
  display:inline-flex!important;
  align-items:center;
  min-height:26px!important;
  padding:4px 8px!important;
  border:1px solid transparent!important;
  border-radius:3px!important;
  font-size:10px!important;
  font-weight:800!important;
  line-height:1.2!important;
  letter-spacing:.04em;
  text-transform:uppercase;
  white-space:nowrap;
}
.asbo-account-order-status{border-color:var(--ase-line)!important;background:transparent!important;color:#59606A!important}
.asbo-account-artwork-badge--needed{border-color:var(--ase-line-dark)!important;background:transparent!important;color:#545A63!important}
.asbo-account-artwork-badge--awaiting_review{border-color:#D9BD78!important;background:#FBF6E9!important;color:#725824!important}
.asbo-account-artwork-badge--changes_requested{border-color:#D9A5A8!important;background:#FCF2F2!important;color:var(--ase-red)!important}
.asbo-account-artwork-badge--approved{border-color:var(--ase-navy)!important;background:var(--ase-navy)!important;color:var(--ase-white)!important}
.asbo-artwork-status-guide{display:none!important}

/* Artwork hub = production tickets separated by rules. */
.asbo-artwork-hub__list{display:grid!important;gap:0!important;margin-top:8px;border-top:1px solid var(--ase-line)!important}
.asbo-artwork-hub-card{
  position:relative;
  display:grid!important;
  grid-template-columns:3px minmax(0,1fr) auto!important;
  overflow:visible!important;
  border:0!important;
  border-bottom:1px solid var(--ase-line)!important;
  border-radius:0!important;
  background:transparent!important;
  box-shadow:none!important;
}
.asbo-artwork-hub-card__accent{background:var(--ase-steel)!important}
.asbo-artwork-hub-card--awaiting_review .asbo-artwork-hub-card__accent{background:var(--ase-gold)!important}
.asbo-artwork-hub-card--changes_requested .asbo-artwork-hub-card__accent{background:var(--ase-red)!important}
.asbo-artwork-hub-card--approved .asbo-artwork-hub-card__accent{background:var(--ase-navy)!important}
.asbo-artwork-hub-card__body{padding:24px 24px 24px 20px!important}
.asbo-artwork-hub-card__heading{display:flex!important;align-items:flex-start!important;justify-content:space-between!important;gap:20px!important}
.asbo-artwork-hub-card__heading>div>span{color:var(--ase-muted)!important;font-size:11px!important;font-weight:800!important;letter-spacing:.08em!important;text-transform:uppercase}
.asbo-artwork-hub-card__heading h3{margin:4px 0 0!important;font-family:"Roboto Slab",Georgia,serif!important;font-size:20px!important}
.asbo-artwork-hub-card__body>p{max-width:720px!important;margin:11px 0 18px!important;color:var(--ase-muted)!important;font-size:14px!important;line-height:1.6!important}
.asbo-artwork-hub-card__meta{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:14px 24px!important}
.asbo-artwork-hub-card__meta span{display:flex!important;min-width:0;flex-direction:column;gap:3px;color:var(--ase-ink)!important;font-size:12px!important;overflow-wrap:anywhere}
.asbo-artwork-hub-card__meta b{color:#777B83!important;font-size:9px!important;font-weight:800!important;letter-spacing:.1em!important;text-transform:uppercase}
.asbo-artwork-hub-card__action{
  align-self:center;
  display:flex!important;
  align-items:center;
  min-height:44px!important;
  margin:0 4px 0 0!important;
  padding:10px 14px!important;
  border:0!important;
  border-radius:0!important;
  background:transparent!important;
  color:var(--ase-navy)!important;
  font-size:13px!important;
  font-weight:800!important;
  text-decoration:none!important;
  white-space:nowrap;
}
.asbo-artwork-hub-card__action:hover{background:var(--ase-cream)!important;text-decoration:underline!important;text-underline-offset:3px}

/* Empty states stay editorial and restrained. */
.asbo-account-empty-state{
  padding:48px 0!important;
  border:0!important;
  border-bottom:1px solid var(--ase-line)!important;
  border-radius:0!important;
  background:transparent!important;
  text-align:left!important;
}
.asbo-account-empty-state>span{display:none!important}
.asbo-account-empty-state h3{font-family:"Roboto Slab",Georgia,serif!important}

/* WooCommerce tables: clean order records on desktop; true reflow on phones. */
.asbo-account-page table.shop_table,
.asbo-account-page table.woocommerce-table{
  width:100%!important;
  max-width:100%!important;
  border:0!important;
  border-top:1px solid var(--ase-line)!important;
  border-bottom:1px solid var(--ase-line)!important;
  border-collapse:collapse!important;
  border-spacing:0!important;
  border-radius:0!important;
  overflow:visible!important;
  background:transparent!important;
  box-shadow:none!important;
}
.asbo-account-page table.shop_table th,
.asbo-account-page table.woocommerce-table th{
  padding:12px 12px!important;
  border:0!important;
  border-bottom:1px solid var(--ase-line)!important;
  background:var(--ase-cream)!important;
  color:#5C6068!important;
  font-size:10px!important;
  font-weight:800!important;
  letter-spacing:.09em!important;
  text-transform:uppercase;
}
.asbo-account-page table.shop_table td,
.asbo-account-page table.woocommerce-table td{
  padding:16px 12px!important;
  border:0!important;
  border-top:1px solid #ECE8E2!important;
  background:transparent!important;
  font-size:14px!important;
  vertical-align:middle!important;
}
.asbo-account-page .woocommerce-orders-table__cell-order-actions .button{
  min-height:40px!important;
  margin:2px!important;
  padding:8px 11px!important;
  border:1px solid var(--ase-line-dark)!important;
  border-radius:4px!important;
  background:transparent!important;
  color:var(--ase-navy)!important;
  box-shadow:none!important;
  font-size:12px!important;
  font-weight:700!important;
}
.asbo-account-page .woocommerce-orders-table__cell-order-actions .button.asbo-artwork{
  border-color:var(--ase-gold)!important;
  background:#FBF6E9!important;
  color:#6F5520!important;
}

/* Forms + addresses: borders and rules before cards/shadows. */
.asbo-account-page form .form-row label{margin-bottom:7px!important;color:var(--ase-navy)!important;font-size:13px!important;font-weight:750!important}
.asbo-account-page form input.input-text,
.asbo-account-page form textarea,
.asbo-account-page form select,
.asbo-account-page .select2-selection{
  width:100%;
  min-height:48px!important;
  max-width:100%;
  border:1px solid var(--ase-line-dark)!important;
  border-radius:5px!important;
  background:var(--ase-white)!important;
  box-shadow:none!important;
  color:var(--ase-ink)!important;
  font-size:16px!important;
}
.asbo-account-page form textarea{min-height:120px!important;padding:12px!important}
.asbo-account-page form input.input-text:focus,
.asbo-account-page form textarea:focus,
.asbo-account-page form select:focus{
  border-color:var(--ase-gold)!important;
  outline:2px solid var(--ase-focus)!important;
  outline-offset:1px!important;
  box-shadow:none!important;
}
.asbo-account-page button.button,
.asbo-account-page a.button,
.asbo-account-page input.button{
  min-height:46px!important;
  padding:10px 16px!important;
  border:1px solid var(--ase-navy)!important;
  border-radius:5px!important;
  background:var(--ase-navy)!important;
  color:var(--ase-white)!important;
  box-shadow:none!important;
  font-size:14px!important;
  font-weight:750!important;
}
.asbo-account-page fieldset{
  margin-top:28px!important;
  padding:22px 0 0!important;
  border:0!important;
  border-top:1px solid var(--ase-line)!important;
  border-radius:0!important;
}
.asbo-account-page legend{padding:0 10px 0 0!important;color:var(--ase-navy)!important;font-weight:800!important}
.asbo-account-page .woocommerce-Addresses,
.asbo-account-page .woocommerce-customer-details .woocommerce-columns{
  display:grid!important;
  grid-template-columns:repeat(2,minmax(0,1fr))!important;
  gap:32px!important;
}
.asbo-account-page .woocommerce-Address,
.asbo-account-page .woocommerce-customer-details .woocommerce-column{
  float:none!important;
  clear:none!important;
  position:static!important;
  width:100%!important;
  min-width:0!important;
  margin:0!important;
  padding:18px 0 0!important;
  border:0!important;
  border-top:2px solid var(--ase-gold)!important;
  border-radius:0!important;
  background:transparent!important;
  box-shadow:none!important;
}
.asbo-account-page address{
  margin-top:14px!important;
  padding:0!important;
  border:0!important;
  border-radius:0!important;
  background:transparent!important;
  color:var(--ase-ink)!important;
  font-style:normal!important;
  line-height:1.7!important;
}

/* Order overview and customer artwork flow become part of the page, not widgets. */
.asbo-view-order-overview{
  align-items:center!important;
  margin:0 0 28px!important;
  padding:0 0 20px!important;
  border:0!important;
  border-bottom:1px solid var(--ase-line)!important;
  border-radius:0!important;
  background:transparent!important;
  box-shadow:none!important;
}
.asbo-account-page #asbo-artwork.asbo-artwork-customer{
  margin:38px 0 0!important;
  padding:26px 0 0!important;
  border:0!important;
  border-top:2px solid var(--ase-gold)!important;
  border-radius:0!important;
  background:transparent!important;
  box-shadow:none!important;
}
#asbo-artwork{scroll-margin-top:120px}

/* Auth screens use the same material language without becoming two floating cards. */
.asbo-account-auth .woocommerce{max-width:980px!important}
.asbo-account-auth #customer_login{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:48px!important}
.asbo-account-auth #customer_login>.u-column1,
.asbo-account-auth #customer_login>.u-column2{
  float:none!important;
  width:auto!important;
  margin:0!important;
  padding:24px 0 0!important;
  border:0!important;
  border-top:2px solid var(--ase-gold)!important;
  border-radius:0!important;
  background:transparent!important;
  box-shadow:none!important;
}

/* Tablet is deliberately its own layout — not a stretched phone. */
@media (min-width:768px){
  .asbo-account-page.asbo-account-logged-in .woocommerce{
    grid-template-columns:clamp(190px,22vw,248px) minmax(0,1fr)!important;
    grid-template-areas:"identity content" "nav content"!important;
    column-gap:clamp(30px,4vw,56px)!important;
    row-gap:0!important;
  }
  .asbo-account-identity{
    grid-area:identity;
    grid-template-columns:48px minmax(0,1fr)!important;
    padding:22px 18px 18px!important;
    border-radius:var(--ase-radius) var(--ase-radius) 0 0!important;
  }
  .asbo-account-menu-toggle{display:none!important}
  .asbo-account-page .woocommerce-MyAccount-navigation{
    grid-area:nav;
    align-self:start;
  }
  .asbo-account-page .woocommerce-MyAccount-navigation ul{
    padding:6px 14px 18px!important;
    border-radius:0 0 var(--ase-radius) var(--ase-radius)!important;
  }
  .asbo-account-page .woocommerce-MyAccount-navigation li a{
    min-height:44px!important;
    padding:10px 10px 10px 16px!important;
    font-size:13px!important;
  }
  .asbo-account-page .woocommerce-MyAccount-content{grid-area:content;padding-top:24px!important}
}

@media (min-width:768px) and (max-width:1099px){
  .asbo-account-page .woocommerce{padding:0 28px!important}
  .asbo-account-page.asbo-account-logged-in .woocommerce{
    grid-template-columns:190px minmax(0,1fr)!important;
    column-gap:30px!important;
  }
  .asbo-account-quick-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}
  .asbo-account-quick-card{border-left:0!important;border-top:1px solid var(--ase-line)!important}
  .asbo-account-quick-card:nth-child(odd){border-right:1px solid var(--ase-line)!important}
  .asbo-account-quick-card:nth-child(-n+2){border-top:0!important}
  .asbo-artwork-hub-card{grid-template-columns:3px minmax(0,1fr)!important}
  .asbo-artwork-hub-card__action{grid-column:2!important;justify-self:start;margin:0 0 20px 20px!important;padding:8px 0!important}
}

/* Mobile reflows. Nothing horizontally scrolls just to preserve desktop UI. */
@media (max-width:767px){
  .asbo-account-page .woocommerce{
    display:block!important;
    width:100%!important;
    margin:24px auto 56px!important;
    padding:0 20px!important;
    font-size:16px!important;
  }
  .asbo-account-identity{margin:0 0 18px!important}
  .asbo-account-page .woocommerce-MyAccount-navigation{display:none!important;margin:-18px 0 24px!important}
  .asbo-account-page .woocommerce.asbo-account-menu-open .asbo-account-identity{border-radius:var(--ase-radius) var(--ase-radius) 0 0!important}
  .asbo-account-page .woocommerce.asbo-account-menu-open .woocommerce-MyAccount-navigation{display:block!important}
  .asbo-account-page .woocommerce-MyAccount-navigation ul{display:grid!important;overflow:visible!important;padding-top:4px!important}
  .asbo-account-page .woocommerce-MyAccount-content{padding-top:22px!important}
  .asbo-account-page .woocommerce-MyAccount-content h2,
  .asbo-account-page .asbo-account-page-header h2{font-size:34px!important}
  .asbo-account-dashboard-hero,
  .asbo-account-section-heading,
  .asbo-account-page-header,
  .asbo-view-order-overview{
    align-items:flex-start!important;
    flex-direction:column!important;
    gap:16px!important;
  }
  .asbo-account-dashboard-hero{padding-bottom:24px!important}
  .asbo-account-dashboard-hero .asbo-account-primary-link{width:100%!important}
  .asbo-account-quick-grid{grid-template-columns:1fr!important;margin:24px 0 36px!important}
  .asbo-account-quick-card{
    min-height:0!important;
    padding:18px 4px!important;
    border:0!important;
    border-top:1px solid var(--ase-line)!important;
  }
  .asbo-account-quick-card:first-child{border-top:0!important}
  .asbo-account-quick-card__index{margin-bottom:9px!important}
  .asbo-account-quick-card p{display:block!important;max-width:88%;font-size:14px!important}
  .asbo-account-order-card{padding:18px 0!important}
  .asbo-account-order-card__top,
  .asbo-account-order-card__meta,
  .asbo-account-order-card__artwork{align-items:flex-start!important;flex-direction:column!important;gap:7px!important}
  .asbo-artwork-hub-card{grid-template-columns:3px minmax(0,1fr)!important}
  .asbo-artwork-hub-card__body{padding:20px 0 14px 16px!important}
  .asbo-artwork-hub-card__heading{flex-direction:column!important;gap:9px!important}
  .asbo-artwork-hub-card__body>p{font-size:15px!important}
  .asbo-artwork-hub-card__meta{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:13px!important}
  .asbo-artwork-hub-card__action{
    grid-column:2!important;
    justify-self:start;
    margin:0 0 18px 16px!important;
    padding:8px 0!important;
  }
  .asbo-account-page table.shop_table,
  .asbo-account-page table.woocommerce-table{display:block!important;border:0!important;background:transparent!important}
  .asbo-account-page table.shop_table thead,
  .asbo-account-page table.woocommerce-table thead{display:none!important}
  .asbo-account-page table.shop_table tbody,
  .asbo-account-page table.woocommerce-table tbody{display:grid!important;gap:0!important}
  .asbo-account-page table.shop_table tr,
  .asbo-account-page table.woocommerce-table tr{
    display:block!important;
    padding:16px 0!important;
    border:0!important;
    border-bottom:1px solid var(--ase-line)!important;
    border-radius:0!important;
    background:transparent!important;
    box-shadow:none!important;
  }
  .asbo-account-page table.shop_table td,
  .asbo-account-page table.woocommerce-table td{
    display:flex!important;
    align-items:flex-start!important;
    justify-content:space-between!important;
    gap:16px!important;
    min-width:0!important;
    padding:7px 0!important;
    border:0!important;
    text-align:right!important;
    overflow-wrap:anywhere;
  }
  .asbo-account-page table.shop_table td:before,
  .asbo-account-page table.woocommerce-table td:before{
    flex:0 0 38%;
    float:none!important;
    color:#777B83!important;
    font-size:10px!important;
    font-weight:800!important;
    letter-spacing:.07em!important;
    text-align:left!important;
    text-transform:uppercase;
  }
  .asbo-account-page .woocommerce-orders-table__cell-order-actions{display:flex!important;flex-wrap:wrap!important;justify-content:flex-start!important;text-align:left!important}
  .asbo-account-page .woocommerce-Addresses,
  .asbo-account-page .woocommerce-customer-details .woocommerce-columns,
  .asbo-account-auth #customer_login{grid-template-columns:1fr!important;gap:28px!important}
  .asbo-account-page .woocommerce-Address,
  .asbo-account-page .woocommerce-customer-details .woocommerce-column{padding-top:16px!important}
}

@media (max-width:420px){
  .asbo-account-page .woocommerce{padding:0 16px!important}
  .asbo-account-identity{grid-template-columns:44px minmax(0,1fr) 44px!important;padding:14px!important}
  .asbo-account-avatar{width:44px!important;height:44px!important;min-width:44px!important}
  .asbo-account-menu-toggle{width:44px!important;padding:0!important;border-left:0!important}
  .asbo-account-menu-toggle>span:first-child{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
  .asbo-artwork-hub-card__meta{grid-template-columns:1fr!important}
}

@media (prefers-reduced-motion:reduce){
  .asbo-account-page *,
  .asbo-account-page *:before,
  .asbo-account-page *:after{scroll-behavior:auto!important;transition:none!important;animation:none!important}
}


/* ASBO 1.2.2 tablet refinement — consistent rail + compact dashboard actions. */
.asbo-account-page .woocommerce-MyAccount-navigation li.asbo-artwork-has-attention a{padding-right:42px!important}
.asbo-account-page .woocommerce-MyAccount-navigation li.asbo-artwork-has-attention a:after{content:"!";position:absolute;right:12px;top:50%;display:grid;place-items:center;width:20px;height:20px;margin-top:-10px;border:1px solid var(--ase-gold);border-radius:50%;background:transparent;color:var(--ase-gold);font-size:11px;font-weight:900;line-height:1}
.asbo-account-quick-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:0!important;margin:24px 0 38px!important;border-top:1px solid var(--ase-line)!important;border-bottom:1px solid var(--ase-line)!important}
.asbo-account-quick-card,.asbo-account-quick-card:not(:first-child),.asbo-account-quick-card:last-child{display:grid!important;grid-template-columns:minmax(0,1fr) auto auto!important;grid-template-rows:auto auto!important;column-gap:10px!important;row-gap:5px!important;min-height:92px!important;padding:16px 18px!important;border:0!important;border-right:1px solid var(--ase-line)!important;border-top:1px solid var(--ase-line)!important;background:transparent!important}
.asbo-account-quick-card:nth-child(odd){border-left:0!important}.asbo-account-quick-card:nth-child(even){border-right:0!important}.asbo-account-quick-card:nth-child(-n+2){border-top:0!important}
.asbo-account-quick-card strong{grid-column:1!important;grid-row:1!important;align-self:end;color:var(--ase-navy)!important;font-size:14px!important;font-weight:750!important}.asbo-account-quick-card b{grid-column:1!important;grid-row:2!important;margin:0!important;color:var(--ase-muted)!important;font-size:15px!important;font-weight:650!important;letter-spacing:0!important}.asbo-account-quick-card p,.asbo-account-quick-card__index{display:none!important}.asbo-account-quick-card__arrow{position:static!important;grid-column:3!important;grid-row:1/3!important;align-self:center!important;color:#8A6C2A!important;font-size:18px!important}.asbo-account-quick-card__notice{grid-column:2!important;grid-row:1/3!important;align-self:center!important;display:grid!important;place-items:center;width:24px;height:24px;border-radius:50%;background:var(--ase-gold);color:var(--ase-navy);font-size:12px;font-weight:900;line-height:1}.asbo-account-quick-card:hover{background:var(--ase-cream)!important}
@media (min-width:768px){.asbo-account-page.asbo-account-logged-in .woocommerce{grid-template-rows:auto minmax(0,1fr)!important;align-content:start!important}.asbo-account-identity{grid-column:1!important;grid-row:1!important;align-self:start!important}.asbo-account-page .woocommerce-MyAccount-navigation{grid-column:1!important;grid-row:2!important;align-self:start!important}.asbo-account-page .woocommerce-MyAccount-content{grid-column:2!important;grid-row:1/span 2!important;align-self:start!important}}
@media (max-width:767px){.asbo-account-quick-grid{grid-template-columns:1fr!important}.asbo-account-quick-card,.asbo-account-quick-card:not(:first-child),.asbo-account-quick-card:last-child{min-height:76px!important;padding:14px 4px!important;border-right:0!important;border-top:1px solid var(--ase-line)!important}.asbo-account-quick-card:first-child{border-top:0!important}}


/* ASBO 1.2.2 SIDEBAR STRUCTURE FIX
   Identity + WooCommerce navigation are now one grid item. This prevents tall
   Dashboard/View Order content from stretching the row between them. */
.asbo-account-page .asbo-account-sidebar{
  min-width:0!important;
  max-width:100%!important;
  align-self:start!important;
}

@media (min-width:768px){
  .asbo-account-page.asbo-account-logged-in .woocommerce{
    grid-template-columns:clamp(190px,22vw,248px) minmax(0,1fr)!important;
    grid-template-areas:"sidebar content"!important;
    grid-template-rows:auto!important;
    column-gap:clamp(30px,4vw,56px)!important;
    row-gap:0!important;
    align-items:start!important;
  }
  .asbo-account-page .asbo-account-sidebar{
    grid-area:sidebar!important;
    align-self:start!important;
  }
  .asbo-account-page .asbo-account-identity{
    grid-area:auto!important;
    align-self:auto!important;
  }
  .asbo-account-page .woocommerce-MyAccount-navigation{
    grid-area:auto!important;
    align-self:auto!important;
    margin:0!important;
  }
  .asbo-account-page .woocommerce-MyAccount-content{
    grid-area:content!important;
    align-self:start!important;
  }
}

@media (min-width:768px) and (max-width:1099px){
  .asbo-account-page.asbo-account-logged-in .woocommerce{
    grid-template-columns:190px minmax(0,1fr)!important;
    grid-template-areas:"sidebar content"!important;
    grid-template-rows:auto!important;
    column-gap:30px!important;
  }
}

@media (max-width:767px){
  .asbo-account-page .asbo-account-sidebar{
    display:block!important;
    width:100%!important;
    margin:0!important;
  }
}


/* ASBO 1.2.3 DESKTOP ACCOUNT RAIL + QUICK ACCESS POLISH
   Deliberately desktop/tablet-only. The approved mobile account treatment is
   left unchanged. The customer identity + native WooCommerce nav now read as
   one continuous navy rail on every endpoint, while dashboard shortcuts become
   compact editorial quick links instead of four boxed/table-like buttons. */
@media (min-width:768px){
  .asbo-account-page.asbo-account-logged-in .woocommerce{
    grid-template-areas:"sidebar content"!important;
    grid-template-rows:auto!important;
    align-items:start!important;
  }

  .asbo-account-page .asbo-account-sidebar{
    grid-area:sidebar!important;
    display:flex!important;
    flex-direction:column!important;
    gap:0!important;
    align-self:start!important;
    width:100%!important;
    min-width:0!important;
    margin:0!important;
    padding:0!important;
    overflow:hidden!important;
    border:0!important;
    border-radius:6px!important;
    background:var(--ase-navy)!important;
    box-shadow:none!important;
  }

  .asbo-account-page .asbo-account-sidebar .asbo-account-identity{
    display:grid!important;
    grid-column:auto!important;
    grid-row:auto!important;
    width:100%!important;
    margin:0!important;
    padding:20px 18px 17px!important;
    border:0!important;
    border-bottom:1px solid rgba(210,169,82,.26)!important;
    border-radius:0!important;
    background:transparent!important;
    box-shadow:none!important;
  }

  .asbo-account-page .asbo-account-sidebar .woocommerce-MyAccount-navigation{
    display:block!important;
    grid-column:auto!important;
    grid-row:auto!important;
    width:100%!important;
    margin:0!important;
    padding:0!important;
    background:transparent!important;
  }

  .asbo-account-page .asbo-account-sidebar .woocommerce-MyAccount-navigation ul{
    display:block!important;
    width:100%!important;
    margin:0!important;
    padding:6px 14px 16px!important;
    border:0!important;
    border-radius:0!important;
    background:transparent!important;
    box-shadow:none!important;
  }

  .asbo-account-page .woocommerce-MyAccount-content{
    grid-area:content!important;
    grid-column:auto!important;
    grid-row:auto!important;
    align-self:start!important;
    min-width:0!important;
  }

  /* Editorial quick-access ledger: no outer box, no table-cell appearance. */
  .asbo-account-page .asbo-account-quick-grid{
    display:grid!important;
    grid-template-columns:repeat(2,minmax(0,1fr))!important;
    gap:0 32px!important;
    margin:26px 0 40px!important;
    padding:0!important;
    border:0!important;
    background:transparent!important;
  }

  .asbo-account-page .asbo-account-quick-card,
  .asbo-account-page .asbo-account-quick-card:not(:first-child),
  .asbo-account-page .asbo-account-quick-card:last-child{
    display:grid!important;
    grid-template-columns:minmax(0,1fr) auto auto!important;
    grid-template-rows:auto auto!important;
    column-gap:10px!important;
    row-gap:3px!important;
    min-height:72px!important;
    margin:0!important;
    padding:13px 0!important;
    border:0!important;
    border-bottom:1px solid var(--ase-line)!important;
    border-radius:0!important;
    background:transparent!important;
    box-shadow:none!important;
    transform:none!important;
  }

  .asbo-account-page .asbo-account-quick-card:nth-child(-n+2){
    border-top:1px solid var(--ase-line)!important;
  }

  .asbo-account-page .asbo-account-quick-card:hover,
  .asbo-account-page .asbo-account-quick-card:focus-visible{
    padding-left:10px!important;
    padding-right:10px!important;
    background:var(--ase-cream)!important;
    outline:none!important;
  }

  .asbo-account-page .asbo-account-quick-card strong{
    grid-column:1!important;
    grid-row:1!important;
    align-self:end!important;
    color:var(--ase-navy)!important;
    font-size:14px!important;
    font-weight:800!important;
    letter-spacing:0!important;
  }

  .asbo-account-page .asbo-account-quick-card b{
    grid-column:1!important;
    grid-row:2!important;
    margin:0!important;
    color:var(--ase-muted)!important;
    font-size:13px!important;
    font-weight:600!important;
    line-height:1.3!important;
  }

  .asbo-account-page .asbo-account-quick-card__arrow{
    position:static!important;
    grid-column:3!important;
    grid-row:1/3!important;
    align-self:center!important;
    color:#876A2A!important;
    font-size:18px!important;
    line-height:1!important;
  }

  .asbo-account-page .asbo-account-quick-card__notice{
    grid-column:2!important;
    grid-row:1/3!important;
    align-self:center!important;
    width:22px!important;
    height:22px!important;
    border:1px solid var(--ase-gold)!important;
    border-radius:50%!important;
    background:transparent!important;
    color:#725824!important;
    font-size:11px!important;
  }
}

@media (min-width:1100px){
  .asbo-account-page.asbo-account-logged-in .woocommerce{
    grid-template-columns:238px minmax(0,1fr)!important;
    column-gap:48px!important;
  }
}

@media (min-width:768px) and (max-width:1099px){
  .asbo-account-page.asbo-account-logged-in .woocommerce{
    grid-template-columns:190px minmax(0,1fr)!important;
    column-gap:30px!important;
  }
}


/* ASBO 1.2.4 TWO-CONTAINER ACCOUNT LAYOUT
   Desktop/tablet has exactly two sibling layout containers: the account rail
   and native WooCommerce content. Mobile styling/behavior is intentionally
   preserved from the approved account redesign. */
@media (min-width:768px){
  .asbo-account-page.asbo-account-logged-in > .woocommerce,
  .asbo-account-page.asbo-account-logged-in .woocommerce{
    display:grid!important;
    grid-template-areas:"asbo-sidebar asbo-main"!important;
    grid-template-rows:auto!important;
    align-items:start!important;
    align-content:start!important;
    width:100%!important;
    min-width:0!important;
  }

  .asbo-account-page.asbo-account-logged-in .woocommerce > .asbo-account-sidebar{
    grid-area:asbo-sidebar!important;
    display:flex!important;
    flex-direction:column!important;
    align-self:start!important;
    width:100%!important;
    min-width:0!important;
    margin:0!important;
    padding:0!important;
    overflow:hidden!important;
    border:0!important;
    border-radius:6px!important;
    background:var(--ase-navy)!important;
    box-shadow:none!important;
  }

  .asbo-account-page.asbo-account-logged-in .woocommerce > .woocommerce-MyAccount-content{
    grid-area:asbo-main!important;
    display:block!important;
    float:none!important;
    clear:none!important;
    width:100%!important;
    min-width:0!important;
    max-width:none!important;
    margin:0!important;
    align-self:start!important;
  }

  .asbo-account-page .asbo-account-sidebar > .asbo-account-identity,
  .asbo-account-page .asbo-account-sidebar > .woocommerce-MyAccount-navigation{
    flex:0 0 auto!important;
    width:100%!important;
    min-width:0!important;
    max-width:none!important;
    margin:0!important;
  }

  /* Quick access is a shared editorial link band, not four cards/table cells. */
  .asbo-account-page .asbo-account-quick-grid{
    display:flex!important;
    flex-wrap:wrap!important;
    align-items:stretch!important;
    gap:0 28px!important;
    margin:25px 0 40px!important;
    padding:10px 0!important;
    border-top:1px solid var(--ase-line)!important;
    border-bottom:1px solid var(--ase-line)!important;
    background:transparent!important;
  }

  .asbo-account-page .asbo-account-quick-card,
  .asbo-account-page .asbo-account-quick-card:not(:first-child),
  .asbo-account-page .asbo-account-quick-card:last-child,
  .asbo-account-page .asbo-account-quick-card:nth-child(-n+2){
    position:relative!important;
    display:grid!important;
    grid-template-columns:minmax(0,1fr) auto auto!important;
    grid-template-rows:auto auto!important;
    flex:1 1 180px!important;
    column-gap:8px!important;
    row-gap:2px!important;
    min-width:0!important;
    min-height:58px!important;
    margin:0!important;
    padding:10px 0!important;
    border:0!important;
    border-radius:0!important;
    background:transparent!important;
    box-shadow:none!important;
    transform:none!important;
  }

  .asbo-account-page .asbo-account-quick-card:hover,
  .asbo-account-page .asbo-account-quick-card:focus-visible{
    padding-left:0!important;
    padding-right:0!important;
    background:transparent!important;
    outline:none!important;
  }

  .asbo-account-page .asbo-account-quick-card:hover strong,
  .asbo-account-page .asbo-account-quick-card:focus-visible strong{
    color:#725824!important;
    text-decoration:underline!important;
    text-decoration-color:var(--ase-gold)!important;
    text-underline-offset:4px!important;
  }

  .asbo-account-page .asbo-account-quick-card strong{
    grid-column:1!important;
    grid-row:1!important;
    align-self:end!important;
    color:var(--ase-navy)!important;
    font-size:14px!important;
    font-weight:800!important;
    line-height:1.2!important;
  }

  .asbo-account-page .asbo-account-quick-card b{
    grid-column:1!important;
    grid-row:2!important;
    margin:0!important;
    color:var(--ase-muted)!important;
    font-size:12px!important;
    font-weight:600!important;
    line-height:1.25!important;
  }

  .asbo-account-page .asbo-account-quick-card__arrow{
    position:static!important;
    grid-column:3!important;
    grid-row:1/3!important;
    align-self:center!important;
    color:#876A2A!important;
    font-size:17px!important;
    line-height:1!important;
  }

  .asbo-account-page .asbo-account-quick-card__notice{
    grid-column:2!important;
    grid-row:1/3!important;
    align-self:center!important;
    display:grid!important;
    place-items:center!important;
    width:20px!important;
    height:20px!important;
    border:1px solid var(--ase-gold)!important;
    border-radius:50%!important;
    background:transparent!important;
    color:#725824!important;
    font-size:10px!important;
    font-weight:900!important;
    line-height:1!important;
  }
}

@media (min-width:1100px){
  .asbo-account-page.asbo-account-logged-in .woocommerce{
    grid-template-columns:238px minmax(0,1fr)!important;
    column-gap:48px!important;
  }
}

@media (min-width:768px) and (max-width:1099px){
  .asbo-account-page.asbo-account-logged-in .woocommerce{
    grid-template-columns:190px minmax(0,1fr)!important;
    column-gap:30px!important;
  }
  .asbo-account-page .asbo-account-quick-card{
    flex-basis:calc(50% - 14px)!important;
  }
}


/* ========================================================================== 
   ASBO 1.2.5 CUSTOMER PROJECT HUB
   Dashboard content is now useful project information instead of duplicate
   navigation. All Star navy/gold/cream, modest corners, borders-before-shadow,
   mobile-first reflow, and restrained production/editorial styling are kept.
   ========================================================================== */
.asbo-dashboard-project-hub{display:block;min-width:0}
.asbo-dashboard-project-hub [hidden]{display:none!important}

.asbo-project-hub__welcome{
  padding:4px 0 28px!important;
  border-bottom:1px solid var(--ase-line)!important;
}
.asbo-project-hub__welcome h2{
  margin:0 0 9px!important;
  font-family:"Roboto Slab",Georgia,serif!important;
  font-size:clamp(32px,4vw,46px)!important;
  line-height:1.08!important;
  color:var(--ase-navy)!important;
}
.asbo-project-hub__welcome p{
  max-width:700px;
  margin:0!important;
  color:var(--ase-muted)!important;
  font-size:16px!important;
  line-height:1.55!important;
}

.asbo-project-attention{
  margin:28px 0 0!important;
  padding:20px 0 0!important;
  border-top:2px solid var(--ase-red)!important;
}
.asbo-project-attention__heading{margin-bottom:8px}
.asbo-project-attention__heading .asbo-account-kicker{color:var(--ase-red)!important}
.asbo-project-attention__heading h3,
.asbo-current-project h3,
.asbo-project-orders h3,
.asbo-next-project h3{
  margin:0!important;
  font-family:"Roboto Slab",Georgia,serif!important;
  color:var(--ase-navy)!important;
}
.asbo-project-attention__items{display:grid;gap:0;border-top:1px solid var(--ase-line)}
.asbo-project-attention__item{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:18px;
  padding:15px 0;
  border-bottom:1px solid var(--ase-line);
}
.asbo-project-attention__item>div{display:flex;min-width:0;flex-direction:column;gap:3px}
.asbo-project-attention__item strong{color:var(--ase-navy);font-size:14px}
.asbo-project-attention__item span{color:var(--ase-muted);font-size:13px;line-height:1.45}
.asbo-project-attention__item a{
  flex:0 0 auto;
  color:var(--ase-red)!important;
  font-size:13px!important;
  font-weight:800!important;
  text-decoration:none!important;
}
.asbo-project-attention__item--needed a{color:#725824!important}
.asbo-project-attention__item a:hover{text-decoration:underline!important;text-underline-offset:3px}

.asbo-current-project{
  margin:30px 0 0!important;
  padding:26px 28px 24px!important;
  border:0!important;
  border-top:2px solid var(--ase-gold)!important;
  border-radius:0 0 6px 6px!important;
  background:var(--ase-cream)!important;
  box-shadow:none!important;
}
.asbo-current-project__top{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:22px;
}
.asbo-current-project__top h3{font-size:clamp(25px,3vw,34px)!important;line-height:1.12!important}
.asbo-current-project__state{
  display:inline-flex;
  align-items:center;
  min-height:28px;
  padding:5px 9px;
  border:1px solid #CAB985;
  border-radius:3px;
  background:rgba(255,255,255,.5);
  color:#6C5422;
  font-size:10px;
  font-weight:850;
  letter-spacing:.045em;
  line-height:1.1;
  text-transform:uppercase;
  white-space:nowrap;
}
.asbo-current-project__state--changes_requested{border-color:#D9A5A8;color:var(--ase-red);background:#FFF7F7}
.asbo-current-project__state--approved{border-color:var(--ase-navy);background:var(--ase-navy);color:var(--ase-white)}
.asbo-current-project__summary{
  max-width:720px;
  margin:12px 0 24px!important;
  color:var(--ase-muted)!important;
  font-size:15px!important;
  line-height:1.55!important;
}

.asbo-project-progress{
  display:grid;
  grid-template-columns:repeat(4,minmax(0,1fr));
  gap:0;
  margin:0 0 24px;
}
.asbo-project-progress__step{
  position:relative;
  min-width:0;
  padding:16px 12px 0 0;
  border-top:1px solid #CFC8BE;
  color:#858A91;
}
.asbo-project-progress__step:before{
  content:"";
  position:absolute;
  top:-4px;
  left:0;
  width:7px;
  height:7px;
  border:1px solid #B8B2A9;
  border-radius:50%;
  background:var(--ase-cream);
}
.asbo-project-progress__step.is-complete,
.asbo-project-progress__step.is-current{border-top-color:var(--ase-gold)}
.asbo-project-progress__step.is-complete:before{border-color:var(--ase-gold);background:var(--ase-gold)}
.asbo-project-progress__step.is-current:before{width:9px;height:9px;top:-5px;border:2px solid var(--ase-gold);background:var(--ase-navy)}
.asbo-project-progress__number{display:block;margin-bottom:3px;color:#777B83;font-size:9px;font-weight:800;letter-spacing:.1em}
.asbo-project-progress__step strong{display:block;color:inherit;font-size:12px;line-height:1.25}
.asbo-project-progress__step.is-current strong{color:var(--ase-navy)}
.asbo-project-progress__step.is-complete strong{color:#5B5F65}

.asbo-current-project__facts{
  display:grid;
  grid-template-columns:repeat(3,minmax(0,1fr));
  gap:18px;
  margin:0!important;
  padding:18px 0 0!important;
  border-top:1px solid #D5CEC4;
}
.asbo-current-project__facts div{min-width:0}
.asbo-current-project__facts dt{
  margin:0 0 3px;
  color:#777B83;
  font-size:9px;
  font-weight:850;
  letter-spacing:.1em;
  text-transform:uppercase;
}
.asbo-current-project__facts dd{margin:0;color:var(--ase-navy);font-size:13px;font-weight:700;overflow-wrap:anywhere}
.asbo-current-project__action{
  display:inline-flex!important;
  align-items:center;
  gap:12px;
  min-height:44px;
  margin-top:20px;
  padding:0!important;
  border:0!important;
  background:transparent!important;
  color:var(--ase-navy)!important;
  font-size:13px!important;
  font-weight:850!important;
  text-decoration:none!important;
}
.asbo-current-project__action span{color:#876A2A;font-size:18px}
.asbo-current-project__action:hover{text-decoration:underline!important;text-decoration-color:var(--ase-gold)!important;text-underline-offset:4px}

.asbo-project-orders{margin:38px 0 0!important;padding:0!important}
.asbo-project-section-heading{
  display:flex;
  align-items:flex-end;
  justify-content:space-between;
  gap:20px;
  margin-bottom:6px;
}
.asbo-project-section-heading h3{font-size:24px!important;line-height:1.2!important}
.asbo-project-section-heading>a{color:var(--ase-navy)!important;font-size:12px!important;font-weight:800!important;text-decoration:none!important}
.asbo-project-section-heading>a:hover{text-decoration:underline!important;text-underline-offset:3px}
.asbo-project-order-list{border-top:1px solid var(--ase-line)}
.asbo-project-order-row{
  display:grid!important;
  grid-template-columns:minmax(145px,1.25fr) minmax(85px,.8fr) minmax(72px,.65fr) minmax(125px,1fr) auto;
  align-items:center;
  gap:16px;
  min-height:70px;
  padding:12px 4px!important;
  border:0!important;
  border-bottom:1px solid var(--ase-line)!important;
  border-radius:0!important;
  background:transparent!important;
  color:inherit!important;
  box-shadow:none!important;
  text-decoration:none!important;
}
.asbo-project-order-row:hover{background:var(--ase-cream)!important}
.asbo-project-order-row__order{display:flex;min-width:0;flex-direction:column;gap:2px}
.asbo-project-order-row__order strong{color:var(--ase-navy);font-size:14px}
.asbo-project-order-row__order span{color:var(--ase-muted);font-size:11px}
.asbo-project-order-row__status,
.asbo-project-order-row__items{color:#5F646B;font-size:12px}
.asbo-project-order-row__artwork{
  justify-self:start;
  padding:4px 7px;
  border:1px solid var(--ase-line-dark);
  border-radius:3px;
  color:#686D74;
  font-size:9px;
  font-weight:800;
  letter-spacing:.035em;
  line-height:1.2;
  text-transform:uppercase;
}
.asbo-project-order-row__artwork--awaiting_review{border-color:#D9BD78;background:#FBF6E9;color:#725824}
.asbo-project-order-row__artwork--changes_requested{border-color:#D9A5A8;background:#FCF2F2;color:var(--ase-red)}
.asbo-project-order-row__artwork--approved{border-color:var(--ase-navy);background:var(--ase-navy);color:var(--ase-white)}
.asbo-project-order-row__artwork--needed{border-color:#C7B679;color:#725824}
.asbo-project-order-row__arrow{color:#876A2A;font-size:17px}
.asbo-project-orders__empty{padding:24px 0;border-top:1px solid var(--ase-line);border-bottom:1px solid var(--ase-line)}
.asbo-project-orders__empty p{margin:0!important;color:var(--ase-muted)!important}

.asbo-next-project{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:30px;
  margin:42px 0 0!important;
  padding:23px 26px!important;
  border:0!important;
  border-radius:6px!important;
  background:var(--ase-navy)!important;
  color:var(--ase-white)!important;
  box-shadow:none!important;
}
.asbo-next-project .asbo-account-kicker{color:var(--ase-gold)!important}
.asbo-next-project h3{color:var(--ase-white)!important;font-size:22px!important}
.asbo-next-project p{margin:6px 0 0!important;color:#C8CCD4!important;font-size:13px!important;line-height:1.45!important}
.asbo-next-project>a{
  display:inline-flex!important;
  align-items:center;
  justify-content:center;
  gap:10px;
  min-height:46px;
  flex:0 0 auto;
  padding:10px 15px!important;
  border:1px solid var(--ase-gold)!important;
  border-radius:5px!important;
  background:var(--ase-gold)!important;
  color:var(--ase-navy)!important;
  font-size:13px!important;
  font-weight:850!important;
  text-decoration:none!important;
}
.asbo-next-project>a:hover{background:#DFC070!important;border-color:#DFC070!important}

@media (max-width:767px){
  .asbo-project-hub__welcome{padding-bottom:22px!important}
  .asbo-project-hub__welcome h2{font-size:34px!important}
  .asbo-project-attention__item{align-items:flex-start;flex-direction:column;gap:8px;padding:14px 0}
  .asbo-current-project{margin-top:24px!important;padding:22px 18px 20px!important}
  .asbo-current-project__top{flex-direction:column;gap:10px}
  .asbo-current-project__state{white-space:normal}
  .asbo-project-progress{grid-template-columns:repeat(2,minmax(0,1fr));gap:16px 14px}
  .asbo-current-project__facts{grid-template-columns:1fr 1fr;gap:15px}
  .asbo-current-project__facts div:last-child{grid-column:1/-1}
  .asbo-project-section-heading{align-items:flex-start}
  .asbo-project-order-row{
    grid-template-columns:minmax(0,1fr) auto!important;
    gap:7px 14px!important;
    min-height:0!important;
    padding:15px 0!important;
  }
  .asbo-project-order-row__order{grid-column:1;grid-row:1}
  .asbo-project-order-row__status{grid-column:1;grid-row:2}
  .asbo-project-order-row__items{grid-column:1;grid-row:3}
  .asbo-project-order-row__artwork{grid-column:1;grid-row:4;margin-top:2px}
  .asbo-project-order-row__arrow{grid-column:2;grid-row:1/5;align-self:center}
  .asbo-next-project{align-items:flex-start;flex-direction:column;padding:22px 20px!important}
  .asbo-next-project>a{width:100%}
}

@media (max-width:420px){
  .asbo-current-project__facts{grid-template-columns:1fr}
  .asbo-current-project__facts div:last-child{grid-column:auto}
  .asbo-project-progress{grid-template-columns:1fr}
}

CSS;
    }
}
