<?php
/**
 * Admin menu registration.
 *
 * Staff see a "Manifest" menu: Clients, Applications, Manifests, Designs,
 * Branding, Payments, Settings and Help (Applications, Payments and Settings
 * for administrators only).
 *
 * Clients see a short, task-focused menu instead: Home, My page, Price list,
 * Design, Branding, Billing and Help (plus Media, Appearance → Menus and
 * Profile, which the Admin\Portal classes leave in place).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Admin;

use SatoriManifest\Admin\Screens\Applications_Screen;
use SatoriManifest\Admin\Screens\Billing_Screen;
use SatoriManifest\Admin\Screens\Branding_Screen;
use SatoriManifest\Admin\Screens\Client_Home;
use SatoriManifest\Admin\Screens\Clients_Screen;
use SatoriManifest\Admin\Screens\Design_Screen;
use SatoriManifest\Admin\Screens\Help_Screen;
use SatoriManifest\Admin\Screens\Payments_Screen;
use SatoriManifest\Admin\Screens\Settings_Screen;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Manifest\Post_Types;
use SatoriManifest\Registration\Applications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Menu
 *
 * Registers the plugin's admin menus for staff and clients.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */
class Admin_Menu {

	/**
	 * Staff top-level menu slug (also the Clients screen).
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public const MENU_SLUG = 'satori-manifest';

	/**
	 * Client home screen slug.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	public const HOME_SLUG = 'satori-manifest-home';

	/**
	 * Design screen slug.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	public const DESIGN_SLUG = 'satori-manifest-design';

	/**
	 * Branding screen slug.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	public const BRANDING_SLUG = 'satori-manifest-branding';

	/**
	 * Client billing screen slug.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	public const BILLING_SLUG = 'satori-manifest-billing';

	/**
	 * Help screen slug.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	public const HELP_SLUG = 'satori-manifest-help';

	/**
	 * Staff applications screen slug.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	public const APPLICATIONS_SLUG = 'satori-manifest-applications';

	/**
	 * Staff payments screen slug.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	public const PAYMENTS_SLUG = 'satori-manifest-payments';

	/**
	 * Staff settings screen slug.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	public const SETTINGS_SLUG = 'satori-manifest-settings';

	/**
	 * Page hook suffixes keyed by screen slug, filled during registration.
	 *
	 * @since 2.0.0
	 * @var   array<string,string>
	 */
	private static array $hooks = array();

	/**
	 * Registers the admin menu for the current user.
	 *
	 * Hooked to 'admin_menu'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @return void
	 */
	public static function register(): void {
		if ( Client_Role::is_client() ) {
			self::register_client_menu();
			return;
		}

		self::register_staff_menu();
	}

	/**
	 * Returns the hook suffix for one of the plugin screens.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $slug  Screen slug.
	 * @return string
	 */
	public static function hook( string $slug ): string {
		return self::$hooks[ $slug ] ?? '';
	}

	/**
	 * Returns the hook suffixes of every plugin screen a client may open.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return array<int,string>
	 */
	public static function client_hooks(): array {
		return array_values(
			array_filter(
				array_map(
					array( self::class, 'hook' ),
					array( self::HOME_SLUG, self::DESIGN_SLUG, self::BRANDING_SLUG, self::BILLING_SLUG, self::HELP_SLUG )
				)
			)
		);
	}

	/**
	 * Builds an admin URL for a plugin screen, optionally for a given client.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $slug       Screen slug.
	 * @param  int    $client_id  Client user ID (staff only).
	 * @return string
	 */
	public static function url( string $slug, int $client_id = 0 ): string {
		$args = array( 'page' => $slug );

		if ( $client_id && ! Client_Role::is_client() ) {
			$args['client'] = $client_id;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Resolves which client a plugin screen is acting on.
	 *
	 * Clients always act on themselves. Staff pass ?client={user_id}.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return int  Client user ID, or 0 when none is selected.
	 */
	public static function current_client_id(): int {
		if ( Client_Role::is_client() ) {
			return get_current_user_id();
		}

		// Read-only selection of which client to display; actions are nonce-checked.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$client_id = isset( $_GET['client'] ) ? absint( $_GET['client'] ) : 0;

		return ( $client_id && Client_Role::is_client( $client_id ) && current_user_can( 'edit_others_pages' ) ) ? $client_id : 0;
	}

	/**
	 * Registers the client-facing menu.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	private static function register_client_menu(): void {
		$user_id     = get_current_user_id();
		$page_id     = Client_Repository::page_id( $user_id );
		$manifest_id = Client_Repository::manifest_id( $user_id );

		self::$hooks[ self::HOME_SLUG ] = (string) add_menu_page(
			__( 'Home', 'satori-manifest' ),
			__( 'Home', 'satori-manifest' ),
			'read',
			self::HOME_SLUG,
			array( Client_Home::class, 'render' ),
			'dashicons-store',
			2
		);

		if ( $page_id ) {
			add_menu_page(
				__( 'My page', 'satori-manifest' ),
				__( 'My page', 'satori-manifest' ),
				'edit_pages',
				'post.php?post=' . $page_id . '&action=edit',
				'',
				'dashicons-welcome-write-blog',
				3
			);
		}

		if ( $manifest_id ) {
			add_menu_page(
				__( 'Price list', 'satori-manifest' ),
				__( 'Price list', 'satori-manifest' ),
				'edit_' . Post_Types::CAP_PLURAL,
				'post.php?post=' . $manifest_id . '&action=edit',
				'',
				'dashicons-list-view',
				4
			);
		}

		self::$hooks[ self::DESIGN_SLUG ] = (string) add_menu_page(
			__( 'Design', 'satori-manifest' ),
			__( 'Design', 'satori-manifest' ),
			'edit_pages',
			self::DESIGN_SLUG,
			array( Design_Screen::class, 'render' ),
			'dashicons-art',
			5
		);

		self::$hooks[ self::BRANDING_SLUG ] = (string) add_menu_page(
			__( 'Branding', 'satori-manifest' ),
			__( 'Branding', 'satori-manifest' ),
			'edit_pages',
			self::BRANDING_SLUG,
			array( Branding_Screen::class, 'render' ),
			'dashicons-admin-customizer',
			6
		);

		if ( Billing_Screen::is_available( $user_id ) ) {
			self::$hooks[ self::BILLING_SLUG ] = (string) add_menu_page(
				__( 'Billing', 'satori-manifest' ),
				__( 'Billing', 'satori-manifest' ),
				'read',
				self::BILLING_SLUG,
				array( Billing_Screen::class, 'render' ),
				'dashicons-money-alt',
				7
			);
		}

		self::$hooks[ self::HELP_SLUG ] = (string) add_menu_page(
			__( 'Help', 'satori-manifest' ),
			__( 'Help', 'satori-manifest' ),
			'read',
			self::HELP_SLUG,
			array( Help_Screen::class, 'render' ),
			'dashicons-sos',
			80
		);
	}

	/**
	 * Registers the staff menu.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	private static function register_staff_menu(): void {
		$cap = 'edit_others_' . Post_Types::CAP_PLURAL;

		self::$hooks[ self::MENU_SLUG ] = (string) add_menu_page(
			__( 'Manifest', 'satori-manifest' ),
			__( 'Manifest', 'satori-manifest' ),
			$cap,
			self::MENU_SLUG,
			array( Clients_Screen::class, 'render' ),
			'dashicons-list-view',
			26
		);

		// Rename the auto-generated first submenu entry.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Clients', 'satori-manifest' ),
			__( 'Clients', 'satori-manifest' ),
			$cap,
			self::MENU_SLUG,
			array( Clients_Screen::class, 'render' )
		);

		// "Manifests" (the CPT list) is added automatically via show_in_menu.

		self::$hooks[ self::DESIGN_SLUG ] = (string) add_submenu_page(
			self::MENU_SLUG,
			__( 'Designs', 'satori-manifest' ),
			__( 'Designs', 'satori-manifest' ),
			$cap,
			self::DESIGN_SLUG,
			array( Design_Screen::class, 'render' )
		);

		self::$hooks[ self::BRANDING_SLUG ] = (string) add_submenu_page(
			self::MENU_SLUG,
			__( 'Branding', 'satori-manifest' ),
			__( 'Branding', 'satori-manifest' ),
			$cap,
			self::BRANDING_SLUG,
			array( Branding_Screen::class, 'render' )
		);

		self::register_admin_only_screens();

		self::$hooks[ self::HELP_SLUG ] = (string) add_submenu_page(
			self::MENU_SLUG,
			__( 'Help', 'satori-manifest' ),
			__( 'Help', 'satori-manifest' ),
			$cap,
			self::HELP_SLUG,
			array( Help_Screen::class, 'render' )
		);
	}

	/**
	 * Registers the screens only administrators see: applications (with a
	 * pending-count badge), payments and settings.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	private static function register_admin_only_screens(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$pending = Applications::pending_count();
		$label   = __( 'Applications', 'satori-manifest' );

		if ( $pending ) {
			$label .= sprintf( ' <span class="awaiting-mod">%d</span>', $pending );
		}

		self::$hooks[ self::APPLICATIONS_SLUG ] = (string) add_submenu_page(
			self::MENU_SLUG,
			__( 'Applications', 'satori-manifest' ),
			$label,
			'manage_options',
			self::APPLICATIONS_SLUG,
			array( Applications_Screen::class, 'render' ),
			1
		);

		self::$hooks[ self::PAYMENTS_SLUG ] = (string) add_submenu_page(
			self::MENU_SLUG,
			__( 'Payments', 'satori-manifest' ),
			__( 'Payments', 'satori-manifest' ),
			'manage_options',
			self::PAYMENTS_SLUG,
			array( Payments_Screen::class, 'render' )
		);

		self::$hooks[ self::SETTINGS_SLUG ] = (string) add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'satori-manifest' ),
			__( 'Settings', 'satori-manifest' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( Settings_Screen::class, 'render' )
		);
	}
}
