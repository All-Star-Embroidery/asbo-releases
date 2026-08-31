(function (blocks, element, components, blockEditor, i18n) {
    var el = element.createElement;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var RangeControl = components.RangeControl;
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
                            label: 'Product category slug (optional)',
                            help: 'Leave blank to show all visible WooCommerce products.',
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
                        })
                    )
                ),
                el(
                    'div',
                    { className: 'asbo-labs-editor-placeholder', style: { border: '1px solid #d2a952', padding: '28px', background: '#f3eee7' } },
                    el('strong', { style: { display: 'block', fontSize: '20px', marginBottom: '8px', color: '#080f1f' } }, 'ASBO Labs Builder'),
                    el('p', null, 'The interactive beta builder renders on the front end. Production ASBO is not replaced.'),
                    el(Notice, { status: 'warning', isDismissible: false }, 'Labs phase 1 is a UX sandbox: no cart or order writes.')
                )
            );
        },
        save: function () { return null; }
    });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n);
