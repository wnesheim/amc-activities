// Gutenberg block for amc_activities shortcode
// Bill Nesheim
// with some help from ChatGPT
// $Header: /Users/bnesheim/Documents/AMC/WP_Plugin/amc-activities/RCS/block.js,v 1.6 2026/02/26 15:48:24 bnesheim Exp $
// $Version$
//

(function (blocks, element, editor, components) {
    const el = element.createElement;
    const { InspectorControls } = editor;
    const { PanelBody, SelectControl, TextControl, RangeControl } = components;

    blocks.registerBlockType('amc/activities', {
        title: 'AMC Activities',
        icon: 'location-alt',
        category: 'widgets',

        attributes: {
            chapter: { type: 'string', default: '' },
            activityTypes: { type: 'string[]', default: [] },
            events: { type: 'string', default: '-1' },
	    keywords: { type: 'string', default: '' },
	    audience: { type: 'string', default: '' },
	    length: { type: 'string', default: '30' }
        },

        edit: function (props) {
            const a = props.attributes;

	    if (a.events === '-1') { props.setAttributes({ events: AMC_BLOCK_DATA.default_limit}) }
	    if (a.chapter.length === 0) { props.setAttributes({ chapter: AMC_BLOCK_DATA.default_chapter}) }
	    if (a.activityTypes.length === 0) {props.setAttributes({ activityTypes: AMC_BLOCK_DATA.default_activities}) }
				 
            return [
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { title: 'AMC Settings', initialOpen: true },

                        el(SelectControl, {
                            label: 'Chapter',
                            value: a.chapter,
                            options: [{ label: 'Select a chapter…', value: "" }]
                                .concat(AMC_BLOCK_DATA.chapters),
                            onChange: v => props.setAttributes({ chapter: v })
                        }),

                        el(SelectControl, {
                            label: 'Activity Types',
			    multiple: true,
			    value: a.activityTypes,
			    options: [{ label: 'Select activities to filter...', value: ["All Activities"]}]
				.concat(AMC_BLOCK_DATA.activities),
                            onChange: v => props.setAttributes({ activityTypes: v })
                        }),

                        el(RangeControl, {
                            label: 'Events',
			    help: 'Number of events to display',
                            min: 1,
                            max: 500,
                            value: a.events,
			    withInputField: false,
                            onChange: v => props.setAttributes({ limit: v })
                        }),

			el(SelectControl, {
			    label: 'Audience',
			    value: a.audience,
			    options: [{ label: 'Select an audience...', value:"" }]
				.concat(AMC_BLOCK_DATA.audiences),
			    onChange: v => props.setAttributes( { audience: v })
			}),
			
			el(TextControl, {
			    label: 'Keywords',
			    help: 'Keywords',
			    value: a.keywords,
			    onChange: v => props.setAttributes({ keywords: v })
			}),
			
			el(RangeControl, {
			    label: 'Length',
			    min: 0,
			    max: 1000,
			    withInputField: false,
			    help: 'Activity description length (words)',
			    value: a.length,
			    onChange: v => props.setAttributes({ length: v })
			})
                    )
                ),

                el(
                    'div',
                    { className: props.className },
                    el('strong', {}, '[amc_activities ' + ' chapter="' + a.chapter + '"' +
		       ' activities="' + a.activityTypes.join(", ") + '"' +
		       ' events="' + a.events + '"' + 
		       ' audience="' + a.audience + '"' +
		       ' keywords="' + a.keywords + '"' +
		       ' length="' + a.length + '"]')
                )
            ];
        },

        save: function () {
            return null; // Server-rendered
        }
    });

})(window.wp.blocks, window.wp.element, window.wp.editor, window.wp.components);
