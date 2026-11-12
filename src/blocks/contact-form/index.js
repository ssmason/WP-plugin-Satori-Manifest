/**
 * Satori Manifest - Contact form block (editor).
 *
 * The form is rendered on the server (blocks/contact-form/render.php); in the
 * editor a static preview card stands in for it.
 *
 * @package
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import metadata from '../../../blocks/contact-form/block.json';

/**
 * Editor preview.
 *
 * @return {JSX.Element} Placeholder card.
 */
function Edit() {
	return (
		<div {...useBlockProps()}>
			<Placeholder
				icon="email"
				label={__('Contact form', 'satori-manifest')}
				instructions={__(
					'Visitors send a name, email and message. It is emailed to the staff address in Manifest → Settings.',
					'satori-manifest'
				)}
			/>
		</div>
	);
}

registerBlockType(metadata.name, {
	edit: Edit,

	/**
	 * Server-side rendered - no save markup.
	 *
	 * @return {null} Dynamic block.
	 */
	save() {
		return null;
	},
});
