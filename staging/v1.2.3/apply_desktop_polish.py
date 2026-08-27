from __future__ import annotations

import json
import re
import sys
from pathlib import Path

if len(sys.argv) != 2:
    raise SystemExit('usage: apply_desktop_polish.py <plugin-root>')

root = Path(sys.argv[1]).resolve()
main_php = root / 'all-star-bulk-order-block.php'
block_json = root / 'block' / 'block.json'
account_php = root / 'includes' / 'class-asbo-account-experience.php'
readme = root / 'README.txt'

for path in (main_php, block_json, account_php, readme):
    if not path.is_file():
        raise SystemExit(f'missing required file: {path}')

# Promote the tested 1.2.2 account redesign to a real updater-visible 1.2.3.
main = main_php.read_text()
main, n1 = re.subn(r'(\* Version:\s*)1\.2\.2\b', r'\g<1>1.2.3', main, count=1)
main, n2 = re.subn(r"(private const VERSION = ')1\.2\.2(';)", r'\g<1>1.2.3\g<2>', main, count=1)
if n1 != 1 or n2 != 1:
    raise SystemExit('could not promote plugin version from 1.2.2 to 1.2.3')
main_php.write_text(main)

block = json.loads(block_json.read_text())
if block.get('version') != '1.2.2':
    raise SystemExit(f"unexpected block version: {block.get('version')!r}")
block['version'] = '1.2.3'
block_json.write_text(json.dumps(block, indent=2) + '\n')

account = account_php.read_text()
account = account.replace("wp_register_style( 'asbo-account-experience', false, array(), '1.2.2' );", "wp_register_style( 'asbo-account-experience', false, array(), '1.2.3' );", 1)

css = r'''

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
'''

if 'ASBO 1.2.3 DESKTOP ACCOUNT RAIL + QUICK ACCESS POLISH' in account:
    raise SystemExit('desktop polish already applied')
needle = '\nCSS;'
pos = account.rfind(needle)
if pos < 0:
    raise SystemExit('account CSS heredoc terminator not found')
account = account[:pos] + css + account[pos:]
account_php.write_text(account)

r = readme.read_text()
r = r.replace('All Star Bulk Order Block v1.2.2', 'All Star Bulk Order Block v1.2.3', 1)
notes = '''

v1.2.3 desktop account rail + quick access polish:
- Keeps the approved mobile My Account experience unchanged.
- Makes customer identity and WooCommerce account navigation one continuous navy desktop/tablet rail on every endpoint, including Dashboard, Account Details and individual orders.
- Removes endpoint-dependent sidebar gaps by forcing the wrapped sidebar to be one self-contained grid item.
- Restyles Orders, Artwork, Addresses and Account Details dashboard shortcuts as compact editorial quick links rather than boxed/table-like buttons.
- Preserves All Star navy/gold/cream brand rules, restrained borders, modest corners and production-oriented status styling.
- No Supplier Sync pricing, ASBO tiers, cart, checkout, shipping, artwork storage/review persistence, authentication or order-security logic changed.
'''
if 'v1.2.3 desktop account rail + quick access polish:' not in r:
    r = r.rstrip() + notes + '\n'
readme.write_text(r)

print('ASBO 1.2.3 desktop account polish applied successfully')
