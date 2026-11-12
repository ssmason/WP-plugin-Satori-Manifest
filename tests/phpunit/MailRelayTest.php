<?php
/**
 * Tests for routing WordPress's own emails through Brevo.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Mail\WP_Mail_Relay;

/**
 * WP_Mail_Relay.
 */
class MailRelayTest extends Test_Case {

	/**
	 * Recipients come from strings, lists and "Name <email>" forms; invalid ones are dropped.
	 */
	public function test_recipients(): void {
		$this->assertSame( array( 'a@example.com', 'b@example.com' ), WP_Mail_Relay::recipients( 'a@example.com, Bee <b@example.com>, nonsense' ) );
		$this->assertSame( array( 'a@example.com' ), WP_Mail_Relay::recipients( array( 'a@example.com', 'a@example.com' ) ) );
		$this->assertSame( array(), WP_Mail_Relay::recipients( '' ) );
	}

	/**
	 * Plain-text mail gets an escaped HTML body; HTML mail keeps its body; Reply-To is passed on.
	 */
	public function test_message(): void {
		$plain = WP_Mail_Relay::message( 'Hi &amp; bye', "<b>x</b>\nhttps://example.com/", WP_Mail_Relay::headers( "Reply-To: Help <help@example.com>\r\nX-Other: 1" ) );

		$this->assertSame( 'Hi & bye', $plain['subject'] );
		$this->assertStringContainsString( '&lt;b&gt;', $plain['html'] );
		$this->assertStringContainsString( 'href="https://example.com/"', $plain['html'] );
		$this->assertSame( 'help@example.com', $plain['reply_to'] );

		$html = WP_Mail_Relay::message( 'Hi', '<p>Hello</p>', WP_Mail_Relay::headers( array( 'Content-Type: text/html; charset=UTF-8' ) ) );

		$this->assertSame( '<p>Hello</p>', $html['html'] );
		$this->assertSame( 'Hello', $html['text'] );
		$this->assertArrayNotHasKey( 'reply_to', $html );
	}

	/**
	 * Without a Brevo key, wp_mail() is left alone.
	 */
	public function test_off_without_brevo(): void {
		$this->assertNull( WP_Mail_Relay::relay( null, array( 'to' => 'a@example.com' ) ) );
	}
}
