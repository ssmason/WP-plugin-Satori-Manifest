<?php
/**
 * Portal brand.
 *
 * The look of the service itself (not a client's page): the logo and colours
 * used on the login screen, the client dashboard and every email. Taken from
 * Manifest → Settings, falling back to the site logo and site icon, so it
 * works with any theme.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Branding;

use SatoriManifest\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Portal_Brand
 *
 * Logo, name and colours of the client portal.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Portal_Brand {

	/**
	 * Returns the portal name (the site title).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return string
	 */
	public static function name(): string {
		return wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * Returns the logo URL: the portal logo setting, else the site logo, else the site icon.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $size  Image size.
	 * @return string  URL, or '' when no logo is available.
	 */
	public static function logo_url( string $size = 'medium' ): string {
		$candidates = array(
			(int) Settings::get( 'portal_logo_id' ),
			(int) get_theme_mod( 'custom_logo' ),
		);

		foreach ( $candidates as $attachment_id ) {
			$url = $attachment_id ? wp_get_attachment_image_url( $attachment_id, $size ) : false;

			if ( $url ) {
				return (string) $url;
			}
		}

		return (string) get_site_icon_url( 256 );
	}

	/**
	 * Returns the accent colour (buttons, highlights).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return string  Hex colour.
	 */
	public static function accent(): string {
		return (string) Settings::get( 'accent_color' );
	}

	/**
	 * Returns the dark brand colour (headings, backgrounds).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return string  Hex colour.
	 */
	public static function dark(): string {
		return (string) Settings::get( 'dark_color' );
	}

	/**
	 * Returns a readable text colour (dark or white) for text on a background.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $background  Hex colour.
	 * @return string  Hex colour.
	 */
	public static function text_on( string $background ): string {
		$hex = ltrim( $background, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 6 !== strlen( $hex ) ) {
			return '#ffffff';
		}

		$luminance = ( 0.299 * hexdec( substr( $hex, 0, 2 ) ) + 0.587 * hexdec( substr( $hex, 2, 2 ) ) + 0.114 * hexdec( substr( $hex, 4, 2 ) ) ) / 255;

		return $luminance > 0.6 ? self::dark() : '#ffffff';
	}

	/**
	 * Returns the support email address shown to clients.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return string
	 */
	public static function support_email(): string {
		$email = (string) Settings::get( 'support_email' );
		return is_email( $email ) ? $email : (string) get_option( 'admin_email' );
	}

	/**
	 * Returns the CSS custom properties for the portal palette.
	 *
	 * Printed on the login screen and client admin screens; the stylesheets
	 * read --sm-portal-* so a settings change needs no rebuild.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return string  A ":root { … }" rule.
	 */
	public static function css_variables(): string {
		$accent = self::accent();

		return sprintf(
			':root{--sm-portal-accent:%1$s;--sm-portal-accent-text:%2$s;--sm-portal-dark:%3$s;--sm-portal-logo:url("%4$s");}',
			esc_attr( $accent ),
			esc_attr( self::text_on( $accent ) ),
			esc_attr( self::dark() ),
			esc_url( self::logo_url() )
		);
	}
}
