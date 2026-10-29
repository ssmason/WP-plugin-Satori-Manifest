<?php
/**
 * Stripe webhooks.
 *
 * Endpoint: POST /wp-json/satori-manifest/v1/stripe/webhook
 *
 * Register it in the Stripe dashboard (Developers → Webhooks) with these
 * events, and put its signing secret in STRIPE_WEBHOOK_SECRET:
 *
 *   checkout.session.completed      first payment → create the client account
 *   invoice.paid                    renewal → record payment, email a receipt
 *   invoice.payment_failed          record, email the client and staff
 *   customer.subscription.updated   keep plan / status / renewal date in sync
 *   customer.subscription.deleted   mark cancelled, email the client and staff
 *
 * Every request is verified against the signing secret; events are handled
 * at most once.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Billing;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Admin\Screens\Payments_Screen;
use SatoriManifest\Mail\Mailer;
use SatoriManifest\Registration\Application_Approver;
use SatoriManifest\Registration\Applications;
use SatoriManifest\Registration\Plans;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webhooks
 *
 * Receives, verifies and dispatches Stripe events.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Webhooks {

	/**
	 * REST namespace.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	public const ROUTE_NAMESPACE = 'satori-manifest/v1';

	/**
	 * REST route.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	public const ROUTE = '/stripe/webhook';

	/**
	 * Seconds a signed request stays valid (Stripe's recommended tolerance).
	 *
	 * @since 3.0.0
	 * @var   int
	 */
	private const TOLERANCE = 300;

	/**
	 * Option holding recently processed event IDs.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	private const PROCESSED_OPTION = 'satori_manifest_stripe_events';

	/**
	 * Registers the REST route. Hooked to 'rest_api_init'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function register_route(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'receive' ),
				// Stripe cannot log in; every request is authenticated by its signature instead.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Verifies and handles a webhook request.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  \WP_REST_Request $request  Request.
	 * @return \WP_REST_Response
	 */
	public static function receive( \WP_REST_Request $request ): \WP_REST_Response {
		$payload   = (string) $request->get_body();
		$signature = (string) $request->get_header( 'stripe_signature' );

		if ( ! self::verify( $payload, $signature, Stripe_Settings::webhook_secret(), time() ) ) {
			return new \WP_REST_Response( array( 'error' => 'invalid signature' ), 400 );
		}

		$event = json_decode( $payload, true );

		if ( ! is_array( $event ) || empty( $event['id'] ) || empty( $event['type'] ) ) {
			return new \WP_REST_Response( array( 'error' => 'invalid payload' ), 400 );
		}

		if ( self::already_processed( (string) $event['id'] ) ) {
			return new \WP_REST_Response(
				array(
					'received'  => true,
					'duplicate' => true,
				),
				200
			);
		}

		self::dispatch( $event );
		self::mark_processed( (string) $event['id'] );

		return new \WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Checks a Stripe-Signature header (t=…,v1=…) against the payload.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $payload    Raw request body.
	 * @param  string $header     Stripe-Signature header.
	 * @param  string $secret     Webhook signing secret.
	 * @param  int    $now        Current timestamp.
	 * @return bool
	 */
	public static function verify( string $payload, string $header, string $secret, int $now ): bool {
		if ( '' === $secret || '' === $header ) {
			return false;
		}

		$timestamp  = 0;
		$signatures = array();

		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );

			if ( 2 !== count( $pair ) ) {
				continue;
			}

			if ( 't' === $pair[0] ) {
				$timestamp = (int) $pair[1];
			} elseif ( 'v1' === $pair[0] ) {
				$signatures[] = $pair[1];
			}
		}

		if ( ! $timestamp || ! $signatures || abs( $now - $timestamp ) > self::TOLERANCE ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );

		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Routes an event to its handler.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,mixed> $event  Stripe event.
	 * @return void
	 */
	public static function dispatch( array $event ): void {
		$object = (array) ( $event['data']['object'] ?? array() );

		switch ( (string) $event['type'] ) {
			case 'checkout.session.completed':
				self::on_checkout_completed( $object );
				break;

			case 'invoice.paid':
				self::on_invoice_paid( $object );
				break;

			case 'invoice.payment_failed':
				self::on_invoice_failed( $object );
				break;

			case 'customer.subscription.created':
			case 'customer.subscription.updated':
				Subscriptions::sync_from_stripe( $object );
				break;

			case 'customer.subscription.deleted':
				self::on_subscription_deleted( $object );
				break;
		}

		/**
		 * Fires after a verified Stripe event has been handled.
		 *
		 * @since 3.0.0
		 * @param array<string,mixed> $event  Stripe event.
		 */
		do_action( 'satori_manifest_stripe_event', $event );
	}

	/**
	 * First payment: creates the client account and links the subscription.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,mixed> $session  Checkout session.
	 * @return void
	 */
	private static function on_checkout_completed( array $session ): void {
		$app_id = (int) ( $session['client_reference_id'] ?? 0 );
		$app    = $app_id ? Applications::get( $app_id ) : null;

		if ( ! $app || 'subscription' !== ( $session['mode'] ?? '' ) ) {
			return;
		}

		$user_id = Application_Approver::activate( $app_id, true );

		if ( is_wp_error( $user_id ) ) {
			self::notify_staff( __( 'Payment received but the account could not be created', 'satori-manifest' ), $app['business_name'], (string) $app['email'], $user_id->get_error_message() );
			return;
		}

		$customer        = (string) ( $session['customer'] ?? '' );
		$subscription_id = (string) ( $session['subscription'] ?? '' );
		$interval        = Plans::normalize_interval( (string) $app['interval'] );

		Subscriptions::save(
			$user_id,
			array(
				'customer' => $customer,
				'id'       => $subscription_id,
				'status'   => 'active',
				'plan'     => (string) $app['plan'],
				'interval' => $interval,
				'amount'   => Stripe_Client::from_minor_units( (int) ( $session['amount_total'] ?? 0 ) ),
				'currency' => (string) ( $session['currency'] ?? Plans::currency() ),
			)
		);

		// Fill in the renewal date, and tag the subscription with the client in Stripe.
		if ( '' !== $subscription_id ) {
			$subscription = Stripe_Client::request( 'POST', 'subscriptions/' . rawurlencode( $subscription_id ), array( 'metadata' => array( 'user_id' => (string) $user_id ) ) );

			if ( is_array( $subscription ) ) {
				Subscriptions::sync_from_stripe( $subscription, $user_id );
			}
		}

		Payments::record(
			array(
				'user_id'     => $user_id,
				'invoice'     => (string) ( $session['invoice'] ?? '' ),
				'status'      => 'paid',
				'amount'      => Stripe_Client::from_minor_units( (int) ( $session['amount_total'] ?? 0 ) ),
				'currency'    => (string) ( $session['currency'] ?? '' ),
				/* translators: %s: plan title. */
				'description' => sprintf( __( 'First payment — %s', 'satori-manifest' ), Plans::title( (string) $app['plan'] ) ),
			)
		);

		self::notify_staff(
			__( 'New subscription', 'satori-manifest' ),
			(string) $app['business_name'],
			(string) $app['email'],
			Plans::title( (string) $app['plan'] ) . ' — ' . Plans::format( Stripe_Client::from_minor_units( (int) ( $session['amount_total'] ?? 0 ) ) ) . ' ' . Plans::period_label( $interval )
		);
	}

	/**
	 * Successful invoice: records it and emails a receipt for renewals.
	 *
	 * The first invoice is covered by the welcome email.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,mixed> $invoice  Stripe invoice.
	 * @return void
	 */
	private static function on_invoice_paid( array $invoice ): void {
		$user_id = Subscriptions::user_for_customer( (string) ( $invoice['customer'] ?? '' ) );

		if ( ! $user_id ) {
			return;
		}

		$amount = Stripe_Client::from_minor_units( (int) ( $invoice['amount_paid'] ?? 0 ) );

		Payments::record(
			array(
				'user_id'     => $user_id,
				'invoice'     => (string) ( $invoice['id'] ?? '' ),
				'number'      => (string) ( $invoice['number'] ?? '' ),
				'status'      => 'paid',
				'amount'      => $amount,
				'currency'    => (string) ( $invoice['currency'] ?? '' ),
				'description' => (string) ( $invoice['lines']['data'][0]['description'] ?? '' ),
				'invoice_url' => (string) ( $invoice['hosted_invoice_url'] ?? '' ),
			)
		);

		Subscriptions::save( $user_id, array( 'status' => 'active' ) );

		if ( 'subscription_cycle' !== ( $invoice['billing_reason'] ?? '' ) ) {
			return;
		}

		$subscription = Subscriptions::for_user( $user_id );

		self::email_client(
			'payment-receipt',
			$user_id,
			array(
				'amount'      => Plans::format( $amount, (string) ( $invoice['currency'] ?? '' ) ),
				'plan'        => Plans::title( $subscription['plan'] ),
				'number'      => (string) ( $invoice['number'] ?? '' ),
				'invoice_url' => (string) ( $invoice['hosted_invoice_url'] ?? '' ),
			)
		);
	}

	/**
	 * Failed invoice: records it and tells the client and staff.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,mixed> $invoice  Stripe invoice.
	 * @return void
	 */
	private static function on_invoice_failed( array $invoice ): void {
		$user_id = Subscriptions::user_for_customer( (string) ( $invoice['customer'] ?? '' ) );

		if ( ! $user_id ) {
			return;
		}

		$amount = Stripe_Client::from_minor_units( (int) ( $invoice['amount_due'] ?? 0 ) );
		$user   = get_userdata( $user_id );

		Payments::record(
			array(
				'user_id'     => $user_id,
				'invoice'     => (string) ( $invoice['id'] ?? '' ),
				'number'      => (string) ( $invoice['number'] ?? '' ),
				'status'      => 'failed',
				'amount'      => $amount,
				'currency'    => (string) ( $invoice['currency'] ?? '' ),
				'invoice_url' => (string) ( $invoice['hosted_invoice_url'] ?? '' ),
				'reason'      => (string) ( $invoice['last_finalization_error']['message'] ?? '' ),
			)
		);

		Subscriptions::save( $user_id, array( 'status' => 'past_due' ) );

		self::email_client( 'payment-failed', $user_id, array( 'amount' => Plans::format( $amount, (string) ( $invoice['currency'] ?? '' ) ) ) );

		if ( $user ) {
			self::notify_staff( __( 'Payment failed', 'satori-manifest' ), $user->display_name, $user->user_email, Plans::format( $amount, (string) ( $invoice['currency'] ?? '' ) ) );
		}
	}

	/**
	 * Subscription ended: marks it cancelled and tells the client and staff.
	 *
	 * The client's page is left online — whether to unpublish it is a staff
	 * decision, made from the Payments screen.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,mixed> $subscription  Stripe subscription.
	 * @return void
	 */
	private static function on_subscription_deleted( array $subscription ): void {
		$user_id = Subscriptions::sync_from_stripe( $subscription );

		if ( ! $user_id ) {
			return;
		}

		Subscriptions::save( $user_id, array( 'status' => 'canceled' ) );
		self::email_client( 'subscription-cancelled', $user_id );

		$user = get_userdata( $user_id );

		if ( $user ) {
			self::notify_staff( __( 'Subscription cancelled', 'satori-manifest' ), $user->display_name, $user->user_email, Plans::title( Subscriptions::for_user( $user_id )['plan'] ) );
		}
	}

	/**
	 * Emails a client using one of the billing templates.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string               $template  Template name.
	 * @param  int                  $user_id   Client user ID.
	 * @param  array<string,string> $vars      Extra placeholders.
	 * @return void
	 */
	private static function email_client( string $template, int $user_id, array $vars = array() ): void {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		Mailer::send(
			$template,
			$user->user_email,
			array_merge(
				array(
					'contact_name'  => '' !== $user->first_name ? $user->first_name : $user->display_name,
					'business_name' => $user->display_name,
					'billing_url'   => Admin_Menu::url( Admin_Menu::BILLING_SLUG ),
				),
				$vars
			)
		);
	}

	/**
	 * Emails staff about a billing event.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $event          What happened.
	 * @param  string $business_name  Client business name.
	 * @param  string $email          Client email.
	 * @param  string $details        One-line details.
	 * @return void
	 */
	private static function notify_staff( string $event, string $business_name, string $email, string $details ): void {
		foreach ( Mailer::staff_recipients() as $recipient ) {
			Mailer::send(
				'staff-billing-notice',
				$recipient,
				array(
					'event'         => $event,
					'business_name' => $business_name,
					'email'         => $email,
					'details'       => $details,
					'review_url'    => Payments_Screen::url(),
				)
			);
		}
	}

	/**
	 * Returns true when an event ID has been handled already.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $event_id  Stripe event ID.
	 * @return bool
	 */
	private static function already_processed( string $event_id ): bool {
		return in_array( $event_id, (array) get_option( self::PROCESSED_OPTION, array() ), true );
	}

	/**
	 * Remembers a handled event ID (the latest 200 are kept).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $event_id  Stripe event ID.
	 * @return void
	 */
	private static function mark_processed( string $event_id ): void {
		$ids   = (array) get_option( self::PROCESSED_OPTION, array() );
		$ids[] = $event_id;

		update_option( self::PROCESSED_OPTION, array_slice( array_unique( $ids ), -200 ), false );
	}
}
