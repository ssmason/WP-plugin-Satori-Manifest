<?php
/**
 * Tests for the dependency-free YAML parser.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Core\Yaml;

/**
 * Yaml::parse() covers the subset the plugin's config files use.
 */
class YamlTest extends Test_Case {

	/**
	 * Scalars are typed: numbers, booleans and strings.
	 */
	public function test_scalars_are_typed(): void {
		$data = Yaml::parse( "count: 3\nprice: 9.5\nlive: true\nname: Studio\n" );

		$this->assertSame( 3, $data['count'] );
		$this->assertSame( 9.5, $data['price'] );
		$this->assertTrue( $data['live'] );
		$this->assertSame( 'Studio', $data['name'] );
	}

	/**
	 * Nested maps and lists of maps.
	 */
	public function test_nested_maps_and_lists(): void {
		$yaml = "plans:\n  basic:\n    title: Basic\n    features:\n      - One\n      - Two\nmenu:\n  - label: Home\n    url: /\n";
		$data = Yaml::parse( $yaml );

		$this->assertSame( 'Basic', $data['plans']['basic']['title'] );
		$this->assertSame( array( 'One', 'Two' ), $data['plans']['basic']['features'] );
		$this->assertSame( 'Home', $data['menu'][0]['label'] );
	}

	/**
	 * Comments are ignored; quoted strings keep their content.
	 */
	public function test_comments_and_quotes(): void {
		$data = Yaml::parse( "# heading\nsubject: \"New: {{name}}\" # trailing\nnote: 'It''s fine'\nempty: \"\"\n" );

		$this->assertSame( 'New: {{name}}', $data['subject'] );
		$this->assertSame( 'empty', array_key_last( $data ) );
		$this->assertSame( '', $data['empty'] );
	}

	/**
	 * Literal blocks keep line breaks (used for email bodies).
	 */
	public function test_literal_block(): void {
		$data = Yaml::parse( "body: |\n  Line one.\n\n  Line two.\nnext: x\n" );

		$this->assertStringContainsString( "Line one.\n\nLine two.", $data['body'] );
		$this->assertSame( 'x', $data['next'] );
	}

	/**
	 * Every shipped config file parses to a non-empty array.
	 */
	public function test_shipped_config_files_parse(): void {
		$files = array_merge(
			glob( SATORI_MANIFEST_PATH . 'config/*.yaml' ),
			glob( SATORI_MANIFEST_PATH . 'config/*/*.yaml' ),
			glob( SATORI_MANIFEST_PATH . 'designs/*/design.yaml' )
		);

		$this->assertNotEmpty( $files );

		foreach ( $files as $file ) {
			$this->assertNotEmpty( Yaml::parse_file( $file ), basename( $file ) . ' should parse' );
		}
	}
}
