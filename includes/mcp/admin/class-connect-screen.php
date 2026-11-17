<?php
/**
 * Client screen: AI assistant.
 *
 * Explains what the assistant does, creates or removes the connection link,
 * shows example requests and the list of recent changes with undo. Clients
 * whose plan does not include it see what it does and how to get it.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp\Admin;

use SatoriManifest\Admin\Assets;
use SatoriManifest\Billing\Plan_Change;
use SatoriManifest\Branding\Portal_Brand;
use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Core\Security;
use SatoriManifest\Mcp\Access;
use SatoriManifest\Mcp\Assistant_Config;
use SatoriManifest\Mcp\Change_Log;
use SatoriManifest\Mcp\Client_Context;
use SatoriManifest\Mcp\Endpoint;
use SatoriManifest\Mcp\Page_Store;
use SatoriManifest\Mcp\Tokens;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Connect_Screen
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Connect_Screen {

	/**
	 * Screen slug.
	 */
	public const SLUG = 'satori-manifest-ai';

	/**
	 * Form action (admin-post.php).
	 */
	public const ACTION = 'satori_manifest_ai_connect';

	/**
	 * How long a new link stays visible after creating it (seconds).
	 */
	private const REVEAL_SECONDS = 600;

	/**
	 * Hook suffix of the screen.
	 *
	 * @var string
	 */
	private static string $hook = '';

	/**
	 * Registers the menu, assets and form handler.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ), 20 );
		add_filter( 'satori_manifest_client_screens', array( self::class, 'allow_screen' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Adds "AI assistant" to the client menu. Hooked to 'admin_menu'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return void
	 */
	public static function add_menu(): void {
		if ( ! Client_Role::is_client() || ! Assistant_Config::enabled() ) {
			return;
		}

		self::$hook = (string) add_menu_page(
			__( 'AI assistant', 'satori-manifest' ),
			__( 'AI assistant', 'satori-manifest' ),
			'read',
			self::SLUG,
			array( self::class, 'render' ),
			'dashicons-format-chat',
			7
		);
	}

	/**
	 * Lets clients open this screen. Hooked to 'satori_manifest_client_screens'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<int,string> $hooks  Allowed hook suffixes.
	 * @return array<int,string>
	 */
	public static function allow_screen( array $hooks ): array {
		if ( '' !== self::$hook ) {
			$hooks[] = self::$hook;
		}

		return $hooks;
	}

	/**
	 * Loads the copy-button script. Hooked to 'admin_enqueue_scripts'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string $hook_suffix  Current screen.
	 * @return void
	 */
	public static function enqueue( string $hook_suffix ): void {
		if ( '' !== self::$hook && self::$hook === $hook_suffix ) {
			// The Home screen's copy script is generic ([data-sm-copy]).
			Assets::enqueue_script( 'satori-manifest-client-home', 'build/js/client-home.js', array( 'wp-i18n' ) );
			wp_set_script_translations( 'satori-manifest-client-home', 'satori-manifest', SATORI_MANIFEST_PATH . 'languages' );
		}
	}

	/**
	 * Renders the screen.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return void
	 */
	public static function render(): void {
		$user_id = get_current_user_id();

		$satori_manifest_allowed  = Access::allowed( $user_id );
		$satori_manifest_token    = Tokens::info( $user_id );
		$satori_manifest_new_link = self::take_new_link( $user_id );
		$satori_manifest_changes  = Change_Log::recent( $user_id, 10 );
		$satori_manifest_support  = Portal_Brand::support_email();
		$satori_manifest_site     = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$satori_manifest_action   = self::ACTION;
		$satori_manifest_offer    = $satori_manifest_allowed ? null : Plan_Change::offer( $user_id );

		// Only an upgrade that brings the assistant is offered here.
		if ( $satori_manifest_offer && ( ! $satori_manifest_offer['upgrade'] || ! in_array( $satori_manifest_offer['to'], Assistant_Config::plans(), true ) ) ) {
			$satori_manifest_offer = null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own redirect.
		$satori_manifest_notice = isset( $_GET['sm_ai'] ) ? sanitize_key( wp_unslash( $_GET['sm_ai'] ) ) : '';

		require SATORI_MANIFEST_PATH . 'templates/mcp/connect.php';
	}

	/**
	 * Handles connect, new link, disconnect and undo.
	 *
	 * Hooked to 'admin_post_satori_manifest_ai_connect'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return void
	 */
	public static function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified with Security::verify_form_nonce() first.
		$nonce = isset( $_POST[ Security::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ Security::NONCE_FIELD ] ) ) : '';

		if ( ! Security::verify_form_nonce( $nonce, self::ACTION ) || ! Client_Role::is_client() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'satori-manifest' ), 403 );
		}

		$user_id = get_current_user_id();
		$task    = isset( $_POST['task'] ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';
		$notice  = '';

		if ( 'connect' === $task && Access::allowed( $user_id ) ) {
			set_transient( self::reveal_key( $user_id ), Tokens::issue( $user_id ), self::REVEAL_SECONDS );
			$notice = 'connected';
		} elseif ( 'disconnect' === $task ) {
			Tokens::revoke( $user_id );
			delete_transient( self::reveal_key( $user_id ) );
			$notice = 'disconnected';
		} elseif ( 'undo' === $task ) {
			$context = Client_Context::for_user( $user_id );
			$notice  = null !== $context && null !== Change_Log::undo_last( new Page_Store( $context ) ) ? 'undone' : 'nothing';
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		wp_safe_redirect( add_query_arg( 'sm_ai', $notice, admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	/**
	 * The link just created, shown once and then forgotten.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int $user_id  Client user ID.
	 * @return string  Connection address, or ''.
	 */
	private static function take_new_link( int $user_id ): string {
		$key = (string) get_transient( self::reveal_key( $user_id ) );

		if ( '' === $key ) {
			return '';
		}

		delete_transient( self::reveal_key( $user_id ) );

		return null !== Tokens::info( $user_id ) ? Endpoint::url( $key ) : '';
	}

	/**
	 * Transient name for the one-time link.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int $user_id  Client user ID.
	 * @return string
	 */
	private static function reveal_key( int $user_id ): string {
		return 'sm_ai_reveal_' . $user_id;
	}
}
