<?php
/**
 * Tool: set_up_price_list - write the whole price list in one go.
 *
 * The main set-up path: the client shares a photo, PDF or text of their
 * menu and the model turns it into sections and items.
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
 * Class Set_Up_Price_List
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Set_Up_Price_List extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'set_up_price_list';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Set up the whole price list', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Replaces the entire price list (sections and headings) with the sections given, in order. Use it to set up a page from a menu or price list the user shares (photo, PDF or text). Keep the business\'s own wording, spelling and order; do not invent items or prices. To keep an existing section\'s photo, pass its id. If the page already has items, set replace_existing to true - only after the user has agreed to replace them. For small edits use the other tools instead.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		return self::object(
			array(
				'sections'         => array(
					'type'     => 'array',
					'minItems' => 1,
					'maxItems' => Price_List::MAX_SECTIONS,
					'items'    => self::object(
						array(
							'id'          => self::section_id_schema(),
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
							'heading'     => array(
								'type'        => 'string',
								'maxLength'   => 120,
								'description' => 'Optional menu heading to start just above this section, e.g. "Main Menu"; it covers this and the following sections until the next heading.',
							),
						),
						array( 'title', 'items' )
					),
				),
				'replace_existing' => array(
					'type'        => 'boolean',
					'description' => 'Must be true when the page already has items. Ask the user first.',
				),
			),
			array( 'sections' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,bool>
	 */
	public function annotations(): array {
		return array_merge(
			parent::annotations(),
			array(
				'destructiveHint' => true,
				'idempotentHint'  => true,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param  array<string,mixed> $args  Arguments.
	 * @return Tool_Result
	 * @throws Tool_Error When the page has items and replace_existing is not true.
	 */
	public function call( array $args ): Tool_Result {
		$current  = $this->store->sections();
		$existing = array_sum( array_map( static fn( array $section ): int => count( (array) ( $section['items'] ?? array() ) ), $current ) );

		if ( $existing > 0 && true !== ( $args['replace_existing'] ?? false ) ) {
			throw new Tool_Error( sprintf( 'The page already has %d items. Ask the user whether to replace them, then call again with replace_existing: true.', $existing ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
		}

		$by_id    = array_column( $current, null, 'id' );
		$sections = array();

		foreach ( (array) $args['sections'] as $input ) {
			if ( '' !== trim( (string) ( $input['heading'] ?? '' ) ) ) {
				$sections[] = Price_List::heading( array( 'title' => (string) $input['heading'] ) );
			}

			$id         = (string) ( $input['id'] ?? '' );
			$base       = isset( $by_id[ $id ] ) ? array(
				'id'   => $id,
				'icon' => $by_id[ $id ]['icon'] ?? 0,
			) : array();
			$sections[] = Price_List::section( (array) $input, $base );
		}

		$items = array_sum( array_map( static fn( array $section ): int => count( $section['items'] ), $sections ) );
		$count = count( (array) $args['sections'] );
		$saved = $this->save_sections( sprintf( 'Set up the price list: %d sections, %d items', $count, $items ), $sections );

		return Tool_Result::ok(
			sprintf( 'Saved %d sections and %d items. The page is live now: %s', $count, $items, $this->store->url() ),
			array( 'price_list' => Price_List::to_public( $saved ) )
		);
	}
}
