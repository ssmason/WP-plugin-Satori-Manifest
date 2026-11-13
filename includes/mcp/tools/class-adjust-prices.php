<?php
/**
 * Tool: adjust_prices - raise or lower many prices at once.
 *
 * "Put everything up 5%, rounded to 10 cents", "50 cents off all cocktails".
 * Supports a preview so the user can check before anything changes.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp\Tools;

use SatoriManifest\Mcp\Price_List;
use SatoriManifest\Mcp\Prices;
use SatoriManifest\Mcp\Server\Tool_Error;
use SatoriManifest\Mcp\Server\Tool_Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Adjust_Prices
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Adjust_Prices extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'adjust_prices';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Raise or lower prices', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Changes every price in the whole list, or in chosen sections, by a percentage or a fixed amount, optionally rounding. Items without a price are skipped. Run with preview: true first and show the user the result; then run again without preview to save.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		return self::object(
			array(
				'percent'     => array(
					'type'        => 'number',
					'minimum'     => -90,
					'maximum'     => 200,
					'description' => 'Percentage change: 5 = 5% up, -10 = 10% down.',
				),
				'amount'      => array(
					'type'        => 'number',
					'minimum'     => -1000,
					'maximum'     => 1000,
					'description' => 'Fixed change in currency units: 0.5 = 50 cents more, -1 = one less.',
				),
				'section_ids' => array(
					'type'        => 'array',
					'maxItems'    => Price_List::MAX_SECTIONS,
					'items'       => self::section_id_schema(),
					'description' => 'Only these sections. A heading id means every section under that heading. Leave out for the whole list.',
				),
				'round_to'    => array(
					'type'        => 'number',
					'enum'        => array( 0, 0.05, 0.1, 0.5, 1 ),
					'description' => 'Round each new price to the nearest 0.05, 0.10, 0.50 or 1. 0 or left out: nearest cent.',
				),
				'preview'     => array(
					'type'        => 'boolean',
					'description' => 'true: show the new prices without saving.',
				),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param  array<string,mixed> $args  Arguments.
	 * @return Tool_Result
	 * @throws Tool_Error When neither or both of percent and amount are given.
	 */
	public function call( array $args ): Tool_Result {
		$percent = isset( $args['percent'] ) ? (float) $args['percent'] : null;
		$amount  = isset( $args['amount'] ) ? (float) $args['amount'] : null;

		if ( ( null === $percent ) === ( null === $amount ) ) {
			throw new Tool_Error( 'Give either percent or amount (not both).' );
		}

		$sections = array_values( $this->store->sections() );
		$only     = array_map( 'strval', (array) ( $args['section_ids'] ?? array() ) );

		// A heading id stands for every section under it ("the cocktail menu").
		foreach ( $only as $id ) {
			$index = Price_List::section_index( $sections, $id, 'any' );

			if ( \SatoriManifest\Manifest\Headings::is_heading( $sections[ $index ] ) ) {
				foreach ( Price_List::under_heading( $sections, $index ) as $under ) {
					$only[] = (string) $sections[ $under ]['id'];
				}
			}
		}

		$changes = array();

		foreach ( $sections as $s => $section ) {
			if ( array() !== $only && ! in_array( (string) $section['id'], $only, true ) ) {
				continue;
			}

			foreach ( array_values( (array) $section['items'] ) as $i => $item ) {
				$old = (string) ( $item['price'] ?? '' );
				$new = Prices::adjust( $old, $percent, $amount, (float) ( $args['round_to'] ?? 0 ) );

				if ( $old !== $new ) {
					$sections[ $s ]['items'][ $i ]['price'] = $new;
					$changes[]                              = array(
						'section' => (string) $section['title'],
						'item'    => (string) $item['label'],
						'from'    => $old,
						'to'      => $new,
					);
				}
			}
		}

		$how = null !== $percent ? sprintf( '%+g%%', $percent ) : sprintf( '%+.2f', $amount );

		if ( array() === $changes ) {
			return Tool_Result::ok( 'No prices would change.' );
		}

		if ( true === ( $args['preview'] ?? false ) ) {
			return Tool_Result::ok( sprintf( 'Preview only - nothing saved. %d prices would change (%s).', count( $changes ), $how ), array( 'changes' => $changes ) );
		}

		$where = array() !== $only ? implode( ', ', array_unique( array_column( $changes, 'section' ) ) ) : 'all sections';

		$this->save_sections( sprintf( 'Changed %d prices by %s (%s)', count( $changes ), $how, $where ), $sections );

		return Tool_Result::ok( sprintf( 'Saved: %d prices changed by %s. Live now.', count( $changes ), $how ), array( 'changes' => $changes ) );
	}
}
