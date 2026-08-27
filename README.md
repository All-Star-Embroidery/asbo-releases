# All Star Bulk Order (ASBO) Releases

Canonical public release repository for the **All Star Bulk Order Block** WordPress/WooCommerce plugin used by All Star Embroidery.

## Current release

**v1.1.8**

Package: `all-star-bulk-order-block-1.1.8.zip`

SHA-256: `e0009d645e160c8deef6193e6050f80505153134fc3dfec29a61e3619a6555cb`

The package has been validated for:

- PHP syntax
- Gutenberg/editor JavaScript syntax
- Inline storefront JavaScript syntax
- `block.json` validity
- WordPress ZIP structure
- ZIP archive integrity

## WordPress update architecture

ASBO reads `latest.json` to discover updates. Starting with **v1.1.8**, the plugin's built-in updater points to:

`https://raw.githubusercontent.com/All-Star-Embroidery/asbo-releases/main/latest.json`

The versioned ZIP must always contain the stable plugin directory:

```text
all-star-bulk-order-block-v1.0.0/
├── all-star-bulk-order-block.php
├── README.txt
└── block/
```

Do not rename that installed plugin folder between releases.

## Current no-Actions seed

v1.1.8 was seeded directly because GitHub Actions minutes were unavailable. The repository copy of the ZIP was uploaded as verified binary bytes, not as generated text/base64 content.

Until the manual publisher is run, `latest.json` points directly at the verified raw repository ZIP. Once the manual publisher runs, it creates/updates the GitHub Release asset and rewrites `latest.json` to the preferred `/releases/download/...` URL.

## Manual-only publisher

`.github/workflows/publish-manual.yml` has **only** a `workflow_dispatch` trigger. It will never run because of a push, issue, PR, or scheduled event.

To publish a future version manually:

1. Put `all-star-bulk-order-block-X.Y.Z.zip` in the repository root.
2. Put release notes in `RELEASE-X.Y.Z.md`.
3. Confirm the plugin header and internal plugin version both report `X.Y.Z`.
4. Open **Actions → Publish ASBO Release (Manual Only) → Run workflow**.
5. Enter `X.Y.Z`.
6. The workflow validates the ZIP, creates or updates tag `asbo-vX.Y.Z`, uploads the ZIP as a real GitHub Release asset, and changes `latest.json` to the release-asset URL.

## Pricing safety

ASBO's public customer pricing remains based on WooCommerce Regular Price for 1+ and the configured ASBO customer pricing matrix for higher quantity tiers. Supplier `unit_buy_price`, private supplier `price_breaks`, MAP/MSRP/list prices, and other supplier-only cost/reference fields must not be exposed by this release repository or the ASBO customer interface.
