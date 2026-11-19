<?php
/**
 * Tests for placeholder links on demo pages.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Frontend\Demo_Links;

/**
 * Demo_Links points outbound links at "#" and keeps links within the site.
 */
class DemoLinksTest extends Test_Case {

	/**
	 * Outbound, tel and mailto links become "#"; site links and anchors stay.
	 */
	public function test_rewrite(): void {
		$home = home_url( '/harbour-kitchen/' );
		$html = '<link href="https://fonts.example/css" rel="stylesheet">'
			. '<a href="https://faderepublic.example/book" target="_blank" rel="noopener">Book</a>'
			. '<a class="x" href="tel:01135550193">Call</a>'
			. '<a href="mailto:&#104;i@petal.test">Email</a>'
			. '<a href="//instagram.com/x">Insta</a>'
			. '<a href="' . $home . '">Example</a>'
			. '<a href="/register/">Register</a>'
			. '<a href="#menu">Menu</a>';

		$out = Demo_Links::rewrite( $html );

		$this->assertStringContainsString( 'href="https://fonts.example/css"', $out, 'Non-anchor tags are left alone.' );
		$this->assertSame( 4, substr_count( $out, 'href="#"' ) );
		$this->assertStringNotContainsString( 'target="_blank"', $out );
		$this->assertStringContainsString( '<a class="x" href="#">Call</a>', $out );
		$this->assertStringContainsString( 'href="' . $home . '"', $out );
		$this->assertStringContainsString( 'href="/register/"', $out );
		$this->assertStringContainsString( 'href="#menu"', $out );
	}
}
