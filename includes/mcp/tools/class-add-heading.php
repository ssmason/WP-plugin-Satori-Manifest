<?php
/**
 * Tool: add_heading - a menu heading ("Main Menu") above a group of sections.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.10.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp\Tools;

use SatoriManifest\Mcp\Price_List;
use SatoriManifest\Mcp\Server\Tool_Error;
use SatoriManifest\Mcp\Server\Tool_Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Add_Heading
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.10.0
 */
final class Add_Heading extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'add_heading';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Add a heading', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Adds a menu heading such as "Main Menu" or "Starters & Snacks" to the price list. A heading sits in the same list as the sections and groups every section below it, up to the next heading. Put it just above the first section it should cover (position = that section\'s position). It can have a note, its own banner colours and a size, and can be switched off to hide its whole group. On the page it becomes a banner of its own with its menu below it, placed automatically. For a new menu (e.g. Christmas Menu), add the heading, then its sections straight after it, before the next heading - otherwise it takes over the sections below it.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		return self::object(
			self::heading_properties() + array( 'position' => self::position_schema( 'heading' ) ),
			array( 'title' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param  array<string,mixed> $args  Arguments.
	 * @return Tool_Result
	 * @throws Tool_Error When the page is full.
	 */
	public function call( array $args ): Tool_Result {
		$sections = $this->store->sections();

		if ( count( $sections ) >= Price_List::MAX_SECTIONS ) {
			throw new Tool_Error( sprintf( 'The page already has the most sections and headings allowed (%d).', Price_List::MAX_SECTIONS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
		}

		$heading  = Price_List::heading( $args );
		$position = (int) ( $args['position'] ?? 0 );
		$saved    = $this->save_sections( sprintf( 'Added the heading "%s"', $heading['title'] ), Price_List::insert( $sections, array( $heading ), $position ) );
		$index    = $position >= 1 ? min( count( $saved ) - 1, $position - 1 ) : count( $saved ) - 1;

		return Tool_Result::ok(
			sprintf( 'Added the heading "%s". It covers: %s.', $heading['title'], $this->covers( $saved, $index ) ),
			array( 'heading' => Price_List::to_public( array( $saved[ $index ] ) )[0] )
		);
	}

	/**
	 * Titles of the sections under a heading, for the reply.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.10.0
	 * @param  array<int,array<string,mixed>> $sections  Saved list.
	 * @param  int                            $index     Heading index.
	 * @return string
	 */
	private function covers( array $sections, int $index ): string {
		$titles = array_map( static fn( int $i ): string => (string) $sections[ $i ]['title'], Price_List::under_heading( $sections, $index ) );

		return array() !== $titles ? implode( ', ', $titles ) : 'no sections yet (move sections below it)';
	}
}
