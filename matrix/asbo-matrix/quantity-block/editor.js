(function (blocks, element, components, blockEditor, i18n) {
    'use strict';

    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var Notice = components.Notice;
    var __ = i18n.__;

    registerBlockType('asbo-matrix/single-product-quantity', {
        edit: function () {
            var blockProps = useBlockProps({ className: 'asbo-single-product-quantity-editor' });

            return el(
                'div',
                blockProps,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __('All Star Quantity', 'asbo-matrix'), initialOpen: true },
                        el(Notice, { status: 'info', isDismissible: false }, __('Use this in place of WooCommerce Product Quantity (Beta) + Add to Cart Button. On the storefront the displayed number is the quantity already in the cart, and the minus/plus controls update the cart automatically.', 'asbo-matrix'))
                    )
                ),
                el(
                    'div',
                    { style: { display: 'inline-grid', gridTemplateColumns: '42px 58px 42px', border: '1px solid #C8CDD6', borderRadius: '5px', overflow: 'hidden', background: '#fff', color: '#080F1F' } },
                    el('span', { style: { display: 'grid', placeItems: 'center', minHeight: '44px', borderRight: '1px solid #E0E3E8' } }, '−'),
                    el('strong', { style: { display: 'grid', placeItems: 'center', minHeight: '44px', fontSize: '16px' } }, '12'),
                    el('span', { style: { display: 'grid', placeItems: 'center', minHeight: '44px', borderLeft: '1px solid #E0E3E8' } }, '+')
                ),
                el('div', { style: { marginTop: '8px', color: '#667084', fontSize: '12px' } }, __('Example: 12 means 12 of the currently selected product variation are already in the cart.', 'asbo-matrix'))
            );
        },
        save: function () {
            return null;
        }
    });
}(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n));
