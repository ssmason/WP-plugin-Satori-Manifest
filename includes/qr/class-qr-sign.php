<?php
/**
 * Printable QR sign: a client's QR code on a card with their own wording.
 *
 * Two shapes, drawn after the signs on the Qrable home page:
 *   - large: top text, subtitle and an accent colour of the client's choice;
 *   - small: top text only (its last word in red), fixed colours.
 *
 * Both take a border (frame) colour, a colour for the code's three corner
 * squares (kept dark so it scans) and an optional line of text under the
 * TAP or SCAN pill - e.g. the company name - in a colour and font of the
 * client's choice; that line makes the sign taller.
 *
 * One shape definition (sign units, top-left origin) feeds both the SVG
 * preview and the vector PDF, so what clients see is what prints.
 * Settings are page meta (`_sm_qr_sign`).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Qr;

use SatoriManifest\Branding\Brand;
use SatoriManifest\Pdf\Font_Metrics;
use SatoriManifest\Pdf\Print_Colour;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Qr_Sign
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */
final class Qr_Sign {

	/**
	 * Page meta holding the sign settings.
	 */
	public const META = '_sm_qr_sign';

	/**
	 * Sign sizes.
	 */
	public const SIZES = array( 'large', 'small' );

	/**
	 * Longest top text and subtitle.
	 */
	public const MAX_TITLE    = 30;
	public const MAX_SUBTITLE = 50;
	public const MAX_FOOTER   = 40;

	/**
	 * Fixed colours: frame, card, small-sign accent, default large accent.
	 */
	public const DARK          = '#14171c';
	public const CARD          = '#fbfbfa';
	public const SMALL_ACCENT  = '#c8102e';
	public const DEFAULT_LARGE = '#106ebe';
	public const INK           = '#111111';

	/**
	 * Fonts for the line under the pill: key => SVG font stack (the PDF uses
	 * Helvetica, Times or Courier, all bold).
	 */
	public const FONTS = array(
		'sans'  => 'Plus Jakarta Sans, Helvetica, Arial, sans-serif',
		'serif' => 'Georgia, Times New Roman, Times, serif',
		'mono'  => 'Courier New, Courier, monospace',
	);

	/**
	 * Lightest the corner squares may be and still scan (Print_Colour::lightness()).
	 */
	public const MAX_CORNER_LIGHTNESS = 0.45;

	/**
	 * Width and height of each shape, in sign units.
	 */
	public const BOX = array(
		'large' => array( 190.0, 232.0 ),
		'small' => array( 122.0, 152.0 ),
	);

	/**
	 * Extra height when there is a line under the pill.
	 */
	public const FOOTER_HEIGHT = array(
		'large' => 30.0,
		'small' => 20.0,
	);

	/**
	 * The sign settings for a page, with defaults filled in.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  int $page_id  Client page ID.
	 * @return array<string,string>
	 */
	public static function settings( int $page_id ): array {
		$saved = get_post_meta( $page_id, self::META, true );

		return self::clean( is_array( $saved ) ? $saved : array(), $page_id );
	}

	/**
	 * Saves sign settings.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  int                 $page_id  Client page ID.
	 * @param  array<string,mixed> $input    Raw settings.
	 * @return array<string,string>
	 */
	public static function save( int $page_id, array $input ): array {
		$clean = self::clean( $input, $page_id );

		update_post_meta( $page_id, self::META, $clean );

		return $clean;
	}

	/**
	 * Cleans settings; empty texts fall back to sensible defaults.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<string,mixed> $input    Raw settings.
	 * @param  int                 $page_id  Client page ID (for the default title).
	 * @return array<string,string>
	 */
	public static function clean( array $input, int $page_id = 0 ): array {
		$size     = in_array( $input['size'] ?? '', self::SIZES, true ) ? (string) $input['size'] : 'large';
		$title    = mb_substr( trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) ), 0, self::MAX_TITLE );
		$subtitle = mb_substr( trim( sanitize_text_field( (string) ( $input['subtitle'] ?? '' ) ) ), 0, self::MAX_SUBTITLE );
		$colour   = (string) sanitize_hex_color( (string) ( $input['colour'] ?? '' ) );
		$footer   = mb_substr( trim( sanitize_text_field( (string) ( $input['footer'] ?? '' ) ) ), 0, self::MAX_FOOTER );
		$font     = (string) ( $input['footer_font'] ?? '' );

		if ( '' === $title ) {
			$name  = $page_id ? (string) Brand::for_page( $page_id )['name'] : '';
			$title = 'small' === $size || '' === $name ? __( 'Our Menu', 'satori-manifest' ) : mb_substr( $name, 0, self::MAX_TITLE );
		}

		return array(
			'size'          => $size,
			'title'         => $title,
			'subtitle'      => '' !== $subtitle || array_key_exists( 'subtitle', $input ) ? $subtitle : __( 'Scan or tap for our prices', 'satori-manifest' ),
			'colour'        => '' !== $colour ? strtolower( $colour ) : self::DEFAULT_LARGE,
			'border'        => self::hex( $input['border'] ?? '', self::DARK ),
			'corners'       => self::hex( $input['corners'] ?? '', self::INK, self::MAX_CORNER_LIGHTNESS ),
			'footer'        => $footer,
			'footer_colour' => self::hex( $input['footer_colour'] ?? '', self::DARK ),
			'footer_font'   => isset( self::FONTS[ $font ] ) ? $font : 'sans',
		);
	}

	/**
	 * Width and height of a sign, in sign units (taller with a footer line).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  array<string,string> $settings  Clean settings.
	 * @return array{0:float,1:float}
	 */
	public static function box( array $settings ): array {
		[ $w, $h ] = self::BOX[ $settings['size'] ];

		return array( $w, $h + ( '' !== $settings['footer'] ? self::FOOTER_HEIGHT[ $settings['size'] ] : 0.0 ) );
	}

	/**
	 * A clean lowercase hex colour, or the fallback (also when lighter than allowed).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  mixed  $value      Raw value.
	 * @param  string $fallback   Default.
	 * @param  float  $max_light  Highest lightness accepted (1 = any).
	 * @return string
	 */
	private static function hex( $value, string $fallback, float $max_light = 1.0 ): string {
		$hex = (string) sanitize_hex_color( is_string( $value ) ? $value : '' );

		if ( '' === $hex || Print_Colour::lightness( Print_Colour::from_hex( $hex ) ) > $max_light ) {
			return $fallback;
		}

		return strtolower( $hex );
	}

	/**
	 * The accent colour a sign is drawn with (small signs are always red).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<string,string> $settings  Clean settings.
	 * @return string
	 */
	public static function accent( array $settings ): string {
		return 'small' === $settings['size'] ? self::SMALL_ACCENT : $settings['colour'];
	}

	/**
	 * The shape: everything except the texts and the code, in sign units.
	 *
	 * Each part: array( kind, …, colour role ). Kinds: rrect (x, y, w, h, r),
	 * path (commands), waves (array of x, y, chord, radius).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * The footer line (text under the pill) adds FOOTER_HEIGHT: the frame and
	 * card grow, and the small sign's bottom corner moves down with them.
	 *
	 * @param  string $size    'large' or 'small'.
	 * @param  bool   $footer  Whether there is a line under the pill.
	 * @return array<string,mixed>
	 */
	public static function shape( string $size, bool $footer = false ): array {
		$extra = $footer ? self::FOOTER_HEIGHT[ $size ] : 0.0;

		if ( 'small' === $size ) {
			$b = $extra;

			return array(
				'parts'    => array(
					array( 'rrect', 0, 0, 122, 152 + $b, 16, 'frame' ),
					array( 'rrect', 4, 4, 114, 144 + $b, 12, 'card' ),
					array( 'path', array( array( 'M', 4, 32 ), array( 'L', 4, 16 ), array( 'C', 4, 9.37, 9.37, 4, 16, 4 ), array( 'L', 30, 4 ), array( 'C', 17.33, 8, 8.67, 17.33, 4, 32 ), array( 'Z' ) ), 'accent' ),
					array( 'path', array( array( 'M', 118, 120 + $b ), array( 'L', 118, 136 + $b ), array( 'C', 118, 142.63 + $b, 112.63, 148 + $b, 106, 148 + $b ), array( 'L', 92, 148 + $b ), array( 'C', 104.67, 144 + $b, 113.33, 134.67 + $b, 118, 120 + $b ), array( 'Z' ) ), 'accent' ),
					array( 'rrect', 12, 112, 98, 22, 11, 'accent' ),
					array( 'waves', array( array( 18, 120.5, 5, 3 ), array( 21, 118, 10, 6 ), array( 24, 115.5, 15, 9 ) ), 'white' ),
				),
				'title'    => array( 61, 24, 14, 104 ),
				'subtitle' => null,
				'qr'       => array( 24, 30, 74 ),
				'label'    => array( 69, 126.5, 9 ),
				'footer'   => $footer ? array( 61, 153, 10, 100 ) : null,
			);
		}

		return array(
			'parts'    => array(
				array( 'rrect', 0, 0, 190, 232 + $extra, 20, 'frame' ),
				array( 'rrect', 5, 5, 180, 222 + $extra, 16, 'card' ),
				array( 'path', array( array( 'M', 5, 40 ), array( 'L', 5, 21 ), array( 'C', 5, 12.16, 12.16, 5, 21, 5 ), array( 'L', 46, 5 ), array( 'C', 27.33, 8.33, 13.67, 20, 5, 40 ), array( 'Z' ) ), 'accent' ),
				array( 'rrect', 20, 190, 152, 28, 14, 'accent' ),
				array( 'waves', array( array( 36, 200, 8, 5 ), array( 40, 196, 16, 10 ), array( 44, 192, 24, 15 ) ), 'white' ),
			),
			'title'    => array( 95, 32, 17, 160 ),
			'subtitle' => array( 95, 46, 7.4, 160 ),
			'qr'       => array( 34, 56, 122 ),
			'label'    => array( 104, 209, 13 ),
			'footer'   => $footer ? array( 95, 243, 14, 160 ) : null,
		);
	}

	/**
	 * The words of a text (the small sign colours the last one).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  string $text  Text.
	 * @return array<int,string>
	 */
	public static function words( string $text ): array {
		$words = preg_split( '/\s+/u', trim( $text ) );

		return is_array( $words ) ? $words : array( $text );
	}

	/**
	 * Font size that keeps a text within a width (shrinks long text).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  string $text   Text.
	 * @param  float  $size   Preferred size.
	 * @param  float  $width   Available width.
	 * @param  string $family  sans, serif or mono.
	 * @return float
	 */
	public static function fit( string $text, float $size, float $width, string $family = 'sans' ): float {
		$measured = Font_Metrics::family_width( Font_Metrics::encode( $text ), $size, $family );

		return $measured > $width ? max( $size * 0.45, $size * $width / $measured ) : $size;
	}

	/**
	 * The sign as an SVG document.
	 *
	 * Elements carry data-sm-sign-* attributes so the QR builder can update
	 * the preview while the client types.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  int                       $page_id   Client page ID.
	 * @param  array<string,string>|null $settings  Settings to draw (null = saved).
	 * @return string
	 */
	public static function svg( int $page_id, ?array $settings = null ): string {
		$settings = null === $settings ? self::settings( $page_id ) : array_merge( self::clean( array(), $page_id ), $settings );
		$size     = $settings['size'];
		$shape    = self::shape( $size, '' !== $settings['footer'] );
		$accent   = self::accent( $settings );
		$box      = self::box( $settings );
		$matrix   = Page_Qr::matrix( $page_id );
		$font     = 'Plus Jakarta Sans, Helvetica, Arial, sans-serif';
		$out      = '';

		foreach ( $shape['parts'] as $part ) {
			$out .= self::svg_part( $part, $accent, $settings );
		}

		// Top text; on the small sign its last word takes the accent colour.
		[ $x, $y, $font_size, $width ] = $shape['title'];
		$title                         = $settings['title'];
		$fitted                        = self::fit( $title, (float) $font_size, (float) $width );
		$words                         = self::words( $title );
		$inner                         = 'small' === $size && count( $words ) > 1
			? esc_html( implode( ' ', array_slice( $words, 0, -1 ) ) ) . ' <tspan fill="' . esc_attr( $accent ) . '" data-sm-sign-colour="fill">' . esc_html( (string) end( $words ) ) . '</tspan>'
			: esc_html( $title );

		$out .= sprintf(
			'<text x="%s" y="%s" text-anchor="middle" font-family="%s" font-weight="800" font-size="%s" fill="%s" data-sm-sign-title data-sm-sign-max="%s" data-sm-sign-size="%s"%s>%s</text>',
			$x,
			$y,
			esc_attr( $font ),
			round( $fitted, 2 ),
			self::DARK,
			$width,
			$font_size,
			'small' === $size ? ' data-sm-sign-split' : '',
			$inner
		);

		if ( null !== $shape['subtitle'] ) {
			[ $x, $y, $font_size, $width ] = $shape['subtitle'];

			$out .= sprintf(
				'<text x="%s" y="%s" text-anchor="middle" font-family="%s" font-weight="800" font-size="%s" fill="%s" data-sm-sign-colour="fill" data-sm-sign-subtitle data-sm-sign-max="%s" data-sm-sign-size="%s">%s</text>',
				$x,
				$y,
				esc_attr( $font ),
				round( self::fit( $settings['subtitle'], (float) $font_size, (float) $width ), 2 ),
				esc_attr( $accent ),
				$width,
				$font_size,
				esc_html( $settings['subtitle'] )
			);
		}

		// The code, scaled to its box; its three corner squares in their own colour.
		[ $x, $y, $qr_size ] = $shape['qr'];
		$out                .= sprintf(
			'<g transform="translate(%s %s) scale(%.4F)"><path fill="%s" d="%s"/><path fill="%s" d="%s"/></g>',
			$x,
			$y,
			$qr_size / max( 1, count( $matrix ) ),
			self::INK,
			Qr_Renderer::path( $matrix, false ),
			esc_attr( $settings['corners'] ),
			Qr_Renderer::path( $matrix, true )
		);

		[ $x, $y, $font_size ] = $shape['label'];
		$out                  .= sprintf(
			'<text x="%s" y="%s" text-anchor="middle" font-family="%s" font-weight="800" font-size="%s" fill="#ffffff" letter-spacing="0.5">%s</text>',
			$x,
			$y,
			esc_attr( $font ),
			$font_size,
			esc_html__( 'TAP or SCAN', 'satori-manifest' )
		);

		if ( null !== $shape['footer'] ) {
			[ $x, $y, $font_size, $width ] = $shape['footer'];
			$family                        = $settings['footer_font'];

			$out .= sprintf(
				'<text x="%s" y="%s" text-anchor="middle" font-family="%s" font-weight="%s" font-size="%s" fill="%s">%s</text>',
				$x,
				$y,
				esc_attr( self::FONTS[ $family ] ),
				'sans' === $family ? '800' : '700',
				round( self::fit( $settings['footer'], (float) $font_size, (float) $width, $family ), 2 ),
				esc_attr( $settings['footer_colour'] ),
				esc_html( $settings['footer'] )
			);
		}

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$s %2$s" class="sm-sign sm-sign--%3$s" data-sm-sign="%3$s" role="img" aria-label="%4$s">%5$s</svg>',
			$box[0],
			$box[1],
			esc_attr( $size ),
			esc_attr( $settings['title'] ),
			$out
		);
	}

	/**
	 * One shape part as SVG.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,mixed>     $part      Part from shape().
	 * @param  string               $accent    Accent colour.
	 * @param  array<string,string> $settings  Clean settings.
	 * @return string
	 */
	private static function svg_part( array $part, string $accent, array $settings ): string {
		$role   = (string) end( $part );
		$colour = self::colour( $role, $accent, $settings );
		$tag    = 'accent' === $role ? ' data-sm-sign-colour="%s"' : '';

		switch ( $part[0] ) {
			case 'rrect':
				return sprintf( '<rect x="%s" y="%s" width="%s" height="%s" rx="%s" fill="%s"' . sprintf( $tag, 'fill' ) . '/>', $part[1], $part[2], $part[3], $part[4], $part[5], esc_attr( $colour ) );

			case 'path':
				$d = '';
				foreach ( $part[1] as $command ) {
					$d .= array_shift( $command ) . implode( ' ', $command ) . ' ';
				}
				return sprintf( '<path d="%s" fill="%s"' . sprintf( $tag, 'fill' ) . '/>', esc_attr( trim( $d ) ), esc_attr( $colour ) );

			case 'waves':
				$d = '';
				foreach ( $part[1] as $wave ) {
					[ $x, $y, $chord, $radius ] = $wave;
					$d                         .= sprintf( 'M%s %s a%s %s 0 0 1 0 %s ', $x, $y, $radius, $radius, $chord );
				}
				return sprintf( '<path d="%s" fill="none" stroke="%s" stroke-width="2.2" stroke-linecap="round"/>', esc_attr( trim( $d ) ), esc_attr( $colour ) );
		}

		return '';
	}

	/**
	 * A colour role as hex.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  string               $role      dark, frame, card, accent or white.
	 * @param  string               $accent    Accent colour.
	 * @param  array<string,string> $settings  Clean settings (for the frame colour).
	 * @return string
	 */
	public static function colour( string $role, string $accent, array $settings = array() ): string {
		return match ( $role ) {
			'dark'   => self::DARK,
			'frame'  => $settings['border'] ?? self::DARK,
			'card'   => self::CARD,
			'accent' => $accent,
			default  => '#ffffff',
		};
	}
}
