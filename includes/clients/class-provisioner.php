<?php
/**
 * Client provisioning.
 *
 * When a user is given the client role, creates everything they need:
 * one page (using the client page template and a starter design), one
 * manifest, and one navigation menu. Provisioning is idempotent — anything
 * that already exists is kept — so it is safe to re-run.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Clients;

use SatoriManifest\Branding\Brand;
use SatoriManifest\Core\Sanitizer;
use SatoriManifest\Designs\Design_Registry;
use SatoriManifest\Frontend\Client_Page;
use SatoriManifest\Manifest\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Provisioner
 *
 * Creates and removes a client's page, manifest and menu.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Provisioner {

	/**
	 * Provisions on user creation when the user is a client.
	 *
	 * Hooked to 'user_register'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int $user_id  New user ID.
	 * @return void
	 */
	public static function on_user_register( int $user_id ): void {
		if ( Client_Role::is_client( $user_id ) ) {
			self::provision( $user_id );
		}
	}

	/**
	 * Provisions when an existing user is switched to the client role.
	 *
	 * Hooked to 'set_user_role'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int    $user_id  User ID.
	 * @param  string $role     New role.
	 * @return void
	 */
	public static function on_set_role( int $user_id, string $role ): void {
		if ( Client_Role::ROLE === $role ) {
			self::provision( $user_id );
		}
	}

	/**
	 * Creates the client's page, manifest and menu (keeping any that exist).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int                 $user_id  Client user ID.
	 * @param  array<string,mixed> $args     {
	 *     Optional overrides.
	 *
	 *     @type string $name      Business name. Defaults to the user's display name.
	 *     @type string $tagline   Tagline.
	 *     @type string $design    Design slug. Defaults to the first design.
	 *     @type array  $sections  Manifest sections. Defaults to a starter section.
	 * }
	 * @return array{page_id:int,manifest_id:int,menu_id:int}
	 */
	public static function provision( int $user_id, array $args = array() ): array {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof \WP_User ) {
			return array(
				'page_id'     => 0,
				'manifest_id' => 0,
				'menu_id'     => 0,
			);
		}

		// WordPress stores display names HTML-encoded ("A &amp; B"); titles need plain text.
		$name    = html_entity_decode( (string) ( $args['name'] ?? $user->display_name ), ENT_QUOTES, 'UTF-8' );
		$name    = '' !== trim( $name ) ? $name : $user->user_login;
		$tagline = (string) ( $args['tagline'] ?? '' );
		$design  = (string) ( $args['design'] ?? '' );
		$design  = Design_Registry::exists( $design ) ? $design : Design_Registry::default_slug();

		$manifest_id = Client_Repository::manifest_id( $user_id );
		if ( ! $manifest_id ) {
			$manifest_id = self::create_manifest( $user_id, $name, $args['sections'] ?? null );
		}

		$page_id = Client_Repository::page_id( $user_id );
		if ( ! $page_id ) {
			$page_id = self::create_page( $user_id, $name, $tagline, $design );
		}

		$menu_id = Client_Repository::menu_id( $user_id );
		if ( ! $menu_id ) {
			$menu_id = self::create_menu( $user_id, $name );
		}

		/**
		 * Fires after a client has been provisioned.
		 *
		 * @since 2.0.0
		 * @param int $user_id      Client user ID.
		 * @param int $page_id      Client page ID.
		 * @param int $manifest_id  Client manifest ID.
		 * @param int $menu_id      Client nav menu term ID.
		 */
		do_action( 'satori_manifest_client_provisioned', $user_id, $page_id, $manifest_id, $menu_id );

		return array(
			'page_id'     => $page_id,
			'manifest_id' => $manifest_id,
			'menu_id'     => $menu_id,
		);
	}

	/**
	 * Removes the client's menu when the user is deleted.
	 *
	 * The page and manifest are posts authored by the client, so WordPress's
	 * own "delete or reassign content" choice on user deletion handles them.
	 * Hooked to 'delete_user' (before the user meta is removed).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int $user_id  User being deleted.
	 * @return void
	 */
	public static function on_delete_user( int $user_id ): void {
		$menu_id = Client_Repository::menu_id( $user_id );

		if ( $menu_id ) {
			wp_delete_nav_menu( $menu_id );
		}
	}

	/**
	 * Creates the client's manifest post.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int        $user_id   Client user ID.
	 * @param  string     $name      Business name.
	 * @param  array|null $sections  Sections, or null for the starter section.
	 * @return int  Manifest ID, 0 on failure.
	 */
	private static function create_manifest( int $user_id, string $name, ?array $sections ): int {
		if ( null === $sections ) {
			$sections = array(
				array(
					'title'       => __( 'Your first section', 'satori-manifest' ),
					'description' => __( 'Group related services or products together.', 'satori-manifest' ),
					'items'       => array(
						array(
							'label'        => __( 'Example item', 'satori-manifest' ),
							'description'  => __( 'Replace with your own service or product.', 'satori-manifest' ),
							'price_prefix' => __( 'from', 'satori-manifest' ),
							'price'        => '25',
						),
					),
				),
			);
		}

		$manifest_id = wp_insert_post(
			array(
				'post_type'   => Post_Types::CPT_MANIFEST,
				'post_status' => 'publish',
				'post_author' => $user_id,
				/* translators: %s: business name. */
				'post_title'  => sprintf( __( '%s price list', 'satori-manifest' ), $name ),
			),
			true
		);

		if ( is_wp_error( $manifest_id ) ) {
			return 0;
		}

		update_post_meta( $manifest_id, Post_Types::META_SECTIONS, wp_slash( (string) wp_json_encode( Sanitizer::sanitize_sections( $sections ) ) ) );
		update_post_meta( $manifest_id, Client_Repository::OWNER, $user_id );
		update_user_meta( $user_id, Client_Repository::USER_MANIFEST, $manifest_id );

		return (int) $manifest_id;
	}

	/**
	 * Creates the client's page with the design's starter content.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int    $user_id  Client user ID.
	 * @param  string $name     Business name.
	 * @param  string $tagline  Tagline.
	 * @param  string $design   Design slug.
	 * @return int  Page ID, 0 on failure.
	 */
	private static function create_page( int $user_id, string $name, string $tagline, string $design ): int {
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_author'  => $user_id,
				'post_title'   => $name,
				'post_name'    => sanitize_title( $name ),
				'post_content' => wp_slash(
					Design_Registry::content(
						$design,
						array_filter(
							array(
								'brand_name' => $name,
								'tagline'    => $tagline,
							)
						)
					)
				),
				'meta_input'   => array(
					'_wp_page_template'            => Client_Page::TEMPLATE,
					Client_Repository::OWNER       => $user_id,
					Client_Repository::PAGE_DESIGN => $design,
				),
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			return 0;
		}

		Brand::set( (int) $page_id, 'sm_brand_name', $name );
		if ( '' !== $tagline ) {
			Brand::set( (int) $page_id, 'sm_brand_tagline', $tagline );
		}

		update_user_meta( $user_id, Client_Repository::USER_PAGE, $page_id );

		return (int) $page_id;
	}

	/**
	 * Creates the client's (initially empty) navigation menu.
	 *
	 * Menu names must be unique, so the user ID is appended.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int    $user_id  Client user ID.
	 * @param  string $name     Business name.
	 * @return int  Menu term ID, 0 on failure.
	 */
	private static function create_menu( int $user_id, string $name ): int {
		/* translators: 1: business name, 2: user ID. */
		$menu_id = wp_create_nav_menu( sprintf( __( '%1$s navigation (#%2$d)', 'satori-manifest' ), $name, $user_id ) );

		if ( is_wp_error( $menu_id ) ) {
			return 0;
		}

		update_term_meta( $menu_id, Client_Repository::OWNER, $user_id );
		update_user_meta( $user_id, Client_Repository::USER_MENU, $menu_id );

		return (int) $menu_id;
	}
}
