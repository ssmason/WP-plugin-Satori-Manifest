/**
 * Satori Manifest — Price List block registration.
 *
 * Registers the satori-manifest/price-list block in the editor.
 *
 * @package
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import metadata from '../block.json';
import './registration';

registerBlockType(metadata.name, {
	/**
	 * Block edit component.
	 */
	edit: Edit,

	/**
	 * Server-side rendered — no save needed.
	 *
	 * @return {null} Returns null for dynamic blocks.
	 */
	save() {
		return null;
	},
});
