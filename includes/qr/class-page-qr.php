<?php
/**
 * A client page's QR code.
 *
 * The code opens the client's page with ?src=qr, so a scan can be told
 * apart from other visits (see Stats\Page_Views). It uses error-correction
 * level Q (25%), so a printed code still scans when scuffed or partly
 * covered. The page address never changes, so a printed code keeps working.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Qr;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Stats\Page_Views;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Page_Qr
 *
 * QR code address and matrix for a client page.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */
class Page_Qr {

	/**
	 * Error-correction level for printed codes.
	 *
	 * @since 3.3.0
	 * @var   string
	 */
	public const ECC = 'Q';

	/**
	 * Returns the address the QR code opens.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int $page_id  Client page ID.
	 * @return string  '' when the page is not a client page.
	 */
	public static function url( int $page_id ): string {
		if ( ! Client_Repository::is_client_page( $page_id ) ) {
			return '';
		}

		return add_query_arg( Page_Views::SOURCE_VAR, Page_Views::SOURCE_QR, (string) get_permalink( $page_id ) );
	}

	/**
	 * Returns the QR matrix for a client page, or an empty array.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int $page_id  Client page ID.
	 * @return array<int,array<int,bool>>
	 */
	public static function matrix( int $page_id ): array {
		$url = self::url( $page_id );

		return '' !== $url ? Qr_Encoder::encode( $url, self::ECC ) : array();
	}
}
