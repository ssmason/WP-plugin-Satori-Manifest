<?php
/**
 * The QR sign as a print-ready, vector A4 PDF.
 *
 * Large sign: one per page, about 16 × 19.5 cm. Small sign: four per page
 * (2 × 2), about 6.5 × 8 cm each, for table cards. Drawn from the same shape
 * as the SVG preview (Qr_Sign::shape()), so it prints exactly as previewed.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Qr;

use SatoriManifest\Branding\Brand;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Pdf\Font_Metrics;
use SatoriManifest\Pdf\Pdf_Document;
use SatoriManifest\Pdf\Print_Colour;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Qr_Sign_Pdf
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */
final class Qr_Sign_Pdf {

	/**
	 * Points per sign unit: large signs are printed bigger than small ones.
	 */
	private const SCALE = array(
		'large' => 2.4,
		'small' => 1.5,
	);

	/**
	 * Builds the PDF.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  int $page_id  Client page ID.
	 * @return string  PDF bytes, or '' when the page is not a client page.
	 */
	public static function render( int $page_id ): string {
		if ( ! Client_Repository::is_client_page( $page_id ) ) {
			return '';
		}

		$settings  = Qr_Sign::settings( $page_id );
		$size      = $settings['size'];
		$scale     = self::SCALE[ $size ];
		[ $w, $h ] = Qr_Sign::box( $settings );
		$pdf       = new Pdf_Document();
		$matrix    = Page_Qr::matrix( $page_id );

		$pdf->set_title( sprintf( '%s - QR sign', (string) Brand::for_page( $page_id )['name'] ) );
		$pdf->add_page();

		$gap     = 28.0;
		$columns = 'small' === $size ? 2 : 1;
		$rows    = 'small' === $size ? 2 : 1;
		$total_w = $columns * $w * $scale + ( $columns - 1 ) * $gap;
		$total_h = $rows * $h * $scale + ( $rows - 1 ) * $gap;
		$left    = ( $pdf->width() - $total_w ) / 2;
		$top     = ( $pdf->height() - $total_h ) / 2 - 10;

		for ( $row = 0; $row < $rows; $row++ ) {
			for ( $column = 0; $column < $columns; $column++ ) {
				self::draw(
					$pdf,
					$settings,
					$matrix,
					$left + $column * ( $w * $scale + $gap ),
					$top + $row * ( $h * $scale + $gap ),
					$scale
				);
			}
		}

		$note = Font_Metrics::encode( __( 'Print at actual size (100%) and cut along the outline. The code always opens your latest prices.', 'satori-manifest' ) );
		$pdf->text( ( $pdf->width() - Font_Metrics::width( $note, 8 ) ) / 2, $pdf->height() - 36, $note, 8, false, array( 0.42, 0.42, 0.42 ) );

		return $pdf->output();
	}

	/**
	 * File name, e.g. "paddys-point-qr-sign.pdf".
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  int $page_id  Client page ID.
	 * @return string
	 */
	public static function filename( int $page_id ): string {
		$slug = sanitize_title( (string) Brand::for_page( $page_id )['name'] );

		return ( '' !== $slug ? $slug : 'price-list' ) . '-qr-sign.pdf';
	}

	/**
	 * Draws one sign with its top-left corner at x, y.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  Pdf_Document               $pdf       Document.
	 * @param  array<string,string>       $settings  Sign settings.
	 * @param  array<int,array<int,bool>> $matrix    QR matrix.
	 * @param  float                      $x         Left.
	 * @param  float                      $y         Top.
	 * @param  float                      $s         Points per sign unit.
	 * @return void
	 */
	private static function draw( Pdf_Document $pdf, array $settings, array $matrix, float $x, float $y, float $s ): void {
		$shape  = Qr_Sign::shape( $settings['size'], '' !== $settings['footer'] );
		$accent = Qr_Sign::accent( $settings );
		$rgb    = static fn( string $role ): array => Print_Colour::from_hex( Qr_Sign::colour( $role, $accent, $settings ) );
		$at     = static fn( float $px, float $py ): array => array( $x + $px * $s, $y + $py * $s );

		foreach ( $shape['parts'] as $part ) {
			$role = (string) end( $part );

			if ( 'rrect' === $part[0] ) {
				[ $px, $py ] = $at( (float) $part[1], (float) $part[2] );
				$pdf->rounded_rect( $px, $py, $part[3] * $s, $part[4] * $s, $part[5] * $s, $rgb( $role ) );
			} elseif ( 'path' === $part[0] ) {
				$commands = array();
				foreach ( $part[1] as $command ) {
					$name   = array_shift( $command );
					$points = array();
					foreach ( array_chunk( $command, 2 ) as $point ) {
						$points = array_merge( $points, $at( (float) $point[0], (float) $point[1] ) );
					}
					$commands[] = array_merge( array( $name ), $points );
				}
				$pdf->path( $commands, $rgb( $role ) );
			} elseif ( 'waves' === $part[0] ) {
				foreach ( $part[1] as $wave ) {
					[ $wx, $wy, $chord, $radius ] = $wave;
					// An arc of the given radius bulging right, as one cubic curve.
					$bulge = ( $radius - sqrt( max( 0, $radius * $radius - ( $chord / 2 ) * ( $chord / 2 ) ) ) ) * 4 / 3;
					$pdf->path(
						array(
							array_merge( array( 'M' ), $at( $wx, $wy ) ),
							array_merge( array( 'C' ), $at( $wx + $bulge, $wy ), $at( $wx + $bulge, $wy + $chord ), $at( $wx, $wy + $chord ) ),
						),
						null,
						$rgb( $role ),
						2.2 * $s
					);
				}
			}
		}

		// Top text (small sign: last word in the accent colour), subtitle, code, label.
		[ $tx, $ty, $size, $width ] = $shape['title'];
		$title                      = $settings['title'];
		$font                       = Qr_Sign::fit( $title, (float) $size, (float) $width ) * $s;
		$words                      = Qr_Sign::words( $title );
		$head                       = 'small' === $settings['size'] && count( $words ) > 1 ? implode( ' ', array_slice( $words, 0, -1 ) ) . ' ' : $title;
		$tail                       = 'small' === $settings['size'] && count( $words ) > 1 ? (string) end( $words ) : '';
		$head_w                     = Font_Metrics::width( Font_Metrics::encode( $head ), $font, true );
		$tail_w                     = Font_Metrics::width( Font_Metrics::encode( $tail ), $font, true );
		[ $cx, $cy ]                = $at( (float) $tx, (float) $ty );
		$start                      = $cx - ( $head_w + $tail_w ) / 2;

		$pdf->text( $start, $cy, Font_Metrics::encode( $head ), $font, true, $rgb( 'dark' ) );

		if ( '' !== $tail ) {
			$pdf->text( $start + $head_w, $cy, Font_Metrics::encode( $tail ), $font, true, $rgb( 'accent' ) );
		}

		if ( null !== $shape['subtitle'] && '' !== $settings['subtitle'] ) {
			[ $tx, $ty, $size, $width ] = $shape['subtitle'];
			$font                       = Qr_Sign::fit( $settings['subtitle'], (float) $size, (float) $width ) * $s;
			$text                       = Font_Metrics::encode( $settings['subtitle'] );
			[ $cx, $cy ]                = $at( (float) $tx, (float) $ty );

			$pdf->text( $cx - Font_Metrics::width( $text, $font, true ) / 2, $cy, $text, $font, true, $rgb( 'accent' ) );
		}

		// Qr_Renderer::pdf() counts a 4-module quiet zone; no white box, the card is light.
		[ $qx, $qy, $qsize ] = $shape['qr'];
		$module              = $qsize * $s / max( 1, count( $matrix ) );
		[ $px, $py ]         = $at( (float) $qx, (float) $qy );
		Qr_Renderer::pdf( $pdf, $matrix, $px - 4 * $module, $py - 4 * $module, $qsize * $s + 8 * $module, Print_Colour::from_hex( Qr_Sign::INK ), false, false );
		Qr_Renderer::pdf( $pdf, $matrix, $px - 4 * $module, $py - 4 * $module, $qsize * $s + 8 * $module, Print_Colour::from_hex( $settings['corners'] ), false, true );

		[ $lx, $ly, $size ] = $shape['label'];
		$label              = Font_Metrics::encode( __( 'TAP or SCAN', 'satori-manifest' ) );
		[ $cx, $cy ]        = $at( (float) $lx, (float) $ly );
		$pdf->text( $cx - Font_Metrics::width( $label, $size * $s, true ) / 2, $cy, $label, $size * $s, true, $rgb( 'white' ) );

		// Line under the pill (e.g. the company name), in its own colour and font.
		if ( null !== $shape['footer'] ) {
			[ $fx, $fy, $size, $width ] = $shape['footer'];
			$family                     = $settings['footer_font'];
			$font                       = Qr_Sign::fit( $settings['footer'], (float) $size, (float) $width, $family ) * $s;
			$text                       = Font_Metrics::encode( $settings['footer'] );
			[ $cx, $cy ]                = $at( (float) $fx, (float) $fy );

			$pdf->text( $cx - Font_Metrics::family_width( $text, $font, $family ) / 2, $cy, $text, $font, true, Print_Colour::from_hex( $settings['footer_colour'] ), $family );
		}
	}
}
