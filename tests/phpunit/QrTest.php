<?php
/**
 * Tests for the QR code encoder and renderer.
 *
 * The encoder was verified end to end by decoding its output with a real
 * scanner (Chrome's BarcodeDetector) across versions 1–19 and every
 * error-correction level; these tests pin that output and its structure.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Qr\Page_Qr;
use SatoriManifest\Qr\Qr_Download;
use SatoriManifest\Qr\Qr_Encoder;
use SatoriManifest\Qr\Qr_Renderer;

/**
 * Qr_Encoder, Qr_Renderer and Page_Qr.
 */
class QrTest extends Test_Case {

	/**
	 * A scanner-verified code is reproduced exactly.
	 */
	public function test_matches_verified_reference(): void {
		$matrix = Qr_Encoder::encode( 'http://localhost:5080/blue-door-barbers/?src=qr', 'Q' );
		$bits   = '';

		foreach ( $matrix as $row ) {
			foreach ( $row as $dark ) {
				$bits .= $dark ? '1' : '0';
			}
		}

		$this->assertCount( 37, $matrix, 'Version 5 is 37 modules wide.' );
		$this->assertSame( 'fb9fc8220692e70ac232c2da4096b800', md5( $bits ) );
	}

	/**
	 * The smallest version that fits is chosen, and the three finder patterns are in place.
	 */
	public function test_version_and_finder_patterns(): void {
		$this->assertCount( 21, Qr_Encoder::encode( 'Hello', 'M' ), 'Short text fits version 1.' );

		$matrix = Qr_Encoder::encode( str_repeat( 'a', 200 ), 'M' );
		$size   = count( $matrix );
		$this->assertGreaterThan( 41, $size, 'Longer text needs a bigger version.' );

		foreach ( array( array( 0, 0 ), array( 0, $size - 7 ), array( $size - 7, 0 ) ) as $corner ) {
			list( $row, $col ) = $corner;
			$this->assertTrue( $matrix[ $row ][ $col ], 'Finder outer ring is dark.' );
			$this->assertFalse( $matrix[ $row + 1 ][ $col + 1 ], 'Finder inner ring is light.' );
			$this->assertTrue( $matrix[ $row + 3 ][ $col + 3 ], 'Finder centre is dark.' );
		}
	}

	/**
	 * Text that cannot fit is refused.
	 */
	public function test_too_long(): void {
		$this->expectException( \InvalidArgumentException::class );
		Qr_Encoder::encode( str_repeat( 'x', 4000 ), 'H' );
	}

	/**
	 * SVG and PNG output include the quiet zone.
	 */
	public function test_renderers(): void {
		$matrix = Qr_Encoder::encode( 'Hello', 'M' );

		$svg = Qr_Renderer::svg( $matrix, '#0b1620', 'Code' );
		$this->assertStringContainsString( 'viewBox="0 0 29 29"', $svg );
		$this->assertStringContainsString( 'fill="#0b1620"', $svg );

		$png = Qr_Renderer::png( $matrix, 10 );
		$this->assertStringStartsWith( "\x89PNG", $png );
		$this->assertSame( array( 290, 290 ), array_slice( (array) getimagesizefromstring( $png ), 0, 2 ) );
	}

	/**
	 * A client page's code opens the page and marks the visit as a scan.
	 */
	public function test_page_qr(): void {
		$page_id = Client_Repository::page_id( $this->make_client( 'Scan Me' ) );

		$this->assertStringContainsString( 'src=qr', Page_Qr::url( $page_id ) );
		$this->assertStringStartsWith( (string) get_permalink( $page_id ), Page_Qr::url( $page_id ) );
		$this->assertNotEmpty( Page_Qr::matrix( $page_id ) );
		$this->assertSame( '', Page_Qr::url( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );
		$this->assertStringContainsString( '_wpnonce=', Qr_Download::url( $page_id, 'svg' ) );
	}
}
