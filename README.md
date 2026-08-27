# All Star Bulk Order (ASBO) Releases

Canonical public release repository for the **All Star Bulk Order Block** WordPress/WooCommerce plugin used by All Star Embroidery.

## Current release

**v1.2.1**

Package: `all-star-bulk-order-block-1.2.1.zip`

GitHub Release asset SHA-256: `9217fcd3a41b3cb6106720f157f893ae2079f50f06571dd70b851c2b6a8c347e`

Release asset size: **71,264 bytes**

The published Release asset matches the validated v1.2.0 package byte-for-byte and passes ZIP integrity plus PHP syntax checks for the main plugin, artwork-review module, and My Account experience module. The plugin header and `block.json` both report v1.2.0.

## v1.2.0 My Account + artwork experience

v1.2.0 is a major customer-account and artwork-review UX release. It keeps WooCommerce's native `[woocommerce_my_account]` system underneath the experience rather than replacing customer/account functionality.

### All Star My Account

- Responsive All Star account shell with navy navigation, clean white content surfaces, stronger typography, and restrained warm-gold accents.
- Restyles Dashboard, Orders, Downloads, Addresses, Payment Methods, Account Details, login/register, order views, forms, notices, tables, and buttons.
- Preserves native WooCommerce authentication, order ownership, billing/shipping, passwords, payment methods, downloads, logout, and third-party endpoints such as Wishlist.
- Adds a clearer dashboard with quick access to Orders, Artwork, Addresses, and Account Details.

### Artwork hub

- Adds **Artwork** as a native My Account endpoint directly after Orders.
- Shows artwork status across customer orders: **Artwork Needed**, **Awaiting Review**, **Changes Requested**, and **Approved**.
- Adds direct **Add artwork**, **Upload revision**, and **View artwork** actions that return customers to the correct order/artwork section.
- Adds artwork status and contextual artwork actions to the normal WooCommerce Orders experience.

### Customer + admin artwork review

- Redesigns the customer Artwork component and WooCommerce admin Artwork Review panel toward the approved **Style B / card-based modern** direction.
- Uses All Star navy for hierarchy, restrained warm gold for accents, clearer file cards, review history, status states, and responsive spacing.
- Keeps existing secure artwork uploads, protected storage, customer notes, review history, replacement artwork, and 10K-stitch guidance intact.

### Approval reliability

- Hardens **Approve Artwork** and **Request Changes** persistence.
- Artwork status is explicitly saved to order meta and the WooCommerce order, caches are cleared, the order is reloaded, and the stored status is verified before success is reported.
- Approval email/success messaging occurs only after the persisted status verifies successfully.
- Artwork review remains separate from WooCommerce Processing/Completed/payment/fulfillment status.

## Artwork workflow

ASBO owns the complete post-checkout artwork workflow while preserving the order metadata and protected upload storage used by the previous Code Snippets implementation.

Existing artwork remains compatible through:

- `_ase_order_artwork_files`
- `_ase_artwork_status`
- `_ase_artwork_customer_notes`


## v1.2.1 account and artwork corrections

- Fixes artwork approval/request-changes actions in the WooCommerce admin by removing invalid nested forms and using authenticated AJAX actions.
- Fixes the My Account Artwork link with a rewrite refresh plus a fallback route that does not depend on permalink regeneration.
- Forces Billing and Shipping addresses into a stable two-column desktop/tablet layout.
- Reworks the My Account UI toward a lighter HeroUI-inspired surface system with fewer cards, minimal shadows, subtle separators, soft fields, status chips and restrained All Star navy/gold accents.
- Pricing, Supplier Sync, cart, shipping, checkout, artwork storage and order security remain unchanged.

## WordPress update architecture

ASBO reads `latest.json` to discover updates. The plugin's built-in updater points to:

`https://raw.githubusercontent.com/All-Star-Embroidery/asbo-releases/main/latest.json`

`latest.json` then points WordPress at the versioned GitHub Release asset.

The versioned ZIP must always contain the stable plugin directory:

```text
all-star-bulk-order-block-v1.0.0/
├── all-star-bulk-order-block.php
├── README.txt
├── includes/
└── block/
```

Do not rename that installed plugin folder between releases.

## Manual-only publisher

The permanent publisher is `.github/workflows/publish-manual.yml` and has **only** a `workflow_dispatch` trigger. It does not run on pushes, issues, pull requests, schedules, or tags.

For future releases, keep the versioned ZIP and matching `RELEASE-X.Y.Z.md` in the repository, validate the stable plugin-folder structure, then use **Actions → Publish ASBO Release (Manual Only)**. Temporary migration/repair workflows should be removed after use so ongoing publishing remains manual-only.

## Pricing safety

ASBO's public customer pricing remains based on WooCommerce Regular Price for 1+ and the configured ASBO customer pricing matrix for higher quantity tiers. Supplier `unit_buy_price`, private supplier `price_breaks`, MAP/MSRP/list prices, and other supplier-only cost/reference fields must not be exposed by this release repository or the ASBO customer interface.
