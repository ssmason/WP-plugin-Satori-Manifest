<?php
/**
 * Client data access.
 *
 * Single place that knows how a client is linked to their page, manifest
 * and navigation menu. The link is stored in both directions: user meta on
 * the client, and a client ID on the page/manifest/menu, so lookups work
 * from either side without scanning.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Clients;

use SatoriManifest\Designs\Design_Registry;
use SatoriManifest\Manifest\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client_Repository
 *
 * Lookups between clients and their page, manifest and menu.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Client_Repository {

	/**
	 * User meta: the client's page ID.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	public const USER_PAGE = 'satori_manifest_page_id';

	/**
	 * User meta: the client's manifest ID.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	public const USER_MANIFEST = 'satori_manifest_manifest_id';

	/**
	 * User meta: the client's nav menu term ID.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	public const USER_MENU = 'satori_manifest_menu_id';

	/**
	 * Post/term meta: owning client user ID.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	public const OWNER = '_satori_manifest_client_id';

	/**
	 * Page meta: selected design slug.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	public const PAGE_DESIGN = '_satori_manifest_design';

	/**
	 * Returns the client's page ID, or 0.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int $user_id  Client user ID.
	 * @return int
	 */
	public static function page_id( int $user_id ): int {
		$page_id = (int) get_user_meta( $user_id, self::USER_PAGE, true );
		$page    = $page_id ? get_post( $page_id ) : null;

		return ( $page instanceof \WP_Post && 'page' === $page->post_type && 'trash' !== $page->post_status ) ? $page_id : 0;
	}

	/**
	 * Returns the client's manifest ID, or 0.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int $user_id  Client user ID.
	 * @return int
	 */
	public static function manifest_id( int $user_id ): int {
		$manifest_id = (int) get_user_meta( $user_id, self::USER_MANIFEST, true );
		$manifest    = $manifest_id ? get_post( $manifest_id ) : null;

		return ( $manifest instanceof \WP_Post && Post_Types::CPT_MANIFEST === $manifest->post_type && 'trash' !== $manifest->post_status ) ? $manifest_id : 0;
	}

	/**
	 * Returns the client's nav menu term ID, or 0.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int $user_id  Client user ID.
	 * @return int
	 */
	public static function menu_id( int $user_id ): int {
		$menu_id = (int) get_user_meta( $user_id, self::USER_MENU, true );

		return ( $menu_id && wp_get_nav_menu_object( $menu_id ) ) ? $menu_id : 0;
	}

	/**
	 * Returns the client user ID that owns a page, or 0 when it is not a client page.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int $page_id  Page ID.
	 * @return int
	 */
	public static function client_for_page( int $page_id ): int {
		if ( $page_id <= 0 || 'page' !== get_post_type( $page_id ) ) {
			return 0;
		}

		return (int) get_post_meta( $page_id, self::OWNER, true );
	}

	/**
	 * Returns true when the page is a client price-list page.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int $page_id  Page ID.
	 * @return bool
	 */
	public static function is_client_page( int $page_id ): bool {
		return self::client_for_page( $page_id ) > 0;
	}

	/**
	 * Returns the manifest ID linked to a client page, or 0.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int $page_id  Page ID.
	 * @return int
	 */
	public static function manifest_for_page( int $page_id ): int {
		$client_id = self::client_for_page( $page_id );
		return $client_id ? self::manifest_id( $client_id ) : 0;
	}

	/**
	 * Returns the design slug selected for a page (validated against the registry).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int $page_id  Page ID.
	 * @return string
	 */
	public static function design_for_page( int $page_id ): string {
		$slug = (string) get_post_meta( $page_id, self::PAGE_DESIGN, true );
		return Design_Registry::exists( $slug ) ? $slug : Design_Registry::default_slug();
	}

	/**
	 * Returns all client users, ordered by display name.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return \WP_User[]
	 */
	public static function all_clients(): array {
		return get_users(
			array(
				'role'    => Client_Role::ROLE,
				'orderby' => 'display_name',
				'order'   => 'ASC',
			)
		);
	}
}
