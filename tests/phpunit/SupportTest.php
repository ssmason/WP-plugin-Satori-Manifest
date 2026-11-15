<?php
/**
 * Tests for support tickets.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Support\Ticket_Notifier;
use SatoriManifest\Support\Tickets;

/**
 * Class SupportTest
 */
class SupportTest extends Test_Case {

	/**
	 * Raise, reply both ways, close and reopen; lists and clean-up.
	 */
	public function test_ticket_lifecycle(): void {
		$client = $this->make_client( 'Ticket Bar' );
		$other  = $this->make_client( 'Other Bar' );
		$staff  = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertSame( 0, Tickets::create( $client, '', 'No subject' ) );

		$ticket = Tickets::create( $client, 'Logo <b>change</b>', "Please swap the logo.\n<script>x</script>Thanks" );

		$this->assertGreaterThan( 0, $ticket );
		$this->assertSame( 'Logo change', Tickets::subject( $ticket ) );
		$this->assertSame( Tickets::OPEN, Tickets::status( $ticket ) );
		$this->assertSame( $client, Tickets::owner( $ticket ) );
		$this->assertStringNotContainsString( '<script', Tickets::messages( $ticket )[0]->post_content );

		// Staff reply: in progress.
		Tickets::reply( $ticket, $staff, 'Done.' );
		$this->assertSame( Tickets::IN_PROGRESS, Tickets::status( $ticket ) );

		// Closed, then the client adds to it: opened again.
		Tickets::set_status( $ticket, Tickets::CLOSED );
		Tickets::reply( $ticket, $client, 'One more thing' );
		$this->assertSame( Tickets::OPEN, Tickets::status( $ticket ) );
		$this->assertCount( 3, Tickets::messages( $ticket ) );

		$this->assertCount( 1, Tickets::list( $client ) );
		$this->assertCount( 0, Tickets::list( $other ) );
		$this->assertCount( 1, Tickets::list( 0, Tickets::OPEN ) );
		$this->assertSame( 1, Tickets::counts()[ Tickets::OPEN ] );

		// Notifications go to the configured support address.
		$this->assertContains( 'hello@qrable.net', Ticket_Notifier::recipients() );

		Tickets::forget( $client );
		$this->assertFalse( Tickets::exists( $ticket ) );
		$this->assertCount(
			0,
			get_posts(
				array(
					'post_type'   => Tickets::MESSAGE_TYPE,
					'post_status' => 'any',
					'post_parent' => $ticket,
				)
			)
		);
	}
}
