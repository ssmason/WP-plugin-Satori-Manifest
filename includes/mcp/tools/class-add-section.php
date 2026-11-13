<?php
/**
 * Tool: add_section.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
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
 * Class Add_Section
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Add_Section extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'add_section';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Add a section', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Adds a new section (e.g. "Christmas menu", "Kids") with its items, at the end or at a position.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		return self::object(
			array(
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
				'position'    => self::position_schema( 'section' ),
			),
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
			throw new Tool_Error( sprintf( 'The page already has the most sections allowed (%d).', Price_List::MAX_SECTIONS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
		}

		$section  = Price_List::section( $args );
		$position = (int) ( $args['position'] ?? 0 );
		$saved    = $this->save_sections( sprintf( 'Added the section "%s"', $section['title'] ), Price_List::insert( $sections, array( $section ), $position ) );
		$index    = $position >= 1 ? min( count( $saved ) - 1, $position - 1 ) : count( $saved ) - 1;

		return Tool_Result::ok(
			sprintf( 'Added "%s" with %d items.', $section['title'], count( $section['items'] ) ),
			array( 'section' => Price_List::to_public( array( $saved[ $index ] ) )[0] )
		);
	}
}
