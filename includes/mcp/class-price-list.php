<?php
/**
 * Price list operations on the stored sections array.
 *
 * Pure functions: sections in, sections out. The assistant tools use these,
 * then save the result through Page_Store (which sanitises it again).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp;

use SatoriManifest\Manifest\Headings;
use SatoriManifest\Mcp\Server\Tool_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Price_List
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Price_List {

	/**
	 * Most sections on one page.
	 */
	public const MAX_SECTIONS = 60;

	/**
	 * Most items in one section.
	 */
	public const MAX_ITEMS = 200;

	/**
	 * Item fields the assistant can set.
	 */
	public const ITEM_FIELDS = array( 'label', 'description', 'price', 'price_prefix', 'badge' );

	/**
	 * Sections as shown to the model.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<int,array<string,mixed>> $sections  Stored sections.
	 * @return array<int,array<string,mixed>>
	 */
	public static function to_public( array $sections ): array {
		return array_map(
			static fn( array $section ): array => Headings::is_heading( $section ) ? array(
				'id'      => (string) ( $section['id'] ?? '' ),
				'type'    => 'heading',
				'title'   => (string) ( $section['title'] ?? '' ),
				'note'    => (string) ( $section['description'] ?? '' ),
				'visible' => false !== ( $section['visible'] ?? true ),
				'in_menu' => ! empty( $section['in_menu'] ),
				'style'   => (array) ( $section['style'] ?? array() ),
			) : array(
				'id'          => (string) ( $section['id'] ?? '' ),
				'type'        => 'section',
				'title'       => (string) ( $section['title'] ?? '' ),
				'description' => (string) ( $section['description'] ?? '' ),
				'image_id'    => (int) ( $section['image'] ?? 0 ),
				'items'       => array_map(
					static fn( array $item ): array => ! empty( $item['image'] ) && '' === (string) ( $item['label'] ?? '' )
						? array( 'image_id' => (int) $item['image'] )
						: array(
							'label'        => (string) ( $item['label'] ?? '' ),
							'description'  => (string) ( $item['description'] ?? '' ),
							'price'        => '' === (string) ( $item['price'] ?? '' ) ? null : (string) $item['price'],
							'price_prefix' => (string) ( $item['price_prefix'] ?? '' ),
							'badge'        => (string) ( $item['badge'] ?? '' ),
						),
					array_values( (array) ( $section['items'] ?? array() ) )
				),
			),
			array_values( $sections )
		);
	}

	/**
	 * Builds a stored item from model input.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<string,mixed> $input  { label, description?, price?, price_prefix?, badge? }.
	 * @param  array<string,mixed> $base   Existing item to update, or empty for a new one.
	 * @return array<string,mixed>
	 * @throws Tool_Error When the price is not a price or the label ends up empty.
	 */
	public static function item( array $input, array $base = array() ): array {
		$item = array_merge(
			array(
				'label'        => '',
				'description'  => '',
				'price'        => '',
				'price_prefix' => '',
				'badge'        => '',
			),
			array_intersect_key( $base, array_flip( self::ITEM_FIELDS ) )
		);

		foreach ( self::ITEM_FIELDS as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$item[ $field ] = 'price' === $field ? Prices::parse( $input[ $field ] ) : trim( (string) $input[ $field ] );
			}
		}

		if ( '' === $item['label'] ) {
			throw new Tool_Error( 'Every item needs a label (its name).' );
		}

		return $item;
	}

	/**
	 * Builds a stored section from model input.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<string,mixed> $input  { title, description?, items? }.
	 * @param  array<string,mixed> $base   Existing section to update, or empty for a new one.
	 * @return array<string,mixed>
	 * @throws Tool_Error When the title is empty or there are too many items.
	 */
	public static function section( array $input, array $base = array() ): array {
		$section = array_merge(
			array(
				'id'          => '',
				'title'       => '',
				'description' => '',
				'icon'        => 0,
				'image'       => 0,
				'items'       => array(),
			),
			$base
		);

		// Checked by the tool first (the client's own image, or 0 to remove).
		if ( array_key_exists( 'image_id', $input ) ) {
			$section['image'] = absint( $input['image_id'] );
		}

		foreach ( array( 'title', 'description' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$section[ $field ] = trim( (string) $input[ $field ] );
			}
		}

		if ( array_key_exists( 'items', $input ) ) {
			$items = (array) $input['items'];

			if ( count( $items ) > self::MAX_ITEMS ) {
				throw new Tool_Error( sprintf( 'A section can have at most %d items. Split it into more sections.', self::MAX_ITEMS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
			}

			$section['items'] = array_map( static fn( $item ): array => self::item( (array) $item ), array_values( $items ) );
		}

		if ( '' === $section['title'] ) {
			throw new Tool_Error( 'Every section needs a title.' );
		}

		return $section;
	}

	/**
	 * Builds a stored heading from model input.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.10.0
	 * @param  array<string,mixed> $input  { title?, note?, visible?, background?, text_colour?, size? }.
	 * @param  array<string,mixed> $base   Existing heading to update, or empty for a new one.
	 * @return array<string,mixed>
	 * @throws Tool_Error When the title is empty or a colour is not a hex colour.
	 */
	public static function heading( array $input, array $base = array() ): array {
		$heading = array_merge(
			array(
				'id'          => '',
				'type'        => Headings::TYPE,
				'title'       => '',
				'description' => '',
				'visible'     => true,
				'in_menu'     => false,
				'style'       => array(
					'background' => '',
					'text'       => '',
					'size'       => 'm',
				),
				'icon'        => 0,
				'items'       => array(),
			),
			$base
		);

		if ( array_key_exists( 'title', $input ) ) {
			$heading['title'] = trim( (string) $input['title'] );
		}
		if ( array_key_exists( 'note', $input ) ) {
			$heading['description'] = trim( (string) $input['note'] );
		}
		if ( array_key_exists( 'visible', $input ) ) {
			$heading['visible'] = (bool) $input['visible'];
		}
		if ( array_key_exists( 'in_menu', $input ) ) {
			$heading['in_menu'] = (bool) $input['in_menu'];
		}
		if ( array_key_exists( 'size', $input ) ) {
			$heading['style']['size'] = (string) $input['size'];
		}

		foreach ( array(
			'background'  => 'background',
			'text_colour' => 'text',
		) as $field => $key ) {
			if ( ! array_key_exists( $field, $input ) ) {
				continue;
			}

			$value = trim( (string) $input[ $field ] );

			if ( '' !== $value && null === sanitize_hex_color( $value ) ) {
				throw new Tool_Error( sprintf( '%s must be a hex colour like #4B5F57, or "" for the design\'s own.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
			}

			$heading['style'][ $key ] = strtolower( $value );
		}

		if ( '' === $heading['title'] ) {
			throw new Tool_Error( 'A heading needs a title.' );
		}

		return $heading;
	}

	/**
	 * Indexes of the sections under a heading (up to the next heading).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.10.0
	 * @param  array<int,array<string,mixed>> $sections  Sections and headings.
	 * @param  int                            $index     The heading's index.
	 * @return array<int,int>
	 */
	public static function under_heading( array $sections, int $index ): array {
		$sections = array_values( $sections );
		$out      = array();
		$count    = count( $sections );

		for ( $i = $index + 1; $i < $count; $i++ ) {
			if ( Headings::is_heading( $sections[ $i ] ) ) {
				break;
			}

			$out[] = $i;
		}

		return $out;
	}

	/**
	 * Index of a section (or heading) by id.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<int,array<string,mixed>> $sections        Sections and headings.
	 * @param  string                         $id              Section id.
	 * @param  string                         $want            'section', 'heading' or 'any'.
	 * @return int
	 * @throws Tool_Error When there is no such entry, or it is the wrong kind.
	 */
	public static function section_index( array $sections, string $id, string $want = 'section' ): int {
		foreach ( array_values( $sections ) as $index => $section ) {
			if ( (string) ( $section['id'] ?? '' ) !== $id ) {
				continue;
			}

			$is_heading = Headings::is_heading( $section );

			if ( 'section' === $want && $is_heading ) {
				throw new Tool_Error( sprintf( '"%s" is a heading, not a section. Items go in sections; use update_heading to change a heading.', (string) $section['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
			}

			if ( 'heading' === $want && ! $is_heading ) {
				throw new Tool_Error( sprintf( '"%s" is a section, not a heading.', (string) $section['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
			}

			return $index;
		}

		throw new Tool_Error(
			sprintf(
				'There is no section with id "%s". Sections: %s. Call get_page for the current list.',
				$id, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
				self::section_names( $sections ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
			)
		);
	}

	/**
	 * Finds one item by its name.
	 *
	 * Matching ignores case, extra spaces and curly quotes. When the same name is
	 * in more than one section, the section id must be given.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<int,array<string,mixed>> $sections    Sections.
	 * @param  string                         $label       Item name.
	 * @param  string                         $section_id  Optional section to look in.
	 * @return array{0:int,1:int}  [ section index, item index ].
	 * @throws Tool_Error When nothing or more than one item matches.
	 */
	public static function find_item( array $sections, string $label, string $section_id = '' ): array {
		$sections = array_values( $sections );
		$scope    = '' !== $section_id ? array( self::section_index( $sections, $section_id ) ) : array_keys( $sections );
		$wanted   = self::normalise( $label );
		$found    = array();
		$names    = array();

		foreach ( $scope as $s ) {
			foreach ( array_values( (array) ( $sections[ $s ]['items'] ?? array() ) ) as $i => $item ) {
				$name = (string) ( $item['label'] ?? '' );

				if ( self::normalise( $name ) === $wanted ) {
					$found[] = array( $s, $i );
				}
				$names[] = $name;
			}
		}

		if ( 1 === count( $found ) ) {
			return $found[0];
		}

		if ( count( $found ) > 1 ) {
			$in = array_map( static fn( array $hit ): string => (string) $sections[ $hit[0] ]['title'] . ' (' . (string) $sections[ $hit[0] ]['id'] . ')', $found );

			throw new Tool_Error( sprintf( '"%s" is in more than one section: %s. Say which with section_id.', $label, implode( ', ', array_unique( $in ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
		}

		$close = self::closest( $label, $names );

		throw new Tool_Error(
			sprintf( 'No item called "%s".', $label ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
			. ( array() !== $close ? ' Did you mean: ' . implode( ', ', $close ) . '?' : '' ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
		);
	}

	/**
	 * Moves an element of a list to a 1-based position (clamped).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<int,mixed> $entries   List.
	 * @param  int              $from      0-based index.
	 * @param  int              $position  1-based target position.
	 * @return array<int,mixed>
	 */
	public static function move( array $entries, int $from, int $position ): array {
		$entries = array_values( $entries );
		$element = array_splice( $entries, $from, 1 );
		$to      = max( 0, min( count( $entries ), $position - 1 ) );

		array_splice( $entries, $to, 0, $element );

		return $entries;
	}

	/**
	 * Inserts elements at a 1-based position (0 or past the end appends).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<int,mixed> $entries   List.
	 * @param  array<int,mixed> $elements  Elements to insert.
	 * @param  int              $position  1-based position.
	 * @return array<int,mixed>
	 */
	public static function insert( array $entries, array $elements, int $position = 0 ): array {
		$entries = array_values( $entries );
		$at      = $position >= 1 ? min( count( $entries ), $position - 1 ) : count( $entries );

		array_splice( $entries, $at, 0, $elements );

		return $entries;
	}

	/**
	 * "Breakfast (s_1a2b3c4d), Drinks (s_…)" for messages.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<int,array<string,mixed>> $sections  Sections.
	 * @return string
	 */
	public static function section_names( array $sections ): string {
		if ( array() === $sections ) {
			return 'none yet';
		}

		return implode(
			', ',
			array_map( static fn( array $section ): string => ( Headings::is_heading( $section ) ? 'heading ' : '' ) . (string) ( $section['title'] ?? '' ) . ' (' . (string) ( $section['id'] ?? '' ) . ')', $sections )
		);
	}

	/**
	 * Comparable form of a name.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string $name  Name.
	 * @return string
	 */
	private static function normalise( string $name ): string {
		$name = str_replace( array( "\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}" ), array( "'", "'", '"', '"' ), $name );

		return mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $name ) ) );
	}

	/**
	 * Up to three names close to the one asked for.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string            $label  Name asked for.
	 * @param  array<int,string> $names  Existing names.
	 * @return array<int,string>
	 */
	private static function closest( string $label, array $names ): array {
		$wanted = self::normalise( $label );
		$scores = array();

		foreach ( array_unique( $names ) as $name ) {
			$candidate = self::normalise( $name );

			if ( '' === $candidate ) {
				continue;
			}

			similar_text( $wanted, $candidate, $percent );

			if ( str_contains( $candidate, $wanted ) || str_contains( $wanted, $candidate ) ) {
				$percent += 40;
			}

			if ( $percent >= 50 ) {
				$scores[ $name ] = $percent;
			}
		}

		arsort( $scores );

		return array_slice( array_map( 'strval', array_keys( $scores ) ), 0, 3 );
	}
}
