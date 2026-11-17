<?php
/**
 * Tests for the plan catalogue.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Registration\Plans;

/**
 * Plans reads config/plans.yaml.
 */
class PlansTest extends Test_Case {

	/**
	 * Both shipped plans load with monthly and yearly prices.
	 */
	public function test_catalogue_loads(): void {
		$plans = Plans::all();

		$this->assertArrayHasKey( 'self-managed', $plans );
		$this->assertArrayHasKey( 'plus', $plans );
		$this->assertGreaterThan( 0, Plans::price( 'self-managed', 'month' ) );
		$this->assertGreaterThan( 0, Plans::price( 'self-managed', 'year' ) );
		$this->assertSame( 'eur', Plans::currency() );
	}

	/**
	 * Yearly billing is cheaper than twelve months.
	 */
	public function test_yearly_saving(): void {
		$monthly = Plans::price( 'plus', 'month' );
		$yearly  = Plans::price( 'plus', 'year' );

		$this->assertEqualsWithDelta( $monthly * 12 - $yearly, Plans::yearly_saving( 'plus' ), 0.001 );
		$this->assertGreaterThan( 0, Plans::yearly_saving( 'plus' ) );
		$this->assertTrue( Plans::has_interval_choice() );
	}

	/**
	 * Unknown slugs and intervals fall back safely.
	 */
	public function test_unknown_values(): void {
		$this->assertNull( Plans::get( 'nope' ) );
		$this->assertSame( 'nope', Plans::title( 'nope' ) );
		$this->assertSame( 0.0, Plans::price( 'nope', 'month' ) );
		$this->assertSame( 'month', Plans::normalize_interval( 'weekly' ) );
		$this->assertSame( 'year', Plans::normalize_interval( 'year' ) );
	}

	/**
	 * Amounts are formatted with the currency symbol.
	 */
	public function test_format(): void {
		$this->assertSame( '€9', Plans::format( 9 ) );
		$this->assertSame( '€9.50', Plans::format( 9.5 ) );
		$this->assertSame( '£24', Plans::format( 24, 'gbp' ) );
		$this->assertSame( '10 CHF', Plans::format( 10, 'chf' ) );
	}
}
