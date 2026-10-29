<?php
/**
 * Tests for the price-list sanitizer.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Core\Sanitizer;

/**
 * Sanitizer::sanitize_sections() and section IDs.
 */
class SanitizerTest extends Test_Case {

	/**
	 * Empty input stays empty.
	 */
	public function test_empty_sections_returns_empty_array(): void {
		$this->assertSame( array(), Sanitizer::sanitize_sections( array() ) );
	}

	/**
	 * Every section gets a permanent id, and existing valid ids are kept.
	 */
	public function test_sections_get_permanent_ids(): void {
		$result = Sanitizer::sanitize_sections(
			array(
				array(
					'title' => 'Cuts',
					'id'    => 's_0123abcd',
					'items' => array(),
				),
				array(
					'title' => 'Colour',
					'items' => array(),
				),
			)
		);

		$this->assertSame( 's_0123abcd', $result[0]['id'] );
		$this->assertMatchesRegularExpression( '/^s_[a-f0-9]{8}$/', $result[1]['id'] );
	}

	/**
	 * Duplicate ids are replaced so each section stays addressable.
	 */
	public function test_duplicate_ids_are_replaced(): void {
		$result = Sanitizer::sanitize_sections(
			array(
				array(
					'title' => 'A',
					'id'    => 's_0123abcd',
				),
				array(
					'title' => 'B',
					'id'    => 's_0123abcd',
				),
			)
		);

		$this->assertNotSame( $result[0]['id'], $result[1]['id'] );
	}

	/**
	 * Malformed ids are rejected.
	 */
	public function test_section_id_validation(): void {
		$this->assertSame( 's_0123abcd', Sanitizer::section_id( 's_0123abcd' ) );
		$this->assertSame( '', Sanitizer::section_id( '<script>' ) );
		$this->assertSame( '', Sanitizer::section_id( 's_XYZ' ) );
	}

	/**
	 * Markup is stripped from labels and prices become numbers.
	 */
	public function test_items_are_cleaned(): void {
		$result = Sanitizer::sanitize_sections(
			array(
				array(
					'title' => '<b>Cuts</b>',
					'items' => array(
						array(
							'label' => 'Fade <script>x</script>',
							'price' => '25.50',
						),
					),
				),
			)
		);

		$this->assertSame( 'Cuts', $result[0]['title'] );
		$this->assertStringNotContainsString( '<script>', $result[0]['items'][0]['label'] );
		$this->assertEquals( 25.5, $result[0]['items'][0]['price'] );
	}
}
