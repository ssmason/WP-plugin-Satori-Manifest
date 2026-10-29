<?php
/**
 * Tests for the website embed code.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Clients\Embed_Code;
use SatoriManifest\Frontend\Embed;

/**
 * Embed_Code builds the snippet clients paste into their own site.
 */
class EmbedTest extends Test_Case {

	/**
	 * The snippet frames the page in embed mode and resizes safely.
	 */
	public function test_snippet_for_client_page(): void {
		$page_id = Client_Repository::page_id( $this->make_client( 'Harbour Lights' ) );
		$code    = Embed_Code::for_page( $page_id );

		$this->assertStringContainsString( '<iframe src="' . esc_url( Embed::url( $page_id ) ) . '"', $code );
		$this->assertStringContainsString( Embed::QUERY_VAR . '=1', $code );
		$this->assertStringContainsString( 'title="Harbour Lights — prices"', $code );
		$this->assertStringContainsString( 'data-sm-embed', $code );

		// The resize script only trusts this site's origin and our message type.
		$this->assertStringContainsString( 'e.origin!=="' . Embed_Code::site_origin() . '"', $code );
		$this->assertStringContainsString( '"' . Embed::MESSAGE_TYPE . '"', $code );
	}

	/**
	 * Pages that are not client pages get no snippet.
	 */
	public function test_no_snippet_for_other_pages(): void {
		$this->assertSame( '', Embed_Code::for_page( 0 ) );
		$this->assertSame( '', Embed_Code::for_page( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );
	}

	/**
	 * The site origin keeps scheme, host and port only.
	 */
	public function test_site_origin(): void {
		$this->assertSame( 'http://example.org', Embed_Code::site_origin() );
	}
}
