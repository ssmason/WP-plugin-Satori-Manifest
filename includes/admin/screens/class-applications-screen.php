<?php
/**
 * Applications screen (administrators only).
 *
 * Lists registrations with their details and email history. Approving one:
 *   1. creates the client user (username from the form screen, strong
 *      random password) — which provisions their page, manifest and menu;
 *   2. applies the chosen design and pre-fills branding from the application;
 *   3. emails the client their username and a secure "set your password"
 *      link (passwords are never sent by email).
 * Declining marks the application and can optionally email the applicant.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Admin\Screens;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Billing\Stripe_Settings;
use SatoriManifest\Core\Security;
use SatoriManifest\Designs\Design_Registry;
use SatoriManifest\Registration\Application_Approver;
use SatoriManifest\Registration\Applications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Applications_Screen
 *
 * Admin UI and actions for applications.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */
class Applications_Screen {

	/**
	 * Action name for admin-post.php.
	 *
	 * @since 2.1.0
	 * @var   string
	 */
	public const ACTION = 'satori_manifest_application';

	/**
	 * Returns the screen URL, optionally filtered by status.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  string $status  Status filter.
	 * @return string
	 */
	public static function url( string $status = '' ): string {
		$args = array( 'page' => Admin_Menu::APPLICATIONS_SLUG );

		if ( '' !== $status ) {
			$args['status'] = $status;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Renders the screen.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage applications.', 'satori-manifest' ), 403 );
		}

		// Display-only filters and flags.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$satori_manifest_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'pending';
		$satori_manifest_notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';
		$satori_manifest_error  = isset( $_GET['sm_error'] ) ? sanitize_text_field( wp_unslash( $_GET['sm_error'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $satori_manifest_status, array_merge( Applications::STATUSES, array( 'all' ) ), true ) ) {
			$satori_manifest_status = 'pending';
		}

		$satori_manifest_apps   = Applications::all( 'all' === $satori_manifest_status ? '' : $satori_manifest_status );
		$satori_manifest_counts = array();
		foreach ( Applications::STATUSES as $satori_manifest_key ) {
			$satori_manifest_counts[ $satori_manifest_key ] = count( Applications::all( $satori_manifest_key ) );
		}
		$satori_manifest_billing = Stripe_Settings::is_configured();
		$satori_manifest_types   = Applications::business_types();
		$satori_manifest_designs = Design_Registry::all();

		require SATORI_MANIFEST_PATH . 'templates/admin/applications.php';
	}

	/**
	 * Handles approve / decline / resend / delete. Hooked to admin_post_satori_manifest_application.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return void
	 */
	public static function handle(): void {
		// The nonce is read here and verified by Security::verify_form_nonce().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$nonce = isset( $_POST[ Security::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Security::NONCE_FIELD ] ) ) : '';

		if ( ! Security::verify_form_nonce( $nonce, self::ACTION ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'satori-manifest' ), 403 );
		}

		$id              = isset( $_POST['application_id'] ) ? absint( $_POST['application_id'] ) : 0;
		$decision        = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$login           = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ), true ) : '';
		$design          = isset( $_POST['design'] ) ? sanitize_key( wp_unslash( $_POST['design'] ) ) : '';
		$reason          = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
		$notify          = ! empty( $_POST['notify'] );
		$require_payment = ! empty( $_POST['require_payment'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$app = Applications::get( $id );

		if ( ! $app ) {
			self::back( 'pending', '', __( 'That application no longer exists.', 'satori-manifest' ) );
		}

		switch ( $decision ) {
			case 'approve':
				$result = Application_Approver::approve( $app, $login, $design, $notify, $require_payment );
				if ( is_wp_error( $result ) ) {
					self::back( 'pending', '', $result->get_error_message() );
				}
				self::back( $result, 'awaiting_payment' === $result ? 'payment-requested' : 'approved' );
				break;

			case 'activate':
				// Create the account without waiting for payment (e.g. paid another way).
				$result = Application_Approver::activate( $id, $notify );
				if ( is_wp_error( $result ) ) {
					self::back( 'awaiting_payment', '', $result->get_error_message() );
				}
				self::back( 'approved', 'approved' );
				break;

			case 'resend':
				if ( 'awaiting_payment' === $app['status'] ) {
					Application_Approver::send_payment_link( $app );
				}
				self::back( 'awaiting_payment', 'resent' );
				break;

			case 'decline':
				Application_Approver::decline( $app, $reason, $notify );
				self::back( 'declined', 'declined' );
				break;

			case 'delete':
				if ( in_array( $app['status'], array( 'approved', 'declined' ), true ) ) {
					wp_delete_post( $id, true );
					self::back( 'all', 'deleted' );
				}
				break;
		}

		self::back( 'pending' );
	}

	/**
	 * Redirects back to the screen.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  string $status  Tab to show.
	 * @param  string $notice  Notice code.
	 * @param  string $error   Error message.
	 * @return void
	 */
	private static function back( string $status, string $notice = '', string $error = '' ): void {
		$url = self::url( $status );

		if ( '' !== $notice ) {
			$url = add_query_arg( 'sm_notice', $notice, $url );
		}

		if ( '' !== $error ) {
			$url = add_query_arg( 'sm_error', rawurlencode( $error ), $url );
		}

		wp_safe_redirect( $url );
		exit;
	}
}
