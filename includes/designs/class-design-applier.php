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
use SatoriManifest\Core\Media_Importer;
use SatoriManifest\Core\Page_Cache;
use SatoriManifest\Manifest\Manifest_Repository;

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
	 * @param  string $mode             Colour mode to save ('light' or 'dark'); '' keeps the current one.
	 * @return void
	 */
	public static function apply( int $page_id, string $design, bool $replace_content, string $mode = '' ): void {
		update_post_meta( $page_id, Client_Repository::PAGE_DESIGN, $design );

		if ( in_array( $mode, Design_Registry::COLOUR_MODES, true ) ) {
			update_post_meta( $page_id, Client_Repository::PAGE_MODE, $mode );
		}

		Brand::flush();

		// Design and colour mode are page settings, not a post update, so clear the cached page here.
		Page_Cache::clear( $page_id );

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
						),
						self::import_images( $page_id, $design ),
						self::section_ids( $page_id )
					)
				),
			)
		);

		Client_Repository::remember_starter( $page_id );
	}

	/**
	 * Adds the design's photos to the page owner's media library.
	 *
	 * Attached to the page so they appear in the client's media picker; applying
	 * the same design again reuses them.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  int    $page_id  Client page ID.
	 * @param  string $design   Design slug.
	 * @return array<string,int>  Attachment IDs by photo name.
	 */
	private static function import_images( int $page_id, string $design ): array {
		$entry = Design_Registry::get( $design );
		$owner = Client_Repository::client_for_page( $page_id );
		$ids   = array();

		foreach ( $entry ? $entry['images'] : array() as $name => $image ) {
			$id = Media_Importer::import( $image['path'], $owner, $page_id );

			if ( $id ) {
				$ids[ $name ] = $id;
			}
		}

		return $ids;
	}

	/**
	 * The page owner's price list section ids, in order.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  int $page_id  Client page ID.
	 * @return array<int,string>
	 */
	public static function section_ids( int $page_id ): array {
		$manifest_id = Client_Repository::manifest_id( Client_Repository::client_for_page( $page_id ) );

		return $manifest_id
			? array_values( array_filter( array_map( static fn( array $section ): string => (string) ( $section['id'] ?? '' ), Manifest_Repository::get_sections( $manifest_id ) ) ) )
			: array();
	}
}
