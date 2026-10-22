/**
 * External dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import Edit from './edit';
import metadata from './block.json';
import { paypalIcon } from '../paypalIcon';

registerBlockType( metadata, {
	icon: paypalIcon,
	edit: Edit,
	save() {
		return null;
	},
} );
