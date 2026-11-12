<?php
/**
 * First password.
 *
 * New clients start with a random password nobody knows (passwords are never
 * emailed). Until they choose their own, their Home screen asks them to, so a
 * client who was logged in straight after paying can come back later.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Clients;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class First_Password
 *
 * Tracks whether a client still has to choose a password.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */
class First_Password {

	/**
	 * User meta set while the client has not chosen a password yet.
	 *
	 * @since 3.8.0
	 * @var   string
	 */
	public const META = '_satori_manifest_needs_password';

	/**
	 * Registers the hooks that notice a new password.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'profile_update', array( self::class, 'on_profile_update' ), 10, 2 );
		add_action( 'after_password_reset', array( self::class, 'on_password_reset' ) );
	}

	/**
	 * Marks a new client as still needing a password.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  int $user_id  Client user ID.
	 * @return void
	 */
	public static function mark_needed( int $user_id ): void {
		update_user_meta( $user_id, self::META, '1' );
	}

	/**
	 * Whether the client still has to choose a password.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  int $user_id  User ID.
	 * @return bool
	 */
	public static function needed( int $user_id ): bool {
		return '1' === get_user_meta( $user_id, self::META, true );
	}

	/**
	 * Clears the flag when the password changes in Profile.
	 *
	 * Hooked to 'profile_update'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  int      $user_id   User ID.
	 * @param  \WP_User $old_user  User before the update.
	 * @return void
	 */
	public static function on_profile_update( int $user_id, \WP_User $old_user ): void {
		$user = get_userdata( $user_id );

		if ( $user && $user->user_pass !== $old_user->user_pass ) {
			delete_user_meta( $user_id, self::META );
		}
	}

	/**
	 * Clears the flag after a password reset (e.g. the welcome email link).
	 *
	 * Hooked to 'after_password_reset'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  \WP_User $user  User.
	 * @return void
	 */
	public static function on_password_reset( \WP_User $user ): void {
		delete_user_meta( $user->ID, self::META );
	}
}
