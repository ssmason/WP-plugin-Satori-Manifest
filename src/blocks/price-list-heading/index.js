/**
 * Satori Manifest - Price list heading block.
 *
 * Shows one heading of the client's price list as a banner. The page keeps
 * these in step with the price list automatically (Manifest\Page_Sync); the
 * editor shows the server rendering, and the wording, colours and on/off
 * switch are edited in the Price list editor.
 *
 * @package
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

import metadata from '../../../blocks/price-list-heading/block.json';

/**
 * Editor view: the banner as the page shows it.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Attributes.
 * @return {JSX.Element} Preview.
 */
function Edit({ attributes }) {
	return (
		<div {...useBlockProps()}>
			<ServerSideRender
				block={metadata.name}
				attributes={attributes}
				EmptyResponsePlaceholder={() => (
					<p className="satori-manifest-price-list__heading-empty">
						{__(
							'This heading is switched off or no longer in the price list.',
							'satori-manifest'
						)}
					</p>
				)}
			/>
		</div>
	);
}

registerBlockType(metadata.name, {
	edit: Edit,

	/**
	 * Server-side rendered - no save needed.
	 *
	 * @return {null} Dynamic block.
	 */
	save() {
		return null;
	},
});
