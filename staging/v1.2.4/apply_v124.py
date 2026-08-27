from __future__ import annotations

import json
import re
import sys
from pathlib import Path

if len(sys.argv) != 2:
    raise SystemExit('usage: apply_v124.py <plugin-root>')

root = Path(sys.argv[1]).resolve()
main_php = root / 'all-star-bulk-order-block.php'
block_json = root / 'block' / 'block.json'
account_php = root / 'includes' / 'class-asbo-account-experience.php'
readme = root / 'README.txt'

for path in (main_php, block_json, account_php, readme):
    if not path.is_file():
        raise SystemExit(f'missing required file: {path}')

# ---------------------------------------------------------------------------
# Version bump.
# ---------------------------------------------------------------------------
main = main_php.read_text()
main, n1 = re.subn(r'(\* Version:\s*)1\.2\.3\b', r'\g<1>1.2.4', main, count=1)
main, n2 = re.subn(r"(private const VERSION = ')1\.2\.3(';)", r'\g<1>1.2.4\g<2>', main, count=1)
if n1 != 1 or n2 != 1:
    raise SystemExit('could not bump plugin version from 1.2.3 to 1.2.4')
main_php.write_text(main)

block = json.loads(block_json.read_text())
if block.get('version') != '1.2.3':
    raise SystemExit(f"unexpected block version: {block.get('version')!r}")
block['version'] = '1.2.4'
block_json.write_text(json.dumps(block, indent=2) + '\n')

account = account_php.read_text()
account = account.replace("wp_register_style( 'asbo-account-experience', false, array(), '1.2.3' );", "wp_register_style( 'asbo-account-experience', false, array(), '1.2.4' );", 1)

# ---------------------------------------------------------------------------
# Structural correction.
#
# v1.2.3 tried to form the desktop rail by opening an <aside> in the
# woocommerce_before_account_navigation hook and closing it in the matching
# after hook. That made the page vulnerable to template/hook ordering and, on
# the affected endpoints, the account content could end up inside the sidebar.
#
# v1.2.4 takes ownership of the WooCommerce account-navigation action instead.
# The template now receives exactly two first-level layout containers:
#   1) <aside class="asbo-account-sidebar"> (identity + native WC nav)
#   2) <div class="woocommerce-MyAccount-content"> (native WC content)
# No cross-row grid tricks, no open/close wrapper split across hooks.
# ---------------------------------------------------------------------------
old_hooks = """        add_action( 'woocommerce_before_account_navigation', array( __CLASS__, 'render_account_identity' ), 5 );
        add_action( 'woocommerce_after_account_navigation', array( __CLASS__, 'close_account_sidebar' ), 100 );"""
new_hooks = """        remove_action( 'woocommerce_account_navigation', 'woocommerce_account_navigation', 10 );
        add_action( 'woocommerce_account_navigation', array( __CLASS__, 'render_account_sidebar' ), 10 );"""
if old_hooks not in account:
    raise SystemExit('v1.2.3 account navigation hooks not found')
account = account.replace(old_hooks, new_hooks, 1)

wrapped_identity = """<aside class=\"asbo-account-sidebar\" aria-label=\"<?php esc_attr_e( 'Account navigation', 'all-star-bulk-order' ); ?>\">
        <div class=\"asbo-account-identity\">"""
if wrapped_identity not in account:
    raise SystemExit('v1.2.3 split-hook aside wrapper not found')
account = account.replace(wrapped_identity, '<div class="asbo-account-identity">', 1)

close_method = """    public static function close_account_sidebar(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }
        echo '</aside>';
    }

"""
if close_method not in account:
    raise SystemExit('v1.2.3 close_account_sidebar method not found')
account = account.replace(close_method, '', 1)

method_anchor = "    public static function render_dashboard(): void {"
sidebar_method = """    public static function render_account_sidebar(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }
        ?>
        <aside class=\"asbo-account-sidebar\" aria-label=\"<?php esc_attr_e( 'Account navigation', 'all-star-bulk-order' ); ?>\">
            <?php self::render_account_identity(); ?>
            <?php wc_get_template( 'myaccount/navigation.php' ); ?>
        </aside>
        <?php
    }

"""
if method_anchor not in account:
    raise SystemExit('render_dashboard method anchor not found')
if 'public static function render_account_sidebar' in account:
    raise SystemExit('render_account_sidebar already exists')
account = account.replace(method_anchor, sidebar_method + method_anchor, 1)

css = r'''

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
'''

if 'ASBO 1.2.4 TWO-CONTAINER ACCOUNT LAYOUT' in account:
    raise SystemExit('v1.2.4 account patch already applied')
needle = '\nCSS;'
pos = account.rfind(needle)
if pos < 0:
    raise SystemExit('CSS heredoc terminator not found')
account = account[:pos] + css + account[pos:]
account_php.write_text(account)

r = readme.read_text()
r = r.replace('All Star Bulk Order Block v1.2.3', 'All Star Bulk Order Block v1.2.4', 1)
notes = '''

v1.2.4 two-container account layout correction:
- Replaces the split before/after-navigation wrapper hooks with one explicit server-rendered account sidebar callback.
- Desktop/tablet My Account now has exactly two sibling containers: a continuous navy sidebar and the WooCommerce content area.
- Prevents Dashboard, Account Details and long order pages from being nested into or squeezed to the sidebar width.
- Preserves the approved mobile Account menu behavior and mobile responsive design.
- Reworks Dashboard quick access into an unboxed editorial link band instead of four rectangle/table-style buttons.
- Keeps artwork attention indicators and the All Star navy/gold/cream brand system.
- No Supplier Sync pricing, ASBO tiers, cart, checkout, shipping, artwork storage/review persistence, authentication or order-security logic changed.
'''
if 'v1.2.4 two-container account layout correction:' not in r:
    r = r.rstrip() + notes + '\n'
readme.write_text(r)

print('ASBO 1.2.4 two-container account layout patch applied successfully')
