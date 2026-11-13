<?php
/**
 * Tool: update_section - rename, re-describe or rewrite one section.
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
 * Class Update_Section
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Update_Section extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'update_section';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Change a section', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Changes one section\'s title or description, and/or replaces all of its items (pass the complete new list of items, in order). Fields left out stay as they are.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		return self::object(
			array(
				'section_id'  => self::section_id_schema(),
				'title'       => array(
					'type'      => 'string',
					'minLength' => 1,
					'maxLength' => 120,
				),
				'description' => array(
					'type'      => 'string',
					'maxLength' => 500,
				),
				'items'       => self::items_schema(),
			),
			array( 'section_id' )
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
		$sections           = array_values( $this->store->sections() );
		$index              = Price_List::section_index( $sections, (string) $args['section_id'] );
		$sections[ $index ] = Price_List::section( $args, $sections[ $index ] );
		$saved              = $this->save_sections( sprintf( 'Changed the section "%s"', $sections[ $index ]['title'] ), $sections );

		return Tool_Result::ok(
			sprintf( 'Updated "%s".', $sections[ $index ]['title'] ),
			array( 'section' => Price_List::to_public( array( $saved[ $index ] ) )[0] )
		);
	}
}
