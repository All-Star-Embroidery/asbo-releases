# All Star Bulk Order Block v1.2.2 — Account Redesign Preview

Brand-system and responsive redesign of the WooCommerce My Account + Artwork experience.

## Crafted Commerce design system

- Uses the 2026 All Star Embroidery palette: `#080F1F` navy, `#D2A952` Heritage Gold, `#F3EEE7` Warm Cream, white, Steel Gray, and Utility Red.
- Uses Inter for functional UI and Roboto Slab for selected editorial headings when those brand fonts are available on the site.
- Replaces the previous HeroUI/SaaS visual language with a flatter production/editorial system: rules, records, restrained rectangular statuses, and deliberate whitespace.
- Removes unnecessary floating cards, soft pill overload, heavy radius, and decorative shadows.

## Responsive account shell

- Removes the old viewport-breakout `100vw` / translate sizing pattern from My Account.
- Mobile uses a compact navy customer identity bar plus a real **Account menu** disclosure instead of a horizontally scrolling tab strip.
- Tablet gets a dedicated two-column account rail + content layout rather than an enlarged phone layout.
- Desktop keeps a wider navy navigation rail and fluid content area without over-stretching text.
- Mobile touch targets are at least 44px, form fields are 48px, long emails/filenames/order content can wrap, and mobile tables reflow into readable records.
- Includes `prefers-reduced-motion` handling.

## Artwork

- Artwork hub rows now read like production tickets rather than app cards.
- Artwork Needed, Awaiting Review, Changes Requested, and Approved use restrained rectangular production labels.
- Approved uses All Star navy rather than generic success green; Changes Requested reserves Utility Red for action-required states.
- Customer artwork inside an order is visually integrated into the order page with a gold production rule rather than a detached floating widget.

## Preserved functionality

This preview does not change Supplier Sync pricing, supplier cost fields, WooCommerce Regular Price rules, ASBO bulk tiers, cart totals, shipping, checkout, artwork file storage, artwork review persistence/AJAX, order ownership/security, or customer authentication/endpoints.
