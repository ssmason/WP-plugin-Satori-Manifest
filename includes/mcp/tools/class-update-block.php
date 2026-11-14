<?php
/**
 * Tool: update_block - replace a block inside a group on the page.
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
 * Class Update_Block
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */
final class Update_Block extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'update_block';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Change something on the page', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Replaces one block inside a group (id from get_page_layout, e.g. "g2.1") with a new one, using the same block types as add_block. Not for price lists or heading banners. Can be undone.';
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
				'block'    => self::block_schema(),
			),
			array( 'block_id', 'block' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param  array<string,mixed> $args  Arguments.
	 * @return Tool_Result
	 */
	public function call( array $args ): Tool_Result {
		( new Page_Blocks( $this->store ) )->update( (string) $args['block_id'], (array) $args['block'] );

		return Tool_Result::ok( 'Changed. Live now: ' . $this->store->url() );
	}
}
