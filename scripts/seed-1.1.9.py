#!/usr/bin/env python3
from pathlib import Path
import shutil
import sys

if len(sys.argv) != 2:
    raise SystemExit('usage: seed-1.1.9.py <plugin-folder>')

root = Path(sys.argv[1])
main = root / 'all-star-bulk-order-block.php'
block = root / 'block' / 'block.json'
readme = root / 'README.txt'
source_review = Path('staging/v1.1.9/class-asbo-artwork-review.php')
target_review = root / 'includes' / 'class-asbo-artwork-review.php'

s = main.read_text()
for old, new in [
    (' * Version: 1.1.8', ' * Version: 1.1.9'),
    ("    private const VERSION = '1.1.8';", "    private const VERSION = '1.1.9';"),
]:
    if s.count(old) != 1:
        raise SystemExit(f'expected one source marker {old!r}; found {s.count(old)}')
    s = s.replace(old, new, 1)

boot_old = '\nASBO_Plugin::boot();\n'
boot_new = "\nrequire_once __DIR__ . '/includes/class-asbo-artwork-review.php';\n\nASBO_Plugin::boot();\nASBO_Artwork_Review::boot();\n"
if s.count(boot_old) != 1:
    raise SystemExit(f'ASBO boot marker expected once; found {s.count(boot_old)}')
s = s.replace(boot_old, boot_new, 1)
main.write_text(s)

b = block.read_text()
if b.count('"version": "1.1.8"') != 1:
    raise SystemExit('block.json v1.1.8 version marker not found exactly once')
block.write_text(b.replace('"version": "1.1.8"', '"version": "1.1.9"', 1))

r = readme.read_text()
if 'All Star Bulk Order Block v1.1.8' in r:
    r = r.replace('All Star Bulk Order Block v1.1.8', 'All Star Bulk Order Block v1.1.9', 1)
r += '''\n\nv1.1.9 artwork review workflow:\n- Consolidates the post-checkout artwork uploader into ASBO while preserving the existing protected file storage and WooCommerce order metadata.\n- Replaces the customer-facing upload box with one state-aware Artwork component on Thank You and My Account > Orders > View.\n- Adds artwork statuses: Artwork Needed, Awaiting Review, Changes Requested, and Approved.\n- Emails WooCommerce new-order recipients when new or revised artwork is submitted.\n- Adds a native ASBO Artwork Review panel to WooCommerce orders with file preview/download, customer notes, review history, Approve Artwork, and Request Changes.\n- Approval emails the customer and records the action in WooCommerce Order Notes.\n- Request Changes requires a reason, emails the customer with a direct re-upload link, and records the review in the order history.\n- Revised uploads return the order to Awaiting Review and notify the team again.\n- Adds an Artwork status column to classic and HPOS WooCommerce order lists.\n- Reuses the legacy _ase_order_artwork_files, _ase_artwork_status, and _ase_artwork_customer_notes metadata so existing uploads remain available.\n- If the old Code Snippets uploader is still active, ASBO suppresses its duplicate customer/admin panels during migration.\n- Pricing, Supplier Sync, bulk tiers, cart totals, 10K stitch calculations, and checkout behavior are unchanged.\n'''
readme.write_text(r)

target_review.parent.mkdir(parents=True, exist_ok=True)
shutil.copy2(source_review, target_review)
print('ASBO v1.1.9 artwork workflow transform applied')
