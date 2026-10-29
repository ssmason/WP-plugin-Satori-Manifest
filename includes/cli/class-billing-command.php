<?php
/**
 * WP-CLI: billing.
 *
 * Usage:
 *   wp satori-manifest billing status
 *   wp satori-manifest billing simulate-payment <application_id>
 *
 * "simulate-payment" runs the same code as a real Stripe
 * checkout.session.completed webhook, without calling Stripe — handy for
 * local testing before keys are added.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Cli;

use SatoriManifest\Billing\Stripe_Client;
use SatoriManifest\Billing\Stripe_Settings;
use SatoriManifest\Billing\Webhooks;
use SatoriManifest\Registration\Applications;
use SatoriManifest\Registration\Plans;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Billing status and local payment simulation.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Billing_Command {

	/**
	 * Shows whether Stripe is configured.
	 *
	 * ## EXAMPLES
	 *
	 *     wp satori-manifest billing status
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public function status(): void {
		\WP_CLI::log( sprintf( 'Stripe:   %s', '' !== Stripe_Settings::mode() ? Stripe_Settings::mode() . ' mode' : 'not configured (STRIPE_SECRET_KEY)' ) );
		\WP_CLI::log( sprintf( 'Webhook:  %s', Stripe_Settings::has_webhook_secret() ? 'signing secret set' : 'no signing secret (STRIPE_WEBHOOK_SECRET)' ) );
		\WP_CLI::log( sprintf( 'Endpoint: %s', Stripe_Settings::webhook_url() ) );
		\WP_CLI::log( sprintf( 'Currency: %s', strtoupper( Plans::currency() ) ) );
	}

	/**
	 * Simulates a successful first payment for an application awaiting payment.
	 *
	 * Creates the client account, records the payment and sends the emails —
	 * exactly what the Stripe webhook does. No request is made to Stripe.
	 *
	 * ## OPTIONS
	 *
	 * <application_id>
	 * : Application ID (Manifest → Applications).
	 *
	 * ## EXAMPLES
	 *
	 *     wp satori-manifest billing simulate-payment 140
	 *
	 * @subcommand simulate-payment
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<int,string> $args  Positional args.
	 * @return void
	 */
	public function simulate_payment( array $args ): void {
		$app = Applications::get( absint( $args[0] ?? 0 ) );

		if ( ! $app ) {
			\WP_CLI::error( 'Application not found.' );
		}

		$interval = Plans::normalize_interval( (string) $app['interval'] );
		$token    = substr( md5( (string) wp_rand() ), 0, 12 );

		// Keep the simulation offline: answer any Stripe call it makes.
		add_filter( 'satori_manifest_pre_stripe_request', static fn() => new \WP_Error( 'simulated', 'Simulated — Stripe not called.' ) );

		Webhooks::dispatch(
			array(
				'id'   => 'evt_sim_' . $token,
				'type' => 'checkout.session.completed',
				'data' => array(
					'object' => array(
						'id'                  => 'cs_sim_' . $token,
						'mode'                => 'subscription',
						'client_reference_id' => (string) $app['id'],
						'customer'            => 'cus_sim_' . $token,
						'subscription'        => 'sub_sim_' . $token,
						'invoice'             => 'in_sim_' . $token,
						'amount_total'        => Stripe_Client::to_minor_units( Plans::price( (string) $app['plan'], $interval ) ),
						'currency'            => Plans::currency(),
					),
				),
			)
		);

		$app = Applications::get( (int) $app['id'] );
		\WP_CLI::success( sprintf( 'Application #%d is now "%s" (client #%d).', $app['id'], $app['status'], $app['user_id'] ) );
	}
}
