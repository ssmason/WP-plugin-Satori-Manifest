<?php
/**
 * Demo seeder.
 *
 * Creates (and removes) the demo clients defined in config/demo/*.yaml —
 * one per design, some with navigation and some standalone. Used by
 * `wp satori-manifest demo`.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Clients;

use SatoriManifest\Branding\Brand;
use SatoriManifest\Branding\Page_Title_Sync;
use SatoriManifest\Core\Config;
use SatoriManifest\Core\Sanitizer;
use SatoriManifest\Designs\Design_Applier;
use SatoriManifest\Designs\Design_Registry;
use SatoriManifest\Manifest\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Demo_Seeder
 *
 * Demo client data.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Demo_Seeder {

	/**
	 * User meta flag marking demo clients.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	public const DEMO_FLAG = 'satori_manifest_demo';

	/**
	 * Seeds every demo client.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $password  Password for every demo user.
	 * @return array<int,array<string,string>|\WP_Error>  One row (or error) per demo file.
	 */
	public static function seed_all( string $password ): array {
		$rows = array();

		foreach ( Config::collect( 'demo' ) as $file => $data ) {
			$rows[] = self::seed( (string) $file, (array) $data, $password );
		}

		return $rows;
	}

	/**
	 * Creates one demo client from its YAML definition.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string              $file      Demo file name (for messages).
	 * @param  array<string,mixed> $data      Parsed YAML.
	 * @param  string              $password  Password to set.
	 * @return array<string,string>|\WP_Error  Summary row, or error (e.g. user exists).
	 */
	public static function seed( string $file, array $data, string $password ) {
		$user  = (array) ( $data['user'] ?? array() );
		$login = sanitize_user( (string) ( $user['login'] ?? $file ), true );

		if ( username_exists( $login ) ) {
			/* translators: %s: username. */
			return new \WP_Error( 'satori_manifest_exists', sprintf( __( '%s already exists — skipped (use --reset to recreate).', 'satori-manifest' ), $login ) );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => (string) ( $user['email'] ?? $login . '@example.test' ),
				'user_pass'    => $password,
				'display_name' => (string) ( $user['name'] ?? $login ),
				'nickname'     => (string) ( $user['name'] ?? $login ),
				'role'         => Client_Role::ROLE,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_user_meta( $user_id, self::DEMO_FLAG, 1 );

		// Role assignment provisioned the basics; replace with the demo content.
		$page_id     = Client_Repository::page_id( $user_id );
		$manifest_id = Client_Repository::manifest_id( $user_id );
		$menu_id     = Client_Repository::menu_id( $user_id );

		foreach ( (array) ( $data['brand'] ?? array() ) as $field => $value ) {
			Brand::set( $page_id, sanitize_key( (string) $field ), is_bool( $value ) ? (int) $value : (string) $value );
		}

		// Match the Branding screen: page title follows the business name (slug unchanged).
		Page_Title_Sync::sync( $page_id );

		if ( ! empty( $data['sections'] ) && is_array( $data['sections'] ) ) {
			update_post_meta( $manifest_id, Post_Types::META_SECTIONS, wp_slash( (string) wp_json_encode( Sanitizer::sanitize_sections( $data['sections'] ) ) ) );
		}

		$design = (string) ( $data['design'] ?? '' );
		if ( Design_Registry::exists( $design ) ) {
			Design_Applier::apply( $page_id, $design, true );
		}

		foreach ( (array) ( $data['menu'] ?? array() ) as $position => $link ) {
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'    => (string) ( $link['label'] ?? '' ),
					'menu-item-url'      => esc_url_raw( (string) ( $link['url'] ?? '' ) ),
					'menu-item-status'   => 'publish',
					'menu-item-type'     => 'custom',
					'menu-item-position' => (int) $position + 1,
				)
			);
		}

		return array(
			'login'    => $login,
			'password' => $password,
			'design'   => $design,
			'page'     => (string) get_permalink( $page_id ),
			'menu'     => empty( $data['menu'] ) ? 'none (standalone)' : count( (array) $data['menu'] ) . ' links',
		);
	}

	/**
	 * Deletes every demo client with their page, manifest and menu.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return array<int,string>  Removed usernames.
	 */
	public static function remove_all(): array {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		$removed = array();
		$users   = get_users(
			array(
				'meta_key'   => self::DEMO_FLAG, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- CLI-only, small user table.
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		foreach ( $users as $user ) {
			// Deleting without reassignment also deletes their page and manifest.
			wp_delete_user( $user->ID );
			$removed[] = $user->user_login;
		}

		return $removed;
	}
}
