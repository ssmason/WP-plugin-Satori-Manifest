<?php
/**
 * Tests for the application workflow.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Billing\Checkout;
use SatoriManifest\Registration\Application_Approver;
use SatoriManifest\Registration\Applications;

/**
 * Approve → (pay) → active, and decline.
 */
class ApplicationApproverTest extends Test_Case {

	/**
	 * Without Stripe, approval creates the account straight away.
	 */
	public function test_approve_without_billing_creates_account(): void {
		$app    = $this->make_application();
		$status = Application_Approver::approve( $app, '', '', true, true );

		$this->assertSame( 'approved', $status );

		$app = Applications::get( (int) $app['id'] );
		$this->assertGreaterThan( 0, $app['user_id'] );
		$this->assertSame( 'copperkettle', get_userdata( $app['user_id'] )->user_login );
		$this->assertContains( 'Welcome to Satori Manifest Tests — your page is ready', $this->sent_subjects() );
	}

	/**
	 * With Stripe on and payment required, the applicant gets a payment link.
	 */
	public function test_approve_with_billing_waits_for_payment(): void {
		add_filter( 'satori_manifest_billing_enabled', '__return_true' );

		$app    = $this->make_application();
		$status = Application_Approver::approve( $app, 'kettle', 'atelier', true, true );

		$this->assertSame( 'awaiting_payment', $status );
		$this->assertFalse( username_exists( 'kettle' ) );
		$this->assertStringContainsString( Checkout::QUERY_VAR . '=' . $app['id'], Checkout::payment_url( (int) $app['id'] ) );
		$this->assertContains( 'You\'re approved — start your Satori Manifest Tests plan', $this->sent_subjects() );

		// Paying (or staff activating) creates the account with the approved username and design.
		$user_id = Application_Approver::activate( (int) $app['id'] );
		$this->assertSame( 'kettle', get_userdata( $user_id )->user_login );

		// Activation is idempotent.
		$this->assertSame( $user_id, Application_Approver::activate( (int) $app['id'] ) );
	}

	/**
	 * A decided application cannot be approved again.
	 */
	public function test_cannot_approve_twice(): void {
		$app = $this->make_application();
		Application_Approver::decline( $app, 'Not a fit', true );

		$app = Applications::get( (int) $app['id'] );
		$this->assertSame( 'declined', $app['status'] );
		$this->assertWPError( Application_Approver::approve( $app, '', '', false, false ) );
	}

	/**
	 * A taken username is refused.
	 */
	public function test_taken_username(): void {
		$this->make_client( 'Taken' );
		$this->assertWPError( Application_Approver::approve( $this->make_application(), 'taken', '', false, false ) );
	}
}
