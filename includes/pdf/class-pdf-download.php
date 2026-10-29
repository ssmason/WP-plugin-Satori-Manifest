<?php
/**
 * PDF download.
 *
 * Serves a client's price list as a PDF from a nonce-protected link
 * (admin-post.php). Clients can download their own page; staff any client
 * page they can edit.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.2.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Pdf;

use SatoriManifest\Clients\Client_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Pdf_Download
 *
 * Download links and the download handler.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.2.0
 */
class Pdf_Download {

	/**
	 * Action name for admin-post.php (also the nonce action).
	 *
	 * @since 3.2.0
	 * @var   string
	 */
	public const ACTION = 'satori_manifest_pdf';

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Returns the download link for a client page.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  int $page_id  Client page ID.
	 * @return string
	 */
	public static function url( int $page_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => self::ACTION,
					'page_id' => $page_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $page_id
		);
	}

	/**
	 * Streams the PDF. Hooked to admin_post_satori_manifest_pdf.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return void
	 */
	public static function handle(): void {
		$page_id = isset( $_GET['page_id'] ) ? absint( $_GET['page_id'] ) : 0;
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::ACTION . '_' . $page_id ) || ! Client_Repository::is_client_page( $page_id ) || ! current_user_can( 'edit_post', $page_id ) ) {
			wp_die( esc_html__( 'Sorry, you cannot download this price list.', 'satori-manifest' ), 403 );
		}

		$pdf = Price_List_Pdf::render( $page_id );

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . Price_List_Pdf::filename( $page_id ) . '"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		header( 'X-Content-Type-Options: nosniff' );

		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary PDF built from escaped, encoded text.
		exit;
	}
}
