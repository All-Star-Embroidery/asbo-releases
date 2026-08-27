# All Star Bulk Order Block v1.2.1

Reliability and UI/UX correction release for the All Star Embroidery customer account and artwork workflow.

## Artwork approval actually works

- Fixes the WooCommerce admin **Approve Artwork** button that previously appeared to only refresh the order page.
- Root cause: the Artwork Review metabox contained forms nested inside WooCommerce's existing order-edit form. Nested forms are invalid HTML, so browsers could submit the outer WooCommerce form instead of the artwork action.
- Replaces the nested forms with dedicated authenticated WordPress AJAX actions for **Approve Artwork** and **Request Changes**.
- Disables review controls while saving, surfaces success/error feedback inline, and reloads only after the action succeeds.
- Approval and requested-change states are reloaded from WooCommerce and verified after persistence before success is reported or customer email is sent.
- Existing audit history and WooCommerce Order Notes remain intact.

## Artwork tab 404 fix

- Fixes the **Artwork** tab in My Account returning a 404 on sites where permalink rewrite rules had not refreshed correctly.
- Keeps the native WooCommerce Artwork endpoint registered and bumps the endpoint rewrite version so rules refresh again after update.
- Adds a reliable query-string fallback for the account navigation, so clicking Artwork works even if a hosting/cache layer delays permalink regeneration.
- Keeps Artwork highlighted as the active account section when the fallback route is used.

## Billing and Shipping address layout

- Forces WooCommerce Billing and Shipping address sections into a true two-column desktop/tablet layout.
- Resets theme float, clear, width, margin and positioning rules that could place the two address blocks diagonally or on separate rows.
- Uses a clean single-column stacked layout on smaller screens.

## HeroUI-inspired My Account redesign

The account experience now follows a lighter HeroUI-style surface system while remaining native WooCommerce/PHP rather than introducing a fragile React runtime into the account shortcode.

- Removes the large dark profile card and most nested card containers.
- Uses a simple customer identity row and a quiet navigation rail with a soft active state.
- Reworks Dashboard quick actions into a clean information strip separated by subtle rules instead of individual cards.
- Converts recent orders and Artwork activity into natural list rows with lightweight status chips.
- Reduces heavy borders, shadows and rounded containers throughout Orders, Artwork, Addresses, Account Details, Payment Methods, Downloads and order views.
- Uses soft HeroUI-style fields with transparent borders at rest, clearer focus treatment, restrained radius and native accessibility.
- Keeps All Star Embroidery navy and warm gold as hierarchy/accent colors rather than filling every surface with branding.
- Restyles the customer Artwork section so it flows as part of the WooCommerce order instead of looking like a separate widget dropped onto the page.

## Preserved functionality

No Supplier Sync pricing, `unit_buy_price`, WooCommerce Regular Price rules, ASBO bulk discount tiers, cart totals, savings calculations, shipping rules, product selection, checkout logic, artwork file storage, customer order ownership/security or automatic stitch-count charging changed in this release.
