<?php
/**
 * Demo seeder.
 *
 * Creates (and removes) the demo clients defined in config/demo/*.yaml -
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
use SatoriManifest\Core\Media_Importer;
use SatoriManifest\Core\Sanitizer;
use SatoriManifest\Designs\Design_Applier;
use SatoriManifest\Designs\Design_Registry;
use SatoriManifest\Manifest\Manifest_Repository;
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
			return new \WP_Error( 'satori_manifest_exists', sprintf( __( '%s already exists, skipped (use --reset to recreate).', 'satori-manifest' ), $login ) );
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
			if ( in_array( $field, array( 'sm_brand_logo', 'sm_background_image' ), true ) && ! is_numeric( $value ) ) {
				$value = self::import_image( (string) $value, $user_id );
			}

			Brand::set( $page_id, sanitize_key( (string) $field ), is_bool( $value ) ? (int) $value : (string) $value );
		}

		// Match the Branding screen: page title follows the business name (slug unchanged).
		Page_Title_Sync::sync( $page_id );

		if ( ! empty( $data['sections'] ) && is_array( $data['sections'] ) ) {
			// Section icons are files in config/demo/ ("assets/icon.png").
			foreach ( $data['sections'] as &$section ) {
				if ( is_array( $section ) && ! empty( $section['icon'] ) && ! is_numeric( $section['icon'] ) ) {
					$section['icon'] = self::import_image( (string) $section['icon'], $user_id );
				}
			}
			unset( $section );

			update_post_meta( $manifest_id, Post_Types::META_SECTIONS, wp_slash( (string) wp_json_encode( Sanitizer::sanitize_sections( $data['sections'] ) ) ) );
		}

		$design = (string) ( $data['design'] ?? '' );
		if ( Design_Registry::exists( $design ) ) {
			Design_Applier::apply( $page_id, $design, true );
		}

		if ( ! empty( $data['content'] ) ) {
			self::apply_content( $page_id, $manifest_id, $user_id, $data );
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
	 * Replaces the page content with the demo's own block markup.
	 *
	 * Placeholders: {{image_id:key}} and {{image_url:key}} for files listed under
	 * page_images, and {{hide_except:Title,Title}} for a price list that shows
	 * only those sections (becomes the JSON list of the other section ids).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.6.0
	 * @param  int                 $page_id      Client page ID.
	 * @param  int                 $manifest_id  Client manifest ID.
	 * @param  int                 $user_id      Demo client user ID.
	 * @param  array<string,mixed> $data         Demo definition.
	 * @return void
	 */
	private static function apply_content( int $page_id, int $manifest_id, int $user_id, array $data ): void {
		$replace = array();

		foreach ( (array) ( $data['page_images'] ?? array() ) as $key => $file ) {
			$id = self::import_image( (string) $file, $user_id );

			$replace[ '{{image_id:' . $key . '}}' ]  = (string) $id;
			$replace[ '{{image_url:' . $key . '}}' ] = esc_url( (string) wp_get_attachment_image_url( $id, 'full' ) );
		}

		$ids = array();
		foreach ( Manifest_Repository::get_sections( $manifest_id ) as $section ) {
			$ids[ (string) $section['title'] ] = (string) $section['id'];
		}

		$content = (string) preg_replace_callback(
			'/\{\{hide_except:([^}]+)\}\}/',
			static function ( array $found ) use ( $ids ): string {
				$keep = array_map( 'trim', explode( ',', $found[1] ) );
				return (string) wp_json_encode( array_values( array_diff_key( $ids, array_flip( $keep ) ) ) );
			},
			strtr( (string) $data['content'], $replace )
		);

		wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => wp_slash( $content ),
			)
		);

		Client_Repository::remember_starter( $page_id );
	}

	/**
	 * Adds an image shipped in config/demo/ to the media library.
	 *
	 * Owned by the demo user, so removing the demo client removes the image too.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.4.0
	 * @param  string $file     Path relative to config/demo/ (e.g. "assets/logo.png").
	 * @param  int    $user_id  Demo client user ID.
	 * @return int  Attachment ID, or 0 when the file is missing or the import fails.
	 */
	private static function import_image( string $file, int $user_id ): int {
		return Media_Importer::import( SATORI_MANIFEST_PATH . 'config/demo/' . ltrim( str_replace( '..', '', $file ), '/' ), $user_id );
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
