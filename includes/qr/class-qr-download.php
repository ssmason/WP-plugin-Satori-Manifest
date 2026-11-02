<?php
/**
 * QR code download.
 *
 * Serves a client page's QR code as PNG (print-ready, about 1000 px) or
 * SVG (scales to any size) from a nonce-protected link. Clients can
 * download their own; staff any client page they can edit.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Qr;

use SatoriManifest\Branding\Brand;
use SatoriManifest\Clients\Client_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Qr_Download
 *
 * Download links and handler.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */
class Qr_Download {

	/**
	 * Action name for admin-post.php (also the nonce action prefix).
	 *
	 * @since 3.3.0
	 * @var   string
	 */
	public const ACTION = 'satori_manifest_qr';

	/**
	 * Pixels per module in the PNG.
	 *
	 * @since 3.3.0
	 * @var   int
	 */
	private const PNG_SCALE = 24;

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Returns the download link for a page's QR code.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int    $page_id  Client page ID.
	 * @param  string $format   "png" or "svg".
	 * @return string
	 */
	public static function url( int $page_id, string $format = 'png' ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => self::ACTION,
					'page_id' => $page_id,
					'format'  => 'svg' === $format ? 'svg' : 'png',
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $page_id
		);
	}

	/**
	 * Streams the QR code. Hooked to admin_post_satori_manifest_qr.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @return void
	 */
	public static function handle(): void {
		$page_id = isset( $_GET['page_id'] ) ? absint( $_GET['page_id'] ) : 0;
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		$format  = isset( $_GET['format'] ) && 'svg' === sanitize_key( wp_unslash( $_GET['format'] ) ) ? 'svg' : 'png';

		if ( ! wp_verify_nonce( $nonce, self::ACTION . '_' . $page_id ) || ! Client_Repository::is_client_page( $page_id ) || ! current_user_can( 'edit_post', $page_id ) ) {
			wp_die( esc_html__( 'Sorry, you cannot download this QR code.', 'satori-manifest' ), 403 );
		}

		$matrix = Page_Qr::matrix( $page_id );
		$name   = sanitize_title( (string) Brand::for_page( $page_id )['name'] );
		$body   = 'svg' === $format
			? Qr_Renderer::svg( $matrix, '#000000', __( 'QR code for the price list', 'satori-manifest' ) )
			: Qr_Renderer::png( $matrix, self::PNG_SCALE );

		nocache_headers();
		header( 'Content-Type: ' . ( 'svg' === $format ? 'image/svg+xml' : 'image/png' ) );
		header( 'Content-Disposition: attachment; filename="' . ( '' !== $name ? $name : 'price-list' ) . '-qr-code.' . $format . '"' );
		header( 'Content-Length: ' . strlen( $body ) );
		header( 'X-Content-Type-Options: nosniff' );

		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated image; SVG attributes escaped in Qr_Renderer.
		exit;
	}
}
