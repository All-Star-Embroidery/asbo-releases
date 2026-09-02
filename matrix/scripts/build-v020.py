from __future__ import annotations

import json
import re
from pathlib import Path

ROOT = Path('matrix/asbo-matrix')
PHP = ROOT / 'asbo-matrix.php'
JS = ROOT / 'assets' / 'matrix.js'
BLOCK = ROOT / 'block' / 'block.json'
EDITOR_ASSET = ROOT / 'block' / 'editor.asset.php'

php = PHP.read_text()

php = php.replace(' * Version: 0.1.2', ' * Version: 0.2.0', 1)
php = php.replace("private const VERSION = '0.1.2';", "private const VERSION = '0.2.0';", 1)

blocks_hook = "        add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'register_store_api_extensions' ) );\n"
init_hook = "        add_action( 'init', array( __CLASS__, 'register_block' ) );\n"
if blocks_hook not in php:
    if init_hook not in php:
        raise SystemExit('init hook anchor not found')
    php = php.replace(init_hook, init_hook + blocks_hook, 1)

old_register = r'''    public static function register_block(): void {
        $path = plugin_dir_path( __FILE__ ) . 'block';
        if ( file_exists( $path . '/block.json' ) ) {
            register_block_type(
                $path,
                array(
                    'render_callback' => array( __CLASS__, 'render_block' ),
                )
            );
        }
    }
'''
new_register = r'''    public static function register_block(): void {
        $pricing_path = plugin_dir_path( __FILE__ ) . 'block';
        if ( file_exists( $pricing_path . '/block.json' ) ) {
            register_block_type(
                $pricing_path,
                array(
                    'render_callback' => array( __CLASS__, 'render_block' ),
                )
            );
        }

        $quantity_path = plugin_dir_path( __FILE__ ) . 'quantity-block';
        if ( file_exists( $quantity_path . '/block.json' ) ) {
            register_block_type( $quantity_path );
        }
    }
'''
if old_register in php:
    php = php.replace(old_register, new_register, 1)
elif "plugin_dir_path( __FILE__ ) . 'quantity-block'" not in php:
    raise SystemExit('register_block method anchor not found')

method_anchor = "    private static function fetch_update_manifest( bool $force = false ): ?array {\n"
extension_method = r'''    /**
     * Expose the small amount of ASBO identity data the custom quantity block
     * needs to distinguish cart lines for the same variation using different
     * decoration methods. This is public Store API data only; no private order
     * or customer data is exposed.
     */
    public static function register_store_api_extensions(): void {
        if (
            ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ||
            ! class_exists( '\\Automattic\\WooCommerce\\StoreApi\\Schemas\\V1\\CartItemSchema' )
        ) {
            return;
        }

        woocommerce_store_api_register_endpoint_data(
            array(
                'endpoint'        => \\Automattic\\WooCommerce\\StoreApi\\Schemas\\V1\\CartItemSchema::IDENTIFIER,
                'namespace'       => 'asbo_matrix',
                'data_callback'   => static function ( $cart_item ): array {
                    $asbo = isset( $cart_item['asbo'] ) && is_array( $cart_item['asbo'] )
                        ? $cart_item['asbo']
                        : array();

                    return array(
                        'parent_product_id' => absint( $asbo['parent_product_id'] ?? 0 ),
                        'decoration'        => sanitize_text_field( (string) ( $asbo['decoration'] ?? '' ) ),
                        'source'            => sanitize_text_field( (string) ( $asbo['source'] ?? '' ) ),
                    );
                },
                'schema_callback' => static function (): array {
                    return array(
                        'properties' => array(
                            'parent_product_id' => array(
                                'type'     => 'integer',
                                'readonly' => true,
                            ),
                            'decoration' => array(
                                'type'     => 'string',
                                'readonly' => true,
                            ),
                            'source' => array(
                                'type'     => 'string',
                                'readonly' => true,
                            ),
                        ),
                    );
                },
                'schema_type'     => ARRAY_A,
            )
        );
    }

'''
if 'public static function register_store_api_extensions' not in php:
    if method_anchor not in php:
        raise SystemExit('manifest method anchor not found')
    php = php.replace(method_anchor, extension_method + method_anchor, 1)

PHP.write_text(php)

js = JS.read_text()

cookie_helper_anchor = r'''    function findBetaQuantityInput() {
'''
dispatch_helper = r'''    function announceDecoration(productId, value) {
        if (!productId || !value || typeof window.CustomEvent !== 'function') return;
        document.dispatchEvent(new CustomEvent('asbo-matrix:decoration-change', {
            detail: {
                productId: String(productId),
                decoration: value
            }
        }));
    }

'''
if 'function announceDecoration' not in js:
    if cookie_helper_anchor not in js:
        raise SystemExit('matrix JS helper anchor not found')
    js = js.replace(cookie_helper_anchor, dispatch_helper + cookie_helper_anchor, 1)

change_anchor = r'''                selectedDecoration = input.value;
                block.setAttribute('data-default-decoration', selectedDecoration);
                syncForm();
                updateTier();
'''
change_replacement = r'''                selectedDecoration = input.value;
                block.setAttribute('data-default-decoration', selectedDecoration);
                syncForm();
                announceDecoration(productId, selectedDecoration);
                updateTier();
'''
if change_anchor in js:
    js = js.replace(change_anchor, change_replacement, 1)
elif 'announceDecoration(productId, selectedDecoration);' not in js:
    raise SystemExit('matrix decoration change anchor not found')

initial_anchor = r'''        bindForm();
        updateTier();
'''
initial_replacement = r'''        bindForm();
        announceDecoration(productId, selectedDecoration);
        updateTier();
'''
if initial_anchor in js:
    js = js.replace(initial_anchor, initial_replacement, 1)

JS.write_text(js)

block = json.loads(BLOCK.read_text())
block['version'] = '0.2.0'
block['category'] = 'all-star-embroidery'
BLOCK.write_text(json.dumps(block, indent=2) + '\n')

asset = EDITOR_ASSET.read_text()
asset = re.sub(r"'version'\s*=>\s*'[^']+'", "'version'      => '0.2.0'", asset, count=1)
EDITOR_ASSET.write_text(asset)

print('ASBO Matrix v0.2.0 patch applied')
