<?php
/**
 * Tests for the examples bar on demo pages.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Clients\Demo_Seeder;
use SatoriManifest\Frontend\Example_Bar;

/**
 * Example_Bar.
 */
class ExampleBarTest extends Test_Case {

	/**
	 * Only demo clients' pages count as examples.
	 */
	public function test_only_demo_pages(): void {
		$client = $this->make_client( 'Real Barbers' );
		$demo   = $this->make_client( 'Demo Barbers' );
		update_user_meta( $demo, Demo_Seeder::DEMO_FLAG, 1 );

		$this->assertFalse( Example_Bar::is_demo_page( Client_Repository::page_id( $client ) ) );
		$this->assertTrue( Example_Bar::is_demo_page( Client_Repository::page_id( $demo ) ) );
	}

	/**
	 * Demo pages are listed once each; real clients never appear.
	 */
	public function test_groups_list_demo_pages_once(): void {
		$client = $this->make_client( 'Real Barbers' );
		$first  = $this->make_client( 'Demo One' );
		$second = $this->make_client( 'Demo Two' );
		update_user_meta( $first, Demo_Seeder::DEMO_FLAG, 1 );
		update_user_meta( $second, Demo_Seeder::DEMO_FLAG, 1 );

		$ids = array();
		foreach ( Example_Bar::groups() as $group ) {
			$ids = array_merge( $ids, array_column( $group['links'], 'id' ) );
		}

		$this->assertContains( Client_Repository::page_id( $first ), $ids );
		$this->assertContains( Client_Repository::page_id( $second ), $ids );
		$this->assertNotContains( Client_Repository::page_id( $client ), $ids );
		$this->assertSame( $ids, array_unique( $ids ) );
	}
}
