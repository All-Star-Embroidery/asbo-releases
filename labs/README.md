# ASBO Labs

ASBO Labs is the isolated beta lane for the next All Star Bulk Order experience.

## Hard safety rules

- Production `main` and production `latest.json` are not modified by Labs builds.
- The production ASBO plugin remains active and customer-facing.
- Labs has its own plugin slug (`asbo-labs`), Gutenberg block (`allstar/asbo-labs-builder`), CSS namespace (`.asbo-labs-*`), JavaScript state, REST namespace (`asbo-labs/v1`) and beta update feed (`beta.json` on the `asbo-labs` branch).
- Labs phase 1 reads WooCommerce catalog/product/variation data only. It does **not** add to cart, create orders, upload artwork, change stock, change pricing, or write WooCommerce product/order metadata.
- Supplier cost/reference fields are never sent to the Labs browser payload.
- Exact production ASBO pricing is intentionally not duplicated. Labs uses customer-visible WooCommerce Regular Price for the UX sandbox until the V2 experience is approved and a customer-safe production pricing adapter is connected.

## Editor use

Install and activate **ASBO Labs**, then add the **ASBO Labs Builder** Gutenberg block to the private beta page. A shortcode fallback also exists:

```text
[asbo_labs]
```

Optional category filter:

```text
[asbo_labs category="hats" limit="30"]
```

The block includes Inspector controls for the product-category slug and product limit.

## Beta 1.3.0 direction

This first Labs build implements the approved interaction direction rather than the production ASBO structure:

1. **Items** — desktop split workspace: product library left, focused product configurator right.
2. **Artwork** — short, choice-based artwork and production-details step.
3. **Checkout** — review-only safety stop in phase 1.

The old full Intro step is removed. A small **New here?** helper explains the three steps and automatically disappears after 10 seconds.

### Desktop/tablet

- Uses horizontal space instead of stacking a long accordion down the page.
- Keeps product browsing and configuration visible together.
- Shows a persistent project summary footer.
- Artwork uses a wide work area plus project-summary rail.

### Mobile

- Uses the same information architecture but reflows into a focused single-column builder.
- Color/variation choices become horizontally scrollable cards.
- Primary progression stays available in the sticky project footer.

## Hallmark + All Star design guardrails

- All Star Navy is the structural anchor; Heritage Gold is an accent, not a background effect.
- No gradient hero, glassmorphism, floating AI-dashboard card grid, or pill-everything treatment.
- Roboto Slab is reserved for editorial hierarchy while Inter/system UI handles functional controls.
- Layout is intentionally biased/asymmetric on desktop rather than centered-everything.
- Borders/rules and whitespace do more work than shadows.
- Corners stay modest.
- Mobile reflows instead of shrinking desktop UI.

## Promotion model

Labs is not a second production codebase. The V2 builder is developed here until approved. Integration comes later:

`UX sandbox → integration beta → release candidate → production ASBO`

When V2 is approved, the production plugin adopts the tested V2 module/markup and connects it to the existing ASBO pricing, cart, artwork and checkout services. Promotion must not be a rewrite from screenshots.

## Update lanes

Production:

```text
main/latest.json → approved ASBO production release
```

Labs:

```text
asbo-labs/beta.json → Labs branch ZIP
```

The feeds are deliberately separate.
