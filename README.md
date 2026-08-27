# All Star Bulk Order (ASBO) Releases

Canonical public release repository for the **All Star Bulk Order Block** WordPress/WooCommerce plugin used by All Star Embroidery.

## Current release

**v1.1.10**

Package: `all-star-bulk-order-block-1.1.10.zip`

GitHub Release asset SHA-256: `ce3e4b615c4a483292fa716e0e589752a436292e3fcae4534273a7fbfc0ffb5f`

Release asset size: **60,183 bytes**

The package was validated for PHP syntax, artwork-review PHP syntax, Gutenberg/editor JavaScript syntax, inline storefront JavaScript syntax, `block.json`, WordPress ZIP structure, and ZIP archive integrity before publication.

## v1.1.10 artwork visual polish

The v1.1.9 upload/review workflow remains intact, but its customer and admin presentation now follows a quieter Impeccable-style hierarchy using All Star Embroidery’s navy, muted warm gold, white, and neutral palette.

- Reduced nested cards, shadows, and competing borders.
- Muted gold is used as an accent rather than a dominant surface color.
- Navy establishes primary hierarchy and the admin approval action.
- Green/red remain reserved for meaningful Approved / Changes Requested states.
- Customer file rows and review history are lighter and more integrated with the WooCommerce order page.
- The WooCommerce Artwork Review metabox now reads as one coherent review surface rather than multiple cards.
- Responsive spacing, focus states, file previews, timelines, and action hierarchy were refined.

This release is visual-only; upload handling, protected storage, artwork status logic, emails, WooCommerce Order Notes, pricing, Supplier Sync, cart, shipping, and checkout logic are unchanged.

## Artwork workflow

ASBO owns the complete post-checkout artwork workflow while preserving the order metadata and protected upload storage used by the previous Code Snippets implementation.

Customer artwork states:

- **Artwork Needed**
- **Awaiting Review**
- **Changes Requested**
- **Approved**

The existing customer upload area on the Thank You page and **My Account → Orders → View Order** is one state-aware Artwork component rather than a separate duplicate review box.

WooCommerce administrators receive an **ASBO Artwork Review** panel inside the existing order-edit screen with secure file preview/download, customer notes, review history, **Approve Artwork**, and **Request Changes & Email Customer** actions. Artwork review remains separate from the WooCommerce payment/fulfillment order status.

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

To publish a future version manually:

1. Put `all-star-bulk-order-block-X.Y.Z.zip` in the repository root.
2. Put release notes in `RELEASE-X.Y.Z.md`.
3. Confirm the plugin header and internal plugin version both report `X.Y.Z`.
4. Open **Actions → Publish ASBO Release (Manual Only) → Run workflow**.
5. Enter `X.Y.Z`.
6. The workflow validates the ZIP, creates or updates tag `asbo-vX.Y.Z`, uploads the ZIP as a real GitHub Release asset, and updates `latest.json` to the Release-asset URL.

Temporary one-time seed workflows may be used during connector-driven migrations, but they must be removed after the release is verified. Ongoing publishing remains manual-only.

## Pricing safety

ASBO's public customer pricing remains based on WooCommerce Regular Price for 1+ and the configured ASBO customer pricing matrix for higher quantity tiers. Supplier `unit_buy_price`, private supplier `price_breaks`, MAP/MSRP/list prices, and other supplier-only cost/reference fields must not be exposed by this release repository or the ASBO customer interface.
