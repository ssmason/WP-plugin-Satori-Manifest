<?php
/**
 * Tests for menu headings in a price list.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Core\Sanitizer;
use SatoriManifest\Manifest\Headings;
use SatoriManifest\Mcp\Assistant_Config;
use SatoriManifest\Mcp\Client_Context;
use SatoriManifest\Mcp\Page_Store;
use SatoriManifest\Mcp\Server\Tool_Error;
use SatoriManifest\Mcp\Toolbox;
use SatoriManifest\Pdf\Price_List_Pdf;

/**
 * Class HeadingsTest
 */
class HeadingsTest extends Test_Case {

	/**
	 * Resets the assistant settings cache.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		Assistant_Config::flush();
	}

	/**
	 * A list with a heading over two sections, then a plain section.
	 *
	 * @param  bool $visible  Whether the heading is switched on.
	 * @return array<int,array<string,mixed>>
	 */
	private function entries( bool $visible = true ): array {
		return array(
			array(
				'title' => 'Breakfast',
				'items' => array(
					array(
						'label' => 'Full Irish',
						'price' => '8.95',
					),
				),
			),
			array(
				'type'        => 'heading',
				'title'       => 'Main Menu',
				'description' => 'Served 9:30am - 10pm',
				'visible'     => $visible,
				'style'       => array(
					'background' => '#4B5F57',
					'text'       => 'not-a-colour',
					'size'       => 'huge',
				),
				'items'       => array(
					array(
						'label' => 'Ignored',
						'price' => '1',
					),
				),
			),
			array(
				'title' => 'Mains',
				'items' => array(
					array(
						'label' => 'Sirloin',
						'price' => '22.95',
					),
				),
			),
			array(
				'title' => 'Burgers',
				'items' => array(
					array(
						'label' => 'The Classic',
						'price' => '11.95',
					),
				),
			),
		);
	}

	/**
	 * Headings are cleaned and kept in order; switching one off hides its group only.
	 */
	public function test_sanitize_and_visibility(): void {
		$clean   = Sanitizer::sanitize_sections( $this->entries( false ) );
		$heading = $clean[1];

		$this->assertTrue( Headings::is_heading( $heading ) );
		$this->assertSame( 'Main Menu', $heading['title'] );
		$this->assertFalse( $heading['visible'] );
		$this->assertSame( '#4B5F57', $heading['style']['background'] );
		$this->assertSame( '', $heading['style']['text'] );
		$this->assertSame( 'm', $heading['style']['size'] );
		$this->assertSame( array(), $heading['items'] );
		$this->assertMatchesRegularExpression( '/^s_[a-f0-9]{8}$/', $heading['id'] );

		$this->assertSame( array( 'Breakfast' ), array_column( Headings::visible( $clean ), 'title' ) );
		$this->assertSame( array( 'Breakfast', 'Mains', 'Burgers' ), array_column( Headings::sections_only( $clean ), 'title' ) );

		$on = Sanitizer::sanitize_sections( $this->entries() );
		$this->assertCount( 4, Headings::visible( $on ) );
		$this->assertSame( '--sm-heading-bg:#4B5F57;', Headings::style_vars( $on[1] ) );
	}

	/**
	 * The page shows the heading as a banner; switched off, the group disappears from page and PDF.
	 */
	public function test_page_and_pdf(): void {
		$client  = $this->make_client( 'Heading Bar' );
		$store   = new Page_Store( Client_Context::for_user( $client ) );
		$page_id = Client_Repository::page_id( $client );

		$store->save_sections( $this->entries() );

		$this->go_to( get_permalink( $page_id ) );
		$html = render_block( parse_blocks( '<!-- wp:satori-manifest/price-list /-->' )[0] );

		$this->assertStringContainsString( 'satori-manifest-price-list__heading', $html );
		$this->assertStringContainsString( 'Main Menu', $html );
		$this->assertStringContainsString( 'id="main-menu"', $html );
		$this->assertStringContainsString( 'Sirloin', $html );

		$pdf_on = Price_List_Pdf::render( $page_id );

		$entries               = $store->sections();
		$entries[1]['visible'] = false;
		$store->save_sections( $entries );

		$html = render_block( parse_blocks( '<!-- wp:satori-manifest/price-list /-->' )[0] );
		$this->assertStringNotContainsString( 'Main Menu', $html );
		$this->assertStringNotContainsString( 'Sirloin', $html );
		$this->assertStringContainsString( 'Full Irish', $html );

		$this->assertLessThan( strlen( $pdf_on ), strlen( Price_List_Pdf::render( $page_id ) ), 'The hidden menu is left out of the PDF.' );
	}

	/**
	 * The assistant adds, switches off and prices by heading, and never puts items in one.
	 */
	public function test_assistant_tools(): void {
		$client = $this->make_client( 'Heading Tools' );
		$store  = new Page_Store( Client_Context::for_user( $client ) );

		$store->save_sections( array_values( array_filter( $this->entries(), static fn( array $entry ): bool => 'heading' !== ( $entry['type'] ?? '' ) ) ) );

		$result = $this->tool( $store, 'add_heading' )->call(
			array(
				'title'      => 'Main Menu',
				'position'   => 2,
				'background' => '#4B5F57',
				'size'       => 'l',
			)
		)->to_array();

		$this->assertStringContainsString( 'covers: Mains, Burgers', $result['content'][0]['text'] );

		$heading_id = $store->sections()[1]['id'];

		// Prices by heading: everything under "Main Menu" up by €1, breakfast untouched.
		$this->tool( $store, 'adjust_prices' )->call(
			array(
				'amount'      => 1,
				'section_ids' => array( $heading_id ),
			)
		);
		$this->assertSame( '23.95', $store->sections()[2]['items'][0]['price'] );
		$this->assertSame( '12.95', $store->sections()[3]['items'][0]['price'] );
		$this->assertSame( '8.95', $store->sections()[0]['items'][0]['price'] );

		// "Turn the main menu off".
		$this->tool( $store, 'update_heading' )->call(
			array(
				'heading_id' => $heading_id,
				'visible'    => false,
			)
		);
		$this->assertFalse( $store->sections()[1]['visible'] );
		$this->assertSame( '#4b5f57', $store->sections()[1]['style']['background'] );

		// Items never go into a heading.
		try {
			$this->tool( $store, 'add_items' )->call(
				array(
					'section_id' => $heading_id,
					'items'      => array( array( 'label' => 'Soup' ) ),
				)
			);
			$this->fail( 'Expected a Tool_Error.' );
		} catch ( Tool_Error $error ) {
			$this->assertStringContainsString( 'is a heading', $error->getMessage() );
		}

		// Undo brings it back on.
		$this->tool( $store, 'undo_last_change' )->call( array() );
		$this->assertTrue( $store->sections()[1]['visible'] );

		// Set-up can start headings from a menu.
		$this->tool( $store, 'set_up_price_list' )->call(
			array(
				'replace_existing' => true,
				'sections'         => array(
					array(
						'title'   => 'Cocktails',
						'heading' => 'Drinks',
						'items'   => array(
							array(
								'label' => 'Mojito',
								'price' => 7,
							),
						),
					),
				),
			)
		);
		$this->assertSame( array( 'Drinks', 'Cocktails' ), array_column( $store->sections(), 'title' ) );
		$this->assertTrue( Headings::is_heading( $store->sections()[0] ) );
	}

	/**
	 * Finds a tool by name.
	 *
	 * @param  Page_Store $store  Client's page.
	 * @param  string     $name   Tool name.
	 * @return \SatoriManifest\Mcp\Server\Tool
	 */
	private function tool( Page_Store $store, string $name ): \SatoriManifest\Mcp\Server\Tool {
		foreach ( Toolbox::tools( $store ) as $tool ) {
			if ( $tool->name() === $name ) {
				return $tool;
			}
		}

		$this->fail( 'No tool ' . $name );
	}
}
