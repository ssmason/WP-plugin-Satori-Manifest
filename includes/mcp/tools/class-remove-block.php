<?php
/**
 * Tool: remove_block - remove a block from a group on the page.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp\Tools;

use SatoriManifest\Mcp\Page_Blocks;
use SatoriManifest\Mcp\Server\Tool_Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Remove_Block
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */
final class Remove_Block extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'remove_block';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Remove something from the page', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Removes one block from inside a group (id from get_page_layout). Not for price lists or heading banners: switch a heading off with update_heading instead. Confirm with the user first; can be undone.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		return self::object(
			array(
				'block_id' => array(
					'type'    => 'string',
					'pattern' => '^g[0-9]+[.][0-9]+$',
				),
			),
			array( 'block_id' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,bool>
	 */
	public function annotations(): array {
		return array_merge( parent::annotations(), array( 'destructiveHint' => true ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param  array<string,mixed> $args  Arguments.
	 * @return Tool_Result
	 */
	public function call( array $args ): Tool_Result {
		( new Page_Blocks( $this->store ) )->remove( (string) $args['block_id'] );

		return Tool_Result::ok( 'Removed. Say "undo" to bring it back.' );
	}
}
