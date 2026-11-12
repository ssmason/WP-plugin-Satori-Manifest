<?php
/**
 * Tests for the price list block's view data.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Manifest\Manifest_Repository;
use SatoriManifest\Manifest\Price_List_View;

/**
 * Price_List_View: own manifest, hidden sections, ownership, wrapper and anchors.
 */
class PriceListViewTest extends Test_Case {

	/**
	 * A client page shows its own manifest; hidden sections drop out.
	 */
	public function test_own_manifest_and_hidden_sections(): void {
		$client   = $this->make_client( 'View Bistro' );
		$page     = Client_Repository::page_id( $client );
		$sections = Manifest_Repository::get_sections( Client_Repository::manifest_id( $client ) );

		$view = Price_List_View::prepare( array(), $page );
		$this->assertCount( count( $sections ), $view['manifests'][0] );

		$hidden = Price_List_View::prepare( array( 'excludedSectionIds' => array( $sections[0]['id'] ) ), $page );
		$this->assertNull( $hidden, 'Hiding the only section leaves nothing on the front end.' );
	}

	/**
	 * Another client's manifest is never shown on a client page.
	 */
	public function test_other_clients_manifest_is_ignored(): void {
		$mine   = Client_Repository::page_id( $this->make_client( 'Mine Cafe' ) );
		$theirs = Client_Repository::manifest_id( $this->make_client( 'Theirs Cafe' ) );

		$this->assertNull( Price_List_View::prepare( array( 'manifestIds' => array( $theirs ) ), $mine ) );
	}

	/**
	 * Classes and CSS variables come from sanitised attributes only.
	 */
	public function test_wrapper_is_sanitised(): void {
		$page = Client_Repository::page_id( $this->make_client( 'Style Bar' ) );
		$view = Price_List_View::prepare(
			array(
				'layout'          => 'two-column',
				'showItemBorder'  => false,
				'titleFontFamily' => 'Georgia; background:url(x)',
				'titleFontWeight' => '999',
				'titleFontSize'   => 20,
			),
			$page
		);

		$this->assertStringContainsString( 'is-layout-two-column', $view['wrapper']['class'] );
		$this->assertStringContainsString( 'has-no-item-border', $view['wrapper']['class'] );
		$this->assertStringContainsString( '--sm-title-size:20px;', $view['wrapper']['style'] );
		$this->assertStringNotContainsString( 'url(', $view['wrapper']['style'] );
		$this->assertStringNotContainsString( '--sm-title-weight', $view['wrapper']['style'] );
	}

	/**
	 * Anchors are readable and unique within a block.
	 */
	public function test_anchors(): void {
		$seen = array();

		$this->assertSame( 'hot-drinks', Price_List_View::anchor( 'Hot drinks', $seen ) );
		$this->assertSame( 'hot-drinks-2', Price_List_View::anchor( 'Hot drinks', $seen ) );
		$this->assertSame( '', Price_List_View::anchor( '', $seen ) );
	}
}
