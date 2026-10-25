<?php
/**
 * Font library.
 *
 * Reads the curated font list from config/fonts.yaml and builds the Google
 * Fonts stylesheet URL for the fonts a page actually uses.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fonts
 *
 * Font lookup and Google Fonts URL builder.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Fonts {

	/**
	 * Returns all fonts keyed by slug.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return array<string,array{label:string,family:string,google:string}>
	 */
	public static function all(): array {
		$fonts = array();

		foreach ( Config::get( 'fonts.yaml' ) as $slug => $font ) {
			if ( ! is_array( $font ) || empty( $font['family'] ) ) {
				continue;
			}

			$fonts[ sanitize_key( (string) $slug ) ] = array(
				'label'  => (string) ( $font['label'] ?? $slug ),
				'family' => (string) $font['family'],
				'google' => (string) ( $font['google'] ?? '' ),
			);
		}

		return $fonts;
	}

	/**
	 * Returns a single font definition, or null when unknown.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $slug  Font slug.
	 * @return array{label:string,family:string,google:string}|null
	 */
	public static function get( string $slug ): ?array {
		$fonts = self::all();
		return $fonts[ $slug ] ?? null;
	}

	/**
	 * Builds a Google Fonts css2 URL for the given font slugs.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  array<int,string> $slugs  Font slugs.
	 * @return string  Stylesheet URL, or empty string when no web fonts are needed.
	 */
	public static function google_url( array $slugs ): string {
		$families = array();

		foreach ( array_unique( array_filter( $slugs ) ) as $slug ) {
			$font = self::get( $slug );
			if ( $font && '' !== $font['google'] ) {
				$families[] = 'family=' . $font['google'];
			}
		}

		if ( empty( $families ) ) {
			return '';
		}

		return 'https://fonts.googleapis.com/css2?' . implode( '&', $families ) . '&display=swap';
	}
}
