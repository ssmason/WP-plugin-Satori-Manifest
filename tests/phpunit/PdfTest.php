<?php
/**
 * Tests for the price-list PDF.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Core\Sanitizer;
use SatoriManifest\Manifest\Post_Types;
use SatoriManifest\Pdf\Font_Metrics;
use SatoriManifest\Pdf\Pdf_Document;
use SatoriManifest\Pdf\Pdf_Download;
use SatoriManifest\Pdf\Price_List_Pdf;
use SatoriManifest\Pdf\Print_Colour;

/**
 * Pdf_Document, Font_Metrics, Print_Colour and Price_List_Pdf.
 */
class PdfTest extends Test_Case {

	/**
	 * Text is measured with the Helvetica widths and wrapped to fit.
	 */
	public function test_metrics_and_wrapping(): void {
		$this->assertEqualsWithDelta( 27.8, Font_Metrics::width( '  ', 10 ) / 2 * 10, 0.2 );
		$this->assertSame( "\x80", Font_Metrics::encode( '€' ) );

		$lines = Font_Metrics::wrap( 'Full head of foils with toner and blow dry', 10, 100 );
		$this->assertGreaterThan( 1, count( $lines ) );

		foreach ( $lines as $line ) {
			$this->assertLessThanOrEqual( 100, Font_Metrics::width( $line, 10 ) );
		}
	}

	/**
	 * Colours too light for paper fall back to a readable one.
	 */
	public function test_print_colours(): void {
		$this->assertSame( array( 0.0, 0.0, 0.0 ), Print_Colour::from_hex( '#000' ) );
		$this->assertSame( Print_Colour::from_hex( '#1c1c1c' ), Print_Colour::readable( '#f5f0e8', '#1c1c1c', 0.6 ) );
		$this->assertSame( Print_Colour::from_hex( '#d46a84' ), Print_Colour::readable( '#d46a84', '#1c1c1c', 0.75 ) );
		$this->assertSame( array(), Print_Colour::from_hex( 'nope' ) );
	}

	/**
	 * The writer produces a structurally valid PDF and escapes text.
	 */
	public function test_document_structure(): void {
		$pdf = new Pdf_Document();
		$pdf->add_page();
		$pdf->text( 50, 50, 'Price (from) \\ list', 12, true, array( 0, 0, 0 ) );
		$pdf->add_page();
		$out = $pdf->output();

		$this->assertStringStartsWith( '%PDF-1.4', $out );
		$this->assertStringEndsWith( "%%EOF\n", $out );
		$this->assertStringContainsString( '/Count 2', $out );
		$this->assertMatchesRegularExpression( '/startxref\n\d+\n/', $out );
	}

	/**
	 * A client's price list renders, with every item, across pages when long.
	 */
	public function test_price_list_pdf(): void {
		$user_id     = $this->make_client( 'Harbour Lights' );
		$page_id     = Client_Repository::page_id( $user_id );
		$manifest_id = Client_Repository::manifest_id( $user_id );
		$items       = array();

		for ( $i = 1; $i <= 80; $i++ ) {
			$items[] = array(
				'label' => 'Item ' . $i,
				'price' => (string) $i,
			);
		}

		update_post_meta(
			$manifest_id,
			Post_Types::META_SECTIONS,
			wp_slash(
				(string) wp_json_encode(
					Sanitizer::sanitize_sections(
						array(
							array(
								'title' => 'Everything',
								'items' => $items,
							),
						)
					)
				)
			)
		);

		$out = Price_List_Pdf::render( $page_id );

		$this->assertStringStartsWith( '%PDF-1.4', $out );
		$this->assertMatchesRegularExpression( '#/Count [2-9]#', $out, 'A long list spans several pages.' );
		$this->assertSame( 'harbour-lights-prices.pdf', Price_List_Pdf::filename( $page_id ) );
		$this->assertSame( '', Price_List_Pdf::render( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );
	}

	/**
	 * Download links are nonce-protected and tied to the page.
	 */
	public function test_download_link(): void {
		$page_id = Client_Repository::page_id( $this->make_client( 'Link Test' ) );
		$url     = Pdf_Download::url( $page_id );

		$this->assertStringContainsString( 'action=' . Pdf_Download::ACTION, $url );
		$this->assertStringContainsString( 'page_id=' . $page_id, $url );
		$this->assertStringContainsString( '_wpnonce=', $url );
	}
}
