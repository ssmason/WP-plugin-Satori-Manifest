<?php
/**
 * Public price-list PDF.
 *
 * Lets a client page's visitors download the price list as a PDF from
 * {page}/?sm_pdf=1 (linked from the page footer unless the client switches
 * it off under Branding → Footer). The PDF is cached for a few minutes so a
 * busy page does not rebuild it on every click.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Pdf;

use SatoriManifest\Branding\Brand;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Frontend\Client_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Public_Pdf
 *
 * Visitor-facing PDF download.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */
class Public_Pdf {

	/**
	 * Query var that requests the PDF.
	 *
	 * @since 3.3.0
	 * @var   string
	 */
	public const QUERY_VAR = 'sm_pdf';

	/**
	 * Seconds a built PDF is reused.
	 *
	 * @since 3.3.0
	 * @var   int
	 */
	private const CACHE_SECONDS = 10 * MINUTE_IN_SECONDS;

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'maybe_serve' ), 5 );
	}

	/**
	 * Returns the public PDF link for a client page.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int $page_id  Client page ID.
	 * @return string
	 */
	public static function url( int $page_id ): string {
		return add_query_arg( self::QUERY_VAR, '1', (string) get_permalink( $page_id ) );
	}

	/**
	 * Returns true when visitors may download a page's PDF.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int $page_id  Client page ID.
	 * @return bool
	 */
	public static function is_available( int $page_id ): bool {
		return $page_id > 0
			&& 'publish' === get_post_status( $page_id )
			&& Client_Repository::is_client_page( $page_id )
			&& ! empty( Brand::for_page( $page_id )['footer']['show_pdf'] );
	}

	/**
	 * Streams the PDF for ?sm_pdf=1 requests. Hooked early to 'template_redirect'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @return void
	 */
	public static function maybe_serve(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only download.
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}

		$page_id = Client_Page::current_page_id();

		if ( ! self::is_available( $page_id ) ) {
			return; // Fall through to the normal page.
		}

		$pdf = self::cached( $page_id );

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . Price_List_Pdf::filename( $page_id ) . '"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		header( 'Cache-Control: public, max-age=300' );
		header( 'X-Robots-Tag: noindex' );
		header( 'X-Content-Type-Options: nosniff' );

		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary PDF built from escaped, encoded text.
		exit;
	}

	/**
	 * Returns the PDF, rebuilt when the page or price list changes (or after a few minutes).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int $page_id  Client page ID.
	 * @return string
	 */
	private static function cached( int $page_id ): string {
		$manifest_id = Client_Repository::manifest_for_page( $page_id );
		$key         = 'sm_pdf_' . md5( $page_id . '|' . get_post_modified_time( 'U', true, $page_id ) . '|' . ( $manifest_id ? get_post_modified_time( 'U', true, $manifest_id ) : 0 ) );
		$pdf         = get_transient( $key );

		if ( ! is_string( $pdf ) || '' === $pdf ) {
			$pdf = Price_List_Pdf::render( $page_id );
			set_transient( $key, $pdf, self::CACHE_SECONDS );
		}

		return $pdf;
	}
}
