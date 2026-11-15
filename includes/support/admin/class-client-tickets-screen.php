<?php
/**
 * Client screen: Support - raise tickets, follow them and reply.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.12.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Support\Admin;

use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Core\Security;
use SatoriManifest\Support\Ticket_Notifier;
use SatoriManifest\Support\Tickets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client_Tickets_Screen
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.12.0
 */
final class Client_Tickets_Screen {

	/**
	 * Menu slug.
	 */
	public const SLUG = 'satori-manifest-support';

	/**
	 * Form action (admin-post.php) and nonce action.
	 */
	public const ACTION = 'satori_manifest_ticket';

	/**
	 * Hook suffix of the screen.
	 *
	 * @var string
	 */
	private static string $hook = '';

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ), 20 );
		add_filter( 'satori_manifest_client_screens', array( self::class, 'allow_screen' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Adds Support to the client menu.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @return void
	 */
	public static function add_menu(): void {
		if ( ! Client_Role::is_client() ) {
			return;
		}

		self::$hook = (string) add_menu_page(
			__( 'Support', 'satori-manifest' ),
			__( 'Support', 'satori-manifest' ),
			'read',
			self::SLUG,
			array( self::class, 'render' ),
			'dashicons-tickets-alt',
			79
		);
	}

	/**
	 * Lets clients open this screen (Screen_Guard).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
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
	 * The screen's hook suffix ('' until the menu is added).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @return string
	 */
	public static function hook(): string {
		return self::$hook;
	}

	/**
	 * The screen's address, optionally on one ticket.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  int $ticket  Ticket, or 0 for the list.
	 * @return string
	 */
	public static function url( int $ticket = 0 ): string {
		$args = array( 'page' => self::SLUG );

		if ( $ticket ) {
			$args['ticket'] = $ticket;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Renders the list (with the new-ticket form) or one ticket.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @return void
	 */
	public static function render(): void {
		$user_id = get_current_user_id();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$satori_manifest_ticket = isset( $_GET['ticket'] ) ? absint( $_GET['ticket'] ) : 0;
		$satori_manifest_notice = isset( $_GET['sm_ticket'] ) ? sanitize_key( wp_unslash( $_GET['sm_ticket'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $satori_manifest_ticket && Tickets::owner( $satori_manifest_ticket ) !== $user_id ) {
			$satori_manifest_ticket = 0;
		}

		$satori_manifest_tickets  = $satori_manifest_ticket ? array() : Tickets::list( $user_id );
		$satori_manifest_messages = $satori_manifest_ticket ? Tickets::messages( $satori_manifest_ticket ) : array();
		$satori_manifest_action   = self::ACTION;

		require SATORI_MANIFEST_PATH . 'templates/support/client.php';
	}

	/**
	 * Handles new tickets, replies and closing. Hooked to admin_post_satori_manifest_ticket.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
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
		$ticket  = isset( $_POST['ticket'] ) ? absint( $_POST['ticket'] ) : 0;
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['message'] ) ) : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['subject'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$notice = 'invalid';
		$target = 0;

		if ( 'new' === $task ) {
			$target = Tickets::create( $user_id, $subject, $message );

			if ( $target ) {
				Ticket_Notifier::new_ticket( $target, trim( $message ) );
				$notice = 'created';
			}
		} elseif ( $ticket && Tickets::owner( $ticket ) === $user_id ) {
			$target = $ticket;

			if ( 'reply' === $task && Tickets::reply( $ticket, $user_id, $message ) ) {
				Ticket_Notifier::client_reply( $ticket, trim( $message ) );
				$notice = 'replied';
			} elseif ( 'close' === $task ) {
				Tickets::set_status( $ticket, Tickets::CLOSED );
				$notice = 'closed';
			}
		}

		wp_safe_redirect( add_query_arg( 'sm_ticket', $notice, self::url( $target ) ) );
		exit;
	}
}
