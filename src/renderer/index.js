import { registerBlockType, getBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import './style.scss';

registerBlockType( metadata.name, { edit: Edit, save } );
if (
	window.rrbSettings?.legacy &&
	! getBlockType( 'mytheme/custom-hook-block' )
) {
	registerBlockType( 'mytheme/custom-hook-block', {
		...metadata,
		name: 'mytheme/custom-hook-block',
		title: __( 'Legacy registered renderer', 'registered-render-blocks' ),
		attributes: {
			...metadata.attributes,
			renderer: { type: 'string', default: '' },
			hookName: { type: 'string', default: '' },
		},
		supports: { ...metadata.supports, inserter: false },
		edit: Edit,
		save,
	} );
}
