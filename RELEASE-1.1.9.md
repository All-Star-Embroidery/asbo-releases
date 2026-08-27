# All Star Bulk Order Block v1.1.9

Artwork review and approval workflow for All Star Embroidery orders.

## Customer artwork experience

- Replaces the separate post-checkout upload presentation with one state-aware **Artwork** component on the WooCommerce Thank You page and **My Account → Orders → View Order**.
- Uses the existing secure artwork storage and existing order metadata, so previously uploaded files remain attached to their orders.
- Artwork now has clear customer-facing states:
  - **Artwork Needed**
  - **Awaiting Review**
  - **Changes Requested**
  - **Approved**
- When artwork is waiting for review, the upload controls collapse instead of encouraging duplicate submissions.
- Customers can still intentionally replace a submission before review through a secondary **Need to replace the submitted artwork?** control.
- When changes are requested, the exact review note appears in the same Artwork component and the revised-upload form is presented immediately.
- Approved artwork becomes a clear approved state and customer re-upload is blocked unless the store is contacted.
- The 10K stitch allowance remains visible as secondary information without changing any pricing calculations.

## WooCommerce admin artwork review

- Adds a native-looking **ASBO Artwork Review** panel inside the existing WooCommerce order edit screen rather than replacing WooCommerce order management.
- Supports classic WooCommerce orders and HPOS order screens.
- Shows attached files, image previews where available, secure downloads, customer artwork notes, artwork plan, previous-order reference, 10K stitch reminder, current artwork status, and review history.
- Adds **Approve Artwork** and **Request Changes & Email Customer** actions.
- Request Changes requires a reason so the customer receives useful instructions.
- Adds an **Artwork** status column to both classic and HPOS WooCommerce order lists.

## Email workflow

- Emails the same recipients configured for WooCommerce New Order notifications when new artwork is submitted.
- Sends a new admin notification when revised artwork is submitted after changes were requested.
- Approving artwork emails the customer that their artwork is cleared for production.
- Requesting changes emails the customer the exact reason and includes a direct link back to their order to upload revised artwork.

## Audit trail

- New submissions, revised submissions, approvals, and requested changes are stored in an artwork review history tied to the WooCommerce order.
- Important review actions also continue to create private WooCommerce Order Notes.
- Existing legacy artwork metadata is reused:
  - `_ase_order_artwork_files`
  - `_ase_artwork_status`
  - `_ase_artwork_customer_notes`
- Legacy `received` artwork status is interpreted as **Awaiting Review**.

## Migration from the old Code Snippets uploader

- ASBO v1.1.9 contains the artwork uploader itself.
- If the old **All Star Post-Checkout Artwork** Code Snippets snippet is still enabled, ASBO removes only its customer/admin display hooks so duplicate artwork panels are not shown.
- Existing files and order metadata are preserved.
- After verifying v1.1.9 on the live site, the legacy artwork snippet can be disabled because ASBO now owns the complete upload/review workflow.

## Preserved behavior

No customer pricing, ASBO bulk-discount tiers, Supplier Sync pricing/cost architecture, cart totals, savings calculations, checkout product logic, shipping rules, or automatic stitch-count charging changed in this release.
