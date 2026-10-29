/**
 * Satori Manifest — Registration form block (editor).
 *
 * The form is rendered on the server (block/registration/render.php); in the
 * editor a static preview card stands in for it.
 *
 * @package
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import metadata from '../../../blocks/registration-form/block.json';

/**
 * Editor preview.
 *
 * @return {JSX.Element} Placeholder card.
 */
function Edit() {
	return (
		<div {...useBlockProps()}>
			<Placeholder
				icon="id-alt"
				label={__('Registration form', 'satori-manifest')}
				instructions={__(
					'Visitors fill in their business details here. Applications appear under Manifest → Applications for you to approve.',
					'satori-manifest'
				)}
			/>
		</div>
	);
}

registerBlockType(metadata.name, {
	edit: Edit,

	/**
	 * Server-side rendered — no save markup.
	 *
	 * @return {null} Dynamic block.
	 */
	save() {
		return null;
	},
});
