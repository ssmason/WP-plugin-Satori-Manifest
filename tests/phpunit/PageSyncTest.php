<?php
/**
 * Tests for headings on the page: automatic heading and menu groups.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Manifest\Page_Sync;
use SatoriManifest\Mcp\Client_Context;
use SatoriManifest\Mcp\Page_Store;

/**
 * Class PageSyncTest
 */
class PageSyncTest extends Test_Case {

	/**
	 * Top-level blocks of a page: [ name, group class, inner block, headingId ].
	 *
	 * @param  int $page_id  Page.
	 * @return array<int,string>
	 */
	private function layout( int $page_id ): array {
		$out = array();

		foreach ( parse_blocks( (string) get_post_field( 'post_content', $page_id ) ) as $block ) {
			if ( 'core/group' !== $block['blockName'] || ! str_contains( (string) ( $block['attrs']['className'] ?? '' ), 'sm-auto-' ) ) {
				if ( null !== $block['blockName'] ) {
					$out[] = 'other';
				}
				continue;
			}

			$inner = $block['innerBlocks'][0] ?? array();
			$out[] = ( str_contains( $block['attrs']['className'], Page_Sync::HEADING_CLASS ) ? 'heading:' : 'menu:' ) . ( $block['attrs']['metadata']['name'] ?? '' );
		}

		return $out;
	}

	/**
	 * A section with one item.
	 *
	 * @param  string $title  Title.
	 * @param  string $item   Item name.
	 * @return array<string,mixed>
	 */
	private function section( string $title, string $item ): array {
		return array(
			'title' => $title,
			'items' => array(
				array(
					'label' => $item,
					'price' => '5',
				),
			),
		);
	}

	/**
	 * A heading.
	 *
	 * @param  string $title  Title.
	 * @return array<string,mixed>
	 */
	private function heading( string $title ): array {
		return array(
			'type'    => 'heading',
			'title'   => $title,
			'visible' => true,
		);
	}

	/**
	 * A new heading gets its banner group and menu group, in price list order,
	 * moves with the list and goes when deleted; client content is left alone.
	 */
	public function test_groups_follow_the_price_list(): void {
		$client  = $this->make_client( 'Sync Bar' );
		$store   = new Page_Store( Client_Context::for_user( $client ) );
		$page_id = Client_Repository::page_id( $client );

		wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => '<!-- wp:group {"layout":{"type":"constrained"}} -->' . "\n" . '<div class="wp-block-group"><!-- wp:satori-manifest/price-list /--></div>' . "\n" . '<!-- /wp:group -->',
			)
		);

		$saved = $store->save_sections(
			array(
				$this->section( 'Breakfast', 'Full Irish' ),
				$this->heading( 'Lunch' ),
				$this->section( 'Sandwiches', 'Ham Salad' ),
				$this->heading( 'Christmas Menu' ),
				$this->section( 'Christmas Dinner', 'Turkey' ),
			)
		);

		$this->assertSame(
			array( 'other', 'heading:Heading: Lunch', 'menu:Menu: Lunch', 'heading:Heading: Christmas Menu', 'menu:Menu: Christmas Menu' ),
			$this->layout( $page_id )
		);

		$this->go_to( get_permalink( $page_id ) );
		$html = do_blocks( (string) get_post_field( 'post_content', $page_id ) );
		$this->assertSame( 1, substr_count( $html, 'Turkey' ), 'The Christmas section shows once, in its own menu.' );
		$this->assertSame( 1, substr_count( $html, 'Full Irish' ) );
		$this->assertLessThan( strpos( $html, 'Turkey' ), strpos( $html, 'Christmas Menu' ) );

		// Moved above Lunch in the price list: its groups move above Lunch's on the page.
		$store->save_sections( array( $saved[0], $saved[3], $saved[4], $saved[1], $saved[2] ) );
		$this->assertSame(
			array( 'other', 'heading:Heading: Christmas Menu', 'menu:Menu: Christmas Menu', 'heading:Heading: Lunch', 'menu:Menu: Lunch' ),
			$this->layout( $page_id )
		);

		// Switched off: nothing of it renders (no empty band); Lunch still does.
		$off               = $store->sections();
		$off[1]['visible'] = false;
		$store->save_sections( $off );
		$html = do_blocks( (string) get_post_field( 'post_content', $page_id ) );
		$this->assertStringNotContainsString( 'Christmas', $html );
		$this->assertStringNotContainsString( 'Turkey', $html );
		$this->assertStringContainsString( 'Ham Salad', $html );

		// Deleted: its groups go.
		$store->save_sections( array( $saved[0], $saved[1], $saved[2] ) );
		$this->assertSame( array( 'other', 'heading:Heading: Lunch', 'menu:Menu: Lunch' ), $this->layout( $page_id ) );

		// A heading group the client has added their own content to stays put.
		$store->save_sections( array( $saved[0], $saved[1], $saved[2], $this->heading( 'Specials' ) ) );
		$content = (string) get_post_field( 'post_content', $page_id );
		$content = str_replace( '<!-- wp:satori-manifest/price-list-heading', '<!-- wp:paragraph -->' . "\n" . '<p>Ask about today\'s fish</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n" . '<!-- wp:satori-manifest/price-list-heading', $content );
		wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => wp_slash( $content ),
			)
		);
		$store->save_sections( array( $saved[0], $saved[1], $saved[2] ) );
		$this->assertStringContainsString( "today's fish", (string) get_post_field( 'post_content', $page_id ) );
	}
}
