<?php
/**
 * Print colours.
 *
 * Converts brand colours for printing on white paper: a dark design's light
 * text colour (or a pale accent) would be unreadable on paper, so colours
 * that are too light are swapped for a readable fallback.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.2.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Pdf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Print_Colour
 *
 * Hex → PDF RGB, with a lightness check.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.2.0
 */
class Print_Colour {

	/**
	 * Returns the colour as 0–1 RGB, or the fallback when it is too light for paper.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  string $hex        Brand colour, e.g. "#D46A84".
	 * @param  string $fallback   Colour to use instead, e.g. "#1c1c1c".
	 * @param  float  $max_light  Highest acceptable lightness (0 black … 1 white).
	 * @return array<int,float>
	 */
	public static function readable( string $hex, string $fallback, float $max_light ): array {
		$rgb = self::from_hex( $hex );

		if ( ! $rgb || self::lightness( $rgb ) > $max_light ) {
			return self::from_hex( $fallback );
		}

		return $rgb;
	}

	/**
	 * Converts "#rgb" or "#rrggbb" to 0–1 RGB floats ([] when invalid).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  string $hex  Hex colour.
	 * @return array<int,float>
	 */
	public static function from_hex( string $hex ): array {
		$hex = ltrim( trim( $hex ), '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
			return array();
		}

		return array_map(
			static fn( string $pair ): float => hexdec( $pair ) / 255,
			str_split( $hex, 2 )
		);
	}

	/**
	 * Returns perceived lightness (0–1).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  array<int,float> $rgb  0–1 RGB.
	 * @return float
	 */
	public static function lightness( array $rgb ): float {
		return 0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2];
	}
}
