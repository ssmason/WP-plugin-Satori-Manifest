<?php
/**
 * Tests for templated emails.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Mail\Email_Builder;
use SatoriManifest\Mail\Mailer;

/**
 * Email_Builder renders every template in config/emails/.
 */
class EmailBuilderTest extends Test_Case {

	/**
	 * Placeholders are replaced and HTML-escaped.
	 */
	public function test_placeholders_are_filled_and_escaped(): void {
		$message = Email_Builder::build(
			'application-received',
			array(
				'contact_name'  => 'Sam',
				'business_name' => 'Fish & <Chips>',
				'plan'          => 'Self-managed',
			)
		);

		$this->assertStringContainsString( 'Sam', $message['subject'] );
		$this->assertStringContainsString( 'Fish &amp; &lt;Chips&gt;', $message['html'] );
		$this->assertStringNotContainsString( '{{', $message['html'] );
		$this->assertStringContainsString( 'Fish & <Chips>', $message['text'] );
	}

	/**
	 * Detail rows with no value are left out.
	 */
	public function test_empty_detail_rows_are_skipped(): void {
		$message = Email_Builder::build(
			'application-received',
			array(
				'business_name' => 'Copper Kettle',
				'plan'          => 'Self-managed',
				'billing'       => '',
			)
		);

		$this->assertStringContainsString( 'Plan: Self-managed', $message['text'] );
		$this->assertStringNotContainsString( 'Billing:', $message['text'] );
	}

	/**
	 * Every shipped template renders with a subject.
	 */
	public function test_every_template_renders(): void {
		foreach ( glob( SATORI_MANIFEST_PATH . 'config/emails/*.yaml' ) as $file ) {
			$message = Email_Builder::build( basename( $file, '.yaml' ) );
			$this->assertNotSame( '', $message['subject'], basename( $file ) );
			$this->assertStringContainsString( '<!doctype html>', $message['html'] );
		}
	}

	/**
	 * Unknown templates produce nothing and are not sent.
	 */
	public function test_unknown_template(): void {
		$this->assertSame( '', Email_Builder::build( 'does-not-exist' )['subject'] );
		$this->assertFalse( Mailer::send( 'does-not-exist', 'a@example.org' )['sent'] );
	}

	/**
	 * Mailer sends HTML through wp_mail with a Reply-To header.
	 */
	public function test_mailer_sends_html(): void {
		$result = Mailer::send( 'payment-failed', 'client@example.org', array( 'amount' => '€9' ) );

		$this->assertTrue( $result['sent'] );
		$this->assertSame( 'wp_mail', $result['transport'] );

		$sent = tests_retrieve_phpmailer_instance()->get_sent();
		$this->assertSame( 'client@example.org', $sent->to[0][0] );
		$this->assertStringContainsString( 'text/html', $sent->header );
		$this->assertStringContainsString( 'Reply-To:', $sent->header );
	}
}
