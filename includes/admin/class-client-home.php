<?php
/**
 * Client Home screen.
 *
 * The landing page after login: page status, the QR destination URL and
 * shortcuts to every task a client can perform.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client_Home
 *
 * Renders the client dashboard.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Client_Home {

	/**
	 * Redirects the core Dashboard to the Home screen for clients.
	 *
	 * Hooked to 'load-index.php'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function redirect_dashboard(): void {
		if ( Client_Role::is_client() ) {
			wp_safe_redirect( Admin_Lockdown::home_url() );
			exit;
		}
	}

	/**
	 * Returns the data the Home view needs for a client.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int $user_id  Client user ID.
	 * @return array<string,mixed>
	 */
	public static function summary( int $user_id ): array {
		$page_id     = Client_Repository::page_id( $user_id );
		$manifest_id = Client_Repository::manifest_id( $user_id );
		$menu_id     = Client_Repository::menu_id( $user_id );
		$sections    = $manifest_id ? Manifest_Repository::get_sections( $manifest_id ) : array();
		$item_count  = 0;

		foreach ( $sections as $section ) {
			$item_count += count( (array) ( $section['items'] ?? array() ) );
		}

		$design = $page_id ? Design_Registry::get( Client_Repository::design_for_page( $page_id ) ) : null;
		$brand  = $page_id ? Brand::for_page( $page_id ) : array();

		return array(
			'user'        => get_userdata( $user_id ),
			'page_id'     => $page_id,
			'page_status' => $page_id ? get_post_status( $page_id ) : '',
			'page_url'    => $page_id ? (string) get_permalink( $page_id ) : '',
			'manifest_id' => $manifest_id,
			'sections'    => count( $sections ),
			'items'       => $item_count,
			'menu_id'     => $menu_id,
			'menu_items'  => $menu_id ? count( (array) wp_get_nav_menu_items( $menu_id ) ) : 0,
			'design'      => $design,
			'brand'       => $brand,
			'modified'    => $page_id ? (string) get_the_modified_date( '', $page_id ) : '',
		);
	}

	/**
	 * Renders the Home screen.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function render(): void {
		$satori_manifest_summary = self::summary( Admin_Menu::current_client_id() );
		require SATORI_MANIFEST_PATH . 'admin/views/client-home.php';
	}
}
