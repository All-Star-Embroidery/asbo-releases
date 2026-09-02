# All Star Bulk Order Block v1.3.0

Streamlined production ordering flow for All Star Embroidery.

## Customer workflow

- Removes the full-page **Intro** step and opens directly on **Items**.
- Replaces Intro with a dismissible first-visit **New here?** popup that auto-closes after 10 seconds.
- Uses three customer-facing progress stages: **Items → Artwork → Checkout**.
- Checkout is marked active while WooCommerce checkout is being prepared; ASBO does not add a fake checkout page.
- Fixes the frontend progress layout from four columns to three.

## WordPress block organization

- Moves **All Star Bulk Order** into the `all-star-embroidery` inserter category.
- Registers **All Star Embroidery** as a fallback block category when another All Star plugin has not already registered it.
- Keeps the welcome-popup title/text, workflow labels, colors, radii, thresholds and summary-bar controls editable in Gutenberg.

## Current store policy alignment

- Updates the default standard-shipping reference from **$9.99 to $10.00** and normalizes the legacy $9.99 default at runtime.
- Updates the default artwork incentive wording to **Digitizing + setup included**.
- Treats **Reuse approved artwork** as already covering digitizing/setup, even below the normal free-artwork quantity threshold.
- `Total Saved` continues to show actual bulk-price savings and the $10 shipping saving when its threshold is reached.
- Removes the theoretical $15 digitizing/setup amount from `Total Saved` because ASBO does not itself levy that cart fee. The legacy $15 setting remains in block metadata for backward compatibility/reference.
- **No new WooCommerce cart fee is introduced in this release.**

## Artwork + accessibility polish

- Pre-checkout artwork guidance now matches the protected uploader's supported formats: **JPG, PNG and PDF**.
- Increases the **New here?** close target and offsets the popup below the WordPress admin bar during logged-in testing.
- Uses a versioned first-visit key so future materially changed onboarding can be shown once again without affecting saved order-builder state.

## Compatibility / preserved systems

- Keeps WooCommerce Regular Price authoritative for the `1+` storefront price.
- Keeps `_asbo_pricing_matrix` authoritative for decorated quantity tiers above 1.
- Keeps real supplier variation IDs, variation validation, atomic cart rollback and ASBO cart metadata unchanged.
- Keeps protected artwork storage, artwork review/status history, customer/admin artwork actions and order metadata unchanged.
- Keeps the v1.2.5 My Account two-container shell and customer project-hub dashboard intact.
- Keeps Supplier Sync ownership boundaries intact; supplier wholesale/cost data is not used as customer ASBO pricing.

## Packaging / update channel

- WordPress package root remains `all-star-bulk-order-block-v1.0.0/` to preserve the installed plugin basename.
- The production updater is changed to 1.3.0 only after the validated GitHub Release asset exists.
