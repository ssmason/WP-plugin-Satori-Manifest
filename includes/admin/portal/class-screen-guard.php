<?php
/**
 * Client screen guard.
 *
 * Keeps clients on the screens they are allowed to use: their plugin screens,
 * their own page and price list, media, profile and menus. Everything else
 * redirects to their Home screen.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Admin\Portal;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Manifest\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Screen_Guard
 *
 * Server-side allow-list for client admin screens.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Screen_Guard {

	/**
	 * Admin screen bases clients may open (plugin screens are added separately).
	 *
	 * @since 2.0.0
	 * @var   array<int,string>
	 */
	private const ALLOWED_BASES = array( 'post', 'upload', 'media', 'profile', 'nav-menus', 'async-upload' );

	/**
	 * Registers hooks. Each callback checks the user is a client.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'current_screen', array( self::class, 'guard_screen' ) );
		add_filter( 'login_redirect', array( self::class, 'login_redirect' ), 10, 3 );
	}

	/**
	 * Returns the client Home screen URL.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return string
	 */
	public static function home_url(): string {
		return admin_url( 'admin.php?page=' . Admin_Menu::HOME_SLUG );
	}

	/**
	 * Redirects clients away from any screen not on the allow-list.
	 *
	 * Post edit screens are allowed only for the client's own page and
	 * manifest; core capability checks also enforce this.
	 * Hooked to 'current_screen'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  \WP_Screen $screen  Current screen.
	 * @return void
	 */
	public static function guard_screen( \WP_Screen $screen ): void {
		if ( ! Client_Role::is_client() ) {
			return;
		}

		if ( in_array( $screen->id, Admin_Menu::client_hooks(), true ) ) {
			return;
		}

		if ( in_array( $screen->base, self::ALLOWED_BASES, true ) ) {
			if ( 'post' !== $screen->base || self::is_own_post_screen( $screen ) ) {
				return;
			}
		}

		wp_safe_redirect( self::home_url() );
		exit;
	}

	/**
	 * Sends clients to their Home screen after logging in.
	 *
	 * Hooked to 'login_redirect'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string             $redirect_to  Requested redirect.
	 * @param  string             $requested    Original requested redirect.
	 * @param  \WP_User|\WP_Error $user         Logged-in user or error.
	 * @return string
	 */
	public static function login_redirect( string $redirect_to, string $requested, $user ): string {
		if ( $user instanceof \WP_User && Client_Role::is_client( $user->ID ) ) {
			return self::home_url();
		}

		return $redirect_to;
	}

	/**
	 * Returns true when a post screen is for the client's own page or manifest.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  \WP_Screen $screen  Current screen.
	 * @return bool
	 */
	private static function is_own_post_screen( \WP_Screen $screen ): bool {
		// phpcs:disable WordPress.Security.NonceVerification
		$post_id = 0;
		if ( isset( $_GET['post'] ) ) {
			$post_id = absint( $_GET['post'] );
		} elseif ( isset( $_POST['post_ID'] ) ) {
			$post_id = absint( $_POST['post_ID'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification

		if ( ! $post_id || ! in_array( $screen->post_type, array( 'page', Post_Types::CPT_MANIFEST ), true ) ) {
			return false;
		}

		$user_id = get_current_user_id();

		return in_array( $post_id, array( Client_Repository::page_id( $user_id ), Client_Repository::manifest_id( $user_id ) ), true );
	}
}
