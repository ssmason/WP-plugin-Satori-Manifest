<?php
/**
 * PDF font metrics.
 *
 * Character widths of the PDF standard fonts Helvetica and Helvetica-Bold,
 * so text can be measured, wrapped and right-aligned without embedding a
 * font file. Widths are in 1/1000 of the font size, for Windows-1252
 * characters 32–255 (the encoding the standard fonts use).
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
 * Class Font_Metrics
 *
 * Measures and wraps text in the standard Helvetica fonts.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.2.0
 */
class Font_Metrics {

	/**
	 * Helvetica widths, characters 32–255.
	 *
	 * @since 3.2.0
	 * @var   array<int,int>
	 */
	private const REGULAR = array(
		278,
		278,
		356,
		556,
		556,
		890,
		668,
		192,
		333,
		333,
		389,
		584,
		278,
		333,
		278,
		278,
		556,
		486,
		556,
		556,
		556,
		556,
		556,
		556,
		556,
		556,
		278,
		278,
		584,
		584,
		584,
		556,
		1016,
		668,
		668,
		723,
		723,
		668,
		611,
		778,
		723,
		278,
		501,
		668,
		556,
		833,
		723,
		778,
		668,
		778,
		723,
		668,
		611,
		723,
		668,
		944,
		668,
		668,
		611,
		278,
		278,
		278,
		470,
		556,
		333,
		556,
		556,
		501,
		556,
		556,
		261,
		556,
		556,
		223,
		223,
		501,
		223,
		833,
		556,
		556,
		556,
		556,
		333,
		501,
		278,
		556,
		501,
		723,
		501,
		501,
		501,
		334,
		260,
		334,
		584,
		751,
		556,
		0,
		223,
		556,
		333,
		1000,
		556,
		556,
		333,
		1000,
		668,
		333,
		1000,
		0,
		611,
		0,
		0,
		206,
		206,
		333,
		333,
		351,
		556,
		1000,
		333,
		1000,
		501,
		333,
		944,
		0,
		501,
		668,
		278,
		333,
		556,
		556,
		556,
		556,
		260,
		556,
		333,
		737,
		370,
		556,
		584,
		333,
		737,
		553,
		400,
		549,
		333,
		333,
		333,
		577,
		537,
		333,
		333,
		333,
		365,
		556,
		834,
		834,
		834,
		611,
		668,
		668,
		668,
		668,
		668,
		668,
		1000,
		723,
		668,
		668,
		668,
		668,
		278,
		278,
		278,
		278,
		723,
		723,
		778,
		778,
		778,
		778,
		778,
		584,
		778,
		723,
		723,
		723,
		723,
		668,
		668,
		611,
		556,
		556,
		556,
		556,
		556,
		556,
		890,
		501,
		556,
		556,
		556,
		556,
		278,
		278,
		278,
		278,
		556,
		556,
		556,
		556,
		556,
		556,
		556,
		549,
		611,
		556,
		556,
		556,
		556,
		501,
		556,
		501,
	);

	/**
	 * Helvetica-Bold widths, characters 32–255.
	 *
	 * @since 3.2.0
	 * @var   array<int,int>
	 */
	private const BOLD = array(
		278,
		333,
		475,
		556,
		556,
		890,
		723,
		238,
		333,
		333,
		389,
		584,
		278,
		333,
		278,
		278,
		556,
		504,
		556,
		556,
		556,
		556,
		556,
		556,
		556,
		556,
		333,
		333,
		584,
		584,
		584,
		611,
		975,
		723,
		723,
		723,
		723,
		668,
		611,
		778,
		723,
		278,
		556,
		723,
		611,
		833,
		723,
		778,
		668,
		778,
		723,
		668,
		611,
		723,
		668,
		944,
		668,
		668,
		611,
		333,
		278,
		333,
		584,
		556,
		333,
		556,
		611,
		556,
		611,
		556,
		333,
		611,
		611,
		278,
		278,
		556,
		278,
		890,
		611,
		611,
		611,
		611,
		389,
		556,
		333,
		611,
		556,
		778,
		556,
		556,
		501,
		389,
		280,
		389,
		584,
		751,
		556,
		0,
		278,
		556,
		501,
		1000,
		556,
		556,
		333,
		1000,
		668,
		333,
		1000,
		0,
		611,
		0,
		0,
		243,
		243,
		501,
		501,
		351,
		556,
		1000,
		333,
		1000,
		556,
		333,
		944,
		0,
		501,
		668,
		278,
		333,
		556,
		556,
		556,
		556,
		280,
		556,
		333,
		737,
		370,
		556,
		584,
		333,
		737,
		553,
		400,
		549,
		333,
		333,
		333,
		577,
		556,
		333,
		333,
		333,
		365,
		556,
		834,
		834,
		834,
		611,
		723,
		723,
		723,
		723,
		723,
		723,
		1000,
		723,
		668,
		668,
		668,
		668,
		278,
		278,
		278,
		278,
		723,
		723,
		778,
		778,
		778,
		778,
		778,
		584,
		778,
		723,
		723,
		723,
		723,
		668,
		668,
		611,
		556,
		556,
		556,
		556,
		556,
		556,
		890,
		556,
		556,
		556,
		556,
		556,
		278,
		278,
		278,
		278,
		611,
		611,
		611,
		611,
		611,
		611,
		611,
		549,
		611,
		611,
		611,
		611,
		611,
		556,
		611,
		556,
	);

	/**
	 * Converts UTF-8 text to the Windows-1252 bytes the standard fonts use.
	 *
	 * Characters with no Windows-1252 equivalent are transliterated or dropped.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  string $text  UTF-8 text.
	 * @return string  Windows-1252 bytes.
	 */
	public static function encode( string $text ): string {
		$converted = iconv( 'UTF-8', 'CP1252//TRANSLIT//IGNORE', $text );

		return false === $converted ? (string) preg_replace( '/[^\x20-\x7E]/', '', $text ) : $converted;
	}

	/**
	 * Returns the width of Windows-1252 text in points.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  string $text  Windows-1252 text (see encode()).
	 * @param  float  $size  Font size in points.
	 * @param  bool   $bold  Helvetica-Bold instead of Helvetica.
	 * @return float
	 */
	public static function width( string $text, float $size, bool $bold = false ): float {
		$table = $bold ? self::BOLD : self::REGULAR;
		$units = 0;
		$count = strlen( $text );

		for ( $i = 0; $i < $count; $i++ ) {
			$code   = ord( $text[ $i ] );
			$units += $code >= 32 ? $table[ $code - 32 ] : 0;
		}

		return $units * $size / 1000;
	}

	/**
	 * Wraps Windows-1252 text into lines no wider than a maximum.
	 *
	 * Breaks at spaces; a single word longer than the line is broken by characters.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  string $text       Windows-1252 text.
	 * @param  float  $size       Font size in points.
	 * @param  float  $max_width  Maximum line width in points.
	 * @param  bool   $bold       Bold font.
	 * @return array<int,string>
	 */
	public static function wrap( string $text, float $size, float $max_width, bool $bold = false ): array {
		$lines = array();
		$line  = '';

		foreach ( preg_split( '/\s+/', trim( $text ) ) as $word ) {
			$candidate = '' === $line ? $word : $line . ' ' . $word;

			if ( self::width( $candidate, $size, $bold ) <= $max_width ) {
				$line = $candidate;
				continue;
			}

			if ( '' !== $line ) {
				$lines[] = $line;
			}

			// Very long word: split it across lines.
			while ( isset( $word[1] ) && self::width( $word, $size, $bold ) > $max_width ) {
				$cut = strlen( $word );
				while ( $cut > 1 && self::width( substr( $word, 0, $cut ), $size, $bold ) > $max_width ) {
					--$cut;
				}
				$lines[] = substr( $word, 0, $cut );
				$word    = substr( $word, $cut );
			}

			$line = $word;
		}

		if ( '' !== $line ) {
			$lines[] = $line;
		}

		return $lines;
	}
}
