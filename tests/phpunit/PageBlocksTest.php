<?php
/**
 * Tests for the AI assistant's page block tools.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Mcp\Assistant_Config;
use SatoriManifest\Mcp\Client_Context;
use SatoriManifest\Mcp\Page_Store;
use SatoriManifest\Mcp\Server\Tool_Error;
use SatoriManifest\Mcp\Toolbox;

/**
 * Class PageBlocksTest
 */
class PageBlocksTest extends Test_Case {

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
	 * Tools by name for a client.
	 *
	 * @param  Page_Store $store  Client's page.
	 * @return array<string,\SatoriManifest\Mcp\Server\Tool>
	 */
	private function tools( Page_Store $store ): array {
		$out = array();

		foreach ( Toolbox::tools( $store ) as $tool ) {
			$out[ $tool->name() ] = $tool;
		}

		return $out;
	}

	/**
	 * The assistant adds, changes and removes blocks inside groups, never the
	 * price list's own blocks, nothing unsafe, and every change can be undone.
	 */
	public function test_blocks_in_groups(): void {
		$client  = $this->make_client( 'Blocks Bar' );
		$page_id = Client_Repository::page_id( $client );
		$store   = new Page_Store( Client_Context::for_user( $client ) );
		$tools   = $this->tools( $store );

		wp_set_current_user( $client );
		wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => '<!-- wp:group {"metadata":{"name":"Menu"},"layout":{"type":"constrained"}} -->' . "\n" . '<div class="wp-block-group"><!-- wp:satori-manifest/price-list /--></div>' . "\n" . '<!-- /wp:group -->',
			)
		);
		$original = (string) get_post_field( 'post_content', $page_id );

		$layout = $tools['get_page_layout']->call( array() )->to_array()['structuredContent']['layout'];
		$this->assertSame( 'g1', $layout[0]['id'] );
		$this->assertSame( 'Menu', $layout[0]['name'] );
		$this->assertSame( 'price list menu', $layout[0]['holds'] );
		$this->assertFalse( $layout[0]['blocks'][0]['editable'] );

		$tools['add_block']->call(
			array(
				'group' => 'g1',
				'where' => 'top',
				'block' => array(
					'type'  => 'heading',
					'text'  => 'Today only',
					'level' => 3,
				),
			)
		);
		$tools['add_block']->call(
			array(
				'group'    => 'g1',
				'where'    => 'after',
				'relative' => 'g1.1',
				'block'    => array(
					'type' => 'paragraph',
					'text' => 'Ham, egg & chips <strong>€6.50</strong><script>x</script>',
				),
			)
		);

		$content = (string) get_post_field( 'post_content', $page_id );
		$this->assertStringContainsString( '<h3 class="wp-block-heading">Today only</h3>', $content );
		$this->assertStringContainsString( '<strong>€6.50</strong>', $content );
		$this->assertStringNotContainsString( '<script', $content );
		$this->assertLessThan( strpos( $content, 'wp:satori-manifest/price-list' ), strpos( $content, 'Ham, egg' ) );

		$tools['update_block']->call(
			array(
				'block_id' => 'g1.2',
				'block'    => array(
					'type' => 'paragraph',
					'text' => 'Changed',
				),
			)
		);
		$this->assertStringContainsString( '<p>Changed</p>', (string) get_post_field( 'post_content', $page_id ) );

		// Not the price list's own blocks, and nothing the client may not use.
		foreach ( array( 'g1.3' ) as $managed ) {
			try {
				$tools['remove_block']->call( array( 'block_id' => $managed ) );
				$this->fail( 'Expected a Tool_Error.' );
			} catch ( Tool_Error $error ) {
				$this->assertStringContainsString( 'price list', $error->getMessage() );
			}
		}

		try {
			$tools['add_block']->call(
				array(
					'group' => 'g1',
					'block' => array(
						'type'   => 'markup',
						'markup' => '<!-- wp:html --><script>x</script><!-- /wp:html -->',
					),
				)
			);
			$this->fail( 'Expected a Tool_Error.' );
		} catch ( Tool_Error $error ) {
			$this->assertStringContainsString( 'not available', $error->getMessage() );
		}

		$tools['remove_block']->call( array( 'block_id' => 'g1.1' ) );
		$this->assertStringNotContainsString( 'Today only', (string) get_post_field( 'post_content', $page_id ) );

		// Undo, step by step, back to the page as it was.
		for ( $i = 0; $i < 4; $i++ ) {
			$tools['undo_last_change']->call( array() );
		}
		$this->assertSame( $original, (string) get_post_field( 'post_content', $page_id ) );
	}

	/**
	 * A section image: kept by the sanitizer, set by the assistant only from the
	 * client's own images, removed with 0, and drawn under the section title.
	 */
	public function test_section_image(): void {
		$client = $this->make_client( 'Image Bar' );
		$other  = $this->make_client( 'Other Bar' );
		$store  = new Page_Store( Client_Context::for_user( $client ) );
		$tools  = $this->tools( $store );
		$file   = get_temp_dir() . 'sm-section.png';
		$image  = imagecreatetruecolor( 40, 20 );

		imagepng( $image, $file );

		wp_set_current_user( $other );
		$theirs = self::factory()->attachment->create_upload_object( $file );
		wp_set_current_user( $client );
		$mine = self::factory()->attachment->create_upload_object( $file );

		$tools['add_section']->call( array( 'title' => 'Cakes' ) );
		$id = (string) $store->sections()[0]['id'];

		try {
			$tools['update_section']->call(
				array(
					'section_id' => $id,
					'image_id'   => $theirs,
				)
			);
			$this->fail( 'Another client\'s image was accepted.' );
		} catch ( Tool_Error $error ) {
			$this->assertStringContainsString( 'list_images', $error->getMessage() );
		}

		$tools['update_section']->call(
			array(
				'section_id' => $id,
				'image_id'   => $mine,
			)
		);
		$this->assertSame( $mine, (int) $store->sections()[0]['image'] );
		$this->assertSame( $mine, \SatoriManifest\Mcp\Price_List::to_public( $store->sections() )[0]['image_id'] );
		$GLOBALS['post'] = get_post( Client_Repository::page_id( $client ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- render the block in the page's context.
		$this->assertStringContainsString( 'satori-manifest-price-list__section-image', do_blocks( '<!-- wp:satori-manifest/price-list /-->' ) );

		$tools['update_section']->call(
			array(
				'section_id' => $id,
				'image_id'   => 0,
			)
		);
		$this->assertSame( 0, (int) $store->sections()[0]['image'] );
	}
}
