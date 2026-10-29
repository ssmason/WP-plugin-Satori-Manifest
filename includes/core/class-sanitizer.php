<?php
/**
 * Input sanitization for manifest data.
 *
 * Centralises all sanitization logic for sections and items so the meta box
 * save handler (class-meta-box.php) stays focused on WordPress wiring.
 * Any code path that accepts user-submitted sections JSON should pass the
 * decoded array through this class before persisting it.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Sanitizer
 *
 * Sanitizes decoded sections and items arrays before they are persisted.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */
class Sanitizer {

	/**
	 * Sanitizes a decoded sections array.
	 *
	 * Rebuilds the array with only expected keys (id, title, description,
	 * items), re-numbers sort_order from the array position (honouring
	 * client-side reordering), and delegates item sanitization to
	 * sanitize_items().
	 *
	 * Every section keeps a permanent id so blocks can reference it after
	 * reordering or renaming; new sections (and duplicates) get a fresh one.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @param  array<int,array<string,mixed>> $sections  Raw decoded sections array.
	 * @return array<int,array<string,mixed>>             Sanitized sections array.
	 */
	public static function sanitize_sections( array $sections ): array {
		$out  = array();
		$seen = array();

		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}

			$id = self::section_id( (string) ( $section['id'] ?? '' ) );
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				$id = self::new_section_id();
			}
			$seen[ $id ] = true;

			$raw_items = isset( $section['items'] ) && is_array( $section['items'] )
				? $section['items']
				: array();

			$out[] = array(
				'id'          => $id,
				'title'       => isset( $section['title'] ) ? sanitize_text_field( (string) $section['title'] ) : '',
				'description' => isset( $section['description'] ) ? sanitize_textarea_field( (string) $section['description'] ) : '',
				'sort_order'  => count( $out ),
				'items'       => self::sanitize_items( $raw_items ),
			);
		}

		return $out;
	}

	/**
	 * Validates a section id ("s_" followed by 8 lowercase hex characters).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.2.0
	 * @param  string $id  Candidate id.
	 * @return string  The id, or '' when invalid.
	 */
	public static function section_id( string $id ): string {
		return preg_match( '/^s_[a-f0-9]{8}$/', $id ) ? $id : '';
	}

	/**
	 * Generates a new section id.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.2.0
	 * @return string
	 */
	public static function new_section_id(): string {
		return 's_' . substr( md5( wp_generate_uuid4() ), 0, 8 );
	}

	/**
	 * Sanitizes a decoded items array.
	 *
	 * Rebuilds the array with only expected keys, normalises price to a
	 * two-decimal string, caps the badge at 24 characters and re-numbers
	 * sort_order from the array position.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @param  array<int,array<string,mixed>> $items  Raw decoded items array.
	 * @return array<int,array<string,mixed>>          Sanitized items array.
	 */
	private static function sanitize_items( array $items ): array {
		$out = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$out[] = array(
				'label'        => isset( $item['label'] ) ? sanitize_text_field( (string) $item['label'] ) : '',
				'description'  => isset( $item['description'] ) ? sanitize_text_field( (string) $item['description'] ) : '',
				'price'        => isset( $item['price'] ) && '' !== trim( (string) $item['price'] ) ? number_format( (float) $item['price'], 2, '.', '' ) : '',
				'price_prefix' => isset( $item['price_prefix'] ) ? sanitize_text_field( (string) $item['price_prefix'] ) : '',
				'badge'        => isset( $item['badge'] ) ? mb_substr( sanitize_text_field( (string) $item['badge'] ), 0, 24 ) : '',
				'sort_order'   => count( $out ),
			);
		}

		return $out;
	}
}
