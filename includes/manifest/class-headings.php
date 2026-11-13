<?php
/**
 * Menu headings inside a price list.
 *
 * A heading is an entry in the same ordered list as the sections, e.g.
 * "Starters & Snacks" or "Main Menu". It groups every section below it up
 * to the next heading, so one heading can cover several sections. It has a
 * note ("Served until 2 p.m. daily"), an on/off switch that hides it and its
 * sections, and its own look (background, text colour, size).
 *
 * Stored shape (the other keys match a section so existing code that walks
 * sections and items keeps working):
 *   { id, type: "heading", title, description (the note), visible,
 *     style: { background, text, size }, icon: 0, sort_order, items: [] }
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.10.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Manifest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Headings
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.10.0
 */
final class Headings {

	/**
	 * Entry type of a heading.
	 */
	public const TYPE = 'heading';

	/**
	 * Heading sizes: small, medium, large.
	 */
	public const SIZES = array( 's', 'm', 'l' );

	/**
	 * Whether an entry is a heading.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.10.0
	 * @param  mixed $entry  Price list entry.
	 * @return bool
	 */
	public static function is_heading( $entry ): bool {
		return is_array( $entry ) && self::TYPE === ( $entry['type'] ?? '' );
	}

	/**
	 * Cleans a heading entry.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.10.0
	 * @param  array<string,mixed> $raw    Raw entry.
	 * @param  string              $id     Its (already valid) id.
	 * @param  int                 $order  Position in the list.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $raw, string $id, int $order ): array {
		$style = isset( $raw['style'] ) && is_array( $raw['style'] ) ? $raw['style'] : array();
		$size  = (string) ( $style['size'] ?? 'm' );

		return array(
			'id'          => $id,
			'type'        => self::TYPE,
			'title'       => sanitize_text_field( (string) ( $raw['title'] ?? '' ) ),
			'description' => sanitize_text_field( (string) ( $raw['description'] ?? '' ) ),
			'visible'     => ! isset( $raw['visible'] ) || filter_var( $raw['visible'], FILTER_VALIDATE_BOOLEAN ),
			'style'       => array(
				'background' => (string) sanitize_hex_color( (string) ( $style['background'] ?? '' ) ),
				'text'       => (string) sanitize_hex_color( (string) ( $style['text'] ?? '' ) ),
				'size'       => in_array( $size, self::SIZES, true ) ? $size : 'm',
			),
			'icon'        => 0,
			'sort_order'  => $order,
			'items'       => array(),
		);
	}

	/**
	 * Drops headings that are switched off, with the sections under them.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.10.0
	 * @param  array<int,array<string,mixed>> $entries  Sections and headings in order.
	 * @return array<int,array<string,mixed>>
	 */
	public static function visible( array $entries ): array {
		$out  = array();
		$show = true;

		foreach ( $entries as $entry ) {
			if ( self::is_heading( $entry ) ) {
				$show = false !== ( $entry['visible'] ?? true );
			}

			if ( $show ) {
				$out[] = $entry;
			}
		}

		return $out;
	}

	/**
	 * Only the sections (headings removed), e.g. for counts.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.10.0
	 * @param  array<int,array<string,mixed>> $entries  Sections and headings.
	 * @return array<int,array<string,mixed>>
	 */
	public static function sections_only( array $entries ): array {
		return array_values( array_filter( $entries, static fn( $entry ): bool => ! self::is_heading( $entry ) ) );
	}

	/**
	 * CSS custom properties for a heading's own colours ('' when it uses the design's).
	 *
	 * Values are sanitised hex colours, safe inside a style attribute.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.10.0
	 * @param  array<string,mixed> $heading  Heading entry.
	 * @return string
	 */
	public static function style_vars( array $heading ): string {
		$style = (array) ( $heading['style'] ?? array() );
		$vars  = '';

		foreach ( array(
			'background' => '--sm-heading-bg',
			'text'       => '--sm-heading-text',
		) as $key => $property ) {
			$value = (string) sanitize_hex_color( (string) ( $style[ $key ] ?? '' ) );
			$vars .= '' !== $value ? $property . ':' . $value . ';' : '';
		}

		return $vars;
	}

	/**
	 * The heading's size key.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.10.0
	 * @param  array<string,mixed> $heading  Heading entry.
	 * @return string
	 */
	public static function size( array $heading ): string {
		$size = (string) ( $heading['style']['size'] ?? 'm' );

		return in_array( $size, self::SIZES, true ) ? $size : 'm';
	}
}
