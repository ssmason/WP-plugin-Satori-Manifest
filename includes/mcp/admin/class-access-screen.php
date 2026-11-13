<?php
/**
 * Staff screen: Manifest → AI assistant.
 *
 * One row per client: their plan, the staff setting (follow plan / on / off),
 * whether they may use it now, whether they are connected and how many
 * changes the assistant made. Administrators only, as it affects pricing.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp\Admin;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Billing\Subscriptions;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Core\Security;
use SatoriManifest\Mcp\Access;
use SatoriManifest\Mcp\Assistant_Config;
use SatoriManifest\Mcp\Change_Log;
use SatoriManifest\Mcp\Tokens;
use SatoriManifest\Registration\Plans;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Access_Screen
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Access_Screen {

	/**
	 * Screen slug.
	 */
	public const SLUG = 'satori-manifest-ai-access';

	/**
	 * Form action (admin-post.php).
	 */
	public const ACTION = 'satori_manifest_ai_access';

	/**
	 * Registers the menu and form handler.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ), 20 );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Adds the submenu. Hooked to 'admin_menu'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return void
	 */
	public static function add_menu(): void {
		if ( Client_Role::is_client() || ! current_user_can( 'manage_options' ) || ! Assistant_Config::enabled() ) {
			return;
		}

		add_submenu_page(
			Admin_Menu::MENU_SLUG,
			__( 'AI assistant', 'satori-manifest' ),
			__( 'AI assistant', 'satori-manifest' ),
			'manage_options',
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	/**
	 * Renders the screen.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$satori_manifest_rows   = array_map( array( self::class, 'row' ), Client_Repository::all_clients() );
		$satori_manifest_plans  = array_map( array( Plans::class, 'title' ), Assistant_Config::plans() );
		$satori_manifest_action = self::ACTION;
		$satori_manifest_modes  = array(
			Access::MODE_PLAN => __( 'Follow plan', 'satori-manifest' ),
			Access::MODE_ON   => __( 'On', 'satori-manifest' ),
			Access::MODE_OFF  => __( 'Off', 'satori-manifest' ),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own redirect.
		$satori_manifest_saved = isset( $_GET['sm_saved'] );

		require SATORI_MANIFEST_PATH . 'templates/mcp/access.php';
	}

	/**
	 * Saves one client's setting.
	 *
	 * Hooked to 'admin_post_satori_manifest_ai_access'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return void
	 */
	public static function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified with Security::verify_form_nonce() first.
		$nonce = isset( $_POST[ Security::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ Security::NONCE_FIELD ] ) ) : '';

		if ( ! Security::verify_form_nonce( $nonce, self::ACTION ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'satori-manifest' ), 403 );
		}

		$client = isset( $_POST['client'] ) ? absint( $_POST['client'] ) : 0;
		$mode   = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';

		if ( $client && Client_Role::is_client( $client ) ) {
			Access::set_mode( $client, $mode );
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		wp_safe_redirect( add_query_arg( 'sm_saved', 1, admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	/**
	 * One table row.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  \WP_User $user  Client.
	 * @return array<string,mixed>
	 */
	private static function row( \WP_User $user ): array {
		$page_id = Client_Repository::page_id( (int) $user->ID );
		$plan    = (string) Subscriptions::for_user( (int) $user->ID )['plan'];

		return array(
			'id'      => (int) $user->ID,
			'name'    => $page_id ? get_the_title( $page_id ) : $user->display_name,
			'plan'    => '' !== $plan ? Plans::title( $plan ) : __( 'Set up by staff', 'satori-manifest' ),
			'mode'    => Access::mode( (int) $user->ID ),
			'allowed' => Access::allowed( (int) $user->ID ),
			'token'   => Tokens::info( (int) $user->ID ),
			'changes' => count( Change_Log::recent( (int) $user->ID, Assistant_Config::history() ) ),
		);
	}
}
