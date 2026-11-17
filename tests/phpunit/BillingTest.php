<?php
/**
 * Tests for Stripe billing: checkout, webhooks, subscriptions and payments.
 *
 * No request ever reaches Stripe: the 'satori_manifest_pre_stripe_request'
 * filter answers every call.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Billing\Checkout;
use SatoriManifest\Billing\Payments;
use SatoriManifest\Billing\Subscriptions;
use SatoriManifest\Billing\Webhook_Signature;
use SatoriManifest\Billing\Webhooks;
use SatoriManifest\Registration\Application_Approver;
use SatoriManifest\Registration\Applications;
use SatoriManifest\Registration\Plans;

/**
 * Billing end to end, offline.
 */
class BillingTest extends Test_Case {

	/**
	 * Stripe requests made during the test: [ method, path, params ].
	 *
	 * @var array<int,array<int,mixed>>
	 */
	private array $requests = array();

	/**
	 * Fakes Stripe and switches billing on.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->requests = array();
		add_filter( 'satori_manifest_billing_enabled', '__return_true' );
		add_filter(
			'satori_manifest_pre_stripe_request',
			function ( $pre, string $method, string $path, array $params ) {
				$this->requests[] = array( $method, $path, $params );

				// A test may answer a request itself (an earlier filter).
				if ( null !== $pre ) {
					return $pre;
				}

				if ( 'checkout/sessions' === $path ) {
					return array(
						'id'  => 'cs_test_1',
						'url' => 'https://checkout.stripe.com/c/pay/cs_test_1',
					);
				}

				return new \WP_Error( 'offline', 'Stripe is not called in tests.' );
			},
			10,
			4
		);
	}

	/**
	 * The Checkout session carries the plan, price and application.
	 */
	public function test_checkout_session_parameters(): void {
		$app     = $this->make_application(
			array(
				'plan'     => 'plus',
				'interval' => 'year',
			)
		);
		$session = Checkout::create_session( $app );

		$this->assertSame( 'cs_test_1', $session['id'] );

		$params = $this->requests[0][2];
		$this->assertSame( 'subscription', $params['mode'] );
		$this->assertSame( (string) $app['id'], $params['client_reference_id'] );
		$this->assertSame( 'year', $params['line_items'][0]['price_data']['recurring']['interval'] );
		$this->assertSame( (int) ( Plans::price( 'plus', 'year' ) * 100 ), $params['line_items'][0]['price_data']['unit_amount'] );
		$this->assertSame( 'eur', $params['line_items'][0]['price_data']['currency'] );
		$this->assertSame( '30', $params['subscription_data']['trial_period_days'] );
		$this->assertSame( 'always', $params['payment_method_collection'] );
	}

	/**
	 * A free trial creates the account without a payment; the first real
	 * payment is recorded and tells staff to post the printed QR code.
	 */
	public function test_free_trial(): void {
		$app = $this->make_application();
		Application_Approver::approve( $app, '', '', false, true );
		$user_id = $this->complete_checkout( (int) $app['id'], 0 );

		$this->assertGreaterThan( 0, $user_id );
		$this->assertCount( 0, Payments::recent( 10, $user_id ) );

		$invoice = array(
			'id'             => 'in_trial',
			'customer'       => 'cus_1',
			'amount_paid'    => 0,
			'currency'       => 'eur',
			'billing_reason' => 'subscription_create',
		);
		$this->event( 'invoice.paid', $invoice );
		$this->assertCount( 0, Payments::recent( 10, $user_id ) );

		$this->event(
			'invoice.paid',
			array_merge(
				$invoice,
				array(
					'id'             => 'in_first',
					'amount_paid'    => 900,
					'billing_reason' => 'subscription_cycle',
				)
			)
		);

		$this->assertCount( 1, Payments::recent( 10, $user_id ) );
		$this->assertNotEmpty( preg_grep( '/^First payment received: print and post the QR code/', $this->sent_subjects() ) );
	}

	/**
	 * Signatures are checked against the secret and the timestamp.
	 */
	public function test_signature_verification(): void {
		$payload = '{"id":"evt_1"}';
		$now     = 1700000000;
		$valid   = 't=' . $now . ',v1=' . hash_hmac( 'sha256', $now . '.' . $payload, 'whsec_test' );

		$this->assertTrue( Webhook_Signature::verify( $payload, $valid, 'whsec_test', $now ) );
		$this->assertFalse( Webhook_Signature::verify( $payload, $valid, 'whsec_other', $now ) );
		$this->assertFalse( Webhook_Signature::verify( $payload . ' ', $valid, 'whsec_test', $now ) );
		$this->assertFalse( Webhook_Signature::verify( $payload, $valid, 'whsec_test', $now + 600 ) );
		$this->assertFalse( Webhook_Signature::verify( $payload, $valid, '', $now ) );
	}

	/**
	 * Unsigned requests are rejected by the REST endpoint.
	 */
	public function test_endpoint_rejects_unsigned_requests(): void {
		$request = new \WP_REST_Request( 'POST', '/' . Webhooks::ROUTE_NAMESPACE . Webhooks::ROUTE );
		$request->set_body( '{"id":"evt_1","type":"invoice.paid"}' );

		$this->assertSame( 400, rest_get_server()->dispatch( $request )->get_status() );
	}

	/**
	 * First payment creates the client, links the subscription and logs the payment.
	 */
	public function test_checkout_completed_activates_client(): void {
		$app = $this->make_application();
		Application_Approver::approve( $app, '', '', true, true );

		$user_id = $this->complete_checkout( (int) $app['id'] );

		$this->assertSame( 'approved', Applications::get( (int) $app['id'] )['status'] );
		$this->assertSame( 'active', Subscriptions::for_user( $user_id )['status'] );
		$this->assertSame( 'cus_1', Subscriptions::for_user( $user_id )['customer'] );
		$this->assertSame( $user_id, Subscriptions::user_for_customer( 'cus_1' ) );
		$this->assertCount( 1, Payments::recent( 10, $user_id ) );
		$this->assertContains( 'New subscription: Copper Kettle', $this->sent_subjects() );
	}

	/**
	 * Renewals are logged once and receipted; failures flag the subscription.
	 */
	public function test_invoice_events(): void {
		$app = $this->make_application();
		Application_Approver::approve( $app, '', '', false, true );
		$user_id = $this->complete_checkout( (int) $app['id'] );

		$invoice = array(
			'id'                 => 'in_2',
			'customer'           => 'cus_1',
			'amount_paid'        => 9000,
			'currency'           => 'eur',
			'billing_reason'     => 'subscription_cycle',
			'hosted_invoice_url' => 'https://invoice.stripe.com/i/2',
		);

		$this->event( 'invoice.paid', $invoice );
		$this->event( 'invoice.paid', $invoice );

		$this->assertCount( 2, Payments::recent( 10, $user_id ) );
		$this->assertContains( 'Payment received, thank you', $this->sent_subjects() );

		$this->event(
			'invoice.payment_failed',
			array_merge(
				$invoice,
				array(
					'id'         => 'in_3',
					'amount_due' => 9000,
				)
			)
		);

		$this->assertSame( 'past_due', Subscriptions::for_user( $user_id )['status'] );
		$this->assertContains( 'Your payment didn\'t go through', $this->sent_subjects() );
	}

	/**
	 * Self-service sign-ups left unpaid for a week are deleted; staff-approved ones stay.
	 */
	public function test_abandoned_checkouts_are_purged(): void {
		$abandoned = $this->make_application();
		$approved  = $this->make_application( array( 'email' => 'staff-approved@example.test' ) );

		Application_Approver::approve( $abandoned, '', '', false, true );
		Application_Approver::approve( $approved, '', '', false, true );
		Applications::set_meta( (int) $abandoned['id'], \SatoriManifest\Registration\Abandoned_Checkouts::SELF_CHECKOUT, '1' );

		$this->assertSame( 0, \SatoriManifest\Registration\Abandoned_Checkouts::purge() );
		$this->assertSame( 1, \SatoriManifest\Registration\Abandoned_Checkouts::purge( time() + 8 * DAY_IN_SECONDS ) );
		$this->assertNull( Applications::get( (int) $abandoned['id'] ) );
		$this->assertNotNull( Applications::get( (int) $approved['id'] ) );
	}

	/**
	 * Free trials are counted on their own, not in monthly revenue.
	 */
	public function test_trials_are_not_revenue(): void {
		$stats = \SatoriManifest\Admin\Screens\Payments_Screen::stats(
			array(
				array(
					'subscription' => array(
						'status'   => 'trialing',
						'interval' => 'year',
						'amount'   => 240.0,
					),
				),
				array(
					'subscription' => array(
						'status'   => 'active',
						'interval' => 'month',
						'amount'   => 9.0,
					),
				),
			)
		);

		$this->assertSame( 1, $stats['active'] );
		$this->assertSame( 9.0, $stats['mrr'] );
		$this->assertSame( 1, $stats['trials'] );
		$this->assertSame( 20.0, $stats['trial_mrr'] );
	}

	/**
	 * Cancellation is mirrored; the page stays online for staff to decide.
	 */
	public function test_subscription_deleted(): void {
		$app = $this->make_application();
		Application_Approver::approve( $app, '', '', false, true );
		$user_id = $this->complete_checkout( (int) $app['id'] );

		$this->event(
			'customer.subscription.deleted',
			array(
				'id'       => 'sub_1',
				'customer' => 'cus_1',
				'status'   => 'canceled',
				'metadata' => array( 'plan' => 'self-managed' ),
			)
		);

		$this->assertSame( 'canceled', Subscriptions::for_user( $user_id )['status'] );
		$this->assertSame( 'publish', get_post_status( \SatoriManifest\Clients\Client_Repository::page_id( $user_id ) ) );
	}

	/**
	 * Plus, monthly, with setup: setup is a one-off line item paid at checkout; the
	 * plan's 3 standard codes plus extras are posted; the setup charge never counts
	 * as the first subscription payment.
	 */
	public function test_signup_codes_and_setup(): void {
		$app = $this->make_application(
			array(
				'plan'     => 'plus',
				'interval' => 'month',
				'setup'    => '1',
				'codes'    => \SatoriManifest\Billing\Code_Catalogue::encode( array( 'l' => 1 ) ),
			)
		);
		Application_Approver::approve( $app, '', '', false, true );

		Checkout::create_session( Applications::get( (int) $app['id'] ) );
		$session = end( $this->requests )[2];
		$this->assertCount( 2, $session['line_items'] );
		$this->assertSame( 3900, $session['line_items'][1]['price_data']['unit_amount'] );

		$user_id = $this->complete_checkout( (int) $app['id'], 3900 );

		$items = array_values(
			array_filter(
				$this->requests,
				static fn( array $request ): bool => 'invoiceitems' === $request[1]
			)
		);
		$sizes = \SatoriManifest\Billing\Code_Catalogue::sizes();

		// Only the extra L code goes on the first invoice after the trial.
		$this->assertSame( array( (string) ( $sizes['l']['price'] * 100 ) ), array_map( static fn( array $request ): string => $request[2]['amount'], $items ) );
		$this->assertSame( 'l:1,m:3', get_user_meta( $user_id, \SatoriManifest\Billing\Printed_Codes::META_PENDING, true ) );
		$this->assertSame( 15.0, Subscriptions::for_user( $user_id )['amount'] );
		$this->assertSame( 'Page set up for you', Payments::recent( 10, $user_id )[0]['description'] );

		// Stripe's invoice for the setup is not the first payment...
		$this->event(
			'invoice.paid',
			array(
				'id'             => 'in_1',
				'customer'       => 'cus_1',
				'amount_paid'    => 3900,
				'currency'       => 'eur',
				'billing_reason' => 'subscription_create',
			)
		);
		$this->assertSame( array(), preg_grep( '/^First payment received/', $this->sent_subjects() ) );

		// ...the payment when the trial ends is.
		$this->event(
			'invoice.paid',
			array(
				'id'             => 'in_2',
				'customer'       => 'cus_1',
				'amount_paid'    => 2150,
				'currency'       => 'eur',
				'billing_reason' => 'subscription_cycle',
			)
		);
		$this->assertCount( 1, preg_grep( '/^First payment received/', $this->sent_subjects() ) );
	}

	/**
	 * Setup costs the same on every plan and period; included codes follow the plan.
	 */
	public function test_setup_cost_rules(): void {
		$this->assertSame( 39.0, Plans::setup_cost( 'plus', 'month' ) );
		$this->assertSame( 39.0, Plans::setup_cost( 'plus', 'year' ) );
		$this->assertSame( 39.0, Plans::setup_cost( 'self-managed', 'month' ) );
		$this->assertSame( 1, Plans::codes_included( 'self-managed' ) );
		$this->assertSame( 3, Plans::codes_included( 'plus' ) );
		$this->assertSame(
			0.0,
			\SatoriManifest\Billing\Printed_Codes::signup_setup(
				array(
					'plan'     => 'plus',
					'interval' => 'month',
				)
			)
		);
	}

	/**
	 * Starter → Plus during the trial (no charge, AI on at once), then Plus → Starter
	 * once paying (credit on the next payment); both emailed.
	 */
	public function test_plan_change_both_ways(): void {
		add_filter(
			'satori_manifest_pre_stripe_request',
			function ( $pre, string $method, string $path, array $params ) {
				if ( 'GET' === $method && 'subscriptions/sub_1' === $path ) {
					return array(
						'id'    => 'sub_1',
						'items' => array( 'data' => array( array( 'id' => 'si_1' ) ) ),
					);
				}
				if ( 'products' === $path ) {
					return array( 'id' => 'prod_' . $params['metadata']['plan'] );
				}
				if ( 'POST' === $method && 'subscriptions/sub_1' === $path && isset( $params['items'] ) ) {
					return array(
						'id'                 => 'sub_1',
						'customer'           => 'cus_1',
						'status'             => \SatoriManifest\Billing\Subscriptions::for_user( (int) get_current_user_id() )['status'],
						'metadata'           => $params['metadata'],
						'current_period_end' => time() + 20 * DAY_IN_SECONDS,
						'items'              => array(
							'data' => array(
								array(
									'id'    => 'si_1',
									'price' => array(
										'unit_amount' => $params['items'][0]['price_data']['unit_amount'],
										'currency'    => 'eur',
										'recurring'   => array( 'interval' => 'month' ),
									),
								),
							),
						),
					);
				}
				return $pre;
			},
			5,
			4
		);

		$app = $this->make_application(
			array(
				'plan'     => 'self-managed',
				'interval' => 'month',
			)
		);
		Application_Approver::approve( $app, '', '', false, true );
		$user_id = $this->complete_checkout( (int) $app['id'], 0 );
		wp_set_current_user( $user_id );
		Subscriptions::save(
			$user_id,
			array(
				'status'             => 'trialing',
				'current_period_end' => time() + 20 * DAY_IN_SECONDS,
			)
		);

		$this->assertFalse( \SatoriManifest\Mcp\Access::allowed( $user_id ) );

		$offer = \SatoriManifest\Billing\Plan_Change::offer( $user_id );
		$this->assertSame( 'plus', $offer['to'] );
		$this->assertTrue( $offer['upgrade'] );
		$this->assertTrue( $offer['trial'] );
		$this->assertStringContainsString( 'Nothing to pay now', \SatoriManifest\Billing\Plan_Change::payment_note( $offer ) );

		$this->assertTrue( \SatoriManifest\Billing\Plan_Change::change( $user_id, 'plus' ) );

		$update = end( $this->requests );
		$this->assertSame( 'subscriptions/sub_1', $update[1] );
		$this->assertSame( 'none', $update[2]['proration_behavior'] );
		$this->assertSame( 'si_1', $update[2]['items'][0]['id'] );
		$this->assertSame( 'prod_plus', $update[2]['items'][0]['price_data']['product'] );
		$this->assertSame( 1500, $update[2]['items'][0]['price_data']['unit_amount'] );
		$this->assertSame( 'plus', Subscriptions::for_user( $user_id )['plan'] );
		$this->assertTrue( \SatoriManifest\Mcp\Access::allowed( $user_id ) );
		$this->assertContains( 'Your plan is now Plus', $this->sent_subjects() );
		$this->assertCount( 1, preg_grep( '/^Plan upgraded/', $this->sent_subjects() ) );

		// Paying customer now: downgrade gives a credit on the next payment.
		Subscriptions::save( $user_id, array( 'status' => 'active' ) );
		$offer = \SatoriManifest\Billing\Plan_Change::offer( $user_id );
		$this->assertSame( 'self-managed', $offer['to'] );
		$this->assertFalse( $offer['upgrade'] );
		$this->assertContains( 'Priority support', $offer['changes'] );

		$this->assertTrue( \SatoriManifest\Billing\Plan_Change::change( $user_id, 'self-managed' ) );
		$this->assertSame( 'create_prorations', end( $this->requests )[2]['proration_behavior'] );
		$this->assertSame( 'self-managed', Subscriptions::for_user( $user_id )['plan'] );
		$this->assertFalse( \SatoriManifest\Mcp\Access::allowed( $user_id ) );
		$this->assertCount( 1, preg_grep( '/^Plan downgraded/', $this->sent_subjects() ) );

		// The product made for each plan is reused, not created again.
		$this->assertCount( 2, array_filter( $this->requests, static fn( array $request ): bool => 'products' === $request[1] ) );
		$this->assertTrue( \SatoriManifest\Billing\Plan_Change::change( $user_id, 'plus' ) );
		$this->assertCount( 2, array_filter( $this->requests, static fn( array $request ): bool => 'products' === $request[1] ) );

		// Yearly upgrades pay the difference now.
		$this->assertSame(
			'always_invoice',
			\SatoriManifest\Billing\Plan_Change::proration(
				array(
					'trial'    => false,
					'upgrade'  => true,
					'interval' => 'year',
				)
			)
		);
		$this->assertNull( \SatoriManifest\Billing\Plan_Change::offer( 999999 ) );
	}

	/**
	 * Dispatches checkout.session.completed and returns the new client.
	 *
	 * @param  int $app_id  Application ID.
	 * @param  int $amount  Amount charged at checkout, in cents (0 for a free trial).
	 * @return int
	 */
	private function complete_checkout( int $app_id, int $amount = 9000 ): int {
		$this->event(
			'checkout.session.completed',
			array(
				'id'                  => 'cs_1',
				'mode'                => 'subscription',
				'client_reference_id' => (string) $app_id,
				'customer'            => 'cus_1',
				'subscription'        => 'sub_1',
				'invoice'             => 'in_1',
				'amount_total'        => $amount,
				'currency'            => 'eur',
			)
		);

		return (int) Applications::get( $app_id )['user_id'];
	}

	/**
	 * Dispatches a Stripe event.
	 *
	 * @param  string              $type    Event type.
	 * @param  array<string,mixed> $data_object  Event object.
	 * @return void
	 */
	private function event( string $type, array $data_object ): void {
		Webhooks::dispatch(
			array(
				'id'   => 'evt_' . wp_generate_password( 8, false ),
				'type' => $type,
				'data' => array( 'object' => $data_object ),
			)
		);
	}
}
