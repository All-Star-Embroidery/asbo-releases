from __future__ import annotations

import json
import re
from pathlib import Path

ROOT = Path('matrix/asbo-matrix')
PHP = ROOT / 'asbo-matrix.php'
PRICING_BLOCK = ROOT / 'block' / 'block.json'
PRICING_ASSET = ROOT / 'block' / 'editor.asset.php'
QTY_BLOCK = ROOT / 'quantity-block' / 'block.json'
QTY_ASSET = ROOT / 'quantity-block' / 'editor.asset.php'

php = PHP.read_text()
php = php.replace(' * Version: 0.2.0', ' * Version: 0.2.1', 1)
php = php.replace("private const VERSION = '0.2.0';", "private const VERSION = '0.2.1';", 1)

boot_anchor = "        add_action( 'init', array( __CLASS__, 'register_block' ) );\n"
category_hook = "        add_filter( 'block_categories_all', array( __CLASS__, 'register_block_category' ), 20, 2 );\n"
if category_hook not in php:
    if boot_anchor not in php:
        raise SystemExit('boot anchor not found')
    php = php.replace(boot_anchor, boot_anchor + category_hook, 1)

method_anchor = "    public static function register_block(): void {\n"
category_method = '''    public static function register_block_category( array $categories, $editor_context ): array {
        foreach ( $categories as $category ) {
            if ( isset( $category['slug'] ) && 'all-star-embroidery' === $category['slug'] ) {
                return $categories;
            }
        }

        $categories[] = array(
            'slug'  => 'all-star-embroidery',
            'title' => __( 'All Star Embroidery', 'asbo-matrix' ),
        );

        return $categories;
    }

'''
if 'public static function register_block_category' not in php:
    if method_anchor not in php:
        raise SystemExit('register block method anchor not found')
    php = php.replace(method_anchor, category_method + method_anchor, 1)

PHP.write_text(php)

pricing = json.loads(PRICING_BLOCK.read_text())
pricing['version'] = '0.2.1'
pricing['category'] = 'all-star-embroidery'
PRICING_BLOCK.write_text(json.dumps(pricing, indent=2) + '\n')

qty = json.loads(QTY_BLOCK.read_text())
qty['version'] = '0.2.1'
qty['category'] = 'all-star-embroidery'
qty.pop('ancestor', None)
qty['keywords'] = ['quantity', 'cart', 'bulk']
QTY_BLOCK.write_text(json.dumps(qty, indent=2) + '\n')

for asset_path in (PRICING_ASSET, QTY_ASSET):
    asset = asset_path.read_text()
    asset = re.sub(r"'version'\s*=>\s*'[^']+'", "'version'      => '0.2.1'", asset, count=1)
    asset_path.write_text(asset)

print('ASBO Matrix v0.2.1 discoverability patch applied')
