# All Star Bulk Order (ASBO) Releases

Canonical public release repository for the **All Star Bulk Order Block** WordPress/WooCommerce plugin used by All Star Embroidery.

## Current release

**v1.1.8**

Package: `all-star-bulk-order-block-1.1.8.zip`

GitHub Release asset SHA-256: `db73470d04b2e47358ba0d15a8d79aa128e952b4f031067dfa525a7193c1d0fb`

Release asset size: **45,436 bytes**

The package was validated for PHP syntax, editor JavaScript syntax, `block.json`, WordPress ZIP structure, and ZIP archive integrity before publication.

## WordPress update architecture

ASBO reads `latest.json` to discover updates. Starting with **v1.1.8**, the plugin's built-in updater points to:

`https://raw.githubusercontent.com/All-Star-Embroidery/asbo-releases/main/latest.json`

`latest.json` then points WordPress at the versioned GitHub Release asset.

The versioned ZIP must always contain the stable plugin directory:

```text
all-star-bulk-order-block-v1.0.0/
├── all-star-bulk-order-block.php
├── README.txt
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

## v1.1.8 seed note

A temporary one-time seeder was used only to move the initial v1.1.8 binary package into this new public repository and create its Release asset. That temporary trigger is removed after seeding; ongoing publishing remains manual-only.

## Pricing safety

ASBO's public customer pricing remains based on WooCommerce Regular Price for 1+ and the configured ASBO customer pricing matrix for higher quantity tiers. Supplier `unit_buy_price`, private supplier `price_breaks`, MAP/MSRP/list prices, and other supplier-only cost/reference fields must not be exposed by this release repository or the ASBO customer interface.
