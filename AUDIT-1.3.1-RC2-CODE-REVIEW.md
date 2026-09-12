# ASBO v1.3.1-rc.2 — External Code Review Triage

This document records which findings from the external review of `v1.3.1-rc.1` were verified, what was changed in RC2, and what was intentionally deferred or rejected to avoid unnecessary regression risk.

Production is **not** changed by this work. `main/latest.json` remains on v1.3.0.

## Implemented in RC2

### Correctness / checkout
- Fixed the My Account `?asbo-view=artwork` fallback so it actually renders the Artwork hub instead of the ASBO dashboard.
- Prevented ASBO cart duplication when a shopper returns from checkout, edits the builder, and submits again. The replacement is atomic: old ASBO lines are retained until all new lines have been added successfully, then prior ASBO groups are removed.
- Removed the initial page-load `scrollIntoView`; step changes may still scroll intentionally.
- Added a fresh-nonce AJAX endpoint. The builder gets a new nonce immediately before submit, so full-page cache/CDN HTML cannot strand customers with an expired nonce. 403 errors now produce a session-specific message.
- Added WooCommerce Store API / Checkout Block order-meta persistence through `woocommerce_store_api_checkout_update_order_meta`, while retaining the classic checkout hook.
- Retained `woocommerce_checkout_create_order_line_item` for Decoration metadata; Store API checkout still uses WooCommerce's order line-item creation path.
- Added explicit guards for missing WooCommerce session before committing the replacement cart state.

### Multiple-block safety and browser performance
- Moved the large builder CSS and JS out of per-block inline output into cacheable `assets/asbo-frontend.css` and `assets/asbo-frontend.js`.
- Replaced global `window.ASBO_CONFIG` with per-root JSON configuration.
- Added a per-root initialization guard so one root cannot receive duplicate listeners.
- Moved currency locale into configuration rather than hard-coding `en-US`.
- Moved the large My Account CSS into cacheable `assets/asbo-account.css`.

### WooCommerce / WordPress compatibility
- Declared HPOS (`custom_order_tables`) compatibility.
- Declared Cart/Checkout Blocks (`cart_checkout_blocks`) compatibility after adding Store API order-meta persistence.
- Added plugin metadata fields and a translation-domain loader.
- Added `block/editor.asset.php` with the editor script dependencies.
- Removed the second/dead dynamic block render path from `block.json` and removed `block/render.php`; the plugin's registered `render_callback` is the single render path.
- Added a stable NumberControl fallback for the block editor instead of relying exclusively on the experimental component name.
- Added `index.php` silence files to plugin subdirectories.
- Corrected the stale repository URL in README documentation.

### Update system
- Added short negative caching (5 minutes) when the GitHub update manifest cannot be fetched or parsed, preventing repeated blocking HTTP calls during an outage.
- Kept ASBO auto-update enabled by default for compatibility with the current setup, but added the `asbo_enable_auto_updates` filter and removed the brittle boolean callback type hint.

### Catalog / query performance
- Moved the old title repair from every-request `init` to admin-only `admin_init`.
- Added a transient lock to prevent concurrent repair runs.
- Made the repair non-destructive: it only repairs blank or literal `Product` names/display names instead of overwriting merchant-edited names.
- Removed the second product-category lookup in `render_product()`.
- Precomputes category ancestry once per product term using `get_ancestors()` instead of repeatedly calling `term_is_ancestor_of()` inside nested child x product loops.
- Retains the RC1 request-local pricing-matrix cache and product summary cache.

### Artwork storage hardening
- New artwork records continue to avoid persisting public upload URLs.
- Artwork-directory setup now checks directory/protection-file creation and refuses an upload if the server could not create/read the `.htaccess` protection rather than silently accepting files into an unprotected directory.
- Added a one-request print guard for admin artwork styles.

## Intentionally not implemented in RC2

### ZIP root rename
The review suggested changing the archive root from `all-star-bulk-order-block-v1.0.0/` to `all-star-bulk-order-block/`. We did **not** do this in RC2. The existing root has been stable across installed/released ASBO packages. Changing the directory name in an updater package can itself create a second plugin directory on existing sites. The ugly historical folder name is less risky than changing the installed plugin path mid-stream.

### Clearing filtered-out selections
The category buttons are treated as display filters, not destructive selection controls. Hiding a product should not silently erase quantities the customer already entered. The order/review UI continues to include selected hidden products. If UX testing shows this is confusing, the safer future change is to show a small "selected outside this filter" indicator rather than deleting customer work.

### Variation lazy loading
The review is correct that eagerly hydrating/rendering all variations is the largest remaining architectural performance opportunity. It was not added to RC2 because it changes the builder lifecycle, saved-state restoration, accordion behavior, and pricing UI. This should be an ASBO Labs performance beta with before/after measurements rather than an RC patch.

### Artwork files outside the public webroot
The current HostGator/Apache-style deployment can enforce `.htaccess`, and RC2 now fails closed if that protection cannot be written. A host-agnostic move outside the webroot is still worth designing, but it requires migration/download-path handling for existing orders and should be a dedicated storage migration. nginx deployments still require an equivalent server rule until that migration exists.

### Automatic pruning of artwork revisions
No automatic deletion was added. Old revisions can be operationally important when resolving proof/revision disputes. A future retention policy should distinguish superseded customer files, approved production files, and completed/cancelled orders rather than dropping files merely because a numeric cap was reached.

### Customer artwork downloads
Customer downloads remain intentionally disabled. Admin download/preview stays protected. This can be revisited as a separate permission/UX decision.

### Large CSS redesign/consolidation
RC2 makes the CSS cacheable, which yields the performance benefit without changing appearance. Removing the existing specificity/`!important` layers, viewport breakout, and old design-generation rules is a visual-regression project and should be tested separately across the live theme and responsive breakpoints.

### Rewrite flush relocation
The rewrite flush is already version-option guarded, so it occurs only when the endpoint version changes. Moving it to an activation/upgrade lifecycle is cleaner, but lower-value than the verified fixes above and not worth broadening this RC further.

### File path metadata migration
Existing order artwork records store absolute paths. Changing to relative identifiers should include migration/backward compatibility for existing orders, so it was not mixed into RC2.

## Validation performed

The RC2 build workflow verifies:
- PHP syntax for every PHP file.
- `block.json` JSON validity.
- Gutenberg editor JavaScript syntax.
- Frontend JavaScript syntax.
- Presence of cacheable frontend/account assets.
- ASBO pricing matrix / WooCommerce Regular Price contract retained.
- No WooCommerce fee mutation introduced.
- Rights confirmation remains mandatory.
- Classic + Store API checkout order-meta hooks exist.
- HPOS and Cart/Checkout Blocks declarations exist.
- Fresh nonce flow exists.
- Re-submit replacement path exists.
- Per-root config/init guards exist and `window.ASBO_CONFIG` is gone.
- Dead `block/render.php` path is gone and editor dependencies exist.
- Artwork storage fails closed if the protected directory cannot be secured.
- The established plugin ZIP root is retained intentionally.
- `main/latest.json` has not changed.

The resulting review ZIP is `candidates/v1.3.1-rc.2/all-star-bulk-order-block-1.3.1-rc.2.zip` on `review-v1.3.1-rights-performance`.
