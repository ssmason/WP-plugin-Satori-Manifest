<?php
/**
 * Branding screen.
 *
 * Renders the YAML-defined ACF field groups (see config/acf/) as tabbed
 * forms with acf_form(), saving to the client's page. A live preview of the
 * page sits beside the form and refreshes after each save.
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
 * Class Branding_Screen
 *
 * Tabbed ACF forms for brand, header and footer settings.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Branding_Screen {

	/**
	 * Registers screen hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'attach_load_hook' ), 1000 );
		add_action( 'acf/save_post', array( self::class, 'after_save' ), 20 );
	}

	/**
	 * Attaches the load hook once the screen's hook suffix is known.
	 *
	 * Hooked late to 'admin_menu'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function attach_load_hook(): void {
		$hook = Admin_Menu::hook( Admin_Menu::BRANDING_SLUG );

		if ( '' !== $hook ) {
			add_action( 'load-' . $hook, array( self::class, 'on_load' ) );
		}
	}

	/**
	 * Processes ACF form submissions before output starts.
	 *
	 * ACF's acf_form() encrypts its target post ID into the form, so a
	 * submission can only ever save to the page this screen rendered for.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function on_load(): void {
		$page_id = self::page_id();

		if ( ! Field_Loader::acf_available() || ! $page_id || ! current_user_can( 'edit_post', $page_id ) ) {
			return;
		}

		acf_form_head();
	}

	/**
	 * Keeps the page title in step with the business name after a save.
	 *
	 * The page slug (and therefore the QR code URL) is never changed.
	 * Hooked to 'acf/save_post'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int|string $post_id  Saved post ID.
	 * @return void
	 */
	public static function after_save( $post_id ): void {
		$post_id = (int) $post_id;

		if ( ! Client_Repository::is_client_page( $post_id ) ) {
			return;
		}

		Brand::flush();
		$name = trim( (string) get_post_meta( $post_id, 'sm_brand_name', true ) );

		if ( '' !== $name && get_the_title( $post_id ) !== $name ) {
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => $name,
				)
			);
		}
	}

	/**
	 * Returns the page this screen edits, or 0.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return int
	 */
	public static function page_id(): int {
		$client_id = Admin_Menu::current_client_id();
		return $client_id ? Client_Repository::page_id( $client_id ) : 0;
	}

	/**
	 * Returns the field groups shown as tabs, keyed by tab slug.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return array<string,array<string,mixed>>
	 */
	public static function tabs(): array {
		$tabs = array();

		foreach ( Field_Loader::groups() as $name => $group ) {
			$screen = $group['satori_screen'];
			$slug   = sanitize_key( (string) ( $screen['tab'] ?? $name ) );

			$tabs[ $slug ] = array(
				'label'       => (string) ( $screen['label'] ?? $group['title'] ),
				'icon'        => (string) ( $screen['icon'] ?? 'dashicons-admin-generic' ),
				'description' => (string) ( $screen['description'] ?? '' ),
				'group_key'   => $group['key'],
			);
		}

		return $tabs;
	}

	/**
	 * Renders the screen.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function render(): void {
		$satori_manifest_client_id = Admin_Menu::current_client_id();
		$satori_manifest_page_id   = self::page_id();
		$satori_manifest_tabs      = self::tabs();
		$satori_manifest_clients   = Client_Role::is_client() ? array() : Client_Repository::all_clients();

		// Display-only routing and flags.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$satori_manifest_tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$satori_manifest_updated = isset( $_GET['updated'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! isset( $satori_manifest_tabs[ $satori_manifest_tab ] ) ) {
			$satori_manifest_tab = (string) array_key_first( $satori_manifest_tabs );
		}

		require SATORI_MANIFEST_PATH . 'admin/views/branding-screen.php';
	}
}
