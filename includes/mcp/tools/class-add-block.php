<?php
/**
 * Tool: add_block - add a block inside a group on the page.
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
 * Class Add_Block
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */
final class Add_Block extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'add_block';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Add to the page', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Adds a block inside one of the page\'s groups (ids from get_page_layout): text, a heading, a list, one of the client\'s images (list_images), a button, a separator, a spacer or a quote - or any other block the editor offers, given as block markup. Price lists and heading banners cannot be added here; they come from the price list. where: top or bottom of the group (default bottom), or before/after a block in it. Live straight away; can be undone.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		return self::object(
			array(
				'group'    => array(
					'type'        => 'string',
					'pattern'     => '^g[0-9]+$',
					'description' => 'Group id from get_page_layout, e.g. "g2".',
				),
				'where'    => array(
					'type' => 'string',
					'enum' => array( 'top', 'bottom', 'before', 'after' ),
				),
				'relative' => array(
					'type'        => 'string',
					'pattern'     => '^g[0-9]+[.][0-9]+$',
					'description' => 'For before/after: a block id inside the group, e.g. "g2.1".',
				),
				'block'    => self::block_schema(),
			),
			array( 'group', 'block' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param  array<string,mixed> $args  Arguments.
	 * @return Tool_Result
	 */
	public function call( array $args ): Tool_Result {
		$id = ( new Page_Blocks( $this->store ) )->add( (string) $args['group'], (array) $args['block'], (string) ( $args['where'] ?? 'bottom' ), (string) ( $args['relative'] ?? '' ) );

		return Tool_Result::ok( sprintf( 'Added as %s. Live now: %s', $id, $this->store->url() ) );
	}
}
