<?php
/**
 * Tool: list_designs - the designs and ready-made layouts to choose from.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp\Tools;

use SatoriManifest\Designs\Design_Registry;
use SatoriManifest\Mcp\Server\Tool_Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class List_Designs
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class List_Designs extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'list_designs';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'List designs', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Lists the designs the page can use. "design" kinds restyle the page (fonts, colours, price-list style). "ready-made layout" kinds also come with a complete page layout with photos. Use it to recommend a look for the type of business, then choose_design.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		return self::object( array() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,bool>
	 */
	public function annotations(): array {
		return self::read_only();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param  array<string,mixed> $args  Arguments.
	 * @return Tool_Result
	 */
	public function call( array $args ): Tool_Result {
		$current = $this->store->design()['design'];
		$designs = array();

		foreach ( Design_Registry::all() as $slug => $design ) {
			$designs[] = array(
				'slug'        => (string) $slug,
				'title'       => (string) $design['title'],
				'kind'        => 'full' === $design['kind'] ? 'ready-made layout' : 'design',
				'best_for'    => (string) $design['best_for'],
				'description' => (string) $design['description'],
				'tags'        => $design['tags'],
				'current'     => $slug === $current,
			);
		}

		return Tool_Result::ok( sprintf( '%d designs available.', count( $designs ) ), array( 'designs' => $designs ) );
	}
}
