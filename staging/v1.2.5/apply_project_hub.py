from __future__ import annotations

import json
import re
import sys
from pathlib import Path

if len(sys.argv) != 2:
    raise SystemExit('usage: apply_project_hub.py <plugin-root>')

root = Path(sys.argv[1]).resolve()
main_php = root / 'all-star-bulk-order-block.php'
block_json = root / 'block' / 'block.json'
account_php = root / 'includes' / 'class-asbo-account-experience.php'
readme = root / 'README.txt'

for path in (main_php, block_json, account_php, readme):
    if not path.is_file():
        raise SystemExit(f'missing required file: {path}')

# ---------------------------------------------------------------------------
# Version bump only. Build from the published/validated v1.2.4 package so the
# two-container account shell remains the source of truth.
# ---------------------------------------------------------------------------
main = main_php.read_text()
main, n1 = re.subn(r'(\* Version:\s*)1\.2\.4\b', r'\g<1>1.2.5', main, count=1)
main, n2 = re.subn(r"(private const VERSION = ')1\.2\.4(';)", r'\g<1>1.2.5\g<2>', main, count=1)
if n1 != 1 or n2 != 1:
    raise SystemExit('could not bump plugin version from 1.2.4 to 1.2.5')
main_php.write_text(main)

block = json.loads(block_json.read_text())
if block.get('version') != '1.2.4':
    raise SystemExit(f"unexpected block version: {block.get('version')!r}")
block['version'] = '1.2.5'
block_json.write_text(json.dumps(block, indent=2) + '\n')

account = account_php.read_text()
account, style_count = re.subn(
    r"wp_register_style\( 'asbo-account-experience', false, array\(\), '1\.2\.4' \);",
    "wp_register_style( 'asbo-account-experience', false, array(), '1.2.5' );",
    account,
    count=1,
)
if style_count != 1:
    raise SystemExit('could not bump account stylesheet version to 1.2.5')

# ---------------------------------------------------------------------------
# Replace only render_dashboard(). A small PHP-aware brace scanner is used
# rather than relying on regex across a mixed PHP/HTML method. The account rail,
# Orders, Artwork, Addresses, Account Details, view-order markup, and mobile
# account menu are intentionally untouched.
# ---------------------------------------------------------------------------

def function_span(source: str, signature: str) -> tuple[int, int]:
    start = source.find(signature)
    if start < 0:
        raise SystemExit(f'function signature not found: {signature}')
    brace = source.find('{', start + len(signature))
    if brace < 0:
        raise SystemExit('opening function brace not found')

    depth = 0
    i = brace
    quote = None
    line_comment = False
    block_comment = False
    escape = False

    while i < len(source):
        ch = source[i]
        nxt = source[i + 1] if i + 1 < len(source) else ''

        if line_comment:
            if ch == '\n':
                line_comment = False
            i += 1
            continue

        if block_comment:
            if ch == '*' and nxt == '/':
                block_comment = False
                i += 2
            else:
                i += 1
            continue

        if quote is not None:
            if escape:
                escape = False
            elif ch == '\\':
                escape = True
            elif ch == quote:
                quote = None
            i += 1
            continue

        if ch in ('\'', '"'):
            quote = ch
            i += 1
            continue
        if ch == '/' and nxt == '/':
            line_comment = True
            i += 2
            continue
        if ch == '#':
            line_comment = True
            i += 1
            continue
        if ch == '/' and nxt == '*':
            block_comment = True
            i += 2
            continue

        if ch == '{':
            depth += 1
        elif ch == '}':
            depth -= 1
            if depth == 0:
                end = i + 1
                while end < len(source) and source[end] in ' \t':
                    end += 1
                if end < len(source) and source[end] == '\r':
                    end += 1
                if end < len(source) and source[end] == '\n':
                    end += 1
                return start, end
        i += 1

    raise SystemExit('could not find matching render_dashboard closing brace')

new_dashboard = r'''    public static function render_dashboard(): void {
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

        $orders = wc_get_orders(
            array(
                'customer' => get_current_user_id(),
                'limit'    => 12,
                'orderby'  => 'date',
                'order'    => 'DESC',
            )
        );

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
'''

signature = '    public static function render_dashboard(): void {'
start, end = function_span(account, signature)
account = account[:start] + new_dashboard + account[end:]

css = r'''

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
'''

if 'ASBO 1.2.5 CUSTOMER PROJECT HUB' in account:
    raise SystemExit('v1.2.5 project hub CSS already present')
needle = '\nCSS;'
pos = account.rfind(needle)
if pos < 0:
    raise SystemExit('account CSS heredoc terminator not found')
account = account[:pos] + css + account[pos:]
account_php.write_text(account)

r = readme.read_text()
r = r.replace('All Star Bulk Order Block v1.2.4', 'All Star Bulk Order Block v1.2.5', 1)
notes = '''

v1.2.5 customer project hub dashboard:
- Replaces the redundant Dashboard shortcut/navigation band with useful customer project information.
- Keeps the personal Welcome back greeting as the dashboard anchor.
- Adds a conditional Needs Your Attention area only for Artwork Needed or Changes Requested; Awaiting Review does not create a false customer warning.
- Adds one Current Project section with order workflow, concise status explanation, order date, item count, fulfillment method, and the correct order/artwork action.
- Keeps Recent Orders as the primary lower-dashboard utility while avoiding duplicate navigation cards.
- Adds a restrained Start Another Project CTA with bulk-order-page lookup and Shop fallback.
- Uses the existing All Star navy/gold/cream Crafted Commerce visual system and mobile-first reflow.
- Leaves the v1.2.4 two-container sidebar, approved mobile Account menu, Orders, Artwork, addresses, account details, Supplier Sync pricing, ASBO tiers, cart, shipping, checkout, artwork persistence/review, authentication, and order security unchanged.
'''
if 'v1.2.5 customer project hub dashboard:' not in r:
    r = r.rstrip() + notes + '\n'
readme.write_text(r)

print('ASBO 1.2.5 project hub dashboard applied successfully')
