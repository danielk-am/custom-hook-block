import { registerBlockType, getBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import './style.scss';
import { registerPreview } from './preview-registry';

window.chbEditor = Object.freeze( { registerPreview } );

registerBlockType( metadata.name, {
	edit: Edit,
	save,
	// Explicit empty hookName distinguishes new blocks from version 1.0's
	// omitted attribute, which represented my_custom_hook.
	variations: [
		{
			name: 'choose-output',
			title: __( 'Custom Hook Block', 'custom-hook-block' ),
			isDefault: true,
			attributes: { renderer: '', hookName: '' },
		},
	],
} );
if (
	window.rrbSettings?.compat &&
	! getBlockType( 'registered-render-blocks/renderer' )
) {
	registerBlockType( 'registered-render-blocks/renderer', {
		...metadata,
		name: 'registered-render-blocks/renderer',
		title: __( 'Registered Renderer (compatible)', 'custom-hook-block' ),
		attributes: {
			...metadata.attributes,
			renderer: { type: 'string', default: 'notice' },
			hookName: { type: 'string', default: '' },
		},
		supports: { ...metadata.supports, inserter: false },
		edit: Edit,
		save,
	} );
}
