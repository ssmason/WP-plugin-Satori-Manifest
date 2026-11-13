<?php
/**
 * Tool: update_heading - rename, restyle or switch a menu heading on or off.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.10.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp\Tools;

use SatoriManifest\Mcp\Price_List;
use SatoriManifest\Mcp\Server\Tool_Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Update_Heading
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.10.0
 */
final class Update_Heading extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'update_heading';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Change a heading', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Changes a menu heading: title, note, colours, size, in_menu (a link to it in the jump-link bar under the page header), or visible (false hides the heading and all sections under it, e.g. "turn the breakfast menu off"; true shows them again). Only the fields given change. Can be undone.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		$schema                  = self::object(
			array( 'heading_id' => self::section_id_schema() ) + self::heading_properties(),
			array( 'heading_id' )
		);
		$schema['minProperties'] = 2;

		return $schema;
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
		$index              = Price_List::section_index( $sections, (string) $args['heading_id'], 'heading' );
		$sections[ $index ] = Price_List::heading( $args, $sections[ $index ] );
		$title              = (string) $sections[ $index ]['title'];

		if ( array_key_exists( 'visible', $args ) && 1 === count( $args ) - 1 ) {
			$summary = sprintf( '%s the heading "%s"', $args['visible'] ? 'Showed' : 'Hid', $title );
		} else {
			$summary = sprintf( 'Changed the heading "%s"', $title );
		}

		$saved = $this->save_sections( $summary, $sections );

		return Tool_Result::ok(
			$summary . '. Live now.',
			array( 'heading' => Price_List::to_public( array( $saved[ $index ] ) )[0] )
		);
	}
}
