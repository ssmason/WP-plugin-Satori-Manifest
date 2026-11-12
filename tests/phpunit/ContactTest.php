<?php
/**
 * Tests for the contact form.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Contact\Contact_Handler;

/**
 * Contact_Handler.
 */
class ContactTest extends Test_Case {

	/**
	 * Captured outgoing emails.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $sent = array();

	/**
	 * Captures emails instead of sending them.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->sent = array();
		add_filter(
			'satori_manifest_pre_send_email',
			function ( $pre, array $message, string $to, string $template ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- filter signature.
				$this->sent[] = array(
					'message'  => $message,
					'to'       => $to,
					'template' => $template,
				);
				return array( 'sent' => true );
			},
			10,
			4
		);
	}

	/**
	 * Incomplete messages are refused and nothing is sent.
	 */
	public function test_invalid_message(): void {
		$this->assertSame(
			'invalid',
			Contact_Handler::send(
				array(
					'name'    => 'Ana',
					'email'   => 'not-an-email',
					'message' => 'Hello',
				)
			)
		);
		$this->assertSame( array(), $this->sent );
	}

	/**
	 * A visitor's message goes to staff with their details.
	 */
	public function test_public_message_reaches_staff(): void {
		$this->assertSame(
			'sent',
			Contact_Handler::send(
				array(
					'name'    => 'Ana',
					'email'   => 'ana@example.com',
					'message' => 'Do you post to Ireland?',
				)
			)
		);
		$this->assertCount( 1, $this->sent );
		$this->assertSame( 'contact-message', $this->sent[0]['template'] );
		$this->assertStringContainsString( 'Ana', $this->sent[0]['message']['subject'] );
		$this->assertStringContainsString( 'Do you post to Ireland?', $this->sent[0]['message']['html'] );
	}

	/**
	 * A logged-in client's message uses their account details and page.
	 */
	public function test_client_message_uses_account(): void {
		$user_id = $this->make_client();

		$this->assertSame(
			'sent',
			Contact_Handler::send(
				array(
					'name'    => '',
					'email'   => '',
					'message' => 'Can I change my plan?',
				),
				$user_id
			)
		);
		$this->assertStringContainsString( get_userdata( $user_id )->user_email, $this->sent[0]['message']['html'] );
	}
}
