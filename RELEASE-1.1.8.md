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
- Existing v1.1.7 installations can be bridged once from the old release repository to this v1.1.8 package; after installation, ASBO updates are independent of Supplier Sync releases.
- The permanent publisher in this repository is manual-only and runs only through `workflow_dispatch`.

## Validation

The published v1.1.8 package passed:

- PHP syntax validation
- Gutenberg/editor JavaScript syntax validation
- `block.json` JSON validation
- ZIP integrity validation
- WordPress plugin package structure validation

GitHub Release asset SHA-256:

`db73470d04b2e47358ba0d15a8d79aa128e952b4f031067dfa525a7193c1d0fb`

Release asset size: **45,436 bytes**

## Preserved behavior

No customer prices, quantity thresholds, bulk-discount ladder, WooCommerce Regular Price behavior, Supplier Sync cost architecture, 10K stitch policy, cart calculations, savings calculations, artwork flow, or checkout behavior changed in this release.
