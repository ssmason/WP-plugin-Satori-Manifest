<?php
/**
 * Tests for visitor-facing extras: the public PDF and neutral registration replies.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Branding\Brand;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Pdf\Public_Pdf;
use SatoriManifest\Registration\Registration_Form;

/**
 * Public_Pdf and Registration_Form duplicate handling.
 */
class PublicFeaturesTest extends Test_Case {

	/**
	 * The PDF link is on by default and can be switched off under Branding.
	 */
	public function test_public_pdf_toggle(): void {
		$page_id = Client_Repository::page_id( $this->make_client( 'Toggle Bistro' ) );

		$this->assertTrue( Public_Pdf::is_available( $page_id ) );
		$this->assertStringContainsString( Public_Pdf::QUERY_VAR . '=1', Public_Pdf::url( $page_id ) );

		update_post_meta( $page_id, 'sm_footer_show_pdf', '0' );
		Brand::flush();
		$this->assertFalse( Public_Pdf::is_available( $page_id ) );

		wp_update_post(
			array(
				'ID'          => $page_id,
				'post_status' => 'draft',
			)
		);
		update_post_meta( $page_id, 'sm_footer_show_pdf', '1' );
		Brand::flush();
		$this->assertFalse( Public_Pdf::is_available( $page_id ), 'Unpublished pages offer no PDF.' );
	}

	/**
	 * A repeat registration emails the address owner instead of telling the visitor.
	 */
	public function test_existing_email_is_told_by_email(): void {
		$this->make_client( 'Already Here' );

		$method = new \ReflectionMethod( Registration_Form::class, 'notify_existing' );
		$method->setAccessible( true );
		$method->invoke(
			null,
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
