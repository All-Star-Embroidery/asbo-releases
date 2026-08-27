#!/usr/bin/env python3
from pathlib import Path
import sys

if len(sys.argv) != 2:
    raise SystemExit('usage: seed-1.1.8.py <plugin-folder>')

root = Path(sys.argv[1])
main = root / 'all-star-bulk-order-block.php'
block = root / 'block' / 'block.json'
readme = root / 'README.txt'

s = main.read_text()
for old, new in [
    (' * Version: 1.1.7', ' * Version: 1.1.8'),
    ("    private const VERSION = '1.1.7';", "    private const VERSION = '1.1.8';"),
    ('Update URI: https://github.com/rolejarczyk/ASE.SupplierSync-Releases/tree/main/asbo', 'Update URI: https://github.com/All-Star-Embroidery/asbo-releases'),
    ("private const UPDATE_MANIFEST_URL = 'https://raw.githubusercontent.com/rolejarczyk/ASE.SupplierSync-Releases/main/asbo/latest.json';", "private const UPDATE_MANIFEST_URL = 'https://raw.githubusercontent.com/All-Star-Embroidery/asbo-releases/main/latest.json';"),
]:
    if s.count(old) != 1:
        raise SystemExit(f'expected one source marker {old!r}; found {s.count(old)}')
    s = s.replace(old, new, 1)

css = r'''

/* v1.1.8 — keep the pricing matrix readable on tablets and phones. */
.asbo__table-scroll {
  max-width: 100%;
}

@media (max-width: 1100px) {
  .asbo__table-scroll {
    overflow-x: auto !important;
    overflow-y: hidden;
    padding-bottom: 4px;
    overscroll-behavior-inline: contain;
    scrollbar-width: thin;
    -webkit-overflow-scrolling: touch;
  }

  .asbo__pricing-table {
    width: auto;
    min-width: 100% !important;
    table-layout: auto !important;
  }

  .asbo__pricing-table th,
  .asbo__pricing-table td {
    white-space: nowrap !important;
    font-variant-numeric: tabular-nums;
  }

  .asbo__pricing-table th:first-child,
  .asbo__pricing-table td:first-child {
    position: sticky;
    left: 0;
    z-index: 2;
    width: 132px !important;
    min-width: 132px;
    box-shadow: 1px 0 0 var(--asbo-border);
    overflow-wrap: normal !important;
  }

  .asbo__pricing-table th:not(:first-child),
  .asbo__pricing-table td:not(:first-child) {
    min-width: 70px;
  }

  .asbo__pricing-table thead th:first-child {
    z-index: 3;
    background: var(--asbo-surface);
  }

  .asbo__pricing-table tbody th:first-child,
  .asbo__pricing-table tbody td:first-child {
    background: #fff;
  }
}

@media (max-width: 767px) {
  .asbo__pricing-table th,
  .asbo__pricing-table td {
    padding-right: 7px;
    padding-left: 7px;
  }

  .asbo__pricing-table th:first-child,
  .asbo__pricing-table td:first-child {
    width: 122px !important;
    min-width: 122px;
  }

  .asbo__pricing-table th:not(:first-child),
  .asbo__pricing-table td:not(:first-child) {
    min-width: 64px;
  }
}
'''
marker = '\nCSS;\n    }\n\n    private static function inline_js'
if s.count(marker) != 1:
    raise SystemExit(f'inline CSS marker expected once; found {s.count(marker)}')
s = s.replace(marker, css + marker, 1)
main.write_text(s)

b = block.read_text()
if b.count('"version": "1.1.7"') != 1:
    raise SystemExit('block.json v1.1.7 version marker not found exactly once')
block.write_text(b.replace('"version": "1.1.7"', '"version": "1.1.8"', 1))

r = readme.read_text()
if 'All Star Bulk Order Block v1.1.7' in r:
    r = r.replace('All Star Bulk Order Block v1.1.7', 'All Star Bulk Order Block v1.1.8', 1)
r += '''\n\nv1.1.8 responsive pricing-matrix fix and updater migration:\n- Prevents pricing values from overlapping on tablet/mobile by preserving readable column widths and enabling controlled horizontal scrolling.\n- Keeps the Decoration method column visible while swiping quantity tiers.\n- Migrates the built-in updater to All-Star-Embroidery/asbo-releases.\n- No pricing values, discount tiers, Supplier Sync logic, cart, savings, artwork, or checkout behavior changed.\n'''
readme.write_text(r)

print('ASBO v1.1.8 transform applied')
