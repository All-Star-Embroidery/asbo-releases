# All Star Bulk Order (ASBO) Releases

Canonical public release repository for the **All Star Bulk Order Block** WordPress/WooCommerce plugin used by All Star Embroidery.

## Current release

**v1.2.1**

Package: `all-star-bulk-order-block-1.2.1.zip`

GitHub Release asset SHA-256: `9217fcd3a41b3cb6106720f157f893ae2079f50f06571dd70b851c2b6a8c347e`

Release asset size: **71,264 bytes**

The v1.2.1 package was rebuilt from the validated v1.2.0 Release plus the reviewed v1.2.1 patch, then passed ZIP integrity, PHP syntax checks for the main plugin/artwork-review/My Account modules, JavaScript syntax checks, `block.json` validation, version checks, and package-structure checks before publication.

## v1.2.1 account and artwork corrections

- Fixes **Approve Artwork** and **Request Changes** in the WooCommerce order editor by removing invalid nested forms and using authenticated AJAX actions.
- Verifies the persisted artwork status before reporting success or sending the customer notification email.
- Fixes the My Account **Artwork** link using a rewrite refresh plus a reliable fallback route that does not depend on permalink regeneration finishing immediately.
- Forces Billing and Shipping addresses into a stable two-column desktop/tablet layout and a clean single-column mobile layout.
- Reworks My Account toward a lighter **HeroUI-inspired** surface system with fewer cards, minimal shadows, subtle separators, soft fields, status chips, natural lists, and restrained All Star navy/gold accents.
- Restyles the customer Artwork section so it flows naturally within the WooCommerce order instead of appearing as a separate widget.
- Pricing, Supplier Sync, cart, shipping, checkout, artwork storage, and order security remain unchanged.

## My Account + artwork experience

ASBO keeps WooCommerce's native `[woocommerce_my_account]` system underneath the experience rather than replacing customer/account functionality.

### All Star My Account

- Responsive All Star account presentation with restrained navy/gold branding and WooCommerce-native functionality.
- Styles Dashboard, Orders, Downloads, Addresses, Payment Methods, Account Details, login/register, order views, forms, notices, tables, and buttons.
- Preserves native WooCommerce authentication, order ownership, billing/shipping, passwords, payment methods, downloads, logout, and third-party endpoints such as Wishlist.
- Provides quick access to Orders, Artwork, Addresses, and Account Details.

### Artwork hub

- Adds **Artwork** directly after Orders in My Account.
- Shows artwork status across customer orders: **Artwork Needed**, **Awaiting Review**, **Changes Requested**, and **Approved**.
- Provides direct **Add artwork**, **Upload revision**, and **View artwork** actions that return customers to the correct order/artwork section.
- Adds artwork status and contextual artwork actions to the normal WooCommerce Orders experience.

### Customer + admin artwork review

- Uses the same secure artwork-upload storage and order metadata introduced in the earlier artwork workflow.
- Keeps customer notes, review history, replacement artwork, and 10K-stitch guidance intact.
- WooCommerce administrators receive an **ASBO Artwork Review** section inside the existing order editor; normal WooCommerce payments, refunds, fulfillment, Order Notes, and other order controls remain untouched.

Existing artwork remains compatible through:

- `_ase_order_artwork_files`
- `_ase_artwork_status`
- `_ase_artwork_customer_notes`

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
