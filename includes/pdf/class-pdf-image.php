<?php
/**
 * PDF images.
 *
 * Turns a media library image (PNG, JPEG, WebP or GIF) into a JPEG the PDF
 * writer can embed: resized to a sensible print size and flattened, because
 * JPEG has no transparency. A mostly-white logo (made for dark backgrounds)
 * is flattened onto a dark tile so it stays visible on white paper.
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
 * Class Pdf_Image
 *
 * Attachment → embeddable JPEG.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.2.0
 */
class Pdf_Image {

	/**
	 * Largest pixel width kept (about 3× the printed logo size).
	 *
	 * @since 3.2.0
	 * @var   int
	 */
	private const MAX_PIXELS = 600;

	/**
	 * Loads an attachment as a JPEG for the PDF.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  int $attachment_id  Image attachment ID.
	 * @return array{jpeg:string,pixels_w:int,pixels_h:int,width:float,height:float}|null
	 *         Null when there is no usable image (or GD is unavailable).
	 */
	public static function from_attachment( int $attachment_id ): ?array {
		$file = $attachment_id ? get_attached_file( $attachment_id ) : false;

		if ( ! $file || ! is_readable( $file ) || ! function_exists( 'imagecreatefromstring' ) ) {
			return null;
		}

		$source = imagecreatefromstring( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local media file.

		if ( ! $source ) {
			return null;
		}

		$src_w = imagesx( $source );
		$src_h = imagesy( $source );
		$scale = min( 1.0, self::MAX_PIXELS / max( 1, $src_w ) );
		$w     = max( 1, (int) round( $src_w * $scale ) );
		$h     = max( 1, (int) round( $src_h * $scale ) );

		$background = self::is_light( $source ) ? array( 28, 28, 28 ) : array( 255, 255, 255 );
		$canvas     = imagecreatetruecolor( $w, $h );

		imagefill( $canvas, 0, 0, (int) imagecolorallocate( $canvas, $background[0], $background[1], $background[2] ) );
		imagecopyresampled( $canvas, $source, 0, 0, 0, 0, $w, $h, $src_w, $src_h );

		ob_start();
		imagejpeg( $canvas, null, 90 );
		$jpeg = (string) ob_get_clean();

		return array(
			'jpeg'     => $jpeg,
			'pixels_w' => $w,
			'pixels_h' => $h,
			// Print size in points at 216 dpi-ish: 1 pixel = 1/3 point, so detail stays sharp.
			'width'    => $w / 3,
			'height'   => $h / 3,
		);
	}

	/**
	 * Returns true when the visible pixels of an image are mostly near-white.
	 *
	 * Samples a grid of pixels; transparent ones are ignored.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  \GdImage $image  Image.
	 * @return bool
	 */
	private static function is_light( \GdImage $image ): bool {
		$w       = imagesx( $image );
		$h       = imagesy( $image );
		$step    = max( 1, (int) ( min( $w, $h ) / 40 ) );
		$visible = 0;
		$light   = 0;

		$rows = (int) ceil( $h / $step );
		$cols = (int) ceil( $w / $step );

		for ( $row = 0; $row < $rows; $row++ ) {
			for ( $col = 0; $col < $cols; $col++ ) {
				$colour = imagecolorsforindex( $image, imagecolorat( $image, $col * $step, $row * $step ) );

				if ( $colour['alpha'] > 100 ) {
					continue;
				}

				++$visible;
				$light += ( 0.299 * $colour['red'] + 0.587 * $colour['green'] + 0.114 * $colour['blue'] ) > 225 ? 1 : 0;
			}
		}

		// Opaque images (e.g. a photo or a logo on its own white background) are left as they are.
		$opaque = $visible > 0.95 * $rows * $cols;

		return $visible > 0 && ! $opaque && $light / $visible > 0.8;
	}
}
