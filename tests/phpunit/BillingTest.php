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
use SatoriManifest\Billing\Webhooks;
use SatoriManifest\Registration\Application_Approver;
use SatoriManifest\Registration\Applications;

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
				'plan'     => 'managed',
				'interval' => 'year',
			)
		);
		$session = Checkout::create_session( $app );

		$this->assertSame( 'cs_test_1', $session['id'] );

		$params = $this->requests[0][2];
		$this->assertSame( 'subscription', $params['mode'] );
		$this->assertSame( (string) $app['id'], $params['client_reference_id'] );
		$this->assertSame( 'year', $params['line_items'][0]['price_data']['recurring']['interval'] );
		$this->assertSame( 24000, $params['line_items'][0]['price_data']['unit_amount'] );
		$this->assertSame( 'eur', $params['line_items'][0]['price_data']['currency'] );
	}

	/**
	 * Signatures are checked against the secret and the timestamp.
	 */
	public function test_signature_verification(): void {
		$payload = '{"id":"evt_1"}';
		$now     = 1700000000;
		$valid   = 't=' . $now . ',v1=' . hash_hmac( 'sha256', $now . '.' . $payload, 'whsec_test' );

		$this->assertTrue( Webhooks::verify( $payload, $valid, 'whsec_test', $now ) );
		$this->assertFalse( Webhooks::verify( $payload, $valid, 'whsec_other', $now ) );
		$this->assertFalse( Webhooks::verify( $payload . ' ', $valid, 'whsec_test', $now ) );
		$this->assertFalse( Webhooks::verify( $payload, $valid, 'whsec_test', $now + 600 ) );
		$this->assertFalse( Webhooks::verify( $payload, $valid, '', $now ) );
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
		$this->assertContains( 'Payment received — thank you', $this->sent_subjects() );

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
	 * Dispatches checkout.session.completed and returns the new client.
	 *
	 * @param  int $app_id  Application ID.
	 * @return int
	 */
	private function complete_checkout( int $app_id ): int {
		$this->event(
			'checkout.session.completed',
			array(
				'id'                  => 'cs_1',
				'mode'                => 'subscription',
				'client_reference_id' => (string) $app_id,
				'customer'            => 'cus_1',
				'subscription'        => 'sub_1',
				'invoice'             => 'in_1',
				'amount_total'        => 9000,
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
