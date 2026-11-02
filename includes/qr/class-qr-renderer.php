<?php
/**
 * QR code renderer.
 *
 * Draws a Qr_Encoder matrix as SVG (sharp at any size, for screens and
 * designers), PNG (for printers and anyone without vector software), or
 * straight into a Pdf_Document. Every output includes the standard quiet
 * zone of four light modules, which scanners need.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Qr;

use SatoriManifest\Pdf\Pdf_Document;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Qr_Renderer
 *
 * Matrix → SVG / PNG / PDF.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */
class Qr_Renderer {

	/**
	 * Light modules around the symbol (the ISO minimum).
	 *
	 * @since 3.3.0
	 * @var   int
	 */
	public const QUIET_ZONE = 4;

	/**
	 * Returns an SVG document for the matrix.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  array<int,array<int,bool>> $matrix  QR matrix.
	 * @param  string                     $dark    Hex colour of dark modules.
	 * @param  string                     $title   Accessible title.
	 * @return string
	 */
	public static function svg( array $matrix, string $dark = '#000000', string $title = '' ): string {
		$size = count( $matrix ) + 2 * self::QUIET_ZONE;
		$path = '';

		foreach ( self::runs( $matrix ) as $run ) {
			$path .= sprintf( 'M%d %dh%dv1h-%dz', $run[0] + self::QUIET_ZONE, $run[1] + self::QUIET_ZONE, $run[2], $run[2] );
		}

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" shape-rendering="crispEdges" role="img"%2$s><rect width="%1$d" height="%1$d" fill="#ffffff"/><path fill="%3$s" d="%4$s"/></svg>',
			$size,
			'' !== $title ? ' aria-label="' . esc_attr( $title ) . '"' : '',
			esc_attr( self::hex( $dark ) ),
			$path
		);
	}

	/**
	 * Returns PNG image data for the matrix.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  array<int,array<int,bool>> $matrix  QR matrix.
	 * @param  int                        $scale   Pixels per module.
	 * @param  string                     $dark    Hex colour of dark modules.
	 * @return string  PNG bytes ('' when GD is unavailable).
	 */
	public static function png( array $matrix, int $scale = 20, string $dark = '#000000' ): string {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return '';
		}

		$modules = count( $matrix ) + 2 * self::QUIET_ZONE;
		$image   = imagecreatetruecolor( $modules * $scale, $modules * $scale );
		$rgb     = sscanf( self::hex( $dark ), '#%02x%02x%02x' );
		$light   = (int) imagecolorallocate( $image, 255, 255, 255 );
		$ink     = (int) imagecolorallocate( $image, (int) $rgb[0], (int) $rgb[1], (int) $rgb[2] );

		imagefill( $image, 0, 0, $light );

		foreach ( self::runs( $matrix ) as $run ) {
			$x = ( $run[0] + self::QUIET_ZONE ) * $scale;
			$y = ( $run[1] + self::QUIET_ZONE ) * $scale;
			imagefilledrectangle( $image, $x, $y, $x + $run[2] * $scale - 1, $y + $scale - 1, $ink );
		}

		ob_start();
		imagepng( $image, null, 9 );

		return (string) ob_get_clean();
	}

	/**
	 * Draws the matrix into a PDF, including its quiet zone.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  Pdf_Document               $pdf     Document.
	 * @param  array<int,array<int,bool>> $matrix  QR matrix.
	 * @param  float                      $x       Left edge of the quiet zone.
	 * @param  float                      $y       Top edge of the quiet zone.
	 * @param  float                      $size    Total width (and height) in points.
	 * @param  array<int,float>           $rgb     Dark colour as 0–1 floats.
	 * @return void
	 */
	public static function pdf( Pdf_Document $pdf, array $matrix, float $x, float $y, float $size, array $rgb ): void {
		$module = $size / ( count( $matrix ) + 2 * self::QUIET_ZONE );

		$pdf->rect( $x, $y, $size, $size, array( 1, 1, 1 ) );

		foreach ( self::runs( $matrix ) as $run ) {
			$pdf->rect(
				$x + ( $run[0] + self::QUIET_ZONE ) * $module,
				$y + ( $run[1] + self::QUIET_ZONE ) * $module,
				$run[2] * $module,
				$module,
				$rgb
			);
		}
	}

	/**
	 * Groups dark modules into horizontal runs: array( column, row, length ).
	 *
	 * One rectangle per run keeps SVG, PNG and PDF output small.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  array<int,array<int,bool>> $matrix  QR matrix.
	 * @return array<int,array<int,int>>
	 */
	private static function runs( array $matrix ): array {
		$runs = array();

		foreach ( $matrix as $y => $row ) {
			$start = -1;

			foreach ( $row as $x => $dark ) {
				if ( $dark && $start < 0 ) {
					$start = $x;
				} elseif ( ! $dark && $start >= 0 ) {
					$runs[] = array( $start, $y, $x - $start );
					$start  = -1;
				}
			}

			if ( $start >= 0 ) {
				$runs[] = array( $start, $y, count( $row ) - $start );
			}
		}

		return $runs;
	}

	/**
	 * Returns a valid six-digit hex colour (black when invalid).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  string $hex  Colour.
	 * @return string
	 */
	private static function hex( string $hex ): string {
		$clean = sanitize_hex_color( $hex );

		if ( ! $clean ) {
			return '#000000';
		}

		return 4 === strlen( $clean ) ? '#' . $clean[1] . $clean[1] . $clean[2] . $clean[2] . $clean[3] . $clean[3] : $clean;
	}
}
