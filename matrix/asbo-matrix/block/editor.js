(function (blocks, element, components, blockEditor, i18n) {
    'use strict';

    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var InspectorControls = blockEditor.InspectorControls;
    var useBlockProps = blockEditor.useBlockProps;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var TextareaControl = components.TextareaControl;
    var ToggleControl = components.ToggleControl;
    var Notice = components.Notice;
    var __ = i18n.__;

    registerBlockType('asbo-matrix/pricing', {
        edit: function (props) {
            var attrs = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps({ className: 'asbo-matrix-editor' });

            return el(
                'div',
                blockProps,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __('ASBO Matrix settings', 'asbo-matrix'), initialOpen: true },
                        el(TextControl, {
                            label: __('Heading', 'asbo-matrix'),
                            value: attrs.heading,
                            onChange: function (value) { setAttributes({ heading: value }); }
                        }),
                        el(TextareaControl, {
                            label: __('Description', 'asbo-matrix'),
                            value: attrs.description,
                            onChange: function (value) { setAttributes({ description: value }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show description', 'asbo-matrix'),
                            checked: !!attrs.showDescription,
                            onChange: function (value) { setAttributes({ showDescription: value }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show 10K stitch allowance', 'asbo-matrix'),
                            checked: !!attrs.showStitchAllowance,
                            onChange: function (value) { setAttributes({ showStitchAllowance: value }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show decoration selector when needed', 'asbo-matrix'),
                            checked: !!attrs.showDecorationSelector,
                            onChange: function (value) { setAttributes({ showDecorationSelector: value }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show active quantity tier helper', 'asbo-matrix'),
                            checked: !!attrs.showActiveTier,
                            onChange: function (value) { setAttributes({ showActiveTier: value }); }
                        })
                    )
                ),
                el('div', { style: { borderTop: '3px solid #080F1F', padding: '22px', background: '#fff' } },
                    el('span', { style: { color: '#8A6B27', fontSize: '11px', fontWeight: '800', letterSpacing: '.12em', textTransform: 'uppercase' } }, __('ASBO MATRIX', 'asbo-matrix')),
                    el('h3', { style: { margin: '8px 0 6px', color: '#080F1F', fontSize: '24px' } }, attrs.heading || __('Per-piece pricing by quantity', 'asbo-matrix')),
                    attrs.showDescription ? el('p', { style: { margin: '0 0 16px', color: '#667084' } }, attrs.description) : null,
                    el(Notice, { status: 'info', isDismissible: false }, __('Dynamic product block: on the storefront this renders only when the current WooCommerce product has a filled ASBO tiered pricing matrix. If the matrix is empty, the block outputs nothing and pricing remains untouched.', 'asbo-matrix')),
                    el('div', { style: { marginTop: '16px', padding: '12px 14px', border: '1px solid #DDE2EA', background: '#F7F8FA', color: '#080F1F', fontSize: '13px' } }, __('Use the block toolbar to set Wide or Full width. Additional spacing controls are available in the standard WordPress block settings.', 'asbo-matrix'))
                )
            );
        },
        save: function () {
            return null;
        }
    });
}(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n));
