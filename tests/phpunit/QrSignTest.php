<?php
/**
 * Tests for the printable QR sign (QR builder).
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Qr\Qr_Download;
use SatoriManifest\Qr\Qr_Sign;
use SatoriManifest\Qr\Qr_Sign_Pdf;

/**
 * Class QrSignTest
 */
class QrSignTest extends Test_Case {

	/**
	 * Defaults, cleaning and the small sign's fixed colour.
	 */
	public function test_settings(): void {
		$page_id  = Client_Repository::page_id( $this->make_client( 'Sign Bar' ) );
		$defaults = Qr_Sign::settings( $page_id );

		$this->assertSame( 'large', $defaults['size'] );
		$this->assertSame( 'Sign Bar', $defaults['title'] );
		$this->assertSame( Qr_Sign::DEFAULT_LARGE, $defaults['colour'] );

		$saved = Qr_Sign::save(
			$page_id,
			array(
				'size'     => 'small',
				'title'    => '<b>Our Drinks</b> and a much longer title than allowed',
				'subtitle' => '',
				'colour'   => 'not-a-colour',
			)
		);

		$this->assertSame( 'small', $saved['size'] );
		$this->assertSame( Qr_Sign::MAX_TITLE, mb_strlen( $saved['title'] ) );
		$this->assertStringNotContainsString( '<b>', $saved['title'] );
		$this->assertSame( '', $saved['subtitle'] );
		$this->assertSame( Qr_Sign::SMALL_ACCENT, Qr_Sign::accent( $saved ) );
		$this->assertSame( 'small', Qr_Sign::settings( $page_id )['size'] );
		$this->assertSame( 'large', Qr_Sign::clean( array( 'size' => 'huge' ) )['size'] );
	}

	/**
	 * The SVG carries the wording (escaped), the colour and a real code.
	 */
	public function test_svg(): void {
		$page_id = Client_Repository::page_id( $this->make_client( 'Svg Bar' ) );

		$large = Qr_Sign::svg(
			$page_id,
			array(
				'size'     => 'large',
				'title'    => 'Fish & <Chips>',
				'subtitle' => 'Scan me',
				'colour'   => '#044a14',
			)
		);

		$this->assertStringStartsWith( '<svg', $large );
		$this->assertStringContainsString( 'Fish &amp; &lt;Chips&gt;', $large );
		$this->assertStringContainsString( '#044a14', $large );
		$this->assertStringContainsString( 'Scan me', $large );
		$this->assertMatchesRegularExpression( '/<path fill="#111111" d="M\d/', $large );

		$small = Qr_Sign::svg(
			$page_id,
			array(
				'size'     => 'small',
				'title'    => 'Our Menu',
				'subtitle' => 'ignored',
				'colour'   => '#044a14',
			)
		);

		$this->assertStringContainsString( '<tspan fill="' . Qr_Sign::SMALL_ACCENT . '"', $small );
		$this->assertStringContainsString( '>Menu</tspan>', $small );
		$this->assertStringNotContainsString( '#044a14', $small );
		$this->assertStringNotContainsString( 'ignored', $small );

		// Long text shrinks rather than overflowing.
		$this->assertLessThan( 17.0, Qr_Sign::fit( str_repeat( 'W', 30 ), 17.0, 160.0 ) );
		$this->assertSame( 17.0, Qr_Sign::fit( 'Bar', 17.0, 160.0 ) );
	}

	/**
	 * Both sizes print on one A4 page; the download link is nonce-protected.
	 */
	public function test_pdf_and_download(): void {
		$page_id = Client_Repository::page_id( $this->make_client( 'Pdf Bar' ) );

		foreach ( array( 'large', 'small' ) as $size ) {
			Qr_Sign::save( $page_id, array( 'size' => $size ) );
			$pdf = Qr_Sign_Pdf::render( $page_id );

			$this->assertStringStartsWith( '%PDF-1.4', $pdf );
			$this->assertStringContainsString( '/Count 1', $pdf );
			$this->assertStringContainsString( '/MediaBox [0 0 595.28 841.89]', $pdf );
		}

		$this->assertSame( 'pdf-bar-qr-sign.pdf', Qr_Sign_Pdf::filename( $page_id ) );
		$this->assertSame( '', Qr_Sign_Pdf::render( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );

		$url = Qr_Download::url( $page_id, 'sign' );
		$this->assertStringContainsString( 'format=sign', $url );
		$this->assertStringContainsString( '_wpnonce=', $url );
		$this->assertStringContainsString( 'format=png', Qr_Download::url( $page_id, 'nonsense' ) );
	}
}
