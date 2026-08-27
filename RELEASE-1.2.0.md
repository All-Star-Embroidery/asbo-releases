# All Star Bulk Order Block v1.2.0

Major customer-account and artwork-review UX release for All Star Embroidery.

## All Star My Account experience

- Keeps the native WooCommerce `[woocommerce_my_account]` shortcode and all existing WooCommerce endpoints/functionality intact.
- Rebuilds the presentation into a responsive All Star account shell with a navy account navigation, customer identity block, modern content surface, stronger typography, and restrained gold accents.
- Breaks the shortcode output out of narrow theme content constraints so the account area can use a professional desktop/tablet width while remaining fully responsive on mobile.
- Restyles Dashboard, Orders, Downloads, Addresses, Payment Methods, Account Details, login/register, individual order views, order tables, customer addresses, forms, buttons, and notices.
- Preserves third-party My Account endpoints such as Wishlist; they remain in the native WooCommerce account navigation.

## Dashboard improvements

- Adds a proper All Star customer dashboard with a welcome header and direct access to orders.
- Adds quick-access cards for Orders, Artwork, Addresses, and Account Details.
- Adds recent-order cards showing WooCommerce order status and artwork status where relevant.
- Keeps WooCommerce authentication, order ownership, billing, shipping, password, payment, downloads, and logout behavior unchanged.

## New Artwork hub

- Adds **Artwork** as a native My Account endpoint directly after Orders.
- Lists customer orders that participate in the ASBO artwork workflow.
- Clearly communicates **Artwork Needed**, **Awaiting Review**, **Changes Requested**, and **Approved** states.
- Gives customers direct **Add artwork**, **Upload revision**, or **View artwork** actions that open the correct WooCommerce order and jump to the artwork section.
- Adds artwork status to the normal My Account Orders list and adds context-aware artwork actions there as well.

## Customer order / artwork experience

- Adds a clearer order-overview header with both WooCommerce order status and artwork status.
- Redesigns the existing customer Artwork component toward the approved **Style B / card-based modern** direction.
- Uses a strong All Star navy artwork header, restrained warm gold, readable status hierarchy, cleaner file rows, improved review states, clearer revision controls, and better mobile spacing.
- The existing secure upload, status, review-history, customer-note, 10K-stitch, and replacement-artwork behavior remains intact.

## WooCommerce admin artwork review

- Redesigns the existing ASBO Artwork Review metabox toward the same Style B direction without replacing the WooCommerce order editor.
- Uses a clear navy review header, modern submitted-file cards, structured review details, review timeline, and a stronger action area.
- WooCommerce's native order management, notes, refunds, payments, fulfillment and other metaboxes are preserved.

## Approve Artwork reliability fix

- Hardens both **Approve Artwork** and **Request Changes** status persistence.
- Artwork status now explicitly saves order meta, saves the WooCommerce order, clears WooCommerce/order caches, reloads the order, and verifies the stored artwork status before reporting success.
- Approval email and success confirmation are only sent after the stored status verifies successfully.
- If persistence fails, the administrator receives an error instead of a false success state.
- The artwork workflow remains separate from the WooCommerce Processing/Completed/etc. order status.

## Preserved behavior

No Supplier Sync pricing, `unit_buy_price`, WooCommerce Regular Price rules, ASBO bulk-discount tiers, cart pricing, savings logic, shipping rules, product selection, checkout logic, artwork file storage, order ownership/security, or automatic stitch-count charging changed in this release.
