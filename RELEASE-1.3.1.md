# All Star Bulk Order Block v1.3.1

Production hardening, artwork authorization, compatibility, and performance release based on the reviewed v1.3.1 RC2 candidate.

## Highlights
- Adds explicit customer artwork-use authorization and records the consent/version/timestamp on WooCommerce orders.
- Preserves the protected post-checkout artwork workflow and adds safer storage checks.
- Fixes the My Account Artwork fallback so the Artwork tab renders the Artwork hub rather than the Dashboard.
- Prevents duplicate ASBO cart groups when a customer backs out of checkout, changes quantities, and submits again.
- Adds WooCommerce Checkout Block / Store API order-meta support and formal HPOS + Cart/Checkout Blocks compatibility declarations.
- Fetches a fresh submission nonce instead of baking a nonce into cacheable page HTML.
- Moves the large bulk-order and My Account CSS/JS payloads into browser-cacheable static assets.
- Makes multiple ASBO blocks on one page independent and prevents duplicate event listeners.
- Removes initial force-scroll on page load.
- Reduces repeated pricing/category work and limits the legacy product-title repair to a locked admin-only migration path.
- Adds short negative caching for failed GitHub update checks and makes auto-update behavior filterable.
- Keeps WooCommerce Regular Price authoritative for 1+ and `_asbo_pricing_matrix` authoritative for configured bulk tiers.
- Keeps Supplier Sync variation IDs, ASBO Matrix cart metadata, artwork review, and My Account behavior compatible.

## Packaging
The ZIP intentionally retains the historical `all-star-bulk-order-block-v1.0.0/` root directory so existing WordPress installations update in place rather than risking installation as a second plugin directory.

## Deferred intentionally
Lazy-loading product variation/configuration panels and deeper My Account CSS consolidation remain separate future performance work because they change more of the runtime/UI lifecycle and should be tested independently.
