<?php
/**
 * Stripe Checkout.
 *
 * Approved applicants receive a payment link (payment_url()). Opening it
 * creates a fresh Stripe Checkout session for their plan and billing
 * interval and redirects them to Stripe's hosted payment page — so card
 * details never touch this site. Stripe then calls the webhook, which
 * creates the client account.
 *
 * Payment links never expire on our side; each visit makes a new Stripe
 * session (Stripe sessions expire after 24 hours).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Billing;

use SatoriManifest\Registration\Applications;
use SatoriManifest\Registration\Plans;
use SatoriManifest\Registration\Registration_Form;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Checkout
 *
 * Payment links and Checkout session creation.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Checkout {

	/**
	 * Query var carrying the application ID on payment links.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	public const QUERY_VAR = 'sm_pay';

	/**
	 * Hosts the visitor may be redirected to.
	 *
	 * @since 3.0.0
	 * @var   array<int,string>
	 */
	private const STRIPE_HOSTS = array( 'checkout.stripe.com', 'billing.stripe.com' );

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'maybe_start' ) );
		add_filter( 'allowed_redirect_hosts', array( self::class, 'allow_stripe_hosts' ) );
	}

	/**
	 * Lets wp_safe_redirect() send visitors to Stripe's hosted pages.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<int,string> $hosts  Allowed hosts.
	 * @return array<int,string>
	 */
	public static function allow_stripe_hosts( array $hosts ): array {
		return array_merge( $hosts, self::STRIPE_HOSTS );
	}

	/**
	 * Returns the payment link for an application.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  int $application_id  Application ID.
	 * @return string
	 */
	public static function payment_url( int $application_id ): string {
		$token = Applications::get_meta( $application_id, 'checkout_token' );

		if ( '' === $token ) {
			$token = wp_generate_password( 32, false );
			Applications::set_meta( $application_id, 'checkout_token', $token );
		}

		return add_query_arg(
			array(
				self::QUERY_VAR => $application_id,
				'key'           => $token,
			),
			home_url( '/' )
		);
	}

	/**
	 * Handles a payment link: validates it and redirects to Stripe.
	 *
	 * Hooked to 'template_redirect'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function maybe_start(): void {
		// The secret key in the link authenticates the request; there is no form to nonce.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}

		$id  = absint( $_GET[ self::QUERY_VAR ] );
		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$app   = $id ? Applications::get( $id ) : null;
		$token = $app ? Applications::get_meta( $id, 'checkout_token' ) : '';

		if ( ! $app || '' === $token || ! hash_equals( $token, $key ) ) {
			self::fail( __( 'This payment link is not valid. Please use the link in your approval email, or contact us.', 'satori-manifest' ) );
		}

		if ( 'approved' === $app['status'] ) {
			wp_safe_redirect( self::return_url( $app, 'paid' ) );
			exit;
		}

		if ( 'awaiting_payment' !== $app['status'] ) {
			self::fail( __( 'This application is not waiting for payment. Please contact us if you think this is a mistake.', 'satori-manifest' ) );
		}

		$session = self::create_session( $app );

		if ( is_wp_error( $session ) || empty( $session['url'] ) ) {
			self::fail( __( 'We could not start the payment just now. Please try the link again in a few minutes.', 'satori-manifest' ) );
		}

		Applications::set_meta( $id, 'checkout_session', (string) $session['id'] );

		wp_safe_redirect( (string) $session['url'], 303 );
		exit;
	}

	/**
	 * Creates a Stripe Checkout session for an application.
	 *
	 * Uses the plan's pre-created Stripe Price when one is configured,
	 * otherwise inline price data, so nothing needs setting up in Stripe.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,mixed> $app  Application.
	 * @return array<string,mixed>|\WP_Error  Stripe session or error.
	 */
	public static function create_session( array $app ) {
		$plan     = (string) $app['plan'];
		$interval = Plans::normalize_interval( (string) $app['interval'] );
		$amount   = Plans::price( $plan, $interval );

		if ( $amount <= 0 && '' === Plans::stripe_price( $plan, $interval ) ) {
			return new \WP_Error( 'satori_manifest_no_price', __( 'This plan has no price for the chosen billing period.', 'satori-manifest' ) );
		}

		$metadata = array(
			'application_id' => (string) $app['id'],
			'plan'           => $plan,
			'interval'       => $interval,
			'business_name'  => (string) $app['business_name'],
		);

		$params = array(
			'mode'                       => 'subscription',
			'customer_email'             => (string) $app['email'],
			'client_reference_id'        => (string) $app['id'],
			'line_items'                 => array( self::line_item( $plan, $interval, $amount ) ),
			'metadata'                   => $metadata,
			'subscription_data'          => array( 'metadata' => $metadata ),
			'allow_promotion_codes'      => 'true',
			'billing_address_collection' => 'auto',
			'locale'                     => 'auto',
			'success_url'                => self::return_url( $app, 'paid' ),
			'cancel_url'                 => self::return_url( $app, 'payment-cancelled' ),
		);

		/**
		 * Filters the Stripe Checkout session parameters (e.g. to add tax settings).
		 *
		 * @since 3.0.0
		 * @param array<string,mixed> $params  Session parameters.
		 * @param array<string,mixed> $app     Application.
		 */
		$params = (array) apply_filters( 'satori_manifest_checkout_params', $params, $app );

		return Stripe_Client::request( 'POST', 'checkout/sessions', $params );
	}

	/**
	 * Builds the single subscription line item.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $plan      Plan slug.
	 * @param  string $interval  "month" or "year".
	 * @param  float  $amount    Price in whole units.
	 * @return array<string,mixed>
	 */
	private static function line_item( string $plan, string $interval, float $amount ): array {
		$price_id = Plans::stripe_price( $plan, $interval );

		if ( '' !== $price_id ) {
			return array(
				'price'    => $price_id,
				'quantity' => 1,
			);
		}

		return array(
			'quantity'   => 1,
			'price_data' => array(
				'currency'     => Plans::currency(),
				'unit_amount'  => Stripe_Client::to_minor_units( $amount ),
				'recurring'    => array( 'interval' => $interval ),
				'product_data' => array(
					/* translators: 1: site name, 2: plan title. */
					'name' => sprintf( __( '%1$s — %2$s', 'satori-manifest' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), Plans::title( $plan ) ),
				),
			),
		);
	}

	/**
	 * Returns the page the applicant came from, with a registration result code.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,mixed> $app     Application.
	 * @param  string              $result  Result code shown by the registration form.
	 * @return string
	 */
	private static function return_url( array $app, string $result ): string {
		$page = Applications::get_meta( (int) $app['id'], 'return_url' );
		$page = '' !== $page ? $page : home_url( '/' );

		return add_query_arg( Registration_Form::RESULT_VAR, $result, $page ) . '#sm-register';
	}

	/**
	 * Stops with a friendly message.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $message  Message for the visitor.
	 * @return void
	 */
	private static function fail( string $message ): void {
		wp_die(
			esc_html( $message ),
			esc_html__( 'Payment', 'satori-manifest' ),
			array(
				'response'  => 400,
				'back_link' => false,
				'link_url'  => esc_url( home_url( '/' ) ),
				'link_text' => esc_html__( 'Back to the home page', 'satori-manifest' ),
			)
		);
	}
}
