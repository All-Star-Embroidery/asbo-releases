<?php
/**
 * ASBO artwork upload + review workflow.
 *
 * Consolidates the legacy post-checkout artwork snippet into the ASBO plugin,
 * while preserving its existing order metadata and protected file storage.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ASBO_Artwork_Review {
    private const META_FILES = '_ase_order_artwork_files';
    private const META_STATUS = '_ase_artwork_status';
    private const META_CUSTOMER_NOTES = '_ase_artwork_customer_notes';
    private const META_REVIEW_NOTE = '_asbo_artwork_review_note';
    private const META_HISTORY = '_asbo_artwork_history';
    private const META_REVIEWER = '_asbo_artwork_reviewer';
    private const META_REVIEWED_AT = '_asbo_artwork_reviewed_at';
    private const META_PLAN = '_asbo_artwork_plan';
    private const META_PREVIOUS_ORDER = '_asbo_previous_order_reference';

    public static function boot(): void {
        if ( did_action( 'plugins_loaded' ) ) {
            self::init();
            return;
        }

        add_action( 'plugins_loaded', array( __CLASS__, 'init' ), 20 );
    }

    public static function init(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // Customer experience: one artwork component on Thank You and My Account order pages.
        add_action( 'woocommerce_thankyou', array( __CLASS__, 'render_thankyou' ), 30 );
        add_action( 'woocommerce_view_order', array( __CLASS__, 'render_account' ), 20 );

        // Consolidated upload / review handlers.
        add_action( 'admin_post_asbo_upload_order_artwork', array( __CLASS__, 'handle_upload' ) );
        add_action( 'admin_post_nopriv_asbo_upload_order_artwork', array( __CLASS__, 'handle_upload' ) );
        add_action( 'admin_post_asbo_approve_artwork', array( __CLASS__, 'handle_approve' ) );
        add_action( 'admin_post_asbo_request_artwork_changes', array( __CLASS__, 'handle_request_changes' ) );
        add_action( 'wp_ajax_asbo_approve_artwork', array( __CLASS__, 'handle_approve' ) );
        add_action( 'wp_ajax_asbo_request_artwork_changes', array( __CLASS__, 'handle_request_changes' ) );
        add_action( 'admin_post_asbo_download_order_artwork', array( __CLASS__, 'handle_download' ) );

        // Native WooCommerce admin integration.
        add_action( 'add_meta_boxes', array( __CLASS__, 'register_review_metabox' ) );
        add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_classic_order_column' ), 20 );
        add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_classic_order_column' ), 20, 2 );
        add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_hpos_order_column' ), 20 );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_hpos_order_column' ), 20, 2 );

        // The old Code Snippets implementation can remain active during migration;
        // remove only its UI hooks so customers/admins never see duplicate artwork panels.
        add_action( 'wp_loaded', array( __CLASS__, 'disable_legacy_artwork_ui' ), 99 );
    }

    public static function disable_legacy_artwork_ui(): void {
        remove_action( 'woocommerce_thankyou', 'ase_render_thankyou_artwork_form', 30 );
        remove_action( 'woocommerce_view_order', 'ase_render_account_artwork_form', 20 );
        remove_action( 'woocommerce_admin_order_data_after_order_details', 'ase_show_artwork_in_admin_order', 20 );
    }

    public static function render_thankyou( $order_id ): void {
        self::render_customer_panel( absint( $order_id ), 'thankyou' );
    }

    public static function render_account( $order_id ): void {
        self::render_customer_panel( absint( $order_id ), 'account' );
    }

    private static function render_customer_panel( int $order_id, string $context ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order instanceof WC_Order || self::order_is_inactive( $order ) ) {
            return;
        }

        if (
            'account' === $context
            && ! current_user_can( 'manage_woocommerce' )
            && ! current_user_can( 'view_order', $order_id )
        ) {
            return;
        }

        $files = self::get_files( $order );
        $status = self::normalized_status( $order, $files );
        $plan = sanitize_key( (string) $order->get_meta( self::META_PLAN, true ) );
        $review_note = sanitize_textarea_field( (string) $order->get_meta( self::META_REVIEW_NOTE, true ) );
        $history = self::get_history( $order );
        $return_url = 'account' === $context
            ? wc_get_endpoint_url( 'view-order', $order_id, wc_get_page_permalink( 'myaccount' ) )
            : $order->get_checkout_order_received_url();

        $success = isset( $_GET['asbo-artwork'] ) && 'success' === sanitize_key( wp_unslash( $_GET['asbo-artwork'] ) );
        $error = isset( $_GET['asbo-artwork-error'] )
            ? sanitize_text_field( wp_unslash( $_GET['asbo-artwork-error'] ) )
            : '';

        $status_label = self::status_label( $status );
        $status_class = self::status_class( $status );
        ?>
        <section id="asbo-artwork" class="asbo-artwork-customer" aria-labelledby="asbo-artwork-heading-<?php echo esc_attr( $order_id ); ?>">
            <?php self::customer_styles(); ?>

            <div class="asbo-artwork-customer__heading">
                <div>
                    <span class="asbo-artwork-customer__eyebrow"><?php esc_html_e( 'Order artwork', 'all-star-bulk-order' ); ?></span>
                    <h2 id="asbo-artwork-heading-<?php echo esc_attr( $order_id ); ?>"><?php esc_html_e( 'Artwork', 'all-star-bulk-order' ); ?></h2>
                </div>
                <span class="asbo-artwork-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
            </div>

            <?php if ( $success ) : ?>
                <div class="asbo-artwork-message asbo-artwork-message--success">
                    <?php esc_html_e( 'Your artwork was attached to the order and sent to our team for review.', 'all-star-bulk-order' ); ?>
                </div>
            <?php elseif ( $error ) : ?>
                <div class="asbo-artwork-message asbo-artwork-message--error"><?php echo esc_html( $error ); ?></div>
            <?php endif; ?>

            <?php self::render_customer_status_copy( $order, $status, $plan, $review_note ); ?>

            <?php if ( $files ) : ?>
                <div class="asbo-artwork-files">
                    <strong><?php esc_html_e( 'Artwork on this order', 'all-star-bulk-order' ); ?></strong>
                    <div class="asbo-artwork-files__list">
                        <?php foreach ( array_reverse( $files, true ) as $index => $file ) : ?>
                            <div class="asbo-artwork-file">
                                <span class="asbo-artwork-file__icon" aria-hidden="true">↗</span>
                                <span>
                                    <b><?php echo esc_html( $file['original_name'] ?? $file['name'] ?? __( 'Artwork file', 'all-star-bulk-order' ) ); ?></b>
                                    <?php if ( ! empty( $file['uploaded_at'] ) ) : ?>
                                        <small><?php echo esc_html( self::format_datetime( $file['uploaded_at'] ) ); ?></small>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ( 'previous_order' === $plan ) : ?>
                <div class="asbo-artwork-reference">
                    <strong><?php esc_html_e( 'Previous artwork requested', 'all-star-bulk-order' ); ?></strong>
                    <span><?php echo esc_html( (string) $order->get_meta( self::META_PREVIOUS_ORDER, true ) ?: __( 'Our team will locate the previous approved artwork.', 'all-star-bulk-order' ) ); ?></span>
                </div>
            <?php endif; ?>

            <?php
            $show_open_form = ( 'needed' === $status && 'previous_order' !== $plan ) || 'changes_requested' === $status;
            $show_replacement_form = 'awaiting_review' === $status && ! empty( $files );

            if ( $show_open_form ) {
                self::render_upload_form( $order, $return_url, 'changes_requested' === $status );
            } elseif ( $show_replacement_form ) {
                ?>
                <details class="asbo-artwork-replace">
                    <summary><?php esc_html_e( 'Need to replace the submitted artwork?', 'all-star-bulk-order' ); ?></summary>
                    <?php self::render_upload_form( $order, $return_url, true ); ?>
                </details>
                <?php
            }
            ?>

            <?php if ( $history ) : ?>
                <details class="asbo-artwork-history">
                    <summary><?php esc_html_e( 'Artwork history', 'all-star-bulk-order' ); ?></summary>
                    <ol>
                        <?php foreach ( array_reverse( $history ) as $event ) : ?>
                            <?php if ( ! empty( $event['internal'] ) ) { continue; } ?>
                            <li>
                                <span class="asbo-artwork-history__dot" aria-hidden="true"></span>
                                <div>
                                    <strong><?php echo esc_html( $event['label'] ?? __( 'Artwork updated', 'all-star-bulk-order' ) ); ?></strong>
                                    <?php if ( ! empty( $event['message'] ) ) : ?>
                                        <p><?php echo esc_html( $event['message'] ); ?></p>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $event['timestamp'] ) ) : ?>
                                        <small><?php echo esc_html( self::format_datetime( $event['timestamp'] ) ); ?></small>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </details>
            <?php endif; ?>

            <p class="asbo-artwork-stitch-note">
                <strong><?php esc_html_e( '10K stitch allowance:', 'all-star-bulk-order' ); ?></strong>
                <?php esc_html_e( 'Standard embroidery pricing includes up to 10,000 stitches per design. Additional charges may apply for larger or more complex artwork and will be communicated prior to production.', 'all-star-bulk-order' ); ?>
            </p>
        </section>
        <?php
    }

    private static function render_customer_status_copy( WC_Order $order, string $status, string $plan, string $review_note ): void {
        if ( 'approved' === $status ) {
            ?>
            <div class="asbo-artwork-state asbo-artwork-state--approved">
                <span class="asbo-artwork-state__icon" aria-hidden="true">✓</span>
                <div>
                    <strong><?php esc_html_e( 'Your artwork is approved.', 'all-star-bulk-order' ); ?></strong>
                    <p><?php esc_html_e( 'The reviewed artwork is cleared for production. If anything else is needed, our team will contact you before production begins.', 'all-star-bulk-order' ); ?></p>
                </div>
            </div>
            <?php
            return;
        }

        if ( 'changes_requested' === $status ) {
            ?>
            <div class="asbo-artwork-state asbo-artwork-state--changes">
                <span class="asbo-artwork-state__icon" aria-hidden="true">!</span>
                <div>
                    <strong><?php esc_html_e( 'We need a change before the artwork can be approved.', 'all-star-bulk-order' ); ?></strong>
                    <?php if ( $review_note ) : ?>
                        <p><?php echo nl2br( esc_html( $review_note ) ); ?></p>
                    <?php else : ?>
                        <p><?php esc_html_e( 'Please upload revised artwork below. Our team will review it again before production.', 'all-star-bulk-order' ); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            return;
        }

        if ( 'awaiting_review' === $status ) {
            $copy = 'previous_order' === $plan
                ? __( 'We have your previous-order artwork reference. Our team will confirm the correct approved artwork before production.', 'all-star-bulk-order' )
                : ( 'design_help' === $plan
                    ? __( 'Your request for artwork or digitizing help is with our team for review. We will contact you before production.', 'all-star-bulk-order' )
                    : __( 'We received your artwork. Our team will review file quality, placement, thread matching, complexity, and stitch requirements before production.', 'all-star-bulk-order' ) );
            ?>
            <div class="asbo-artwork-state asbo-artwork-state--review">
                <span class="asbo-artwork-state__icon" aria-hidden="true">⌛</span>
                <div>
                    <strong><?php esc_html_e( 'Artwork received — review pending.', 'all-star-bulk-order' ); ?></strong>
                    <p><?php echo esc_html( $copy ); ?></p>
                </div>
            </div>
            <?php
            return;
        }

        ?>
        <p class="asbo-artwork-customer__intro">
            <?php
            echo esc_html(
                sprintf(
                    __( 'Attach logo artwork to order #%s. Our team will review the file and contact you before production if anything needs changed.', 'all-star-bulk-order' ),
                    $order->get_order_number()
                )
            );
            ?>
        </p>
        <?php
    }

    private static function render_upload_form( WC_Order $order, string $return_url, bool $revision ): void {
        $order_id = $order->get_id();
        ?>
        <form class="asbo-artwork-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="asbo_upload_order_artwork">
            <input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">
            <input type="hidden" name="order_key" value="<?php echo esc_attr( $order->get_order_key() ); ?>">
            <input type="hidden" name="return_url" value="<?php echo esc_url( $return_url ); ?>">
            <?php wp_nonce_field( 'asbo_upload_order_artwork_' . $order_id, 'asbo_artwork_nonce' ); ?>

            <label class="asbo-artwork-field">
                <span><?php echo esc_html( $revision ? __( 'Revised artwork files', 'all-star-bulk-order' ) : __( 'Artwork files', 'all-star-bulk-order' ) ); ?></span>
                <input type="file" name="asbo_artwork_files[]" accept=".jpg,.jpeg,.png,.pdf" multiple required>
                <small><?php esc_html_e( 'Upload up to 5 JPG, PNG, or PDF files. Maximum 10 MB per file.', 'all-star-bulk-order' ); ?></small>
            </label>

            <label class="asbo-artwork-field">
                <span><?php esc_html_e( 'Artwork notes', 'all-star-bulk-order' ); ?></span>
                <textarea name="asbo_artwork_notes" rows="4" placeholder="<?php esc_attr_e( 'Example: Use the white version of the logo on navy hats. Match the gold thread to our brand color.', 'all-star-bulk-order' ); ?>"></textarea>
            </label>

            <button class="asbo-artwork-primary" type="submit">
                <?php echo esc_html( $revision ? __( 'Submit Revised Artwork', 'all-star-bulk-order' ) : __( 'Submit Artwork for Review', 'all-star-bulk-order' ) ); ?>
            </button>
        </form>
        <?php
    }

    public static function handle_upload(): void {
        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';
        $return_url = isset( $_POST['return_url'] )
            ? wp_validate_redirect( wp_unslash( $_POST['return_url'] ), wc_get_checkout_url() )
            : wc_get_checkout_url();

        $order = wc_get_order( $order_id );
        if ( ! $order instanceof WC_Order ) {
            self::redirect_error( $return_url, __( 'The order could not be found.', 'all-star-bulk-order' ) );
        }

        $nonce = isset( $_POST['asbo_artwork_nonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['asbo_artwork_nonce'] ) )
            : '';
        if ( ! wp_verify_nonce( $nonce, 'asbo_upload_order_artwork_' . $order_id ) ) {
            self::redirect_error( $return_url, __( 'The upload link expired. Reload the order page and try again.', 'all-star-bulk-order' ) );
        }

        if ( ! hash_equals( (string) $order->get_order_key(), (string) $order_key ) ) {
            self::redirect_error( $return_url, __( 'This upload link does not belong to that order.', 'all-star-bulk-order' ) );
        }

        if (
            is_user_logged_in()
            && ! current_user_can( 'manage_woocommerce' )
            && (int) $order->get_user_id() > 0
            && (int) $order->get_user_id() !== get_current_user_id()
        ) {
            self::redirect_error( $return_url, __( 'You do not have permission to update this order.', 'all-star-bulk-order' ) );
        }

        if ( self::order_is_inactive( $order ) ) {
            self::redirect_error( $return_url, __( 'Artwork can no longer be uploaded for this order.', 'all-star-bulk-order' ) );
        }

        if ( 'approved' === self::normalized_status( $order ) && ! current_user_can( 'manage_woocommerce' ) ) {
            self::redirect_error( $return_url, __( 'This artwork has already been approved. Contact us if the approved artwork needs to be replaced.', 'all-star-bulk-order' ) );
        }

        if (
            empty( $_FILES['asbo_artwork_files'] )
            || empty( $_FILES['asbo_artwork_files']['name'] )
            || ! is_array( $_FILES['asbo_artwork_files']['name'] )
        ) {
            self::redirect_error( $return_url, __( 'Choose at least one artwork file.', 'all-star-bulk-order' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $allowed_mimes = array(
            'jpg|jpeg' => 'image/jpeg',
            'png'      => 'image/png',
            'pdf'      => 'application/pdf',
        );
        $upload_data = $_FILES['asbo_artwork_files']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $file_count = count( $upload_data['name'] );
        if ( $file_count > 5 ) {
            self::redirect_error( $return_url, __( 'Upload no more than 5 files at one time.', 'all-star-bulk-order' ) );
        }

        $existing_files = self::get_files( $order );
        $status_before = self::normalized_status( $order, $existing_files );
        $uploaded_files = array();
        $errors = array();
        $submission_id = wp_generate_uuid4();

        self::ensure_protected_artwork_directory();

        $upload_dir_filter = static function ( $dirs ) use ( $order_id ) {
            $subdir = '/all-star-artwork/order-' . $order_id;
            $dirs['subdir'] = $subdir;
            $dirs['path'] = $dirs['basedir'] . $subdir;
            $dirs['url'] = $dirs['baseurl'] . $subdir;
            return $dirs;
        };

        add_filter( 'upload_dir', $upload_dir_filter );

        for ( $index = 0; $index < $file_count; $index++ ) {
            $error_code = (int) $upload_data['error'][ $index ];
            if ( UPLOAD_ERR_NO_FILE === $error_code ) {
                continue;
            }

            $original_name = sanitize_file_name( wp_unslash( $upload_data['name'][ $index ] ) );
            $file_size = (int) $upload_data['size'][ $index ];

            if ( UPLOAD_ERR_OK !== $error_code ) {
                $errors[] = sprintf( __( '%s could not be uploaded.', 'all-star-bulk-order' ), $original_name );
                continue;
            }
            if ( $file_size > 10 * MB_IN_BYTES ) {
                $errors[] = sprintf( __( '%s is larger than 10 MB.', 'all-star-bulk-order' ), $original_name );
                continue;
            }

            $temp_name = $upload_data['tmp_name'][ $index ];
            $filetype = wp_check_filetype_and_ext( $temp_name, $original_name, $allowed_mimes );
            if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], $allowed_mimes, true ) ) {
                $errors[] = sprintf( __( '%s is not an allowed JPG, PNG, or PDF file.', 'all-star-bulk-order' ), $original_name );
                continue;
            }

            $file = array(
                'name'     => wp_generate_uuid4() . '-' . $original_name,
                'type'     => $upload_data['type'][ $index ],
                'tmp_name' => $temp_name,
                'error'    => 0,
                'size'     => $file_size,
            );
            $result = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => $allowed_mimes ) );
            if ( isset( $result['error'] ) ) {
                $errors[] = sanitize_text_field( $result['error'] );
                continue;
            }

            $uploaded_files[] = array(
                'original_name' => $original_name,
                'name'          => basename( $result['file'] ),
                'path'          => wp_normalize_path( $result['file'] ),
                'url'           => esc_url_raw( $result['url'] ),
                'mime'          => sanitize_mime_type( $result['type'] ),
                'size'          => $file_size,
                'uploaded_at'   => current_time( 'mysql' ),
                'uploaded_by'   => get_current_user_id(),
                'submission_id' => $submission_id,
            );
        }

        remove_filter( 'upload_dir', $upload_dir_filter );

        if ( ! $uploaded_files ) {
            $message = $errors
                ? implode( ' ', array_map( 'sanitize_text_field', $errors ) )
                : __( 'No artwork files were uploaded.', 'all-star-bulk-order' );
            self::redirect_error( $return_url, $message );
        }

        $notes = isset( $_POST['asbo_artwork_notes'] )
            ? sanitize_textarea_field( wp_unslash( $_POST['asbo_artwork_notes'] ) )
            : '';
        $all_files = array_merge( $existing_files, $uploaded_files );
        $is_revision = in_array( $status_before, array( 'changes_requested', 'awaiting_review' ), true ) || ! empty( $existing_files );

        $order->update_meta_data( self::META_FILES, $all_files );
        $order->update_meta_data( self::META_STATUS, 'awaiting_review' );
        $order->delete_meta_data( self::META_REVIEW_NOTE );
        if ( $notes ) {
            $order->update_meta_data( self::META_CUSTOMER_NOTES, $notes );
        }

        self::append_history(
            $order,
            array(
                'type'      => $is_revision ? 'resubmitted' : 'submitted',
                'label'     => $is_revision ? __( 'Revised artwork submitted', 'all-star-bulk-order' ) : __( 'Artwork submitted', 'all-star-bulk-order' ),
                'message'   => $notes,
                'files'     => array_values( wp_list_pluck( $uploaded_files, 'original_name' ) ),
                'timestamp' => current_time( 'mysql' ),
                'user_id'   => get_current_user_id(),
            )
        );
        $order->save();

        $file_names = implode( ', ', array_map( 'sanitize_text_field', wp_list_pluck( $uploaded_files, 'original_name' ) ) );
        $order->add_order_note(
            sprintf(
                $is_revision ? __( 'Customer submitted revised artwork: %s', 'all-star-bulk-order' ) : __( 'Customer uploaded artwork: %s', 'all-star-bulk-order' ),
                $file_names
            )
        );
        if ( $notes ) {
            $order->add_order_note( sprintf( __( 'Customer artwork notes: %s', 'all-star-bulk-order' ), $notes ) );
        }

        self::send_admin_submission_email( $order, $uploaded_files, $notes, $is_revision );

        $args = array( 'asbo-artwork' => 'success' );
        if ( $errors ) {
            $args['asbo-artwork-error'] = implode( ' ', array_map( 'sanitize_text_field', $errors ) );
        }
        wp_safe_redirect( add_query_arg( $args, $return_url ) );
        exit;
    }

    private static function ensure_protected_artwork_directory(): void {
        $uploads = wp_upload_dir();
        $base = trailingslashit( $uploads['basedir'] ) . 'all-star-artwork';
        if ( ! is_dir( $base ) ) {
            wp_mkdir_p( $base );
        }

        $htaccess = trailingslashit( $base ) . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            $rules = "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
            file_put_contents( $htaccess, $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }

        $index = trailingslashit( $base ) . 'index.php';
        if ( ! file_exists( $index ) ) {
            file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }
    }

    public static function register_review_metabox(): void {
        $screens = array( 'shop_order' );
        if ( function_exists( 'wc_get_page_screen_id' ) ) {
            $screens[] = wc_get_page_screen_id( 'shop-order' );
        }

        foreach ( array_unique( array_filter( $screens ) ) as $screen ) {
            add_meta_box(
                'asbo-artwork-review',
                __( 'ASBO Artwork Review', 'all-star-bulk-order' ),
                array( __CLASS__, 'render_review_metabox' ),
                $screen,
                'normal',
                'high'
            );
        }
    }

    public static function render_review_metabox( $post_or_order ): void {
        $order = self::resolve_order( $post_or_order );
        if ( ! $order instanceof WC_Order ) {
            echo '<p>' . esc_html__( 'Order unavailable.', 'all-star-bulk-order' ) . '</p>';
            return;
        }

        $files = self::get_files( $order );
        $status = self::normalized_status( $order, $files );
        $history = self::get_history( $order );
        $notes = sanitize_textarea_field( (string) $order->get_meta( self::META_CUSTOMER_NOTES, true ) );
        $review_note = sanitize_textarea_field( (string) $order->get_meta( self::META_REVIEW_NOTE, true ) );
        $plan = sanitize_key( (string) $order->get_meta( self::META_PLAN, true ) );
        $previous_order = sanitize_text_field( (string) $order->get_meta( self::META_PREVIOUS_ORDER, true ) );
        $can_review = ! empty( $files ) || in_array( $plan, array( 'previous_order', 'design_help' ), true );

        self::admin_styles();
        ?>
        <div class="asbo-review-admin">
            <?php if ( isset( $_GET['asbo-artwork-action'] ) ) : ?>
                <?php $action = sanitize_key( wp_unslash( $_GET['asbo-artwork-action'] ) ); ?>
                <div class="notice inline <?php echo 'error' === $action ? 'notice-error' : 'notice-success'; ?>">
                    <p>
                        <?php
                        if ( 'approved' === $action ) {
                            esc_html_e( 'Artwork approved and customer email sent.', 'all-star-bulk-order' );
                        } elseif ( 'changes-requested' === $action ) {
                            esc_html_e( 'Changes requested and customer email sent.', 'all-star-bulk-order' );
                        } elseif ( 'error' === $action ) {
                            echo esc_html( isset( $_GET['asbo-artwork-message'] ) ? sanitize_text_field( wp_unslash( $_GET['asbo-artwork-message'] ) ) : __( 'Artwork action could not be completed.', 'all-star-bulk-order' ) );
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="asbo-review-admin__top">
                <div>
                    <span class="asbo-review-admin__eyebrow"><?php esc_html_e( 'Artwork workflow', 'all-star-bulk-order' ); ?></span>
                    <h3><?php echo esc_html( sprintf( __( 'Order #%s', 'all-star-bulk-order' ), $order->get_order_number() ) ); ?></h3>
                </div>
                <span class="asbo-artwork-status <?php echo esc_attr( self::status_class( $status ) ); ?>"><?php echo esc_html( self::status_label( $status ) ); ?></span>
            </div>

            <div class="asbo-review-admin__grid">
                <div class="asbo-review-admin__files">
                    <h4><?php esc_html_e( 'Submitted artwork', 'all-star-bulk-order' ); ?></h4>
                    <?php if ( $files ) : ?>
                        <?php foreach ( array_reverse( $files, true ) as $index => $file ) : ?>
                            <article class="asbo-review-file-card">
                                <?php if ( ! empty( $file['mime'] ) && 0 === strpos( (string) $file['mime'], 'image/' ) ) : ?>
                                    <a class="asbo-review-file-card__preview" href="<?php echo esc_url( self::download_url( $order, $index ) ); ?>" target="_blank" rel="noopener">
                                        <img src="<?php echo esc_url( self::download_url( $order, $index, true ) ); ?>" alt="">
                                    </a>
                                <?php else : ?>
                                    <div class="asbo-review-file-card__document" aria-hidden="true">FILE</div>
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo esc_html( $file['original_name'] ?? $file['name'] ?? __( 'Artwork file', 'all-star-bulk-order' ) ); ?></strong>
                                    <?php if ( ! empty( $file['uploaded_at'] ) ) : ?>
                                        <small><?php echo esc_html( self::format_datetime( $file['uploaded_at'] ) ); ?></small>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url( self::download_url( $order, $index ) ); ?>"><?php esc_html_e( 'Download', 'all-star-bulk-order' ); ?></a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php elseif ( 'previous_order' === $plan ) : ?>
                        <div class="asbo-review-empty">
                            <strong><?php esc_html_e( 'Reuse previous artwork', 'all-star-bulk-order' ); ?></strong>
                            <p><?php echo esc_html( $previous_order ?: __( 'Locate the customer’s previously approved artwork.', 'all-star-bulk-order' ) ); ?></p>
                        </div>
                    <?php elseif ( 'design_help' === $plan ) : ?>
                        <div class="asbo-review-empty">
                            <strong><?php esc_html_e( 'Artwork / digitizing help requested', 'all-star-bulk-order' ); ?></strong>
                            <p><?php esc_html_e( 'The customer asked the All Star team for assistance before production.', 'all-star-bulk-order' ); ?></p>
                        </div>
                    <?php else : ?>
                        <div class="asbo-review-empty">
                            <strong><?php esc_html_e( 'Waiting for customer artwork', 'all-star-bulk-order' ); ?></strong>
                            <p><?php esc_html_e( 'No artwork files are attached to this order yet.', 'all-star-bulk-order' ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="asbo-review-admin__details">
                    <h4><?php esc_html_e( 'Review details', 'all-star-bulk-order' ); ?></h4>
                    <dl>
                        <div><dt><?php esc_html_e( 'Artwork plan', 'all-star-bulk-order' ); ?></dt><dd><?php echo esc_html( self::plan_label( $plan ) ); ?></dd></div>
                        <?php if ( $previous_order ) : ?><div><dt><?php esc_html_e( 'Previous order', 'all-star-bulk-order' ); ?></dt><dd><?php echo esc_html( $previous_order ); ?></dd></div><?php endif; ?>
                        <div><dt><?php esc_html_e( 'Customer', 'all-star-bulk-order' ); ?></dt><dd><?php echo esc_html( trim( $order->get_formatted_billing_full_name() ) ?: $order->get_billing_email() ); ?></dd></div>
                    </dl>

                    <?php if ( $notes ) : ?>
                        <div class="asbo-review-note"><strong><?php esc_html_e( 'Customer artwork notes', 'all-star-bulk-order' ); ?></strong><p><?php echo nl2br( esc_html( $notes ) ); ?></p></div>
                    <?php endif; ?>

                    <div class="asbo-review-stitch">
                        <strong><?php esc_html_e( '10K stitch allowance', 'all-star-bulk-order' ); ?></strong>
                        <p><?php esc_html_e( 'Listed embroidery pricing includes designs up to 10,000 stitches. If size, complexity, or stitch count requires an additional charge, communicate it to the customer before production.', 'all-star-bulk-order' ); ?></p>
                    </div>
                </div>
            </div>

            <?php if ( $history ) : ?>
                <div class="asbo-review-admin__history">
                    <h4><?php esc_html_e( 'Review history', 'all-star-bulk-order' ); ?></h4>
                    <ol>
                        <?php foreach ( array_reverse( $history ) as $event ) : ?>
                            <li>
                                <span aria-hidden="true"></span>
                                <div>
                                    <strong><?php echo esc_html( $event['label'] ?? __( 'Artwork updated', 'all-star-bulk-order' ) ); ?></strong>
                                    <?php if ( ! empty( $event['message'] ) ) : ?><p><?php echo esc_html( $event['message'] ); ?></p><?php endif; ?>
                                    <small><?php echo esc_html( self::format_datetime( $event['timestamp'] ?? '' ) ); ?><?php echo ! empty( $event['actor'] ) ? ' · ' . esc_html( $event['actor'] ) : ''; ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>

            <div class="asbo-review-admin__actions" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" data-approve-nonce="<?php echo esc_attr( wp_create_nonce( 'asbo_approve_artwork_' . $order->get_id() ) ); ?>" data-changes-nonce="<?php echo esc_attr( wp_create_nonce( 'asbo_request_artwork_changes_' . $order->get_id() ) ); ?>">
                <?php if ( $can_review && 'approved' !== $status ) : ?>
                    <button class="button button-primary asbo-review-approve asbo-review-action-approve" type="button">✓ <?php esc_html_e( 'Approve Artwork', 'all-star-bulk-order' ); ?></button>
                <?php endif; ?>

                <?php if ( $can_review ) : ?>
                    <div class="asbo-review-change-form">
                        <label>
                            <span><?php esc_html_e( 'Reason for changes', 'all-star-bulk-order' ); ?></span>
                            <textarea class="asbo-review-change-note" rows="3" placeholder="<?php esc_attr_e( 'Example: The fine lettering is too small to reproduce cleanly. Please upload a vector file or approve simplifying this area.', 'all-star-bulk-order' ); ?>"><?php echo esc_textarea( 'changes_requested' === $status ? $review_note : '' ); ?></textarea>
                        </label>
                        <button class="button asbo-review-changes asbo-review-action-changes" type="button"><?php esc_html_e( 'Request Changes & Email Customer', 'all-star-bulk-order' ); ?></button>
                    </div>
                <?php elseif ( 'approved' !== $status ) : ?>
                    <p class="asbo-review-awaiting"><?php esc_html_e( 'Review actions will appear after artwork is submitted.', 'all-star-bulk-order' ); ?></p>
                <?php endif; ?>
                <p class="asbo-review-action-feedback" role="status" aria-live="polite"></p>
            </div>
            <?php self::admin_action_script(); ?>
        </div>
        <?php
    }

    public static function handle_approve(): void {
        self::require_admin_artwork_permission();
        $order_id = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;
        $nonce = isset( $_REQUEST['asbo_artwork_review_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['asbo_artwork_review_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'asbo_approve_artwork_' . $order_id ) ) {
            self::admin_action_error( null, __( 'The artwork approval link expired. Reload the order and try again.', 'all-star-bulk-order' ), 403 );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order instanceof WC_Order ) {
            self::admin_action_error( null, __( 'Order not found.', 'all-star-bulk-order' ), 404 );
        }

        $files = self::get_files( $order );
        $plan = sanitize_key( (string) $order->get_meta( self::META_PLAN, true ) );
        if ( ! $files && ! in_array( $plan, array( 'previous_order', 'design_help' ), true ) ) {
            self::admin_action_error( $order, __( 'There is no artwork to approve yet.', 'all-star-bulk-order' ) );
        }

        $actor = wp_get_current_user();
        $actor_name = $actor && $actor->exists() ? $actor->display_name : __( 'Store administrator', 'all-star-bulk-order' );
        $now = current_time( 'mysql' );

        $order->delete_meta_data( self::META_REVIEW_NOTE );
        $order->update_meta_data( self::META_REVIEWER, get_current_user_id() );
        $order->update_meta_data( self::META_REVIEWED_AT, $now );
        self::append_history(
            $order,
            array(
                'type'      => 'approved',
                'label'     => __( 'Artwork approved', 'all-star-bulk-order' ),
                'message'   => __( 'Artwork cleared for production.', 'all-star-bulk-order' ),
                'timestamp' => $now,
                'user_id'   => get_current_user_id(),
                'actor'     => $actor_name,
            )
        );

        if ( ! self::persist_artwork_status( $order, 'approved' ) ) {
            self::admin_action_error( $order, __( 'Artwork approval could not be saved. Please reload the order and try again.', 'all-star-bulk-order' ) );
        }

        $verified = wc_get_order( $order_id );
        if ( ! $verified instanceof WC_Order || 'approved' !== self::normalized_status( $verified ) ) {
            self::admin_action_error( $order, __( 'Artwork approval did not verify after saving. Please try again.', 'all-star-bulk-order' ) );
        }

        $verified->add_order_note( sprintf( __( 'Artwork approved by %s.', 'all-star-bulk-order' ), $actor_name ) );
        self::send_customer_approved_email( $verified );
        self::admin_action_success( $verified, 'approved', __( 'Artwork approved. The customer has been notified.', 'all-star-bulk-order' ) );
    }

    public static function handle_request_changes(): void {
        self::require_admin_artwork_permission();
        $order_id = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;
        $nonce = isset( $_REQUEST['asbo_artwork_review_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['asbo_artwork_review_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'asbo_request_artwork_changes_' . $order_id ) ) {
            self::admin_action_error( null, __( 'The artwork review link expired. Reload the order and try again.', 'all-star-bulk-order' ), 403 );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order instanceof WC_Order ) {
            self::admin_action_error( null, __( 'Order not found.', 'all-star-bulk-order' ), 404 );
        }

        $review_note = isset( $_REQUEST['review_note'] ) ? sanitize_textarea_field( wp_unslash( $_REQUEST['review_note'] ) ) : '';
        if ( '' === trim( $review_note ) ) {
            self::admin_action_error( $order, __( 'Add a reason before requesting artwork changes.', 'all-star-bulk-order' ) );
        }

        $actor = wp_get_current_user();
        $actor_name = $actor && $actor->exists() ? $actor->display_name : __( 'Store administrator', 'all-star-bulk-order' );
        $now = current_time( 'mysql' );

        $order->update_meta_data( self::META_REVIEW_NOTE, $review_note );
        $order->update_meta_data( self::META_REVIEWER, get_current_user_id() );
        $order->update_meta_data( self::META_REVIEWED_AT, $now );
        self::append_history(
            $order,
            array(
                'type'      => 'changes_requested',
                'label'     => __( 'Changes requested by our team', 'all-star-bulk-order' ),
                'message'   => $review_note,
                'timestamp' => $now,
                'user_id'   => get_current_user_id(),
                'actor'     => $actor_name,
            )
        );

        if ( ! self::persist_artwork_status( $order, 'changes_requested' ) ) {
            self::admin_action_error( $order, __( 'The artwork review status could not be saved. Please reload the order and try again.', 'all-star-bulk-order' ) );
        }

        $verified = wc_get_order( $order_id );
        if ( ! $verified instanceof WC_Order || 'changes_requested' !== self::normalized_status( $verified ) ) {
            self::admin_action_error( $order, __( 'The requested-changes status did not verify after saving. Please try again.', 'all-star-bulk-order' ) );
        }

        $verified->add_order_note( sprintf( __( 'Artwork changes requested by %1$s: %2$s', 'all-star-bulk-order' ), $actor_name, $review_note ) );
        self::send_customer_changes_email( $verified, $review_note );
        self::admin_action_success( $verified, 'changes-requested', __( 'Changes requested. The customer has been emailed.', 'all-star-bulk-order' ) );
    }

    private static function admin_action_success( WC_Order $order, string $action, string $message ): void {
        if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
            wp_send_json_success(
                array(
                    'action'  => sanitize_key( $action ),
                    'message' => sanitize_text_field( $message ),
                    'status'  => self::normalized_status( $order ),
                )
            );
        }

        self::redirect_admin_action( $order, $action, $message );
    }

    private static function admin_action_error( $order, string $message, int $status_code = 400 ): void {
        if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
            wp_send_json_error( array( 'message' => sanitize_text_field( $message ) ), $status_code );
        }

        if ( $order instanceof WC_Order ) {
            self::redirect_admin_action( $order, 'error', $message );
        }

        wp_die( esc_html( $message ), $status_code );
    }

    private static function send_admin_submission_email( WC_Order $order, array $uploaded_files, string $notes, bool $revision ): void {
        $recipients = self::admin_recipients();
        if ( ! $recipients ) {
            return;
        }

        $heading = $revision ? __( 'Revised artwork submitted', 'all-star-bulk-order' ) : __( 'New artwork submitted', 'all-star-bulk-order' );
        $subject = sprintf(
            $revision ? __( 'Revised artwork for Order #%s — review required', 'all-star-bulk-order' ) : __( 'Artwork submitted for Order #%s — review required', 'all-star-bulk-order' ),
            $order->get_order_number()
        );
        $names = implode( ', ', array_map( 'esc_html', wp_list_pluck( $uploaded_files, 'original_name' ) ) );
        $admin_url = self::admin_order_url( $order );

        $body = '<p>' . esc_html( sprintf( __( '%1$s submitted artwork for Order #%2$s.', 'all-star-bulk-order' ), trim( $order->get_formatted_billing_full_name() ) ?: $order->get_billing_email(), $order->get_order_number() ) ) . '</p>';
        $body .= '<p><strong>' . esc_html__( 'Files:', 'all-star-bulk-order' ) . '</strong> ' . $names . '</p>';
        if ( $notes ) {
            $body .= '<p><strong>' . esc_html__( 'Customer notes:', 'all-star-bulk-order' ) . '</strong><br>' . nl2br( esc_html( $notes ) ) . '</p>';
        }
        $body .= self::email_button( $admin_url, __( 'Review Artwork', 'all-star-bulk-order' ) );

        self::send_wc_email( $recipients, $subject, $heading, $body );
    }

    private static function send_customer_approved_email( WC_Order $order ): void {
        $to = sanitize_email( $order->get_billing_email() );
        if ( ! $to ) {
            return;
        }

        $subject = sprintf( __( 'Your artwork for Order #%s has been approved', 'all-star-bulk-order' ), $order->get_order_number() );
        $heading = __( 'Your artwork has been approved', 'all-star-bulk-order' );
        $body = '<p>' . esc_html( sprintf( __( 'Good news — the artwork for Order #%s has been reviewed and approved for production.', 'all-star-bulk-order' ), $order->get_order_number() ) ) . '</p>';
        $body .= '<p>' . esc_html__( 'If anything else is needed before production begins, our team will contact you.', 'all-star-bulk-order' ) . '</p>';
        $body .= self::email_button( self::customer_order_url( $order ), __( 'View Order & Artwork', 'all-star-bulk-order' ) );
        self::send_wc_email( $to, $subject, $heading, $body );
    }

    private static function send_customer_changes_email( WC_Order $order, string $review_note ): void {
        $to = sanitize_email( $order->get_billing_email() );
        if ( ! $to ) {
            return;
        }

        $subject = sprintf( __( 'Changes requested for artwork on Order #%s', 'all-star-bulk-order' ), $order->get_order_number() );
        $heading = __( 'We need a change to your artwork', 'all-star-bulk-order' );
        $body = '<p>' . esc_html( sprintf( __( 'We reviewed the artwork for Order #%s and need a revision before it can be approved.', 'all-star-bulk-order' ), $order->get_order_number() ) ) . '</p>';
        $body .= '<div style="padding:14px 16px;background:#fff7e8;border-left:4px solid #d79f13;margin:18px 0;"><strong>' . esc_html__( 'What needs changed:', 'all-star-bulk-order' ) . '</strong><br>' . nl2br( esc_html( $review_note ) ) . '</div>';
        $body .= '<p>' . esc_html__( 'Use the button below to return to your order and submit revised artwork. We will review the new file before production.', 'all-star-bulk-order' ) . '</p>';
        $body .= self::email_button( self::customer_order_url( $order ), __( 'Upload Revised Artwork', 'all-star-bulk-order' ) );
        self::send_wc_email( $to, $subject, $heading, $body );
    }

    private static function send_wc_email( string $to, string $subject, string $heading, string $body ): void {
        if ( ! $to || ! is_callable( array( WC(), 'mailer' ) ) ) {
            return;
        }
        $mailer = WC()->mailer();
        $message = method_exists( $mailer, 'wrap_message' ) ? $mailer->wrap_message( $heading, $body ) : $body;
        wp_mail( $to, $subject, $message, array( 'Content-Type: text/html; charset=UTF-8' ) );
    }

    private static function admin_recipients(): string {
        $recipient = '';
        if ( is_callable( array( WC(), 'mailer' ) ) ) {
            $mailer = WC()->mailer();
            if ( method_exists( $mailer, 'get_emails' ) ) {
                $emails = $mailer->get_emails();
                if ( isset( $emails['WC_Email_New_Order'] ) && method_exists( $emails['WC_Email_New_Order'], 'get_recipient' ) ) {
                    $recipient = (string) $emails['WC_Email_New_Order']->get_recipient();
                }
            }
        }
        return $recipient ?: sanitize_email( get_option( 'admin_email' ) );
    }

    private static function email_button( string $url, string $label ): string {
        return '<p style="margin:24px 0;"><a href="' . esc_url( $url ) . '" style="display:inline-block;background:#11192d;color:#fff;text-decoration:none;padding:12px 18px;border-radius:6px;font-weight:700;">' . esc_html( $label ) . '</a></p>';
    }

    public static function handle_download(): void {
        self::require_admin_artwork_permission();
        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        $file_index = isset( $_GET['file_index'] ) ? absint( $_GET['file_index'] ) : -1;
        $inline = ! empty( $_GET['inline'] );
        $nonce = isset( $_GET['asbo_download_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['asbo_download_nonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'asbo_download_order_artwork_' . $order_id . '_' . $file_index ) ) {
            wp_die( esc_html__( 'The download link expired.', 'all-star-bulk-order' ), 403 );
        }

        $order = wc_get_order( $order_id );
        $files = $order instanceof WC_Order ? self::get_files( $order ) : array();
        if ( ! isset( $files[ $file_index ]['path'] ) ) {
            wp_die( esc_html__( 'The artwork file could not be found.', 'all-star-bulk-order' ), 404 );
        }

        $path = wp_normalize_path( $files[ $file_index ]['path'] );
        $real = realpath( $path );
        if ( ! $real || ! is_file( $real ) ) {
            wp_die( esc_html__( 'The artwork file no longer exists.', 'all-star-bulk-order' ), 404 );
        }

        $uploads = wp_upload_dir();
        $allowed_base = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . 'all-star-artwork/' );
        $real_normal = wp_normalize_path( $real );
        if ( 0 !== strpos( $real_normal, $allowed_base ) ) {
            wp_die( esc_html__( 'The artwork path is invalid.', 'all-star-bulk-order' ), 403 );
        }

        $download_name = sanitize_file_name( $files[ $file_index ]['original_name'] ?? basename( $real_normal ) );
        $mime = ! empty( $files[ $file_index ]['mime'] ) ? sanitize_mime_type( $files[ $file_index ]['mime'] ) : 'application/octet-stream';
        nocache_headers();
        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . $download_name . '"' );
        header( 'Content-Length: ' . filesize( $real_normal ) );
        header( 'X-Content-Type-Options: nosniff' );
        readfile( $real_normal ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        exit;
    }

    private static function download_url( WC_Order $order, $file_index, bool $inline = false ): string {
        return wp_nonce_url(
            add_query_arg(
                array(
                    'action'     => 'asbo_download_order_artwork',
                    'order_id'   => $order->get_id(),
                    'file_index' => absint( $file_index ),
                    'inline'     => $inline ? 1 : 0,
                ),
                admin_url( 'admin-post.php' )
            ),
            'asbo_download_order_artwork_' . $order->get_id() . '_' . absint( $file_index ),
            'asbo_download_nonce'
        );
    }

    public static function add_classic_order_column( array $columns ): array {
        return self::insert_artwork_column( $columns );
    }

    public static function render_classic_order_column( string $column, int $post_id ): void {
        if ( 'asbo_artwork' !== $column ) {
            return;
        }
        $order = wc_get_order( $post_id );
        self::render_order_status_cell( $order );
    }

    public static function add_hpos_order_column( array $columns ): array {
        return self::insert_artwork_column( $columns );
    }

    public static function render_hpos_order_column( string $column, $order ): void {
        if ( 'asbo_artwork' !== $column ) {
            return;
        }
        self::render_order_status_cell( self::resolve_order( $order ) );
    }

    private static function insert_artwork_column( array $columns ): array {
        $out = array();
        foreach ( $columns as $key => $label ) {
            if ( 'order_total' === $key ) {
                $out['asbo_artwork'] = __( 'Artwork', 'all-star-bulk-order' );
            }
            $out[ $key ] = $label;
        }
        if ( ! isset( $out['asbo_artwork'] ) ) {
            $out['asbo_artwork'] = __( 'Artwork', 'all-star-bulk-order' );
        }
        return $out;
    }

    private static function render_order_status_cell( $order ): void {
        if ( ! $order instanceof WC_Order ) {
            echo '—';
            return;
        }
        $has_context = (bool) $order->get_meta( self::META_PLAN, true ) || (bool) $order->get_meta( self::META_STATUS, true ) || ! empty( self::get_files( $order ) );
        if ( ! $has_context ) {
            echo '—';
            return;
        }
        $status = self::normalized_status( $order );
        echo '<span class="asbo-order-artwork-badge asbo-order-artwork-badge--' . esc_attr( $status ) . '" style="display:inline-block;padding:3px 7px;border-radius:999px;font-size:11px;font-weight:700;background:' . esc_attr( self::status_inline_background( $status ) ) . ';color:' . esc_attr( self::status_inline_color( $status ) ) . ';">' . esc_html( self::status_label( $status ) ) . '</span>';
    }

    public static function get_order_artwork_status( WC_Order $order ): string {
        return self::normalized_status( $order );
    }

    public static function get_order_artwork_status_label( WC_Order $order ): string {
        return self::status_label( self::normalized_status( $order ) );
    }

    public static function get_order_artwork_file_count( WC_Order $order ): int {
        return count( self::get_files( $order ) );
    }

    private static function get_files( WC_Order $order ): array {
        $files = $order->get_meta( self::META_FILES, true );
        return is_array( $files ) ? $files : array();
    }

    private static function persist_artwork_status( WC_Order $order, string $status ): bool {
        $status = sanitize_key( $status );
        if ( ! in_array( $status, array( 'awaiting_review', 'changes_requested', 'approved' ), true ) ) {
            return false;
        }

        $order_id = $order->get_id();
        $order->update_meta_data( self::META_STATUS, $status );
        $order->save_meta_data();
        $order->save();

        if ( function_exists( 'wc_delete_shop_order_transients' ) ) {
            wc_delete_shop_order_transients( $order_id );
        }
        if ( class_exists( 'WC_Cache_Helper' ) ) {
            WC_Cache_Helper::get_transient_version( 'orders', true );
        }
        wp_cache_delete( $order_id, 'orders' );
        clean_post_cache( $order_id );

        $verified = wc_get_order( $order_id );
        return $verified instanceof WC_Order
            && $status === sanitize_key( (string) $verified->get_meta( self::META_STATUS, true ) );
    }

    private static function get_history( WC_Order $order ): array {
        $history = $order->get_meta( self::META_HISTORY, true );
        return is_array( $history ) ? $history : array();
    }

    private static function append_history( WC_Order $order, array $event ): void {
        $history = self::get_history( $order );
        $history[] = array(
            'type'      => sanitize_key( $event['type'] ?? 'updated' ),
            'label'     => sanitize_text_field( $event['label'] ?? __( 'Artwork updated', 'all-star-bulk-order' ) ),
            'message'   => sanitize_textarea_field( $event['message'] ?? '' ),
            'files'     => isset( $event['files'] ) && is_array( $event['files'] ) ? array_map( 'sanitize_file_name', $event['files'] ) : array(),
            'timestamp' => sanitize_text_field( $event['timestamp'] ?? current_time( 'mysql' ) ),
            'user_id'   => absint( $event['user_id'] ?? 0 ),
            'actor'     => sanitize_text_field( $event['actor'] ?? '' ),
            'internal'  => ! empty( $event['internal'] ),
        );
        $order->update_meta_data( self::META_HISTORY, array_slice( $history, -100 ) );
    }

    private static function normalized_status( WC_Order $order, ?array $files = null ): string {
        $files = null === $files ? self::get_files( $order ) : $files;
        $status = sanitize_key( (string) $order->get_meta( self::META_STATUS, true ) );
        $plan = sanitize_key( (string) $order->get_meta( self::META_PLAN, true ) );

        if ( 'received' === $status ) {
            return 'awaiting_review';
        }
        if ( in_array( $status, array( 'awaiting_review', 'changes_requested', 'approved' ), true ) ) {
            return $status;
        }
        if ( $files ) {
            return 'awaiting_review';
        }
        if ( in_array( $plan, array( 'previous_order', 'design_help' ), true ) ) {
            return 'awaiting_review';
        }
        return 'needed';
    }

    private static function status_label( string $status ): string {
        $labels = array(
            'needed'            => __( 'Artwork Needed', 'all-star-bulk-order' ),
            'awaiting_review'   => __( 'Awaiting Review', 'all-star-bulk-order' ),
            'changes_requested' => __( 'Changes Requested', 'all-star-bulk-order' ),
            'approved'          => __( 'Approved', 'all-star-bulk-order' ),
        );
        return $labels[ $status ] ?? __( 'Artwork Needed', 'all-star-bulk-order' );
    }

    private static function status_class( string $status ): string {
        return 'asbo-artwork-status--' . sanitize_html_class( $status );
    }

    private static function status_inline_background( string $status ): string {
        $colors = array(
            'needed'            => '#eef1f5',
            'awaiting_review'   => '#fff3cf',
            'changes_requested' => '#fff0eb',
            'approved'          => '#e8f6ee',
        );
        return $colors[ $status ] ?? '#eef1f5';
    }

    private static function status_inline_color( string $status ): string {
        $colors = array(
            'needed'            => '#526075',
            'awaiting_review'   => '#7a5500',
            'changes_requested' => '#9b351f',
            'approved'          => '#17693f',
        );
        return $colors[ $status ] ?? '#526075';
    }

    private static function plan_label( string $plan ): string {
        $labels = array(
            'upload_after_checkout' => __( 'Customer upload after checkout', 'all-star-bulk-order' ),
            'previous_order'        => __( 'Reuse artwork from previous order', 'all-star-bulk-order' ),
            'design_help'           => __( 'Artwork / digitizing help', 'all-star-bulk-order' ),
        );
        return $labels[ $plan ] ?? __( 'Customer upload after checkout', 'all-star-bulk-order' );
    }

    private static function order_is_inactive( WC_Order $order ): bool {
        return $order->has_status( array( 'cancelled', 'failed', 'refunded', 'trash' ) );
    }

    private static function resolve_order( $object ) {
        if ( $object instanceof WC_Order ) {
            return $object;
        }
        if ( $object instanceof WP_Post ) {
            return wc_get_order( $object->ID );
        }
        if ( is_numeric( $object ) ) {
            return wc_get_order( absint( $object ) );
        }
        if ( is_object( $object ) && isset( $object->ID ) ) {
            return wc_get_order( absint( $object->ID ) );
        }
        return false;
    }

    private static function format_datetime( string $value ): string {
        if ( ! $value ) {
            return '';
        }
        $timestamp = strtotime( $value );
        return $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : $value;
    }

    private static function customer_order_url( WC_Order $order ): string {
        if ( $order->get_user_id() ) {
            return wc_get_endpoint_url( 'view-order', $order->get_id(), wc_get_page_permalink( 'myaccount' ) );
        }
        return $order->get_checkout_order_received_url();
    }

    private static function admin_order_url( WC_Order $order ): string {
        if ( method_exists( $order, 'get_edit_order_url' ) ) {
            return (string) $order->get_edit_order_url();
        }
        return admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' );
    }

    private static function require_admin_artwork_permission(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to review artwork.', 'all-star-bulk-order' ), 403 );
        }
    }

    private static function redirect_admin_action( WC_Order $order, string $action, string $message = '' ): void {
        $args = array( 'asbo-artwork-action' => sanitize_key( $action ) );
        if ( $message ) {
            $args['asbo-artwork-message'] = sanitize_text_field( $message );
        }
        wp_safe_redirect( add_query_arg( $args, self::admin_order_url( $order ) ) );
        exit;
    }

    private static function redirect_error( string $return_url, string $message ): void {
        wp_safe_redirect( add_query_arg( 'asbo-artwork-error', sanitize_text_field( $message ), $return_url ) );
        exit;
    }

    private static function admin_action_script(): void {
        static $printed = false;
        if ( $printed ) {
            return;
        }
        $printed = true;
        ?>
        <script>
        (function(){
            function postAction(root, action, nonce, reviewNote){
                var feedback = root.querySelector('.asbo-review-action-feedback');
                var buttons = root.querySelectorAll('button');
                buttons.forEach(function(button){ button.disabled = true; });
                if (feedback) { feedback.textContent = 'Saving…'; feedback.className = 'asbo-review-action-feedback is-loading'; }
                var body = new URLSearchParams();
                body.set('action', action);
                body.set('order_id', root.getAttribute('data-order-id') || '0');
                body.set('asbo_artwork_review_nonce', nonce || '');
                if (reviewNote !== undefined) { body.set('review_note', reviewNote); }
                fetch(window.ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: body.toString()
                }).then(function(response){
                    return response.json().then(function(data){ return {ok: response.ok, data:data}; });
                }).then(function(result){
                    if (!result.ok || !result.data || !result.data.success) {
                        var msg = result.data && result.data.data && result.data.data.message ? result.data.data.message : 'The artwork action could not be saved.';
                        throw new Error(msg);
                    }
                    if (feedback) { feedback.textContent = result.data.data.message || 'Saved.'; feedback.className = 'asbo-review-action-feedback is-success'; }
                    window.setTimeout(function(){ window.location.reload(); }, 450);
                }).catch(function(error){
                    if (feedback) { feedback.textContent = error.message || 'The artwork action could not be saved.'; feedback.className = 'asbo-review-action-feedback is-error'; }
                    buttons.forEach(function(button){ button.disabled = false; });
                });
            }
            document.addEventListener('click', function(event){
                var approve = event.target.closest('.asbo-review-action-approve');
                if (approve) {
                    event.preventDefault();
                    var root = approve.closest('.asbo-review-admin__actions');
                    if (root) { postAction(root, 'asbo_approve_artwork', root.getAttribute('data-approve-nonce')); }
                    return;
                }
                var changes = event.target.closest('.asbo-review-action-changes');
                if (changes) {
                    event.preventDefault();
                    var root = changes.closest('.asbo-review-admin__actions');
                    if (!root) { return; }
                    var note = root.querySelector('.asbo-review-change-note');
                    var value = note ? note.value.trim() : '';
                    if (!value) {
                        if (note) { note.focus(); }
                        var feedback = root.querySelector('.asbo-review-action-feedback');
                        if (feedback) { feedback.textContent = 'Add a reason before requesting changes.'; feedback.className = 'asbo-review-action-feedback is-error'; }
                        return;
                    }
                    postAction(root, 'asbo_request_artwork_changes', root.getAttribute('data-changes-nonce'), value);
                }
            });
        })();
        </script>
        <?php
    }

    private static function customer_styles(): void {
        static $printed = false;
        if ( $printed ) {
            return;
        }
        $printed = true;
        ?>
        <style>
        .asbo-artwork-customer{--asbo-navy:#11192d;--asbo-gold:#d7aa32;--asbo-gold-soft:#fff8e7;--asbo-ink:#182033;--asbo-muted:#6f7786;--asbo-line:#e5e7eb;--asbo-soft:#f7f8fa;position:relative;margin:38px 0 0;padding:30px 0 0;border:0;border-top:1px solid var(--asbo-line);border-radius:0;background:transparent;color:var(--asbo-ink);box-shadow:none;overflow:visible}.asbo-artwork-customer__heading{display:flex;align-items:center;justify-content:space-between;gap:22px;margin:0 0 18px;padding:0;background:transparent;color:var(--asbo-ink)}.asbo-artwork-customer__eyebrow{display:block;margin-bottom:4px;color:#8a6a13;font-size:10px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}.asbo-artwork-customer h2{margin:0;color:var(--asbo-navy);font-size:clamp(28px,3vw,38px);font-weight:760;letter-spacing:-.035em;line-height:1}.asbo-artwork-status{display:inline-flex;align-items:center;min-height:28px;padding:5px 10px;border:0;border-radius:999px;font-size:10px;font-weight:850;white-space:nowrap}.asbo-artwork-status--needed{background:#f1f3f5;color:#596575}.asbo-artwork-status--awaiting_review{background:#fff8df;color:#765900}.asbo-artwork-status--changes_requested{background:#fff3ef;color:#9a3d2c}.asbo-artwork-status--approved{background:#edf8f2;color:#226e49}.asbo-artwork-message{margin:0 0 18px;padding:11px 13px;border:0;border-left:3px solid #3d8d61;border-radius:0;background:#f1faf5;color:#315a43;font-size:13px}.asbo-artwork-message--error{border-left-color:#aa4f3d;background:#fff5f2;color:#7b392d}.asbo-artwork-state{display:grid;grid-template-columns:30px minmax(0,1fr);gap:12px;margin:0 0 22px;padding:15px 0 15px 14px;border:0;border-left:3px solid #d8bd70;border-radius:0;background:linear-gradient(90deg,var(--asbo-gold-soft),transparent)}.asbo-artwork-state__icon{display:grid;place-items:center;width:28px;height:28px;border-radius:999px;background:#fff;color:#725600;font-weight:900;box-shadow:0 0 0 1px rgba(114,86,0,.12)}.asbo-artwork-state strong{display:block;margin:0 0 4px;color:var(--asbo-navy);font-size:14px}.asbo-artwork-state p{margin:0;color:var(--asbo-muted);font-size:13px;line-height:1.55}.asbo-artwork-state--changes{border-left-color:#c8694f;background:linear-gradient(90deg,#fff5f1,transparent)}.asbo-artwork-state--changes .asbo-artwork-state__icon{color:#9a3d2c}.asbo-artwork-state--approved{border-left-color:#399063;background:linear-gradient(90deg,#f0f9f4,transparent)}.asbo-artwork-state--approved .asbo-artwork-state__icon{color:#1f6b45}.asbo-artwork-files,.asbo-artwork-reference{margin:0 0 24px;padding:0}.asbo-artwork-files>strong,.asbo-artwork-reference>strong{display:block;margin-bottom:9px;color:var(--asbo-navy);font-size:11px;font-weight:850;letter-spacing:.03em;text-transform:uppercase}.asbo-artwork-files__list{display:grid;border-top:1px solid var(--asbo-line)}.asbo-artwork-file{display:flex;align-items:center;gap:11px;padding:12px 2px;border:0;border-bottom:1px solid var(--asbo-line);border-radius:0;background:transparent}.asbo-artwork-file__icon{display:grid;place-items:center;flex:0 0 28px;width:28px;height:28px;border-radius:8px;background:var(--asbo-soft);color:var(--asbo-navy);font-size:12px}.asbo-artwork-file b{display:block;color:var(--asbo-navy);font-size:13px}.asbo-artwork-file small{display:block;margin-top:2px;color:#8a93a3;font-size:11px}.asbo-artwork-reference{padding:13px 0 13px 14px;border-left:3px solid var(--asbo-gold);background:linear-gradient(90deg,var(--asbo-gold-soft),transparent)}.asbo-artwork-reference span{color:var(--asbo-muted);font-size:13px}.asbo-artwork-form{margin:20px 0 24px;padding:18px 0 0;border:0;border-top:1px solid var(--asbo-line);border-radius:0;background:transparent}.asbo-artwork-field{display:block;margin-bottom:16px}.asbo-artwork-field>span{display:block;margin-bottom:7px;color:var(--asbo-navy);font-size:12px;font-weight:850}.asbo-artwork-field input[type=file],.asbo-artwork-field textarea{width:100%;box-sizing:border-box;padding:12px 13px;border:1px solid transparent;border-radius:11px;background:var(--asbo-soft);color:var(--asbo-ink);font-size:13px;box-shadow:inset 0 0 0 1px rgba(17,25,45,.03)}.asbo-artwork-field input[type=file]:focus,.asbo-artwork-field textarea:focus{outline:0;border-color:#d9bd69;background:#fff;box-shadow:0 0 0 3px rgba(215,170,50,.13)}.asbo-artwork-field small{display:block;margin-top:6px;color:#7f8796;font-size:11px}.asbo-artwork-primary{min-height:42px;padding:9px 15px;border:0;border-radius:10px;background:var(--asbo-navy);color:#fff;font-weight:850;cursor:pointer;box-shadow:0 3px 8px rgba(17,25,45,.12)}.asbo-artwork-primary:hover{background:#1b2947}.asbo-artwork-replace,.asbo-artwork-history{margin:18px 0 0;padding:14px 0 0;border-top:1px solid var(--asbo-line)}.asbo-artwork-replace>summary,.asbo-artwork-history>summary{cursor:pointer;font-size:12px;font-weight:800;color:var(--asbo-navy)}.asbo-artwork-history ol{list-style:none;margin:14px 0 0;padding:0}.asbo-artwork-history li{display:flex;gap:11px;padding:0 0 14px}.asbo-artwork-history__dot{flex:0 0 8px;width:8px;height:8px;margin-top:6px;border-radius:50%;background:var(--asbo-gold)}.asbo-artwork-history strong{display:block}.asbo-artwork-history p{margin:3px 0;color:var(--asbo-muted)}.asbo-artwork-history small{color:#8a93a3}.asbo-artwork-stitch-note{margin:22px 0 0;padding:15px 0 0;border-top:1px solid var(--asbo-line);color:var(--asbo-muted);font-size:12px;line-height:1.55}.asbo-artwork-stitch-note strong{color:var(--asbo-navy)}@media(max-width:600px){.asbo-artwork-customer__heading{align-items:flex-start;flex-direction:column}.asbo-artwork-state{padding-left:10px}}
        </style>
        <?php
    }

    private static function admin_styles(): void {
        ?>
        <style>
            #asbo-artwork-review .inside{padding:0;margin:0}.asbo-review-admin{--navy:#11192d;--navy-2:#1a2743;--gold:#d7aa32;--gold-soft:#fbf5e5;--ink:#182033;--muted:#687184;--line:#e1e5ec;--soft:#f7f8fa;padding:0;color:var(--ink);background:#fff}.asbo-review-admin h3,.asbo-review-admin h4{color:var(--navy)}
            .asbo-review-admin__top{display:flex;align-items:center;justify-content:space-between;gap:18px;margin:0;padding:20px 22px;background:var(--navy);color:#fff}.asbo-review-admin__top h3{margin:3px 0 0;color:#fff;font-size:21px;font-weight:700;letter-spacing:-.015em}.asbo-review-admin__eyebrow{color:#d7b85b;font-size:9px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.asbo-review-admin__top .asbo-artwork-status--awaiting_review{background:#fff3ca;color:#6c5000;border-color:#e4c76f}
            .asbo-review-admin>.notice{margin:14px 18px 0!important}.asbo-review-admin__grid{display:grid;grid-template-columns:minmax(0,1.12fr) minmax(300px,.88fr);gap:16px;padding:18px;background:#f4f6f8}.asbo-review-admin__files,.asbo-review-admin__details{padding:18px;border:1px solid var(--line);border-radius:12px;background:#fff;box-shadow:0 7px 18px rgba(17,25,45,.04)}.asbo-review-admin__details{background:#fff}.asbo-review-admin h4{margin:0 0 14px;font-size:13px;font-weight:850}
            .asbo-review-file-card{display:grid;grid-template-columns:110px minmax(0,1fr);gap:14px;padding:12px;border:1px solid var(--line);border-radius:10px;background:#fbfcfd}.asbo-review-file-card+.asbo-review-file-card{margin-top:9px}.asbo-review-file-card__preview{display:block;width:110px;height:88px;border:1px solid var(--line);border-radius:8px;overflow:hidden;background:#fff}.asbo-review-file-card__preview img{width:100%;height:100%;object-fit:contain}.asbo-review-file-card__document{display:grid;place-items:center;width:110px;height:88px;border-radius:8px;background:var(--navy);color:#fff;font-size:10px;font-weight:850;letter-spacing:.1em}.asbo-review-file-card strong{display:block;margin-top:5px;color:var(--navy);font-size:13px;word-break:break-word}.asbo-review-file-card small{display:block;margin:4px 0 7px;color:#8a93a3;font-size:11px}.asbo-review-file-card a:not(.asbo-review-file-card__preview){color:#35587f;font-size:11px;font-weight:750;text-decoration:none}.asbo-review-file-card a:not(.asbo-review-file-card__preview):hover{text-decoration:underline}
            .asbo-review-admin dl{margin:0}.asbo-review-admin dl>div{display:grid;grid-template-columns:112px 1fr;gap:12px;padding:8px 0;border-bottom:1px solid #edf0f3}.asbo-review-admin dl>div:last-child{border-bottom:0}.asbo-review-admin dt{color:#7f8796;font-size:11px}.asbo-review-admin dd{margin:0;color:var(--navy);font-size:12px;font-weight:700}.asbo-review-note,.asbo-review-stitch,.asbo-review-empty{margin-top:14px;padding:13px 14px;border-radius:9px;background:var(--soft)}.asbo-review-note strong,.asbo-review-stitch strong,.asbo-review-empty strong{color:var(--navy);font-size:12px}.asbo-review-note p,.asbo-review-stitch p,.asbo-review-empty p{margin:5px 0 0;color:var(--muted);font-size:11px;line-height:1.5}.asbo-review-stitch{border-left:3px solid var(--gold);border-radius:0 9px 9px 0;background:var(--gold-soft)}
            .asbo-review-admin__history{margin:0;padding:18px 22px 3px;border-top:1px solid var(--line);background:#fff}.asbo-review-admin__history ol{list-style:none;margin:0;padding:0}.asbo-review-admin__history li{position:relative;display:grid;grid-template-columns:12px minmax(0,1fr);gap:10px;padding:0 0 15px}.asbo-review-admin__history li:not(:last-child):after{content:"";position:absolute;left:4px;top:12px;bottom:0;width:1px;background:var(--line)}.asbo-review-admin__history li>span{position:relative;z-index:1;width:9px;height:9px;margin-top:5px;border:2px solid #fff;border-radius:50%;background:var(--gold);box-shadow:0 0 0 1px var(--gold)}.asbo-review-admin__history strong{display:block;color:var(--navy);font-size:12px}.asbo-review-admin__history p{margin:3px 0;color:var(--muted);font-size:11px}.asbo-review-admin__history small{color:#8a93a3;font-size:10px}
            .asbo-review-admin__actions{display:flex;align-items:flex-start;gap:14px;padding:18px 22px;border-top:1px solid var(--line);background:#f7f8fa}.asbo-review-admin__actions form{margin:0}.asbo-review-admin__actions .button{min-height:38px;padding:6px 13px;border-radius:7px;box-shadow:none;font-weight:750}.asbo-review-approve{background:var(--navy)!important;border-color:var(--navy)!important;color:#fff!important}.asbo-review-approve:hover{background:var(--navy-2)!important;border-color:var(--navy-2)!important}.asbo-review-approve:focus{box-shadow:0 0 0 2px #fff,0 0 0 4px rgba(215,170,50,.5)!important}.asbo-review-change-form{flex:1;max-width:760px}.asbo-review-change-form label span{display:block;margin-bottom:6px;color:var(--navy);font-size:11px;font-weight:800}.asbo-review-change-form textarea{width:100%;margin-bottom:8px;border-color:#cfd5df;border-radius:7px;box-shadow:none}.asbo-review-change-form textarea:focus{border-color:var(--gold);box-shadow:0 0 0 1px var(--gold)}.asbo-review-changes{border-color:#c9a12f!important;background:#fff!important;color:#634b0b!important}.asbo-review-changes:hover{background:var(--gold-soft)!important}.asbo-review-awaiting{margin:0;color:var(--muted)}.asbo-review-action-feedback{flex-basis:100%;margin:0;min-height:18px;font-size:12px;font-weight:700}.asbo-review-action-feedback.is-loading{color:#6f7786}.asbo-review-action-feedback.is-success{color:#24734a}.asbo-review-action-feedback.is-error{color:#a04435}.asbo-review-admin__actions button[disabled]{opacity:.55;cursor:wait}
            .asbo-artwork-status{display:inline-flex;align-items:center;min-height:26px;padding:4px 9px;border:1px solid transparent;border-radius:999px;font-size:9px;font-weight:850;letter-spacing:.03em}.asbo-artwork-status--needed{background:#f0f2f5;color:#536174}.asbo-artwork-status--awaiting_review{background:var(--gold-soft);color:#725600;border-color:#ead89d}.asbo-artwork-status--changes_requested{background:#fff3ef;color:#9a3d2c;border-color:#efc5ba}.asbo-artwork-status--approved{background:#eef8f2;color:#1f6b45;border-color:#c8e6d3}
            @media(max-width:1000px){.asbo-review-admin__grid{grid-template-columns:1fr}.asbo-review-admin__actions{flex-direction:column}.asbo-review-change-form{width:100%;max-width:none}}@media(max-width:600px){.asbo-review-admin__top{align-items:flex-start;flex-direction:column;padding:17px}.asbo-review-admin__grid{padding:12px}.asbo-review-admin__files,.asbo-review-admin__details{padding:14px}.asbo-review-file-card{grid-template-columns:76px minmax(0,1fr)}.asbo-review-file-card__preview,.asbo-review-file-card__document{width:76px;height:62px}.asbo-review-admin dl>div{grid-template-columns:1fr;gap:2px}.asbo-review-admin__history,.asbo-review-admin__actions{padding-left:14px;padding-right:14px}.asbo-review-admin__actions form:first-child,.asbo-review-admin__actions form:first-child .button{width:100%}}
        </style>
        <?php
    }
}
