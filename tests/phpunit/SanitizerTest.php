<?php
/**
 * Tests for Sanitizer::sanitize_sections().
 *
 * Extends PHPUnit\Framework\TestCase directly — WordPress is fully bootstrapped
 * via tests/bootstrap.php so WP functions are available, but WP_UnitTestCase
 * is not compatible with PHPUnit 10 in this environment.
 *
 * @package SatoriManifest
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use SatoriManifest\Sanitizer;

/**
 * Class SanitizerTest
 */
class SanitizerTest extends TestCase {

	/**
	 * Empty input returns an empty array.
	 */
	public function test_empty_sections_returns_empty_array(): void {
		$result = Sanitizer::sanitize_sections( array() );
		$this->assertSame( array(), $result );
	}
}
