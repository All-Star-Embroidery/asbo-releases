from __future__ import annotations

import sys
from pathlib import Path

if len(sys.argv) != 2:
    raise SystemExit('usage: apply_refinement.py <plugin-root>')

root = Path(sys.argv[1]).resolve()
p = root / 'includes' / 'class-asbo-account-experience.php'
s = p.read_text()

old = """    public static function account_menu_item_classes( array $classes, string $endpoint ): array {
        if ( ! self::is_artwork_fallback_request() ) {
            return $classes;
        }

        if ( self::ENDPOINT === $endpoint ) {
            $classes[] = 'is-active';
        } elseif ( 'dashboard' === $endpoint ) {
            $classes = array_values( array_diff( $classes, array( 'is-active' ) ) );
        }

        return array_values( array_unique( $classes ) );
    }
"""
new = """    public static function account_menu_item_classes( array $classes, string $endpoint ): array {
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
"""
if old not in s:
    raise SystemExit('account_menu_item_classes anchor not found')
s = s.replace(old, new, 1)

helper_anchor = "    private static function status_badge( string $status ): void {\n"
helper = """    private static function artwork_attention_count(): int {
        if ( ! is_user_logged_in() ) {
            return 0;
        }

        $orders = wc_get_orders(
            array(
                'customer' => get_current_user_id(),
                'limit'    => 50,
                'orderby'  => 'date',
                'order'    => 'DESC',
            )
        );

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

"""
if helper_anchor not in s:
    raise SystemExit('status badge anchor not found')
s = s.replace(helper_anchor, helper + helper_anchor, 1)

old_loop = """        $artwork_attention = 0;
        foreach ( $orders as $order ) {
            $status = self::artwork_status( $order );
            if ( in_array( $status, array( 'needed', 'changes_requested', 'awaiting_review' ), true ) && self::order_has_artwork_context( $order ) ) {
                $artwork_attention++;
            }
        }
"""
if old_loop not in s:
    raise SystemExit('dashboard artwork loop not found')
s = s.replace(old_loop, "        $artwork_attention = self::artwork_attention_count();\n", 1)

old_call = """            <?php self::quick_card( __( 'Artwork', 'all-star-bulk-order' ), (string) $artwork_attention, 1 === $artwork_attention ? __( 'order currently needs artwork attention.', 'all-star-bulk-order' ) : __( 'orders currently need artwork attention.', 'all-star-bulk-order' ), wc_get_account_endpoint_url( self::ENDPOINT ), '02' ); ?>
"""
new_call = """            <?php self::quick_card( __( 'Artwork', 'all-star-bulk-order' ), (string) $artwork_attention, 1 === $artwork_attention ? __( 'order currently has active artwork.', 'all-star-bulk-order' ) : __( 'orders currently have active artwork.', 'all-star-bulk-order' ), wc_get_account_endpoint_url( self::ENDPOINT ), '02', $artwork_attention > 0 ); ?>
"""
if old_call not in s:
    raise SystemExit('Artwork quick-card call not found')
s = s.replace(old_call, new_call, 1)

old_fn = """    private static function quick_card( string $title, string $value, string $description, string $url, string $index ): void {
        ?>
        <a class=\"asbo-account-quick-card\" href=\"<?php echo esc_url( $url ); ?>\">
            <span class=\"asbo-account-quick-card__index\"><?php echo esc_html( $index ); ?></span>
            <strong><?php echo esc_html( $title ); ?></strong>
            <b><?php echo esc_html( $value ); ?></b>
            <p><?php echo esc_html( $description ); ?></p>
            <span class=\"asbo-account-quick-card__arrow\" aria-hidden=\"true\">→</span>
        </a>
        <?php
    }
"""
new_fn = """    private static function quick_card( string $title, string $value, string $description, string $url, string $index, bool $has_notice = false ): void {
        $classes = 'asbo-account-quick-card' . ( $has_notice ? ' asbo-account-quick-card--notice' : '' );
        ?>
        <a class=\"<?php echo esc_attr( $classes ); ?>\" href=\"<?php echo esc_url( $url ); ?>\">
            <span class=\"asbo-account-quick-card__index\"><?php echo esc_html( $index ); ?></span>
            <strong><?php echo esc_html( $title ); ?></strong>
            <?php if ( $has_notice ) : ?><span class=\"asbo-account-quick-card__notice\" aria-label=\"<?php esc_attr_e( 'Artwork activity', 'all-star-bulk-order' ); ?>\">!</span><?php endif; ?>
            <b><?php echo esc_html( $value ); ?></b>
            <p><?php echo esc_html( $description ); ?></p>
            <span class=\"asbo-account-quick-card__arrow\" aria-hidden=\"true\">→</span>
        </a>
        <?php
    }
"""
if old_fn not in s:
    raise SystemExit('quick_card function anchor not found')
s = s.replace(old_fn, new_fn, 1)

css = r'''

/* ASBO 1.2.2 tablet refinement — consistent rail + compact dashboard actions. */
.asbo-account-page .woocommerce-MyAccount-navigation li.asbo-artwork-has-attention a{padding-right:42px!important}
.asbo-account-page .woocommerce-MyAccount-navigation li.asbo-artwork-has-attention a:after{content:"!";position:absolute;right:12px;top:50%;display:grid;place-items:center;width:20px;height:20px;margin-top:-10px;border:1px solid var(--ase-gold);border-radius:50%;background:transparent;color:var(--ase-gold);font-size:11px;font-weight:900;line-height:1}
.asbo-account-quick-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:0!important;margin:24px 0 38px!important;border-top:1px solid var(--ase-line)!important;border-bottom:1px solid var(--ase-line)!important}
.asbo-account-quick-card,.asbo-account-quick-card:not(:first-child),.asbo-account-quick-card:last-child{display:grid!important;grid-template-columns:minmax(0,1fr) auto auto!important;grid-template-rows:auto auto!important;column-gap:10px!important;row-gap:5px!important;min-height:92px!important;padding:16px 18px!important;border:0!important;border-right:1px solid var(--ase-line)!important;border-top:1px solid var(--ase-line)!important;background:transparent!important}
.asbo-account-quick-card:nth-child(odd){border-left:0!important}.asbo-account-quick-card:nth-child(even){border-right:0!important}.asbo-account-quick-card:nth-child(-n+2){border-top:0!important}
.asbo-account-quick-card strong{grid-column:1!important;grid-row:1!important;align-self:end;color:var(--ase-navy)!important;font-size:14px!important;font-weight:750!important}.asbo-account-quick-card b{grid-column:1!important;grid-row:2!important;margin:0!important;color:var(--ase-muted)!important;font-size:15px!important;font-weight:650!important;letter-spacing:0!important}.asbo-account-quick-card p,.asbo-account-quick-card__index{display:none!important}.asbo-account-quick-card__arrow{position:static!important;grid-column:3!important;grid-row:1/3!important;align-self:center!important;color:#8A6C2A!important;font-size:18px!important}.asbo-account-quick-card__notice{grid-column:2!important;grid-row:1/3!important;align-self:center!important;display:grid!important;place-items:center;width:24px;height:24px;border-radius:50%;background:var(--ase-gold);color:var(--ase-navy);font-size:12px;font-weight:900;line-height:1}.asbo-account-quick-card:hover{background:var(--ase-cream)!important}
@media (min-width:768px){.asbo-account-page.asbo-account-logged-in .woocommerce{grid-template-rows:auto minmax(0,1fr)!important;align-content:start!important}.asbo-account-identity{grid-column:1!important;grid-row:1!important;align-self:start!important}.asbo-account-page .woocommerce-MyAccount-navigation{grid-column:1!important;grid-row:2!important;align-self:start!important}.asbo-account-page .woocommerce-MyAccount-content{grid-column:2!important;grid-row:1/span 2!important;align-self:start!important}}
@media (max-width:767px){.asbo-account-quick-grid{grid-template-columns:1fr!important}.asbo-account-quick-card,.asbo-account-quick-card:not(:first-child),.asbo-account-quick-card:last-child{min-height:76px!important;padding:14px 4px!important;border-right:0!important;border-top:1px solid var(--ase-line)!important}.asbo-account-quick-card:first-child{border-top:0!important}}
'''
needle = '\nCSS;'
pos = s.rfind(needle)
if pos < 0:
    raise SystemExit('CSS heredoc terminator not found')
s = s[:pos] + css + s[pos:]
p.write_text(s)
print('ASBO 1.2.2 refinement applied')
