<?php
/**
 * Stripe customer portal.
 *
 * Lets a client update their card, switch between monthly and yearly,
 * download invoices or cancel — on Stripe's hosted portal, so none of it
 * needs building here. Enable the portal once in the Stripe dashboard
 * (Settings → Billing → Customer portal).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Billing;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Core\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Billing_Portal
 *
 * Opens a portal session for the logged-in client.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Billing_Portal {

	/**
	 * Action name for admin-post.php.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	public const ACTION = 'satori_manifest_billing_portal';

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'open' ) );
	}

	/**
	 * Creates a portal session for the current client and redirects to it.
	 *
	 * Hooked to admin_post_satori_manifest_billing_portal.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function open(): void {
		// The nonce is read here and verified by Security::verify_form_nonce().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce = isset( $_POST[ Security::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Security::NONCE_FIELD ] ) ) : '';

		if ( ! Security::verify_form_nonce( $nonce, self::ACTION ) || ! Client_Role::is_client() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'satori-manifest' ), 403 );
		}

		$back     = Admin_Menu::url( Admin_Menu::BILLING_SLUG );
		$customer = Subscriptions::for_user( get_current_user_id() )['customer'];

		if ( '' === $customer ) {
			wp_safe_redirect( add_query_arg( 'sm_error', 'no-subscription', $back ) );
			exit;
		}

		$session = Stripe_Client::request(
			'POST',
			'billing_portal/sessions',
			array(
				'customer'   => $customer,
				'return_url' => $back,
			)
		);

		if ( is_wp_error( $session ) || empty( $session['url'] ) ) {
			wp_safe_redirect( add_query_arg( 'sm_error', 'portal', $back ) );
			exit;
		}

		wp_safe_redirect( (string) $session['url'], 303 );
		exit;
	}
}
