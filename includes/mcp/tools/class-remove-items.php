<?php
/**
 * Tool: remove_items - delete named items.
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
 * Class Remove_Items
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Remove_Items extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'remove_items';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Remove items', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Deletes items by name. For something only temporarily unavailable, prefer update_items with badge "Sold out". All succeed together or none are removed.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		return self::object(
			array(
				'items' => array(
					'type'     => 'array',
					'minItems' => 1,
					'maxItems' => 200,
					'items'    => self::object(
						array(
							'item'       => array(
								'type'      => 'string',
								'minLength' => 1,
								'maxLength' => 120,
							),
							'section_id' => self::section_id_schema(),
						),
						array( 'item' )
					),
				),
			),
			array( 'items' )
		);
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
	 * @throws Tool_Error When an item cannot be found.
	 */
	public function call( array $args ): Tool_Result {
		$sections = array_values( $this->store->sections() );
		$removed  = array();

		foreach ( (array) $args['items'] as $number => $target ) {
			try {
				[ $s, $i ] = Price_List::find_item( $sections, (string) $target['item'], (string) ( $target['section_id'] ?? '' ) );
			} catch ( Tool_Error $e ) {
				throw new Tool_Error( sprintf( 'Item %d: %s Nothing was removed.', $number + 1, $e->getMessage() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
			}

			$items     = array_values( (array) $sections[ $s ]['items'] );
			$removed[] = (string) $items[ $i ]['label'];

			array_splice( $items, $i, 1 );
			$sections[ $s ]['items'] = $items;
		}

		$this->save_sections( 'Removed ' . implode( ', ', array_slice( $removed, 0, 3 ) ) . ( count( $removed ) > 3 ? sprintf( ' and %d more', count( $removed ) - 3 ) : '' ), $sections );

		return Tool_Result::ok( sprintf( 'Removed %s. Say "undo" to bring them back.', implode( ', ', $removed ) ) );
	}
}
