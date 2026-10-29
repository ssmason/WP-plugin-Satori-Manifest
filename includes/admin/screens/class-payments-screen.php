<?php
/**
 * Payments screen (administrators only).
 *
 * Subscriptions, recent payments and Stripe setup status in one place. Also
 * lets staff take a client's page offline (or back online) — the plugin
 * never does that automatically when a subscription lapses.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Admin\Screens;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Billing\Payments;
use SatoriManifest\Billing\Stripe_Settings;
use SatoriManifest\Billing\Subscriptions;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Core\Security;
use SatoriManifest\Registration\Applications;
use SatoriManifest\Registration\Plans;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Payments_Screen
 *
 * Renders the Payments screen and handles its page on/off action.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Payments_Screen {

	/**
	 * Action name for admin-post.php (page online/offline).
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	public const ACTION = 'satori_manifest_page_visibility';

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Returns the screen URL.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return string
	 */
	public static function url(): string {
		return Admin_Menu::url( Admin_Menu::PAYMENTS_SLUG );
	}

	/**
	 * Renders the screen.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view payments.', 'satori-manifest' ), 403 );
		}

		$satori_manifest_rows     = Subscriptions::all();
		$satori_manifest_payments = Payments::recent( 50 );
		$satori_manifest_stats    = self::stats( $satori_manifest_rows );
		$satori_manifest_awaiting = Applications::all( 'awaiting_payment' );
		$satori_manifest_stripe   = array(
			'mode'        => Stripe_Settings::mode(),
			'webhook'     => Stripe_Settings::has_webhook_secret(),
			'webhook_url' => Stripe_Settings::webhook_url(),
			'dashboard'   => Stripe_Settings::dashboard_url(),
		);
		$satori_manifest_plans    = Plans::all();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own redirect.
		$satori_manifest_notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';

		require SATORI_MANIFEST_PATH . 'templates/admin/payments.php';
	}

	/**
	 * Takes a client's page offline (draft) or back online (published).
	 *
	 * Hooked to admin_post_satori_manifest_page_visibility.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function handle(): void {
		// The nonce is read here and verified by Security::verify_form_nonce().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$nonce = isset( $_POST[ Security::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Security::NONCE_FIELD ] ) ) : '';

		if ( ! Security::verify_form_nonce( $nonce, self::ACTION ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'satori-manifest' ), 403 );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$online  = ! empty( $_POST['online'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$page_id = Client_Role::is_client( $user_id ) ? Client_Repository::page_id( $user_id ) : 0;

		if ( $page_id ) {
			wp_update_post(
				array(
					'ID'          => $page_id,
					'post_status' => $online ? 'publish' : 'draft',
				)
			);
		}

		wp_safe_redirect( add_query_arg( 'sm_notice', $online ? 'online' : 'offline', self::url() ) );
		exit;
	}

	/**
	 * Summarises subscriptions for the headline figures.
	 *
	 * Monthly recurring revenue counts yearly plans as a twelfth.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<int,array<string,mixed>> $rows  Subscriptions::all() rows.
	 * @return array{active:int,attention:int,mrr:float,last_30:array<string,float>}
	 */
	public static function stats( array $rows ): array {
		$active    = 0;
		$attention = 0;
		$mrr       = 0.0;

		foreach ( $rows as $row ) {
			$subscription = $row['subscription'];

			if ( Subscriptions::in_good_standing( $subscription['status'] ) ) {
				++$active;
				$mrr += 'year' === $subscription['interval'] ? $subscription['amount'] / 12 : $subscription['amount'];
			} elseif ( in_array( $subscription['status'], array( 'past_due', 'unpaid', 'incomplete' ), true ) ) {
				++$attention;
			}
		}

		return array(
			'active'    => $active,
			'attention' => $attention,
			'mrr'       => round( $mrr, 2 ),
			'last_30'   => Payments::totals_last_30_days(),
		);
	}
}
