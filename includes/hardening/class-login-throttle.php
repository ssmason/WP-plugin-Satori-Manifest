<?php
/**
 * Login throttle.
 *
 * Slows down password guessing on the client portal: after too many failed
 * logins from one IP address, further attempts from it are refused for a
 * while. A successful login clears the count. Counters are transients, so
 * nothing is written to the users table.
 *
 * Behind a proxy or CDN, filter 'satori_manifest_client_ip' to return the
 * visitor's real address.
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
 * Class Login_Throttle
 *
 * Failed-login counting and lockout.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.2.0
 */
class Login_Throttle {

	/**
	 * Failed attempts allowed before locking out.
	 *
	 * @since 3.2.0
	 * @var   int
	 */
	public const MAX_ATTEMPTS = 5;

	/**
	 * Lockout (and counting window) length in seconds.
	 *
	 * @since 3.2.0
	 * @var   int
	 */
	public const LOCKOUT = 15 * MINUTE_IN_SECONDS;

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'authenticate', array( self::class, 'block_when_locked' ), 30 );
		add_action( 'wp_login_failed', array( self::class, 'record_failure' ) );
		add_action( 'wp_login', array( self::class, 'clear' ) );
	}

	/**
	 * Refuses the login attempt while the IP is locked out.
	 *
	 * Runs after WordPress has checked the password (priority 30), so a locked
	 * out visitor gets the same answer whether or not the password was right.
	 * Hooked to 'authenticate'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  \WP_User|\WP_Error|null $user  Result so far.
	 * @return \WP_User|\WP_Error|null
	 */
	public static function block_when_locked( $user ) {
		if ( self::attempts() < self::MAX_ATTEMPTS ) {
			return $user;
		}

		return new \WP_Error(
			'satori_manifest_locked',
			sprintf(
				/* translators: %d: minutes. */
				__( 'Too many failed login attempts. Please wait %d minutes and try again, or reset your password.', 'satori-manifest' ),
				(int) ( self::LOCKOUT / MINUTE_IN_SECONDS )
			)
		);
	}

	/**
	 * Counts a failed login. Hooked to 'wp_login_failed'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return void
	 */
	public static function record_failure(): void {
		set_transient( self::key(), self::attempts() + 1, self::LOCKOUT );
	}

	/**
	 * Clears the count after a successful login. Hooked to 'wp_login'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return void
	 */
	public static function clear(): void {
		delete_transient( self::key() );
	}

	/**
	 * Returns the failed attempts recorded for the current IP.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return int
	 */
	public static function attempts(): int {
		return (int) get_transient( self::key() );
	}

	/**
	 * Returns the transient key for the current IP (hashed — no IPs are stored).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return string
	 */
	private static function key(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Filters the visitor IP used for login throttling (e.g. behind a proxy).
		 *
		 * @since 3.2.0
		 * @param string $ip  REMOTE_ADDR.
		 */
		$ip = (string) apply_filters( 'satori_manifest_client_ip', $ip );

		return 'sm_login_' . md5( $ip . wp_salt( 'auth' ) );
	}
}
