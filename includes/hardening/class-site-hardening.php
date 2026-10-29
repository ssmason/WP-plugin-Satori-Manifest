<?php
/**
 * Site hardening.
 *
 * Closes the usual ways attackers learn about, or guess into, a WordPress
 * site — none of which this service needs:
 *
 *   - XML-RPC is switched off (a common channel for password guessing).
 *   - The REST users list is limited to people who can list users, so
 *     usernames (half of every login) are not published.
 *   - Login errors no longer say whether a username exists.
 *   - The WordPress version is not printed in page source or feeds.
 *
 * Each can be turned back on with the 'satori_manifest_hardening' filter.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.2.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Hardening;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Site_Hardening
 *
 * Small, independent hardening filters.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.2.0
 */
class Site_Hardening {

	/**
	 * Login error codes that would reveal whether an account exists.
	 *
	 * @since 3.2.0
	 * @var   array<int,string>
	 */
	private const REVEALING_ERRORS = array( 'invalid_username', 'invalid_email', 'incorrect_password' );

	/**
	 * Registers the enabled measures.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return void
	 */
	public static function register(): void {
		if ( self::enabled( 'xmlrpc' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'xmlrpc_methods', '__return_empty_array' );
			add_filter( 'wp_headers', array( self::class, 'remove_pingback_header' ) );
			add_action( 'init', array( self::class, 'refuse_xmlrpc_requests' ), 0 );
		}

		if ( self::enabled( 'rest_users' ) ) {
			add_filter( 'rest_endpoints', array( self::class, 'restrict_user_endpoints' ) );
		}

		if ( self::enabled( 'login_errors' ) ) {
			add_filter( 'wp_login_errors', array( self::class, 'generic_login_errors' ) );
		}

		if ( self::enabled( 'version' ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}
	}

	/**
	 * Returns true when a measure is enabled.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  string $measure  "xmlrpc", "rest_users", "login_errors" or "version".
	 * @return bool
	 */
	public static function enabled( string $measure ): bool {
		/**
		 * Filters whether a hardening measure is enabled.
		 *
		 * @since 3.2.0
		 * @param bool   $enabled  Default true.
		 * @param string $measure  Measure name.
		 */
		return (bool) apply_filters( 'satori_manifest_hardening', true, $measure );
	}

	/**
	 * Answers requests to xmlrpc.php with 403, so even its built-in
	 * system.* methods (used to batch password guesses) are unavailable.
	 *
	 * Hooked early to 'init'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return void
	 */
	public static function refuse_xmlrpc_requests(): void {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			status_header( 403 );
			exit;
		}
	}

	/**
	 * Drops the X-Pingback header (it points at XML-RPC). Hooked to 'wp_headers'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  array<string,string> $headers  Response headers.
	 * @return array<string,string>
	 */
	public static function remove_pingback_header( array $headers ): array {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * Requires the list_users capability for the REST user endpoints.
	 *
	 * Logged-in users can still read their own record (/users/me), which
	 * the block editor needs. Hooked to 'rest_endpoints'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  array<string,mixed> $endpoints  Registered routes.
	 * @return array<string,mixed>
	 */
	public static function restrict_user_endpoints( array $endpoints ): array {
		if ( current_user_can( 'list_users' ) ) {
			return $endpoints;
		}

		foreach ( array( '/wp/v2/users', '/wp/v2/users/(?P<id>[\d]+)' ) as $route ) {
			unset( $endpoints[ $route ] );
		}

		return $endpoints;
	}

	/**
	 * Replaces "unknown username" / "wrong password" with one generic message.
	 *
	 * Hooked to 'wp_login_errors'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  \WP_Error $errors  Login errors.
	 * @return \WP_Error
	 */
	public static function generic_login_errors( \WP_Error $errors ): \WP_Error {
		$codes = $errors->get_error_codes();

		if ( ! array_intersect( $codes, self::REVEALING_ERRORS ) ) {
			return $errors;
		}

		foreach ( self::REVEALING_ERRORS as $code ) {
			$errors->remove( $code );
		}

		$errors->add(
			'satori_manifest_login_failed',
			sprintf(
				'<strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a>',
				esc_html__( 'Error:', 'satori-manifest' ),
				esc_html__( 'The username or password is not right.', 'satori-manifest' ),
				esc_url( wp_lostpassword_url() ),
				esc_html__( 'Lost your password?', 'satori-manifest' )
			)
		);

		return $errors;
	}
}
