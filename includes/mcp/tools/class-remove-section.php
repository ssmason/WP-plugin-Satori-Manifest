<?php
/**
 * Tool: remove_section.
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
 * Class Remove_Section
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Remove_Section extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'remove_section';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Remove a section', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Deletes a whole section and its items, or a heading (the sections under a heading stay). Confirm with the user first. It can be brought back with undo_last_change. To hide a heading for now, use update_heading with visible: false instead.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		return self::object( array( 'section_id' => self::section_id_schema() ), array( 'section_id' ) );
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
		$sections = array_values( $this->store->sections() );
		$index    = Price_List::section_index( $sections, (string) $args['section_id'], 'any' );
		$title    = (string) $sections[ $index ]['title'];
		$kind     = \SatoriManifest\Manifest\Headings::is_heading( $sections[ $index ] ) ? 'heading' : 'section';

		array_splice( $sections, $index, 1 );
		$this->save_sections( sprintf( 'Removed the %s "%s"', $kind, $title ), $sections );

		return Tool_Result::ok( sprintf( 'Removed "%s". Say "undo" to bring it back.', $title ) );
	}
}
