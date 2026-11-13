<?php
/**
 * Base class for the assistant's tools.
 *
 * Holds the client's Page_Store and offers the shared pieces: JSON Schemas
 * for items and sections, and change() - which saves a change to the
 * history so it can be undone.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp\Tools;

use SatoriManifest\Mcp\Change_Log;
use SatoriManifest\Mcp\Page_Store;
use SatoriManifest\Mcp\Price_List;
use SatoriManifest\Mcp\Server\Tool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client_Tool
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
abstract class Client_Tool implements Tool {

	/**
	 * Constructor.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  Page_Store $store  The client's page.
	 */
	public function __construct( protected readonly Page_Store $store ) {}

	/**
	 * Default hints: changes the page, not destructive, not idempotent, closed world.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return array<string,bool>
	 */
	public function annotations(): array {
		return array(
			'readOnlyHint'    => false,
			'destructiveHint' => false,
			'idempotentHint'  => false,
			'openWorldHint'   => false,
		);
	}

	/**
	 * Hints for tools that only read.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return array<string,bool>
	 */
	protected static function read_only(): array {
		return array(
			'readOnlyHint'  => true,
			'openWorldHint' => false,
		);
	}

	/**
	 * Saves sections as one undoable change.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string                         $summary   What changed, in plain words.
	 * @param  array<int,array<string,mixed>> $sections  New sections.
	 * @return array<int,array<string,mixed>>  What was stored.
	 */
	protected function save_sections( string $summary, array $sections ): array {
		return (array) Change_Log::record(
			$this->store,
			$this->name(),
			$summary,
			array( Change_Log::PART_PRICE_LIST ),
			fn(): array => $this->store->save_sections( $sections )
		);
	}

	/**
	 * Schema for one item.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  bool $label_required  Whether label is required (new items).
	 * @return array<string,mixed>
	 */
	protected static function item_schema( bool $label_required = true ): array {
		$schema = array(
			'type'                 => 'object',
			'properties'           => array(
				'label'        => array(
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => 120,
					'description' => 'Name of the item or service, as the business writes it.',
				),
				'description'  => array(
					'type'        => 'string',
					'maxLength'   => 300,
					'description' => 'Optional short description (ingredients, duration…).',
				),
				'price'        => array(
					'type'        => array( 'number', 'string', 'null' ),
					'description' => 'Price as a number, e.g. 5.5. No currency symbol. null or empty for no price - an item with no price shows as a sub-heading.',
				),
				'price_prefix' => array(
					'type'        => 'string',
					'maxLength'   => 20,
					'description' => 'Optional word before the price, e.g. "from".',
				),
				'badge'        => array(
					'type'        => 'string',
					'maxLength'   => 24,
					'description' => 'Optional short tag, e.g. "New", "Vegan", "Sold out".',
				),
			),
			'additionalProperties' => false,
		);

		if ( $label_required ) {
			$schema['required'] = array( 'label' );
		}

		return $schema;
	}

	/**
	 * Schema for a list of new items.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return array<string,mixed>
	 */
	protected static function items_schema(): array {
		return array(
			'type'     => 'array',
			'maxItems' => Price_List::MAX_ITEMS,
			'items'    => self::item_schema(),
		);
	}

	/**
	 * Schema for a section id.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return array<string,mixed>
	 */
	protected static function section_id_schema(): array {
		return array(
			'type'        => 'string',
			'maxLength'   => 10,
			'description' => 'Section id from get_page, e.g. "s_1a2b3c4d".',
		);
	}

	/**
	 * Schema for a 1-based position.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string $what  What is being placed.
	 * @return array<string,mixed>
	 */
	protected static function position_schema( string $what ): array {
		return array(
			'type'        => 'integer',
			'minimum'     => 1,
			'maximum'     => 1000,
			'description' => sprintf( '1-based position for the %s (1 = first). Leave out to add at the end.', $what ),
		);
	}

	/**
	 * An object schema.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<string,mixed> $properties  Properties.
	 * @param  array<int,string>   $required    Required names.
	 * @return array<string,mixed>
	 */
	protected static function object( array $properties, array $required = array() ): array {
		$schema = array(
			'type'                 => 'object',
			'properties'           => (object) $properties,
			'additionalProperties' => false,
		);

		if ( array() !== $required ) {
			$schema['required'] = $required;
		}

		return $schema;
	}
}
