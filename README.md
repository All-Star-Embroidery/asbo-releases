# All Star Bulk Order (ASBO) Releases

Canonical public release repository for the **All Star Bulk Order Block** WordPress/WooCommerce plugin used by All Star Embroidery.

## Current production release

**v1.3.0**

GitHub Release package: `all-star-bulk-order-block-1.3.0.zip`

GitHub Release asset SHA-256: `e7c6c23726a8bc660a78eced36ce651c64e0c2cc26a64772de3d43c508479607`

Release asset size: **84,495 bytes**

Production updater metadata is stored in `latest.json`, which currently points WordPress to the v1.3.0 GitHub Release asset.

See `RELEASE-1.3.0.md` for the detailed release notes.

## v1.3.0 ordering flow

ASBO now uses a streamlined three-stage customer flow:

**Items → Artwork → Checkout**

The old full-page Intro stage was removed. First-time guidance is handled by a dismissible **New here?** popup that automatically closes after 10 seconds.

Checkout remains WooCommerce-owned. ASBO marks Checkout active while it validates the order, builds the WooCommerce cart, and redirects; it does not create a separate fake checkout step.

The frontend progress indicator is intentionally a three-column layout.

## Gutenberg / WordPress block organization

The **All Star Bulk Order** block is registered in the shared:

`all-star-embroidery`

block category.

ASBO also registers **All Star Embroidery** as a fallback block category if another All Star plugin has not already registered it. This keeps All Star blocks grouped together while avoiding a hard dependency on another plugin.

The block remains Gutenberg-editable, including workflow text, onboarding text, colors, radii, thresholds, and summary-bar settings exposed by the block.

## Pricing and store-policy ownership

ASBO customer pricing follows these boundaries:

- WooCommerce **Regular Price** is authoritative for the `1+` customer price.
- `_asbo_pricing_matrix` is authoritative for decorated quantity tiers above 1.
- Supplier Sync wholesale/cost fields are not customer-facing ASBO prices.
- Real supplier variation IDs and variation validation remain intact.
- ASBO does not introduce a new WooCommerce digitizing/setup fee in v1.3.0.

### Current incentives

- Standard shipping reference: **$10.00**.
- Legacy `$9.99` default values are normalized to `$10.00` at runtime.
- Default artwork incentive wording: **Digitizing + setup included**.
- **Reuse approved artwork** immediately counts digitizing/setup as covered, even below the normal free-artwork quantity threshold.
- `Total Saved` reports real bulk-price savings plus the real $10 shipping saving when earned.
- The legacy `$15` digitizing/setup block setting remains only for backward compatibility/reference; it is not added to `Total Saved` because ASBO does not currently levy that cart fee.

## Artwork workflow

Pre-checkout artwork guidance matches the protected uploader's supported formats:

**JPG, PNG, and PDF**

ASBO preserves the protected artwork workflow and its existing customer/admin review behavior, including customer notes, revision history, statuses, and order metadata.

Artwork status values used in the account experience include:

- **Artwork Needed**
- **Awaiting Review**
- **Changes Requested**
- **Approved**

Existing artwork compatibility remains based on metadata including:

- `_ase_order_artwork_files`
- `_ase_artwork_status`
- `_ase_artwork_customer_notes`

## My Account experience

ASBO keeps WooCommerce's native `[woocommerce_my_account]` functionality underneath the branded experience rather than replacing WooCommerce account behavior.

The current account system retains the v1.2.5 two-container desktop/tablet shell and customer project-hub dashboard, while preserving WooCommerce authentication, order ownership, billing/shipping, passwords, payment methods, downloads, logout, and third-party account endpoints.

The **Artwork** account area remains integrated with normal WooCommerce customer orders and provides contextual artwork actions such as adding artwork, uploading a revision, or viewing the current artwork state.

WooCommerce administrators continue to receive the **ASBO Artwork Review** interface inside the existing order editor; normal WooCommerce payment, refund, fulfillment, order-note, and security behavior remains WooCommerce-owned.

## Supplier Sync boundary

Supplier Sync and ASBO are intentionally separate concerns.

Supplier data may provide product/variation identity, inventory, media, weight, supplier cost/reference data, and other catalog information, but customer ASBO pricing must not be derived from private supplier wholesale fields.

Do not expose supplier-only values such as `unit_buy_price`, private supplier `price_breaks`, or other cost/reference fields through the ASBO customer interface or public release metadata.

## WordPress update architecture

ASBO reads the production updater manifest at:

`https://raw.githubusercontent.com/All-Star-Embroidery/asbo-releases/main/latest.json`

`latest.json` points WordPress to the current versioned GitHub Release asset.

The production updater should only move to a new version **after** the corresponding GitHub Release asset has been built, validated, published, and verified.

## Required ZIP structure

The versioned ZIP must preserve the stable installed plugin directory:

```text
all-star-bulk-order-block-v1.0.0/
├── all-star-bulk-order-block.php
├── README.txt
├── includes/
└── block/
```

Do **not** rename the installed plugin folder between releases. The stable directory preserves the WordPress plugin basename and prevents update/install path problems.

## Release validation expectations

Before production updater metadata is changed, a release should verify at minimum:

- ZIP integrity and expected top-level plugin folder.
- PHP syntax for changed PHP modules.
- JavaScript syntax for changed frontend/editor scripts.
- Valid `block/block.json`.
- Matching plugin/block/release version values.
- Presence of ASBO tier pricing and `_asbo_pricing_matrix` behavior.
- Presence of real variation validation and atomic cart handling.
- Presence of ASBO cart/project metadata behavior.
- Presence of protected artwork storage and review systems.
- No accidental use of supplier wholesale fields as public ASBO pricing.
- No unintended WooCommerce cart fee added during unrelated UX releases.

For v1.3.0, the release pipeline completed its build, validation, GitHub Release publication, release-asset verification, production-updater update, and final checks successfully before `latest.json` was moved to 1.3.0.

## Publishing workflows

The permanent general ASBO publisher on `main` is:

`.github/workflows/publish-manual.yml`

It uses a manual `workflow_dispatch` trigger and is intended to remain the normal controlled publishing path.

One-off migration, repair, or guarded release workflows may be used when necessary, but they should not become the permanent release mechanism. Temporary publishing workflows should be removed or kept off `main` once their job is complete.

The repository also contains an ASBO Matrix publishing workflow. **ASBO Matrix is a separate plugin/release stream** and should not be confused with the main All Star Bulk Order Block version.

## Release documentation convention

For each production ASBO release:

1. Maintain a matching `RELEASE-X.Y.Z.md` with customer-facing changes, technical safeguards, compatibility notes, and packaging/update-channel notes.
2. Keep `README.md` pointed at the actual current production release.
3. Keep `latest.json` synchronized with the published GitHub Release asset only after that asset is verified.
4. Preserve the stable `all-star-bulk-order-block-v1.0.0/` ZIP root.
5. Keep historical release notes for reference rather than rewriting old release documents to describe newer behavior.

## Current v1.3.0 compatibility summary

v1.3.0 intentionally preserves:

- WooCommerce Regular Price for `1+` pricing.
- `_asbo_pricing_matrix` quantity-tier pricing.
- Real supplier variation IDs and variation validation.
- Atomic cart rollback and ASBO cart metadata.
- Protected artwork storage, review/status history, and customer/admin artwork actions.
- WooCommerce My Account functionality and the v1.2.5 project-hub/account shell.
- Supplier Sync ownership boundaries.
- The stable plugin directory used by WordPress updates.
