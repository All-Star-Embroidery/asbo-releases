# ASBO v1.3.1-rc.1 — Artwork Rights + Performance Audit

**Status:** review candidate only. Production `latest.json` remains on v1.3.0.

This review started from the exact published v1.3.0 GitHub Release ZIP, not an older source copy.

## Artwork-use authorization

This is a practical product/compliance design, not legal advice. Final wording can be reviewed by Ohio counsel if All Star Embroidery wants a formal legal opinion.

### Why ASBO should capture affirmative authorization

- Ohio's Uniform Electronic Transactions Act provides that an electronic record/signature cannot be denied legal effect solely because it is electronic, and an electronic signature can satisfy a signature requirement. See Ohio Rev. Code §1306.06: https://codes.ohio.gov/ohio-revised-code/section-1306.06
- Ohio's right-of-publicity statute defines protected persona to include name, voice, signature, photograph, image, likeness, or distinctive appearance, and expressly says written consent can include electronic, digital, or other verifiable authorization. See Ohio Rev. Code §§2741.01–.02: https://codes.ohio.gov/ohio-revised-code/chapter-2741
- Ohio trademark law provides civil remedies for certain unauthorized reproduction/use of registered marks. See Ohio Rev. Code §§1329.65–.66: https://codes.ohio.gov/ohio-revised-code/chapter-1329
- Federal copyright law gives copyright owners exclusive reproduction/adaptation/distribution rights, subject to statutory limitations. See 17 U.S.C. §106: https://www.law.cornell.edu/uscode/text/17/106
- Federal trademark law also addresses unauthorized use/reproduction of registered marks and false indications of affiliation, sponsorship, or approval. See 15 U.S.C. §§1114, 1125: https://www.law.cornell.edu/uscode/text/15/1114 and https://www.law.cornell.edu/uscode/text/15/1125

A checkbox is not a complete liability shield and does not make otherwise-infringing work lawful. Its value is that the customer makes an affirmative, retained representation about authorization and expressly permits All Star Embroidery to reproduce the submitted art for proofing/fulfillment.

### Candidate statement (version `2026-09-11-v1`)

> I confirm that I own this artwork or have permission or a license to reproduce and use it for this order, including any copyrights, trademarks, school/team/business logos, names, images, likenesses, or other protected content. I authorize All Star Embroidery to reproduce the submitted artwork solely to prepare proofs and fulfill this order. I understand All Star Embroidery may pause or decline work if authorization is unclear.

Helper copy also explains that finding an image online does not by itself grant reproduction permission.

### Audit trail stored on the WooCommerce order

- `_asbo_artwork_rights_confirmed`
- `_asbo_artwork_rights_version`
- `_asbo_artwork_rights_accepted_at`
- `_asbo_artwork_rights_user_id`
- `_asbo_artwork_rights_statement`
- `_asbo_artwork_rights_source`

The candidate deliberately does **not** store an IP address. It stores the exact statement/version, timestamp, user context when available, source (`builder` or `upload`), and the order itself.

Builder consent is required before ASBO mutates the cart. Legacy/non-builder orders that reach the protected artwork uploader without a prior authorization record must confirm there. The admin Artwork Review panel shows whether authorization was recorded and when.

## Security review

Preserved controls:

- WooCommerce nonce validation for ASBO cart preparation.
- Server-side product/variation validation rather than trusting browser variation data.
- Atomic cart rollback when any selected line fails.
- Stock/purchasability checks.
- Artwork order-key/nonce/ownership checks.
- File-count and per-file size limits.
- Server-side MIME/type verification for JPG/PNG/PDF.
- Protected artwork directory plus authenticated admin download endpoint.
- Artwork history remains bounded to avoid unbounded order-meta growth.

Hardening in RC1:

- New artwork uploads no longer persist their public uploads URL in order metadata. Review/preview/download continues through ASBO's protected endpoint using stored server paths. Existing legacy metadata remains readable for compatibility.

Hosting note: the existing `.htaccess` deny rule protects the artwork upload directory on Apache/LiteSpeed. A future migration to nginx should include an equivalent server-level deny rule for `/wp-content/uploads/all-star-artwork/`.

## Performance audit

### Improvements included in RC1

1. **Pricing matrix request cache** — parsing the same `_asbo_pricing_matrix` string repeatedly in one request now reuses the parsed array.
2. **Category filter query reduction** — product category term IDs are fetched once per product rather than once for every child-category × product combination.
3. **My Account order-query reuse** — Dashboard, Artwork hub, and artwork attention indicator share one bounded per-request customer-order query instead of repeating the same 50-order lookup.
4. **Client product-summary cache** — a quantity or decoration change recalculates the changed product and reuses cached summaries for the rest, instead of rescanning every quantity input across every product on each click/keystroke.
5. Existing query limits remain bounded (builder products max 200; recent previous orders 25; account artwork orders 50).
6. Existing localStorage use remains bounded to the builder state and onboarding key; no interval/timer leak was found.
7. ASBO frontend markup/scripts remain page-scoped rather than globally enqueued across the entire shop.
8. The one-time historical catalog-title repair remains guarded by a version option and returns immediately after it has run.

### Largest remaining performance opportunity (not mixed into RC1)

The current production builder still server-renders the full variation configuration for every enabled product on initial page load. For catalogs with many colors/variations, this is the dominant remaining initial-load cost: WooCommerce product objects are hydrated and a large hidden DOM is generated even for product accordions the shopper never opens.

The fastest architecture would lazy-load a product's variation/configuration panel only when that product is opened, with a small cache after first load. That would materially reduce initial PHP work, response HTML, browser DOM size, and JavaScript startup cost. Because it changes state restoration and the product-opening lifecycle, it should be built/tested in ASBO Labs rather than silently folded into this legal/safety RC.

A second future opportunity is moving the large inline builder CSS/JS into versioned static assets so browsers/CDNs can cache them. That is also better handled as a separately tested performance release.

## Regression boundaries

RC1 intentionally preserves:

- WooCommerce Regular Price as the 1+ price authority.
- `_asbo_pricing_matrix` as decorated quantity-tier authority.
- grouping by parent product + decoration method.
- real Supplier Sync variation IDs and variation attributes.
- current stock validation and atomic cart behavior.
- existing ASBO cart metadata shape used by pricing/checkout.
- current artwork statuses, protected storage, review history, approval/change-request workflow, and customer emails.
- current My Account two-container layout/project hub.
- Supplier Sync ownership of supplier catalog/inventory data.
- no new WooCommerce `add_fee()` behavior.

## Review candidate

Branch: `review-v1.3.1-rights-performance`

Candidate package: `candidates/v1.3.1-rc.1/all-star-bulk-order-block-1.3.1-rc.1.zip`

This ZIP is **not** a GitHub Release and is **not** referenced by production `latest.json`.
