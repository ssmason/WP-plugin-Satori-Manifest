<?php
/**
 * Client summary.
 *
 * Read model describing one client at a glance — used by the client Home
 * screen, the staff Clients list and WP-CLI.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Clients;

use SatoriManifest\Billing\Subscriptions;
use SatoriManifest\Branding\Brand;
use SatoriManifest\Designs\Design_Registry;
use SatoriManifest\Manifest\Manifest_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client_Summary
 *
 * Collects a client's page, price list, menu, design, brand and billing state.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Client_Summary {

	/**
	 * Returns the summary for a client.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  int $user_id  Client user ID.
	 * @return array<string,mixed>
	 */
	public static function for_user( int $user_id ): array {
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
			'user'         => get_userdata( $user_id ),
			'page_id'      => $page_id,
			'page_status'  => $page_id ? get_post_status( $page_id ) : '',
			'page_url'     => $page_id ? (string) get_permalink( $page_id ) : '',
			'manifest_id'  => $manifest_id,
			'sections'     => count( $sections ),
			'items'        => $item_count,
			'menu_id'      => $menu_id,
			'menu_items'   => $menu_id ? count( (array) wp_get_nav_menu_items( $menu_id ) ) : 0,
			'design'       => $design,
			'brand'        => $brand,
			'modified'     => $page_id ? (string) get_the_modified_date( '', $page_id ) : '',
			'subscription' => Subscriptions::for_user( $user_id ),
		);
	}
}
