<?php
/**
 * Design applier.
 *
 * Switches a client page to another design, optionally replacing its
 * content with that design's starter layout.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Designs;

use SatoriManifest\Branding\Brand;
use SatoriManifest\Clients\Client_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Design_Applier
 *
 * Writes the design choice to the page and (on request) its starter content.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Design_Applier {

	/**
	 * Sets a page's design and optionally replaces its content.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  int    $page_id          Client page ID.
	 * @param  string $design           Design slug.
	 * @param  bool   $replace_content  Whether to load the design's starter content.
	 * @return void
	 */
	public static function apply( int $page_id, string $design, bool $replace_content ): void {
		update_post_meta( $page_id, Client_Repository::PAGE_DESIGN, $design );
		Brand::flush();

		if ( ! $replace_content ) {
			return;
		}

		$brand = Brand::for_page( $page_id, $design );

		wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => wp_slash(
					Design_Registry::content(
						$design,
						array_filter(
							array(
								'brand_name' => (string) $brand['name'],
								'tagline'    => (string) $brand['tagline'],
							)
						)
					)
				),
			)
		);
	}
}
