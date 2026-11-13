<?php
/**
 * Tool: update_items - change named items (price, name, description, badge).
 *
 * The everyday tool: "Guinness is now 5.50", "mark the lamb as sold out".
 * All changes are applied together or not at all.
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
 * Class Update_Items
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Update_Items extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'update_items';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Change items', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Changes existing items, found by their current name: price, name, description, price prefix or badge. Only the fields in "set" change. If the same name appears in two sections, pass section_id. Use badge "Sold out" (or "") for availability. All changes succeed together or none are made.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		$set                  = self::item_schema( false );
		$set['minProperties'] = 1;

		return self::object(
			array(
				'changes' => array(
					'type'     => 'array',
					'minItems' => 1,
					'maxItems' => 300,
					'items'    => self::object(
						array(
							'item'       => array(
								'type'        => 'string',
								'minLength'   => 1,
								'maxLength'   => 120,
								'description' => 'Current name of the item.',
							),
							'section_id' => self::section_id_schema(),
							'set'        => $set,
						),
						array( 'item', 'set' )
					),
				),
			),
			array( 'changes' )
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
	 * @throws Tool_Error When an item cannot be found or a value is invalid.
	 */
	public function call( array $args ): Tool_Result {
		$sections = array_values( $this->store->sections() );
		$changed  = array();

		foreach ( (array) $args['changes'] as $number => $change ) {
			try {
				[ $s, $i ] = Price_List::find_item( $sections, (string) $change['item'], (string) ( $change['section_id'] ?? '' ) );

				$sections[ $s ]['items'][ $i ] = Price_List::item( (array) $change['set'], (array) $sections[ $s ]['items'][ $i ] );
			} catch ( Tool_Error $e ) {
				throw new Tool_Error( sprintf( 'Change %d: %s Nothing was saved.', $number + 1, $e->getMessage() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
			}

			$changed[] = (string) $sections[ $s ]['items'][ $i ]['label'];
		}

		$this->save_sections( self::summary( $changed ), $sections );

		return Tool_Result::ok( sprintf( 'Updated %d items: %s. Live now.', count( $changed ), implode( ', ', $changed ) ) );
	}

	/**
	 * "Updated Guinness, Coke and 3 more".
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<int,string> $names  Item names.
	 * @return string
	 */
	private static function summary( array $names ): string {
		$names = array_values( array_unique( $names ) );
		$shown = array_slice( $names, 0, 3 );
		$more  = count( $names ) - count( $shown );

		return 'Updated ' . implode( ', ', $shown ) . ( $more > 0 ? sprintf( ' and %d more', $more ) : '' );
	}
}
