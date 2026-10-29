<?php
/**
 * Embed code.
 *
 * Builds the HTML a client copies into their own website to show their price
 * list: an iframe of the page in embed mode, plus a tiny script that resizes
 * the frame to fit its content. The script only trusts height messages from
 * this site and only resizes the frame that sent them. Without the script
 * (some site builders strip scripts) the frame still works at a fixed height.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.1.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Clients;

use SatoriManifest\Branding\Brand;
use SatoriManifest\Frontend\Embed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Embed_Code
 *
 * Copy-and-paste embed snippet for a client page.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.1.0
 */
class Embed_Code {

	/**
	 * Frame height before the resize script takes over (pixels).
	 *
	 * @since 3.1.0
	 * @var   int
	 */
	private const FALLBACK_HEIGHT = 1200;

	/**
	 * Returns the embed snippet for a client page, or '' when there is none.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.1.0
	 * @param  int $page_id  Client page ID.
	 * @return string  HTML to copy (not for output into this site's pages).
	 */
	public static function for_page( int $page_id ): string {
		if ( ! $page_id || ! Client_Repository::is_client_page( $page_id ) ) {
			return '';
		}

		$brand = Brand::for_page( $page_id );
		/* translators: %s: business name. */
		$title = sprintf( __( '%s — prices', 'satori-manifest' ), (string) $brand['name'] );

		$iframe = sprintf(
			'<iframe src="%1$s" title="%2$s" width="100%%" height="%3$d" style="width:100%%;border:0;" loading="lazy" data-sm-embed></iframe>',
			esc_url( Embed::url( $page_id ) ),
			esc_attr( $title ),
			self::FALLBACK_HEIGHT
		);

		$script = sprintf(
			'<script>window.addEventListener("message",function(e){if(e.origin!==%1$s||!e.data||e.data.type!==%2$s)return;document.querySelectorAll("iframe[data-sm-embed]").forEach(function(f){if(f.contentWindow===e.source){f.style.height=Math.ceil(e.data.height)+"px";}});});</script>',
			wp_json_encode( self::site_origin(), JSON_UNESCAPED_SLASHES ),
			wp_json_encode( Embed::MESSAGE_TYPE, JSON_UNESCAPED_SLASHES )
		);

		return $iframe . "\n" . $script;
	}

	/**
	 * Returns this site's origin (scheme, host and port), e.g. "https://example.com".
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.1.0
	 * @return string
	 */
	public static function site_origin(): string {
		$parts = wp_parse_url( home_url( '/' ) );
		$port  = isset( $parts['port'] ) ? ':' . $parts['port'] : '';

		return ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '' ) . $port;
	}
}
