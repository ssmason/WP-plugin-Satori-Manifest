<?php
/**
 * Tests for plugin settings and the portal brand.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Branding\Portal_Brand;
use SatoriManifest\Core\Settings;
use SatoriManifest\Mail\Mailer;

/**
 * Settings sanitising and the values derived from them.
 */
class SettingsTest extends Test_Case {

	/**
	 * Bad input is replaced by safe defaults.
	 */
	public function test_sanitize(): void {
		$clean = Settings::sanitize(
			array(
				'portal_logo_id'  => '12abc',
				'accent_color'    => 'red; background:url(x)',
				'dark_color'      => '#123456',
				'support_email'   => 'not-an-email',
				'staff_emails'    => 'a@example.org, nope, b@example.org',
				'require_payment' => '',
			)
		);

		$this->assertSame( 12, $clean['portal_logo_id'] );
		$this->assertSame( Settings::defaults()['accent_color'], $clean['accent_color'] );
		$this->assertSame( '#123456', $clean['dark_color'] );
		$this->assertSame( '', $clean['support_email'] );
		$this->assertSame( 'a@example.org, b@example.org', $clean['staff_emails'] );
		$this->assertFalse( $clean['require_payment'] );
	}

	/**
	 * Staff recipients come from settings, else the admin email.
	 */
	public function test_staff_recipients(): void {
		$this->assertSame( array( get_option( 'admin_email' ) ), Mailer::staff_recipients() );

		Settings::save( array( 'staff_emails' => 'one@example.org,two@example.org' ) );

		$this->assertSame( array( 'one@example.org', 'two@example.org' ), Mailer::staff_recipients() );
	}

	/**
	 * Text on the accent colour is readable.
	 */
	public function test_text_on_colour(): void {
		$this->assertSame( '#ffffff', Portal_Brand::text_on( '#0b1620' ) );
		$this->assertSame( Portal_Brand::dark(), Portal_Brand::text_on( '#f2a65a' ) );
		$this->assertStringContainsString( '--sm-portal-accent:', Portal_Brand::css_variables() );
	}
}
