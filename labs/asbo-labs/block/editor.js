(function (blocks, element, components, blockEditor) {
    var el = element.createElement;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var RangeControl = components.RangeControl;
    var SelectControl = components.SelectControl;
    var ToggleControl = components.ToggleControl;
    var Notice = components.Notice;

    blocks.registerBlockType('allstar/asbo-labs-builder', {
        edit: function (props) {
            var attrs = props.attributes;
            return el(
                element.Fragment,
                null,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: 'ASBO Labs settings', initialOpen: true },
                        el(TextControl, {
                            label: 'Product category slug',
                            help: 'Optional. Leave blank to show all visible WooCommerce products.',
                            value: attrs.category || '',
                            onChange: function (value) { props.setAttributes({ category: value }); }
                        }),
                        el(RangeControl, {
                            label: 'Products to load',
                            min: 6,
                            max: 60,
                            step: 6,
                            value: attrs.limit || 24,
                            onChange: function (value) { props.setAttributes({ limit: value }); }
                        }),
                        el(SelectControl, {
                            label: 'Section width',
                            value: attrs.layoutWidth || 'contained',
                            options: [
                                { label: 'Contained', value: 'contained' },
                                { label: 'Wide', value: 'wide' },
                                { label: 'Full section width', value: 'full' }
                            ],
                            onChange: function (value) { props.setAttributes({ layoutWidth: value }); }
                        }),
                        el(ToggleControl, {
                            label: 'Show private beta bar',
                            checked: attrs.showBetaBar !== false,
                            onChange: function (value) { props.setAttributes({ showBetaBar: !!value }); }
                        })
                    )
                ),
                el(
                    'div',
                    { className: 'asbo-labs-editor-placeholder', style: { border: '1px solid #d2a952', padding: '28px', background: '#f3eee7' } },
                    el('strong', { style: { display: 'block', fontSize: '20px', marginBottom: '8px', color: '#080f1f' } }, 'ASBO Labs'),
                    el('p', null, 'The interactive beta experience renders on the front end. Production ASBO is not replaced.'),
                    el('p', null, 'Use Section width to make the beta span the page content, a wide area, or the full section.'),
                    el(Notice, { status: 'warning', isDismissible: false }, 'Labs remains isolated: no cart/order writes and no production feed changes.')
                )
            );
        },
        save: function () { return null; }
    });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor);
