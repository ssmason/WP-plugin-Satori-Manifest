<?php
/**
 * Placeholder links on demo pages.
 *
 * Demo clients carry made-up links (their "website", booking page, social
 * accounts, phone and email) so the designs look complete. Visitors must
 * never land on a dead site, so on demo pages every link that leaves the
 * site (nav, header button, footer, page content) points at "#" instead.
 * Links within this site (other examples, the home page) are kept. Real
 * client pages are never touched.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.16.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Demo_Links
 *
 * Rewrites outbound links on demo pages to "#".
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.16.0
 */
final class Demo_Links {

	/**
	 * Registers the output filter.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.16.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'start' ), 1 );
	}

	/**
	 * Starts buffering the page output on demo pages.
	 *
	 * Hooked to 'template_redirect'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.16.0
	 * @return void
	 */
	public static function start(): void {
		$page_id = Client_Page::current_page_id();

		if ( ! $page_id || is_admin() || ! Example_Bar::is_demo_page( $page_id ) ) {
			return;
		}

		ob_start( array( self::class, 'rewrite' ) );
	}

	/**
	 * Points every outbound link in the HTML at "#".
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.16.0
	 * @param  string $html  Page HTML.
	 * @return string
	 */
	public static function rewrite( string $html ): string {
		$result = preg_replace_callback(
			'/<a\b[^>]*>/i',
			static function ( array $tag ): string {
				if ( ! preg_match( '/\shref=(["\'])(.*?)\1/i', $tag[0], $href ) || ! self::is_outbound( html_entity_decode( $href[2] ) ) ) {
					return $tag[0];
				}

				$out = str_replace( $href[0], ' href="#"', $tag[0] );

				return (string) preg_replace( '/\s(target|rel)=(["\']).*?\2/i', '', $out );
			},
			$html
		);

		return null === $result ? $html : $result;
	}

	/**
	 * Whether a link leaves this site.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.16.0
	 * @param  string $url  Link target.
	 * @return bool
	 */
	public static function is_outbound( string $url ): bool {
		$url = trim( $url );

		if ( '' === $url || '#' === $url[0] || ( '/' === $url[0] && ! str_starts_with( $url, '//' ) ) ) {
			return false;
		}

		$home = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$host = (string) wp_parse_url( str_starts_with( $url, '//' ) ? 'https:' . $url : $url, PHP_URL_HOST );

		return '' === $host || strtolower( $host ) !== strtolower( $home );
	}
}
