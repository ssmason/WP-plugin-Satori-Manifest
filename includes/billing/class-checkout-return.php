<?php
/**
 * Return from Stripe Checkout.
 *
 * Stripe sends a paying customer back to {home}/?sm_welcome={application}&session_id={session}.
 * When Stripe confirms that session is complete and belongs to that application,
 * the account is created (if the webhook has not done it yet), the customer is
 * logged in and taken to their dashboard, where they choose a password. No
 * waiting for the welcome email.
 *
 * Safeguards: the session must match the one stored for the application, Stripe
 * must report it complete, the link works once, only for a day, and never
 * replaces someone who is already logged in. Anything else falls back to the
 * "payment received, check your email" message.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Billing;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Registration\Application_Approver;
use SatoriManifest\Registration\Applications;
use SatoriManifest\Registration\Registration_Form;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Checkout_Return
 *
 * Finishes a sign-up when the customer comes back from Stripe.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */
class Checkout_Return {

	/**
	 * Query var carrying the application ID.
	 *
	 * @since 3.8.0
	 * @var   string
	 */
	public const QUERY_VAR = 'sm_welcome';

	/**
	 * Application meta set once the return link has been used.
	 *
	 * @since 3.8.0
	 * @var   string
	 */
	public const USED_META = 'welcome_used';

	/**
	 * How long after sign-up the return link logs the customer in.
	 *
	 * @since 3.8.0
	 * @var   int
	 */
	public const MAX_AGE = DAY_IN_SECONDS;

	/**
	 * Registers the handler.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'maybe_finish' ), 5 );
	}

	/**
	 * The success URL to give Stripe for an application.
	 *
	 * {CHECKOUT_SESSION_ID} must reach Stripe unencoded, so it is appended by hand.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  int $application_id  Application ID.
	 * @return string
	 */
	public static function success_url( int $application_id ): string {
		return add_query_arg( self::QUERY_VAR, $application_id, home_url( '/' ) ) . '&session_id={CHECKOUT_SESSION_ID}';
	}

	/**
	 * Handles the return from Stripe.
	 *
	 * Hooked to 'template_redirect'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return void
	 */
	public static function maybe_finish(): void {
		// The Stripe session ID (checked with Stripe below) authenticates the request; there is no form to nonce.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}

		$id      = absint( $_GET[ self::QUERY_VAR ] );
		$session = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$app     = $id ? Applications::get( $id ) : null;
		$user_id = $app ? self::verified_user( $app, $session ) : 0;

		if ( ! $user_id ) {
			self::fallback( $app );
		}

		Applications::set_meta( $id, self::USED_META, '1' );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, false, is_ssl() );

		wp_safe_redirect( add_query_arg( 'sm_notice', 'welcome', Admin_Menu::url( Admin_Menu::HOME_SLUG ) ) );
		exit;
	}

	/**
	 * Returns the client to log in, or 0 when the return link must not log anyone in.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  array<string,mixed> $app      Application.
	 * @param  string              $session  Session ID from the URL.
	 * @return int
	 */
	private static function verified_user( array $app, string $session ): int {
		$id     = (int) $app['id'];
		$stored = Applications::get_meta( $id, 'checkout_session' );
		$age    = time() - (int) strtotime( (string) $app['date'] );

		if (
			is_user_logged_in()
			|| '' === $session
			|| '' === $stored
			|| ! hash_equals( $stored, $session )
			|| '1' === Applications::get_meta( $id, self::USED_META )
			|| $age > self::MAX_AGE
		) {
			return 0;
		}

		$checkout = Stripe_Client::request( 'GET', 'checkout/sessions/' . rawurlencode( $session ) );

		if (
			! is_array( $checkout )
			|| 'complete' !== ( $checkout['status'] ?? '' )
			|| (string) ( $checkout['client_reference_id'] ?? '' ) !== (string) $id
		) {
			return 0;
		}

		// Usually the webhook has already created the account; this is a no-op then.
		$user_id = Application_Approver::activate( $id, true );

		return is_wp_error( $user_id ) ? 0 : (int) $user_id;
	}

	/**
	 * Sends the visitor to the "payment received" message on the Register page.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  array<string,mixed>|null $app  Application, when known.
	 * @return void
	 */
	private static function fallback( ?array $app ): void {
		$page = $app ? Applications::get_meta( (int) $app['id'], 'return_url' ) : '';
		$page = '' !== $page ? $page : home_url( '/' );

		wp_safe_redirect( add_query_arg( Registration_Form::RESULT_VAR, 'paid', $page ) . '#sm-register' );
		exit;
	}
}
