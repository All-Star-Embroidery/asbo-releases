# All Star Bulk Order Block v1.1.8

Responsive pricing-matrix readability fix and release-repository migration for the All Star Embroidery bulk-order experience.

## Pricing matrix

- Prevents prices such as `$30.22`, `$29.22`, etc. from visually overlapping on tablets and phones.
- Stops narrow screens from compressing all quantity tiers into columns that are physically smaller than the price text.
- On narrower viewports, the matrix preserves readable minimum column widths and uses a controlled horizontal swipe/scroll instead.
- Keeps the **Decoration method** column visible while the customer scrolls across quantity tiers.
- Uses tabular numerals for cleaner price alignment.
- Mobile receives slightly tighter—but still readable—column widths than tablet.
- Desktop layouts above 1100px retain the existing full-width pricing-table behavior.

## Product details carried forward from v1.1.7

- Keeps the muted **Details & sizing** chip directly in each collapsed product row.
- Opens the existing product-details/size-information modal without requiring the customer to expand the pricing accordion first.
- Keeps the details chip and accordion target as separate native controls for correct pointer and keyboard behavior.

## GitHub updater migration

- Moves ASBO's canonical update manifest to `All-Star-Embroidery/asbo-releases`.
- v1.1.8 reads `https://raw.githubusercontent.com/All-Star-Embroidery/asbo-releases/main/latest.json` for future updates.
- The old release repository can point existing v1.1.7 installs at v1.1.8 once; after that, ASBO updates are independent of Supplier Sync releases.
- A manual-only publisher is included in this repository and runs only through `workflow_dispatch`.

## Validation

The seeded v1.1.8 package passed:

- PHP syntax validation
- Gutenberg/editor JavaScript syntax validation
- Inline storefront JavaScript syntax validation
- `block.json` JSON validation
- ZIP integrity validation
- WordPress plugin package structure validation

Package SHA-256:

`e0009d645e160c8deef6193e6050f80505153134fc3dfec29a61e3619a6555cb`

Package size: **45,270 bytes**

## Preserved behavior

No customer prices, quantity thresholds, bulk-discount ladder, WooCommerce Regular Price behavior, Supplier Sync cost architecture, 10K stitch policy, cart calculations, savings calculations, artwork flow, or checkout behavior changed in this release.
