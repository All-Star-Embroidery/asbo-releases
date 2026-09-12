All Star Bulk Order Block v1.3.1-rc.1

GitHub-managed production release for All Star Embroidery.

Highlights:
- Gutenberg bulk-order block with editable workflow content and styles.
- Supplier Sync compatible with SanMar, S&S Activewear, Momentec, and multi-supplier Woo variations.
- Server-side variation refresh/validation before cart submission.
- Atomic cart rollback if any selected variation fails.
- Detailed cart failure messages instead of generic HTTP 500 responses.
- GitHub manifest updater with automatic WordPress plugin updates.
- Post-checkout artwork workflow remains separate and supported.

Release source:
https://github.com/rolejarczyk/ASE.SupplierSync-Releases/tree/main/asbo


v1.1.1 mobile UX improvements:
- Hides the redundant large expanded-product hero image on screens 767px and below.
- Keeps one product accordion open at a time on mobile to reduce long-page drift.
- Fits the full per-piece pricing matrix inside the mobile viewport without horizontal swiping.
- Tightens mobile spacing, typography, tier messaging, and decoration controls while preserving desktop styling.


v1.1.2 compact ordering workflow:
- Sticky bottom summary/action bar is visible from Intro through checkout preparation.
- Product accordions are single-open on desktop, tablet, and mobile to reduce scroll buildup.
- Large permanent expanded-product hero/details area is replaced by an on-demand Product Details modal with image, description, garment specifications, and size information.
- Product rows, expanded panels, pricing sections, option grids, and sticky summary are more compact across all viewport sizes.
- Pricing now includes a 1+ normal-price tier sourced from the WooCommerce product/variation price when the first configured bulk tier starts above 1.
- Checkout/cart pricing uses the same normal-price fallback below the first bulk threshold, including mixed variation base prices.
- Sticky summary adds Total Saved: volume savings plus $15 digitizing savings at the artwork threshold and $9.99 shipping savings at the shipping threshold by default.
- Savings label and incentive dollar values can be customized in the Gutenberg block sidebar.
- Existing Supplier Sync compatibility for SanMar, S&S Activewear, Momentec, and multi-supplier variations remains intact.


v1.1.3 readability and artwork guidance:
- Responsive clamp()-based typography keeps compact layouts readable across desktop, tablet, and mobile.
- Sticky summary bar is rendered visible from Step 1, before JavaScript initialization.
- Adds one-time 30-second inactivity arrow cue above the current sticky Continue action.
- Reworks Artwork step into two visually guided tasks with clearer selected-state color.
- Logged-in customers can select from their recent WooCommerce orders when reusing approved artwork; guests retain manual reference entry.


v1.1.4 pricing integrity with Supplier Sync 2.0.26+:
- WooCommerce Regular Price is the authoritative customer-facing 1+ price.
- Legacy 1: entries in _asbo_pricing_matrix are ignored; established bulk tiers above 1 are unchanged.
- ASBO never derives customer pricing from unit_buy_price, supplier price_breaks, MAP, MSRP, suggested retail, or list price.
- Total Saved uses Woo Regular Price as the normal-price baseline.
- Checkout now fails clearly when a selected item is missing Woo Regular Price and directs admins to Supplier Sync Quick Repair.
- Removed the old purchasability bypass for blank supplier variation prices now that Supplier Sync standardizes Woo Regular Price.
- Supplier price_breaks remain private and are never rendered by ASBO; only the customer-facing ASBO pricing matrix is displayed.


v1.1.5 stitch-count pricing communication:
- Clarifies that standard embroidery pricing includes up to 10,000 stitches per design.
- Adds a compact, non-alarming 10K stitch allowance note beside per-piece embroidery pricing.
- Adds a fuller 10K Stitch Allowance explanation in the Artwork step, including that any additional embroidery charge is communicated before production begins.
- Updates the production review fine print to use the same customer-facing stitch-count policy.
- Adds matching Gutenberg editor previews for the policy.
- No pricing calculations, checkout rules, stitch-count automation, supplier data, or ASBO discount tiers changed.


v1.1.6 launch ordering polish:
- Adds a customer-visible Starting at price to every collapsed product row, sourced only from Woo Regular Price and the customer-facing ASBO pricing matrix.
- Strengthens the selected decoration method with a gold border and checkmark in addition to the existing dark active background.
- Adds a lightweight subcategory filter when the ASBO block is scoped to a WooCommerce parent category. Only represented direct child categories of that configured parent are shown, so a Headwear/Hats block never surfaces Shirt/Apparel filters.
- Filtering happens instantly without a page reload and automatically closes a product accordion if it becomes hidden by the selected filter.
- Existing pricing calculations, Supplier Sync cost architecture, 10K stitch policy, cart behavior, and checkout workflow are unchanged.


v1.1.7 product-details discoverability polish:
- Adds a muted Details & sizing chip directly with each collapsed product row.
- Opens the existing details/sizing modal without expanding pricing first.
- Removes the redundant details action from the expanded panel.

v1.1.8 responsive pricing-matrix fix:
- Prevents bulk-pricing dollar amounts from overlapping on tablets and phones.
- Uses controlled horizontal scrolling with readable minimum price-column widths.
- Keeps the Decoration method column visible while customers swipe across tiers.
- Migrates the GitHub updater manifest to All-Star-Embroidery/asbo-releases.
- No price values, bulk tiers, supplier pricing, cart, artwork, savings, or checkout calculations changed.


v1.1.9 artwork review workflow:
- Consolidates the post-checkout artwork uploader into ASBO while preserving the existing protected file storage and WooCommerce order metadata.
- Replaces the customer-facing upload box with one state-aware Artwork component on Thank You and My Account > Orders > View.
- Adds artwork statuses: Artwork Needed, Awaiting Review, Changes Requested, and Approved.
- Emails WooCommerce new-order recipients when new or revised artwork is submitted.
- Adds a native ASBO Artwork Review panel to WooCommerce orders with file preview/download, customer notes, review history, Approve Artwork, and Request Changes.
- Approval emails the customer and records the action in WooCommerce Order Notes.
- Request Changes requires a reason, emails the customer with a direct re-upload link, and records the review in the order history.
- Revised uploads return the order to Awaiting Review and notify the team again.
- Adds an Artwork status column to classic and HPOS WooCommerce order lists.
- Reuses the legacy _ase_order_artwork_files, _ase_artwork_status, and _ase_artwork_customer_notes metadata so existing uploads remain available.
- If the old Code Snippets uploader is still active, ASBO suppresses its duplicate customer/admin panels during migration.
- Pricing, Supplier Sync, bulk tiers, cart totals, 10K stitch calculations, and checkout behavior are unchanged.


v1.1.10 Impeccable-style artwork polish:
- Restyles only the customer Artwork component and WooCommerce ASBO Artwork Review panel.
- Uses All Star Embroidery navy, warm gold, white, and quiet neutral surfaces with semantic green/red reserved for actual approval/change states.
- Reduces nested card styling, heavy shadows, and competing borders in favor of one clear visual hierarchy.
- Improves typography, spacing, focus states, file rows, review history, and mobile stacking.
- Makes the admin review surface feel native to WooCommerce while carrying restrained All Star brand cues.
- Upload, approval, email, order-note, artwork status, pricing, Supplier Sync, cart, and checkout logic are unchanged.


v1.2.1 All Star My Account experience:
- Restyles the WooCommerce [woocommerce_my_account] output without replacing WooCommerce endpoints or account functionality.
- Adds a responsive All Star account shell with navy navigation, customer identity, stronger typography, modern forms/tables, and mobile account navigation.
- Adds a dedicated Artwork account endpoint that summarizes artwork progress across the customer’s recent orders.
- Adds artwork status to My Account order lists and direct Add Artwork / Fix Artwork / Artwork actions.
- Adds a dashboard overview with recent orders, artwork attention, address, and account-detail quick access.
- Restyles order details, billing/shipping addresses, payment/account forms, downloads, login/register, and customer order views.
- Upgrades the customer Artwork card and WooCommerce admin Artwork Review panel toward the approved Style-B card-based direction.
- Hardens Approve Artwork and Request Changes persistence by saving order meta, clearing WooCommerce/order caches, and verifying the stored status before reporting success or sending the customer email.
- Preserves WooCommerce authentication, orders, addresses, payment methods, downloads, account details, logout, third-party account endpoints, artwork files, review history, emails, pricing, cart, shipping, and checkout behavior.


v1.2.1 account + artwork reliability/UI update:
- Fixes the Artwork My Account tab 404 with a query-string fallback while keeping the pretty WooCommerce endpoint registered and refreshing rewrite rules.
- Replaces invalid nested admin forms in the WooCommerce artwork metabox with AJAX review controls, fixing Approve Artwork and Request Changes actions that previously only refreshed the order page.
- Verifies persisted artwork status before reporting success or sending customer email.
- Forces Billing and Shipping address sections into a consistent side-by-side desktop layout and clean single-column responsive layout.
- Reworks My Account toward a HeroUI-inspired surface system: lighter native flows, restrained surfaces, pill status chips, soft fields, subtle separators, minimal shadows, and fewer card containers.
- Reduces the card-heavy appearance across Dashboard, Orders, Artwork, Addresses, Account Details, Payment Methods, Downloads, login/register and order views.
- Restyles the customer Artwork section to flow as part of the order rather than appearing as a separate boxed widget.
- No Supplier Sync pricing, customer price tiers, cart, shipping, checkout, artwork file storage, or order ownership/security behavior changed.

v1.2.2 Crafted Commerce account redesign:
- Rebuilds the My Account visual system around the 2026 All Star Embroidery brand guide: #080F1F navy, #D2A952 gold, #F3EEE7 cream, Inter utility type, and Roboto Slab editorial headings.
- Removes the previous HeroUI/SaaS card-and-pill feel in favor of production records, editorial rules, restrained status labels, and a continuous navy account rail.
- Replaces the mobile horizontal account navigation with an accessible Account menu disclosure and 44px+ touch targets.
- Uses dedicated mobile, tablet, and desktop layouts instead of shrinking the desktop shell.
- Removes viewport breakout/translate sizing from the account shell to prevent theme-dependent horizontal overflow.
- Reworks Artwork into production-ticket rows with clearer hierarchy and status treatment.
- Keeps WooCommerce authentication/endpoints, artwork storage/review logic, ASBO pricing, Supplier Sync, cart, checkout, shipping, and order ownership unchanged.

v1.2.3 desktop account rail + quick access polish:
- Keeps the approved mobile My Account experience unchanged.
- Makes customer identity and WooCommerce account navigation one continuous navy desktop/tablet rail on every endpoint, including Dashboard, Account Details and individual orders.
- Removes endpoint-dependent sidebar gaps by forcing the wrapped sidebar to be one self-contained grid item.
- Restyles Orders, Artwork, Addresses and Account Details dashboard shortcuts as compact editorial quick links rather than boxed/table-like buttons.
- Preserves All Star navy/gold/cream brand rules, restrained borders, modest corners and production-oriented status styling.
- No Supplier Sync pricing, ASBO tiers, cart, checkout, shipping, artwork storage/review persistence, authentication or order-security logic changed.

v1.2.4 two-container account layout correction:
- Replaces the split before/after-navigation wrapper hooks with one explicit server-rendered account sidebar callback.
- Desktop/tablet My Account now has exactly two sibling containers: a continuous navy sidebar and the WooCommerce content area.
- Prevents Dashboard, Account Details and long order pages from being nested into or squeezed to the sidebar width.
- Preserves the approved mobile Account menu behavior and mobile responsive design.
- Reworks Dashboard quick access into an unboxed editorial link band instead of four rectangle/table-style buttons.
- Keeps artwork attention indicators and the All Star navy/gold/cream brand system.
- No Supplier Sync pricing, ASBO tiers, cart, checkout, shipping, artwork storage/review persistence, authentication or order-security logic changed.

v1.2.5 customer project hub dashboard:
- Replaces the redundant Dashboard shortcut/navigation band with useful customer project information.
- Keeps the personal Welcome back greeting as the dashboard anchor.
- Adds a conditional Needs Your Attention area only for Artwork Needed or Changes Requested; Awaiting Review does not create a false customer warning.
- Adds one Current Project section with order workflow, concise status explanation, order date, item count, fulfillment method, and the correct order/artwork action.
- Keeps Recent Orders as the primary lower-dashboard utility while avoiding duplicate navigation cards.
- Adds a restrained Start Another Project CTA with bulk-order-page lookup and Shop fallback.
- Uses the existing All Star navy/gold/cream Crafted Commerce visual system and mobile-first reflow.
- Leaves the v1.2.4 two-container sidebar, approved mobile Account menu, Orders, Artwork, addresses, account details, Supplier Sync pricing, ASBO tiers, cart, shipping, checkout, artwork persistence/review, authentication, and order security unchanged.

v1.3.0 streamlined ordering workflow:
- Removes the full-page Intro step and opens directly on Items.
- Replaces Intro with a dismissible first-visit "New here?" popup that auto-closes after 10 seconds; the popup copy remains editable in the Gutenberg sidebar.
- Uses three customer-facing progress stages: Items, Artwork, Checkout. Checkout is marked active while WooCommerce checkout is being prepared; no fake checkout panel is introduced.
- Fixes the frontend progress layout to three columns.
- Moves the block into the All Star Embroidery inserter group and registers that block category as a fallback when another All Star plugin has not already created it.
- Updates the default shipping reference from $9.99 to the current $10 flat-rate value.
- Recognizes Reuse approved artwork as already covering digitizing/setup, even below the normal free-artwork quantity threshold.
- Stops adding a theoretical $15 digitizing/setup value to Total Saved because ASBO does not itself levy that cart fee; the existing $15 setting remains for backward-compatible block configuration/reference.
- Keeps actual volume savings and the $10 shipping saving in Total Saved when their real thresholds are met.
- Aligns pre-checkout artwork copy with the protected uploader’s supported JPG, PNG and PDF formats.
- Increases the welcome-popup close target and offsets it below the WordPress admin bar for safer testing.
- Updates the My Account style handle to 1.3.0 for consistent cache/version diagnostics.
- Keeps WooCommerce Regular Price authoritative at 1+, the customer-facing _asbo_pricing_matrix authoritative for decorated bulk tiers, real supplier variation IDs, cart ASBO metadata, protected artwork storage/review, and the v1.2.5 My Account project hub unchanged.
- No new cart fee is introduced in this release.

v1.3.1-rc.1 review candidate — artwork authorization + conservative performance pass:
- Adds required, versioned artwork-use authorization before ASBO prepares the WooCommerce cart/checkout.
- Persists the exact authorization statement, version, timestamp, user context and source on the WooCommerce order.
- Requires the same confirmation at post-checkout upload for legacy/non-builder orders that do not already have a recorded authorization.
- Shows artwork-rights confirmation in the ASBO Artwork Review admin panel.
- Does not store IP addresses and does not claim that customer confirmation eliminates All Star Embroidery's legal obligations.
- Stops persisting public artwork upload URLs for new submissions; protected review/download continues to use the existing order-authenticated endpoint.
- Adds request-local pricing-matrix caching, removes repeated category-term lookups, reuses bounded My Account order queries, and caches client-side product summaries so a quantity change does not rescan every variation on the page.
- Preserves ASBO pricing tiers, WooCommerce Regular Price ownership, supplier variation IDs, atomic cart behavior, artwork review/status history, My Account, and Supplier Sync boundaries.
- This is a review candidate only; production latest.json is intentionally unchanged.

