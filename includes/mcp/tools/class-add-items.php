<?php
/**
 * Tool: add_items - add items to a section.
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
 * Class Add_Items
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Add_Items extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'add_items';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Add items', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Adds new items to one section, at the end or at a position.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		$items             = self::items_schema();
		$items['minItems'] = 1;

		return self::object(
			array(
				'section_id' => self::section_id_schema(),
				'items'      => $items,
				'position'   => self::position_schema( 'first new item' ),
			),
			array( 'section_id', 'items' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param  array<string,mixed> $args  Arguments.
	 * @return Tool_Result
	 * @throws Tool_Error When the section would be too long.
	 */
	public function call( array $args ): Tool_Result {
		$sections = array_values( $this->store->sections() );
		$index    = Price_List::section_index( $sections, (string) $args['section_id'] );
		$current  = array_values( (array) ( $sections[ $index ]['items'] ?? array() ) );
		$new      = array_map( static fn( $item ): array => Price_List::item( (array) $item ), (array) $args['items'] );

		if ( count( $current ) + count( $new ) > Price_List::MAX_ITEMS ) {
			throw new Tool_Error( sprintf( 'A section can have at most %d items. Add a new section instead.', Price_List::MAX_ITEMS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
		}

		$sections[ $index ]['items'] = Price_List::insert( $current, $new, (int) ( $args['position'] ?? 0 ) );
		$title                       = (string) $sections[ $index ]['title'];
		$names                       = array_column( $new, 'label' );

		$this->save_sections( sprintf( 'Added %d items to "%s"', count( $new ), $title ), $sections );

		return Tool_Result::ok( sprintf( 'Added to "%s": %s.', $title, implode( ', ', $names ) ) );
	}
}
