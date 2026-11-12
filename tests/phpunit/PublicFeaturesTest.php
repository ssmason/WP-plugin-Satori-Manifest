<?php
/**
 * Tests for visitor-facing extras: neutral registration replies.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Registration\Registration_Form;
use SatoriManifest\Registration\Registration_Notifier;

/**
 * Registration_Form duplicate handling.
 */
class PublicFeaturesTest extends Test_Case {

	/**
	 * A repeat registration emails the address owner instead of telling the visitor.
	 */
	public function test_existing_email_is_told_by_email(): void {
		$this->make_client( 'Already Here' );

		Registration_Notifier::account_exists(
			array(
				'email'        => 'already-here@example.test',
				'contact_name' => 'Sam Owner',
			)
		);

		$sent = tests_retrieve_phpmailer_instance()->get_sent();
		$this->assertSame( 'already-here@example.test', $sent->to[0][0] );
		$this->assertStringContainsString( 'already have an account', $sent->body );
		$this->assertArrayNotHasKey( 'duplicate', Registration_Form::messages(), 'No message reveals a duplicate.' );
	}
}
