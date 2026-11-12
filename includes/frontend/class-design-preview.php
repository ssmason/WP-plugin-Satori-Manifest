<?php
/**
 * Design preview with the starter layout.
 *
 * On the Design screen each card previews the design's full starter page -
 * photos, sections and the client's own name and price list - before it is
 * applied. The preview only swaps what is shown; nothing is saved.
 *
 * URL: {client page}?sm_design={slug}&sm_layout=1 (editors of the page only).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Frontend;

use SatoriManifest\Branding\Brand;
use SatoriManifest\Designs\Design_Applier;
use SatoriManifest\Designs\Design_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Design_Preview
 *
 * Shows a design's starter layout in place of the page content.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */
class Design_Preview {

	/**
	 * Query var that asks for the starter layout.
	 *
	 * @since 3.8.0
	 * @var   string
	 */
	public const LAYOUT_VAR = 'sm_layout';

	/**
	 * Registers the content filter.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return void
	 */
	public static function register(): void {
		// Before do_blocks (priority 9) so the starter markup is rendered as blocks.
		add_filter( 'the_content', array( self::class, 'starter_content' ), 1 );
	}

	/**
	 * Replaces the page content with the previewed design's starter layout.
	 *
	 * Hooked to 'the_content'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  string $content  Page content.
	 * @return string
	 */
	public static function starter_content( string $content ): string {
		$page_id = Client_Page::current_page_id();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch, checked against edit rights below.
		if ( ! $page_id || ! isset( $_GET[ self::LAYOUT_VAR ] ) || ! in_the_loop() || (int) get_the_ID() !== $page_id || ! current_user_can( 'edit_post', $page_id ) ) {
			return $content;
		}

		$design  = Client_Page::design_for_request( $page_id );
		$brand   = Brand::for_page( $page_id, $design, Client_Page::mode_for_request( $page_id ) );
		$starter = Design_Registry::content(
			$design,
			array_filter(
				array(
					'brand_name' => (string) $brand['name'],
					'tagline'    => (string) $brand['tagline'],
				)
			),
			array(),
			Design_Applier::section_ids( $page_id )
		);

		return '' !== $starter ? $starter : $content;
	}
}
