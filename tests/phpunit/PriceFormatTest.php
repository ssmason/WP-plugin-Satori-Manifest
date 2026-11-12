<?php
/**
 * Tests for price formatting.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Branding\Brand;

/**
 * Brand::format_price() follows the currency position's convention.
 */
class PriceFormatTest extends Test_Case {

	/**
	 * Symbol after: decimal comma, thousands dot, non-breaking space.
	 */
	public function test_symbol_after_uses_european_style(): void {
		$euro = array(
			'symbol'   => '€',
			'position' => 'after',
		);

		$this->assertSame( "4,50\u{00A0}€", Brand::format_price( 4.5, $euro ) );
		$this->assertSame( "12\u{00A0}€", Brand::format_price( 12.0, $euro ) );
		$this->assertSame( "1.250,75\u{00A0}€", Brand::format_price( 1250.75, $euro ) );
	}

	/**
	 * Symbol before: site locale, no space.
	 */
	public function test_symbol_before_uses_site_locale(): void {
		$pound = array(
			'symbol'   => '£',
			'position' => 'before',
		);

		$this->assertSame( '£4.50', Brand::format_price( 4.5, $pound ) );
		$this->assertSame( '£25', Brand::format_price( 25.0, $pound ) );
	}
}
