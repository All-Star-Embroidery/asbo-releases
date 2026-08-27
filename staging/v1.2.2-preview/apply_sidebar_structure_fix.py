from __future__ import annotations

import sys
from pathlib import Path

if len(sys.argv) != 2:
    raise SystemExit("usage: apply_sidebar_structure_fix.py <plugin-root>")

root = Path(sys.argv[1]).resolve()
account_php = root / "includes" / "class-asbo-account-experience.php"
if not account_php.is_file():
    raise SystemExit(f"missing account file: {account_php}")

account = account_php.read_text()

# Root cause of the large sidebar gap:
# identity, navigation and content were three separate CSS Grid items. The content
# spanned the two sidebar rows, so tall Dashboard / View Order content participated
# in grid track sizing and could push the navigation far below the identity card.
# Make identity + native WooCommerce navigation one real <aside> grid item instead.

hook = "add_action( 'woocommerce_before_account_navigation', array( __CLASS__, 'render_account_identity' ), 5 );"
close_hook = "add_action( 'woocommerce_after_account_navigation', array( __CLASS__, 'close_account_sidebar' ), 100 );"
if close_hook not in account:
    if hook not in account:
        raise SystemExit("before-account-navigation hook not found")
    account = account.replace(hook, hook + "\n        " + close_hook, 1)

identity_markup = '<div class="asbo-account-identity">'
if '<aside class="asbo-account-sidebar"' not in account:
    idx = account.find(identity_markup)
    if idx < 0:
        raise SystemExit("account identity markup not found")
    account = account[:idx] + '<aside class="asbo-account-sidebar" aria-label="<?php esc_attr_e( \'Account navigation\', \'all-star-bulk-order\' ); ?>">\n        ' + account[idx:]

method_anchor = "    public static function render_dashboard(): void {"
close_method = """    public static function close_account_sidebar(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }
        echo '</aside>';
    }

"""
if "public static function close_account_sidebar" not in account:
    if method_anchor not in account:
        raise SystemExit("render_dashboard method anchor not found")
    account = account.replace(method_anchor, close_method + method_anchor, 1)

sidebar_css = r'''

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
'''

if "ASBO 1.2.2 SIDEBAR STRUCTURE FIX" not in account:
    needle = "\nCSS;"
    pos = account.rfind(needle)
    if pos < 0:
        raise SystemExit("account CSS heredoc terminator not found")
    account = account[:pos] + sidebar_css + account[pos:]

account_php.write_text(account)
print("ASBO 1.2.2 structural sidebar fix applied successfully")
