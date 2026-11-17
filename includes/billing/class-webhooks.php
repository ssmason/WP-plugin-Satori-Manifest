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
 * Every request is verified against the signing secret (Webhook_Signature);
 * events are handled at most once (Webhook_Events). Emails go through
 * Billing_Notifier.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Billing;

use SatoriManifest\Registration\Application_Approver;
use SatoriManifest\Registration\Applications;
use SatoriManifest\Registration\Plans;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webhooks
 *
 * Receives Stripe events and handles each event type.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Webhooks {

	/**
	 * User meta: the invoice that paid the one-off setup at sign-up (not a
	 * subscription payment, so it must not count as the first payment).
	 */
	public const META_SETUP_INVOICE = '_satori_manifest_setup_invoice';

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

		if ( ! Webhook_Signature::verify( $payload, $signature, Stripe_Settings::webhook_secret(), time() ) ) {
			return new \WP_REST_Response( array( 'error' => 'invalid signature' ), 400 );
		}

		$event = json_decode( $payload, true );

		if ( ! is_array( $event ) || empty( $event['id'] ) || empty( $event['type'] ) ) {
			return new \WP_REST_Response( array( 'error' => 'invalid payload' ), 400 );
		}

		if ( Webhook_Events::seen( (string) $event['id'] ) ) {
			return new \WP_REST_Response(
				array(
					'received'  => true,
					'duplicate' => true,
				),
				200
			);
		}

		self::dispatch( $event );
		Webhook_Events::remember( (string) $event['id'] );

		return new \WP_REST_Response( array( 'received' => true ), 200 );
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
				if ( 'payment' === ( $object['mode'] ?? '' ) && Printed_Codes::ORDER === ( $object['metadata']['order'] ?? '' ) ) {
					Printed_Codes::on_order_paid( $object );
				} else {
					self::on_checkout_completed( $object );
				}
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
			Billing_Notifier::staff( __( 'Payment received but the account could not be created', 'satori-manifest' ), $app['business_name'], (string) $app['email'], $user_id->get_error_message() );
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
				'amount'   => Plans::price( (string) $app['plan'], $interval ),
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

		$amount = Stripe_Client::from_minor_units( (int) ( $session['amount_total'] ?? 0 ) );
		$trial  = Plans::trial_days() > 0;

		// During a free trial the only charge at checkout is the one-off setup (if chosen).
		if ( $amount > 0 && $trial ) {
			update_user_meta( $user_id, self::META_SETUP_INVOICE, (string) ( $session['invoice'] ?? '' ) );
		}

		if ( $amount > 0 ) {
			Payments::record(
				array(
					'user_id'     => $user_id,
					'invoice'     => (string) ( $session['invoice'] ?? '' ),
					'status'      => 'paid',
					'amount'      => $amount,
					'currency'    => (string) ( $session['currency'] ?? '' ),
					'description' => $trial
						? __( 'Page set up for you', 'satori-manifest' )
						/* translators: %s: plan title. */
						: sprintf( __( 'First payment: %s', 'satori-manifest' ), Plans::title( (string) $app['plan'] ) ),
				)
			);
		}

		$price = Plans::format( Plans::price( (string) $app['plan'], $interval ) ) . ' ' . Plans::period_label( $interval );
		$codes = Printed_Codes::on_signup( $user_id, $app, $session );

		Billing_Notifier::staff(
			__( 'New subscription', 'satori-manifest' ),
			(string) $app['business_name'],
			(string) $app['email'],
			( ! $trial
				? Plans::title( (string) $app['plan'] ) . ', ' . $price . '. ' . $codes
				/* translators: 1: plan title, 2: trial length in days, 3: price and period, e.g. "€9.00 per month". */
				: sprintf( __( '%1$s, free for %2$d days, then %3$s. Send the digital QR code now; print and post the stand after the first payment.', 'satori-manifest' ), Plans::title( (string) $app['plan'] ), Plans::trial_days(), $price ) . ' ' . $codes )
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

		// The €0 invoice at the start of a free trial is not a payment.
		if ( $amount <= 0 ) {
			return;
		}

		// The one-off setup paid at sign-up: already recorded at checkout, and not a subscription payment.
		if ( '' !== (string) ( $invoice['id'] ?? '' ) && get_user_meta( $user_id, self::META_SETUP_INVOICE, true ) === $invoice['id'] ) {
			return;
		}

		$first = self::is_first_payment( $user_id, (string) ( $invoice['id'] ?? '' ) );

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

		$subscription = Subscriptions::for_user( $user_id );

		// First real payment (normally the end of the free trial): time to print and post the stand.
		if ( $first ) {
			$user = get_userdata( $user_id );

			Billing_Notifier::staff(
				__( 'First payment received: print and post the QR code', 'satori-manifest' ),
				$user ? (string) $user->display_name : '',
				$user ? (string) $user->user_email : '',
				Plans::title( $subscription['plan'] ) . ', ' . Plans::format( $amount, (string) ( $invoice['currency'] ?? '' ) ) . '. ' . Printed_Codes::take_pending( $user_id )
			);
		}

		if ( 'subscription_cycle' !== ( $invoice['billing_reason'] ?? '' ) ) {
			return;
		}

		Billing_Notifier::client(
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
	 * Whether this invoice is the client's first real (non-zero) subscription payment.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  int    $user_id     Client user ID.
	 * @param  string $invoice_id  Stripe invoice ID being handled.
	 * @return bool
	 */
	private static function is_first_payment( int $user_id, string $invoice_id ): bool {
		$setup = (string) get_user_meta( $user_id, self::META_SETUP_INVOICE, true );

		foreach ( Payments::recent( 50, $user_id ) as $payment ) {
			if ( '' !== $setup && $payment['invoice'] === $setup ) {
				continue;
			}

			// Printed-code orders (recorded by Checkout session, cs_…) are not subscription payments.
			if ( $payment['amount'] > 0 && $payment['invoice'] !== $invoice_id && ! str_starts_with( $payment['invoice'], 'cs_' ) ) {
				return false;
			}
		}

		return true;
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

		Billing_Notifier::client( 'payment-failed', $user_id, array( 'amount' => Plans::format( $amount, (string) ( $invoice['currency'] ?? '' ) ) ) );

		if ( $user ) {
			Billing_Notifier::staff( __( 'Payment failed', 'satori-manifest' ), $user->display_name, $user->user_email, Plans::format( $amount, (string) ( $invoice['currency'] ?? '' ) ) );
		}
	}

	/**
	 * Subscription ended: marks it cancelled and tells the client and staff.
	 *
	 * The client's page is left online - whether to unpublish it is a staff
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
		Billing_Notifier::client( 'subscription-cancelled', $user_id );

		$user = get_userdata( $user_id );

		if ( $user ) {
			Billing_Notifier::staff( __( 'Subscription cancelled', 'satori-manifest' ), $user->display_name, $user->user_email, Plans::title( Subscriptions::for_user( $user_id )['plan'] ) );
		}
	}
}
