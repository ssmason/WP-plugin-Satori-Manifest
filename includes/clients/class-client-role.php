<?php
/**
 * Client role and capability management.
 *
 * Registers the "Manifest Client" role — a deliberately small capability set
 * that lets a client edit their own page, price list, navigation menu and
 * media, and nothing else. Ownership rules that capabilities alone cannot
 * express (one page per client, menu isolation) live in Access_Control.
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
 * Class Client_Role
 *
 * Creates the client role and grants manifest capabilities to staff roles.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Client_Role {

	/**
	 * Role slug.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	public const ROLE = 'satori_manifest_client';

	/**
	 * Option storing the version the role/caps were last synced for.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	private const SYNC_OPTION = 'satori_manifest_caps_version';

	/**
	 * Staff roles that receive full manifest management capabilities.
	 *
	 * @since 2.0.0
	 * @var   array<int,string>
	 */
	private const STAFF_ROLES = array( 'administrator', 'editor' );

	/**
	 * Returns the client role's capabilities.
	 *
	 * - edit_posts is required by parts of the block editor (media details,
	 *   block renderer); creating posts is blocked by Access_Control.
	 * - edit_theme_options grants Appearance → Menus; every other screen
	 *   and REST route behind that capability is locked down by Access_Control.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return array<string,bool>
	 */
	public static function capabilities(): array {
		$plural = Post_Types::CAP_PLURAL;

		return array(
			'read'                     => true,
			'upload_files'             => true,
			'edit_posts'               => true,
			'edit_pages'               => true,
			'edit_published_pages'     => true,
			'publish_pages'            => true,
			'edit_theme_options'       => true,
			"edit_{$plural}"           => true,
			"edit_published_{$plural}" => true,
			"publish_{$plural}"        => true,
		);
	}

	/**
	 * Creates/updates the client role and staff capabilities when the plugin version changes.
	 *
	 * Hooked to 'init'; also called on activation.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function maybe_sync(): void {
		if ( get_option( self::SYNC_OPTION ) === SATORI_MANIFEST_VERSION ) {
			return;
		}

		self::sync_caps();
		update_option( self::SYNC_OPTION, SATORI_MANIFEST_VERSION, false );
	}

	/**
	 * (Re)creates the client role and grants manifest caps to staff roles.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function sync_caps(): void {
		remove_role( self::ROLE );
		add_role( self::ROLE, __( 'Manifest Client', 'satori-manifest' ), self::capabilities() );

		foreach ( self::STAFF_ROLES as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( Post_Types::all_caps() as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Removes the client role and staff capabilities (used on uninstall).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function remove(): void {
		remove_role( self::ROLE );

		foreach ( self::STAFF_ROLES as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( Post_Types::all_caps() as $cap ) {
				$role->remove_cap( $cap );
			}
		}

		delete_option( self::SYNC_OPTION );
	}

	/**
	 * Returns true when the given (or current) user is a manifest client.
	 *
	 * Staff users who also hold the client role are not treated as clients,
	 * so they are never locked down.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int $user_id  User ID; 0 for the current user.
	 * @return bool
	 */
	public static function is_client( int $user_id = 0 ): bool {
		$user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();

		if ( ! $user instanceof \WP_User || ! $user->exists() ) {
			return false;
		}

		// Read the primitive capability directly: user_can() would run the
		// map_meta_cap filter, which calls back into this method.
		return in_array( self::ROLE, (array) $user->roles, true ) && empty( $user->allcaps['edit_others_pages'] );
	}
}
