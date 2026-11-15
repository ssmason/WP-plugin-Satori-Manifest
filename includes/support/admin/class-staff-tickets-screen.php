<?php
/**
 * Staff screen: Manifest → Support tickets - answer tickets and set their status.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.12.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Support\Admin;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Core\Security;
use SatoriManifest\Manifest\Post_Types;
use SatoriManifest\Support\Ticket_Notifier;
use SatoriManifest\Support\Tickets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Staff_Tickets_Screen
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.12.0
 */
final class Staff_Tickets_Screen {

	/**
	 * Menu slug.
	 */
	public const SLUG = 'satori-manifest-tickets';

	/**
	 * Form action (admin-post.php) and nonce action.
	 */
	public const ACTION = 'satori_manifest_ticket_staff';

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ), 20 );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * The capability needed (same as the Clients screen).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @return string
	 */
	public static function capability(): string {
		return 'edit_others_' . Post_Types::CAP_PLURAL;
	}

	/**
	 * Adds Manifest → Support tickets, with a count of opened tickets.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @return void
	 */
	public static function add_menu(): void {
		if ( Client_Role::is_client() || ! current_user_can( self::capability() ) ) {
			return;
		}

		$open  = Tickets::counts()[ Tickets::OPEN ];
		$badge = $open ? ' <span class="awaiting-mod count-' . (int) $open . '"><span class="pending-count">' . (int) $open . '</span></span>' : '';

		add_submenu_page(
			Admin_Menu::MENU_SLUG,
			__( 'Support tickets', 'satori-manifest' ),
			__( 'Support tickets', 'satori-manifest' ) . $badge,
			self::capability(),
			self::SLUG,
			array( self::class, 'render' )
		);
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
	 * Renders the list (filtered by status) or one ticket.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$satori_manifest_ticket = isset( $_GET['ticket'] ) ? absint( $_GET['ticket'] ) : 0;
		$satori_manifest_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$satori_manifest_notice = isset( $_GET['sm_ticket'] ) ? sanitize_key( wp_unslash( $_GET['sm_ticket'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! Tickets::exists( $satori_manifest_ticket ) ) {
			$satori_manifest_ticket = 0;
		}

		$satori_manifest_tickets  = $satori_manifest_ticket ? array() : Tickets::list( 0, $satori_manifest_filter );
		$satori_manifest_messages = $satori_manifest_ticket ? Tickets::messages( $satori_manifest_ticket ) : array();
		$satori_manifest_counts   = Tickets::counts();
		$satori_manifest_action   = self::ACTION;

		require SATORI_MANIFEST_PATH . 'templates/support/staff.php';
	}

	/**
	 * Handles replies and status changes. Hooked to admin_post_satori_manifest_ticket_staff.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @return void
	 */
	public static function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified with Security::verify_form_nonce() first.
		$nonce = isset( $_POST[ Security::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ Security::NONCE_FIELD ] ) ) : '';

		if ( ! Security::verify_form_nonce( $nonce, self::ACTION ) || Client_Role::is_client() || ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'satori-manifest' ), 403 );
		}

		$ticket  = isset( $_POST['ticket'] ) ? absint( $_POST['ticket'] ) : 0;
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['message'] ) ) : '';
		$status  = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$notice = 'invalid';

		if ( Tickets::exists( $ticket ) ) {
			$notice = 'saved';

			if ( '' !== trim( $message ) && Tickets::reply( $ticket, get_current_user_id(), $message ) ) {
				$notice = 'replied';
			}

			// The status chosen with the reply wins over the automatic "in progress".
			if ( isset( Tickets::statuses()[ $status ] ) ) {
				Tickets::set_status( $ticket, $status );
			}

			if ( 'replied' === $notice ) {
				$notice = Ticket_Notifier::staff_reply( $ticket, trim( $message ) ) ? 'replied' : 'replied-unsent';
			}
		}

		wp_safe_redirect( add_query_arg( 'sm_ticket', $notice, self::url( $ticket ) ) );
		exit;
	}
}
