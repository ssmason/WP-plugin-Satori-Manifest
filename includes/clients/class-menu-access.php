<?php
/**
 * Client navigation menu rules.
 *
 * Server-side: clients see, open and save only their own navigation menu,
 * and can add custom links only. Staff users are never affected.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.7.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Clients;

use SatoriManifest\Admin\Portal\Screen_Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu_Access
 *
 * Keeps clients on their own navigation menu.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.7.0
 */
class Menu_Access {

	/**
	 * Registers the hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_get_nav_menus', array( self::class, 'filter_nav_menus' ) );
		add_action( 'load-nav-menus.php', array( self::class, 'guard_nav_menus_screen' ) );
		add_filter( 'nav_menu_meta_box_object', array( self::class, 'limit_menu_meta_boxes' ) );
	}

	/**
	 * Hides every nav menu except the client's own in the admin.
	 *
	 * Hooked to 'wp_get_nav_menus'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  array<int,\WP_Term> $menus  Menu terms.
	 * @return array<int,\WP_Term>
	 */
	public static function filter_nav_menus( array $menus ): array {
		if ( ! is_admin() || ! Client_Role::is_client() ) {
			return $menus;
		}

		$own = Client_Repository::menu_id( get_current_user_id() );

		return array_values(
			array_filter(
				$menus,
				static fn( $menu ): bool => $menu instanceof \WP_Term && (int) $menu->term_id === $own
			)
		);
	}

	/**
	 * Keeps clients on their own menu on the Menus screen.
	 *
	 * Redirects any request for another menu, the locations tab, menu
	 * creation or deletion back to the client's menu.
	 * Hooked to 'load-nav-menus.php'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function guard_nav_menus_screen(): void {
		if ( ! Client_Role::is_client() ) {
			return;
		}

		$user_id = get_current_user_id();
		$own     = Client_Repository::menu_id( $user_id );

		if ( ! $own ) {
			wp_safe_redirect( Screen_Guard::home_url() );
			exit;
		}

		// Read-only routing checks; the form itself is nonce-protected by core.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		$menu   = isset( $_REQUEST['menu'] ) ? absint( $_REQUEST['menu'] ) : $own;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// A missing or zero "menu" with action=edit/update would create a new menu.
		$forbidden_action = in_array( $action, array( 'delete', 'delete_menus', 'locations' ), true );

		if ( $menu !== $own || $forbidden_action ) {
			wp_safe_redirect( admin_url( 'nav-menus.php?action=edit&menu=' . $own ) );
			exit;
		}

		// Menu items being deleted must belong to the client's menu.
		if ( 'delete-menu-item' === $action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- core verifies the nonce for this action.
			$item_id = isset( $_REQUEST['menu-item'] ) ? absint( $_REQUEST['menu-item'] ) : 0;
			if ( $item_id && ! is_object_in_term( $item_id, 'nav_menu', $own ) ) {
				wp_safe_redirect( admin_url( 'nav-menus.php?action=edit&menu=' . $own ) );
				exit;
			}
		}

		update_user_option( $user_id, 'nav_menu_recently_edited', $own );
	}

	/**
	 * Limits the Menus screen's "add items" boxes to custom links for clients.
	 *
	 * Hooked to 'nav_menu_meta_box_object'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  mixed $data_object  Post type or taxonomy object.
	 * @return mixed  False to hide the box.
	 */
	public static function limit_menu_meta_boxes( $data_object ) {
		return Client_Role::is_client() ? false : $data_object;
	}
}
