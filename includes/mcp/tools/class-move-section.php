<?php
/**
 * Tool: move_section.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp\Tools;

use SatoriManifest\Mcp\Price_List;
use SatoriManifest\Mcp\Server\Tool_Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Move_Section
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Move_Section extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'move_section';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Move a section', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Moves a section to another position in the price list (1 = top).';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		return self::object(
			array(
				'section_id' => self::section_id_schema(),
				'position'   => self::position_schema( 'section' ),
			),
			array( 'section_id', 'position' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,bool>
	 */
	public function annotations(): array {
		return array_merge( parent::annotations(), array( 'idempotentHint' => true ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param  array<string,mixed> $args  Arguments.
	 * @return Tool_Result
	 */
	public function call( array $args ): Tool_Result {
		$sections = array_values( $this->store->sections() );
		$index    = Price_List::section_index( $sections, (string) $args['section_id'] );
		$title    = (string) $sections[ $index ]['title'];
		$saved    = $this->save_sections( sprintf( 'Moved the section "%s"', $title ), Price_List::move( $sections, $index, (int) $args['position'] ) );

		return Tool_Result::ok(
			sprintf( 'Moved "%s". Order now: %s', $title, implode( ', ', array_column( $saved, 'title' ) ) )
		);
	}
}
