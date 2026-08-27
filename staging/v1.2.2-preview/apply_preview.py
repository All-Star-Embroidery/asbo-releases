from __future__ import annotations

import json
import re
import sys
from pathlib import Path


if len(sys.argv) != 2:
    raise SystemExit("usage: apply_preview.py <plugin-root>")

root = Path(sys.argv[1]).resolve()
main_php = root / "all-star-bulk-order-block.php"
block_json = root / "block" / "block.json"
account_php = root / "includes" / "class-asbo-account-experience.php"
readme = root / "README.txt"

for path in (main_php, block_json, account_php, readme):
    if not path.is_file():
        raise SystemExit(f"missing required file: {path}")

# ---------------------------------------------------------------------------
# Version bump only. No pricing/cart/Supplier Sync/artwork-storage code changes.
# ---------------------------------------------------------------------------
text = main_php.read_text()
text, n1 = re.subn(r"(\* Version:\s*)1\.2\.1\b", r"\g<1>1.2.2", text, count=1)
text, n2 = re.subn(r"(private const VERSION = ')1\.2\.1(';)", r"\g<1>1.2.2\g<2>", text, count=1)
if n1 != 1 or n2 != 1:
    raise SystemExit("could not bump main plugin version from 1.2.1")
main_php.write_text(text)

data = json.loads(block_json.read_text())
if data.get("version") != "1.2.1":
    raise SystemExit(f"unexpected block.json version: {data.get('version')!r}")
data["version"] = "1.2.2"
block_json.write_text(json.dumps(data, indent=2) + "\n")

# ---------------------------------------------------------------------------
# Account shell: add a real mobile menu control and append the brand-guide
# responsive design layer after the existing 1.2.1 CSS. Keeping it as a final
# override minimizes risk to WooCommerce behavior while replacing the visual
# system comprehensively.
# ---------------------------------------------------------------------------
account = account_php.read_text()
if "ASBO 1.2.2 BRAND SYSTEM" in account:
    raise SystemExit("1.2.2 brand system already present")

account, style_count = re.subn(
    r"wp_register_style\( 'asbo-account-experience', false, array\(\), '[^']+' \);",
    "wp_register_style( 'asbo-account-experience', false, array(), '1.2.2' );",
    account,
    count=1,
)
if style_count != 1:
    raise SystemExit("could not update account stylesheet cache version")

identity_anchor = """            </div>\n        </div>\n        <?php\n    }\n\n    public static function render_dashboard"""
if identity_anchor not in account:
    raise SystemExit("account identity anchor not found")

identity_replacement = """            </div>
            <button type=\"button\" class=\"asbo-account-menu-toggle\" aria-expanded=\"false\" aria-controls=\"asbo-account-navigation\">
                <span><?php esc_html_e( 'Account menu', 'all-star-bulk-order' ); ?></span>
                <span class=\"asbo-account-menu-toggle__icon\" aria-hidden=\"true\"></span>
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

    public static function render_dashboard"""
account = account.replace(identity_anchor, identity_replacement, 1)

brand_css = r'''

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
'''

needle = "\nCSS;"
pos = account.rfind(needle)
if pos < 0:
    raise SystemExit("account CSS heredoc terminator not found")
account = account[:pos] + brand_css + account[pos:]
account_php.write_text(account)

# Keep the plugin's own changelog useful when the preview ZIP is installed.
r = readme.read_text()
r = r.replace("All Star Bulk Order Block v1.2.1", "All Star Bulk Order Block v1.2.2", 1)
notes = """

v1.2.2 Crafted Commerce account redesign:
- Rebuilds the My Account visual system around the 2026 All Star Embroidery brand guide: #080F1F navy, #D2A952 gold, #F3EEE7 cream, Inter utility type, and Roboto Slab editorial headings.
- Removes the previous HeroUI/SaaS card-and-pill feel in favor of production records, editorial rules, restrained status labels, and a continuous navy account rail.
- Replaces the mobile horizontal account navigation with an accessible Account menu disclosure and 44px+ touch targets.
- Uses dedicated mobile, tablet, and desktop layouts instead of shrinking the desktop shell.
- Removes viewport breakout/translate sizing from the account shell to prevent theme-dependent horizontal overflow.
- Reworks Artwork into production-ticket rows with clearer hierarchy and status treatment.
- Keeps WooCommerce authentication/endpoints, artwork storage/review logic, ASBO pricing, Supplier Sync, cart, checkout, shipping, and order ownership unchanged.
"""
if "v1.2.2 Crafted Commerce account redesign:" not in r:
    r = r.rstrip() + notes + "\n"
readme.write_text(r)

print("ASBO 1.2.2 account redesign patch applied successfully")
