<?php
/**
 * Set-password link.
 *
 * Builds the one-time "choose your password" link sent in welcome emails.
 * Passwords are never emailed; the client always sets their own.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Clients;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Set_Password_Link
 *
 * Wraps WordPress password-reset keys.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Set_Password_Link {

	/**
	 * Returns a set-password URL for a user (the lost-password page on failure).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  int $user_id  User ID.
	 * @return string
	 */
	public static function for_user( int $user_id ): string {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof \WP_User ) {
			return wp_lostpassword_url();
		}

		$key = get_password_reset_key( $user );

		if ( is_wp_error( $key ) ) {
			return wp_lostpassword_url();
		}

		return network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( (string) $key ) . '&login=' . rawurlencode( $user->user_login ), 'login' );
	}
}
