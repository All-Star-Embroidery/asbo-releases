# ASBO Matrix

ASBO Matrix is a small companion plugin for the production All Star Bulk Order plugin.

Its job is intentionally narrow: bring the same customer-facing ASBO quantity pricing matrix to normal WooCommerce single-product templates without changing products that do not have a matrix.

## Hard behavior

- Reads the existing `_asbo_pricing_matrix` product meta used by production ASBO.
- Does **not** require the product's separate `_asbo_enabled` bulk-page checkbox. A valid filled matrix is the trigger.
- If the current product has no valid matrix, the Gutenberg block returns an empty string: no table, no JS, no hidden fields, and no pricing behavior.
- 1+ pricing always displays and falls back to WooCommerce **Regular Price**, matching production ASBO.
- Bulk thresholds use the exact matrix format already stored on the product.
- Embroidery matrices show the existing **10K stitch allowance** message.
- If a matrix contains multiple decoration methods, the block shows a branded decoration selector so the product-page cart submission knows which row to price from.

## Cart integration

ASBO Matrix does not create a competing pricing engine. When the block is present on the product page, its script marks the normal WooCommerce add-to-cart form and sends the selected decoration method.

The companion plugin then adds the same `asbo` cart metadata used by production ASBO. The existing production `woocommerce_before_calculate_totals` logic therefore:

- combines quantities across variations of the same parent product + decoration method;
- applies the same bulk tier price;
- restores WooCommerce Regular Price below the first bulk threshold;
- carries the Decoration label into cart/order metadata through the existing ASBO hooks.

## Gutenberg block

Add **ASBO Matrix** to the WooCommerce Single Product template.

The block supports:

- default, Wide and Full alignment;
- WordPress margin/padding controls;
- custom heading and description;
- show/hide description;
- show/hide 10K stitch allowance;
- show/hide decoration selector when a product has multiple pricing rows;
- show/hide live active-tier helper.

The editor intentionally shows a placeholder because the storefront output is resolved from the current product context.

## Safety

The plugin never writes product pricing/meta and never changes normal WooCommerce pricing when `_asbo_pricing_matrix` is blank or invalid.
