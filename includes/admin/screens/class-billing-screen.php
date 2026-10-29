<?php
/**
 * Billing screen (clients).
 *
 * Shows a client their plan, status, next payment and past payments, with a
 * button to Stripe's customer portal for card changes, invoices and
 * cancelling.
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
use SatoriManifest\Branding\Portal_Brand;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Billing_Screen
 *
 * Renders the client Billing screen.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Billing_Screen {

	/**
	 * Returns true when the Billing menu should show for a client.
	 *
	 * Shown when Stripe is connected or the client already has a subscription.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  int $user_id  Client user ID.
	 * @return bool
	 */
	public static function is_available( int $user_id ): bool {
		return Stripe_Settings::is_configured() || 'none' !== Subscriptions::for_user( $user_id )['status'];
	}

	/**
	 * Renders the screen.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function render(): void {
		$satori_manifest_user_id      = Admin_Menu::current_client_id();
		$satori_manifest_subscription = Subscriptions::for_user( $satori_manifest_user_id );
		$satori_manifest_payments     = Payments::recent( 24, $satori_manifest_user_id );
		$satori_manifest_support      = Portal_Brand::support_email();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own redirect.
		$satori_manifest_error = isset( $_GET['sm_error'] ) ? sanitize_key( wp_unslash( $_GET['sm_error'] ) ) : '';

		require SATORI_MANIFEST_PATH . 'templates/admin/billing.php';
	}
}
