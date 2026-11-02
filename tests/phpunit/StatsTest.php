<?php
/**
 * Tests for visit counting and the monthly summary.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Core\Settings;
use SatoriManifest\Hardening\Client_Ip;
use SatoriManifest\Stats\Monthly_Summary;
use SatoriManifest\Stats\Page_Views;
use SatoriManifest\Stats\View_Counter;

/**
 * Page_Views, View_Counter, Monthly_Summary and Client_Ip.
 */
class StatsTest extends Test_Case {

	/**
	 * Restores the request.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		unset( $_SERVER['HTTP_CF_CONNECTING_IP'] );
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		parent::tear_down();
	}

	/**
	 * Views and scans are counted per day and summed over periods.
	 */
	public function test_record_and_totals(): void {
		$page_id = Client_Repository::page_id( $this->make_client( 'Counting Cafe' ) );

		Page_Views::record( $page_id, false );
		Page_Views::record( $page_id, true );
		Page_Views::record( $page_id, true );

		$this->assertSame(
			array(
				'views' => 3,
				'scans' => 2,
			),
			Page_Views::last_days( $page_id, 30 )
		);
		$this->assertSame( 3, Page_Views::month( $page_id, wp_date( 'Y-m' ) )['views'] );
	}

	/**
	 * Old days are dropped when new visits are recorded.
	 */
	public function test_retention(): void {
		$page_id = Client_Repository::page_id( $this->make_client( 'Old Times' ) );
		update_post_meta( $page_id, Page_Views::META, array( '2001-01-01' => array( 9, 9 ) ) );

		Page_Views::record( $page_id, false );

		$this->assertArrayNotHasKey( '2001-01-01', get_post_meta( $page_id, Page_Views::META, true ) );
	}

	/**
	 * Bots, unpublished and non-client pages are not counted; repeat visits count once.
	 */
	public function test_counter_rules(): void {
		$page_id = Client_Repository::page_id( $this->make_client( 'Rules Bar' ) );
		$browser = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)';

		$this->assertTrue( View_Counter::should_count( $page_id, $browser ) );
		$this->assertFalse( View_Counter::should_count( $page_id, 'Googlebot/2.1' ) );
		$this->assertFalse( View_Counter::should_count( $page_id, '' ) );
		$this->assertFalse( View_Counter::should_count( self::factory()->post->create( array( 'post_type' => 'page' ) ), $browser ) );

		for ( $i = 0; $i < 3; $i++ ) {
			$request = new \WP_REST_Request( 'POST', '/' . View_Counter::ROUTE_NAMESPACE . View_Counter::ROUTE );
			$request->set_header( 'user_agent', $browser );
			$request->set_param( 'page', $page_id );
			$request->set_param( 'source', 'qr' );
			$this->assertSame( 204, rest_get_server()->dispatch( $request )->get_status() );
		}

		$this->assertSame( 1, Page_Views::last_days( $page_id, 1 )['views'], 'Repeat visits within 30 minutes count once.' );
		$this->assertSame( 1, Page_Views::last_days( $page_id, 1 )['scans'] );
	}

	/**
	 * The proxy header is only trusted when Settings say a proxy is in front.
	 */
	public function test_client_ip(): void {
		$_SERVER['REMOTE_ADDR']           = '10.0.0.1';
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.9';

		$this->assertSame( '10.0.0.1', Client_Ip::get() );

		Settings::save( array( 'proxy' => 'cloudflare' ) );
		$this->assertSame( '203.0.113.9', Client_Ip::get() );

		$_SERVER['HTTP_CF_CONNECTING_IP'] = 'not-an-ip';
		$this->assertSame( '10.0.0.1', Client_Ip::get(), 'A bad header falls back to the connection address.' );
	}

	/**
	 * The summary is emailed once per month, and respects the client's opt-out.
	 */
	public function test_monthly_summary(): void {
		$user_id = $this->make_client( 'Summary Salon' );
		$month   = Monthly_Summary::previous_month( wp_date( 'Y-m' ) );

		$this->assertSame( 1, Monthly_Summary::maybe_send() );
		$this->assertSame( 0, Monthly_Summary::maybe_send(), 'Only once per month.' );
		$this->assertStringContainsString( 'in numbers', implode( '|', $this->sent_subjects() ) );

		update_user_meta( $user_id, Monthly_Summary::OPT_OUT_META, 1 );
		$this->assertSame( 0, Monthly_Summary::send_all( $month ) );
	}

	/**
	 * Month arithmetic and the change sentence.
	 */
	public function test_summary_helpers(): void {
		$this->assertSame( '2026-12', Monthly_Summary::previous_month( '2027-01' ) );
		$this->assertSame( 'up 50% on the month before', Monthly_Summary::change_text( 150, 100 ) );
		$this->assertSame( 'down 25% on the month before', Monthly_Summary::change_text( 75, 100 ) );
		$this->assertStringContainsString( 'first month', Monthly_Summary::change_text( 10, 0 ) );
	}
}
