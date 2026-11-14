<?php
/**
 * Tool: get_page_layout - the page's groups and the blocks inside them.
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
 * Class Get_Page_Layout
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */
final class Get_Page_Layout extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'get_page_layout';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'See the page layout', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Lists the page in order: each group (g1, g2…) with its name, what it holds (price list menu, heading banner from the price list, or own content) and the blocks inside it (g2.1, g2.2…) with a short summary. Call this before add_block, update_block or remove_block; ids change when the page changes.';
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
		$layout = ( new Page_Blocks( $this->store ) )->layout();

		return Tool_Result::ok( sprintf( 'The page has %d top-level parts.', count( $layout ) ), array( 'layout' => $layout ) );
	}
}
