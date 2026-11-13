<?php
/**
 * Tool: get_page - everything on the client's page.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp\Tools;

use SatoriManifest\Designs\Design_Registry;
use SatoriManifest\Mcp\Price_List;
use SatoriManifest\Mcp\Server\Tool_Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Get_Page
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Get_Page extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'get_page';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Read my page', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Returns the business\'s page: address (URL), details (name, tagline, contact, opening hours, currency), design, and the full price list with section ids. Call this first, and again whenever you need the current section ids.';
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
		$sections = $this->store->sections();
		$design   = $this->store->design();
		$entry    = Design_Registry::get( $design['design'] );
		$items    = array_sum( array_map( static fn( array $section ): int => count( (array) ( $section['items'] ?? array() ) ), $sections ) );

		return Tool_Result::ok(
			sprintf( 'The page has %d sections and %d items.', count( $sections ), $items ),
			array(
				'url'        => $this->store->url(),
				'details'    => $this->store->details(),
				'design'     => array(
					'slug'        => $design['design'],
					'title'       => (string) ( $entry['title'] ?? $design['design'] ),
					'colour_mode' => $design['mode'],
				),
				'price_list' => Price_List::to_public( $sections ),
			)
		);
	}
}
