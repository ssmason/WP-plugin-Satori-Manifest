<?php
/**
 * Block pattern registration.
 *
 * Registers each design's starter layout (designs/{slug}/content.html) as a
 * block pattern in the "Manifest" category, so the layouts can also be
 * inserted from the block editor's pattern inserter. The Manifest block in
 * these patterns has no manifest selected — on a client page it
 * automatically shows that client's price list.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Manifest;

use SatoriManifest\Designs\Design_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Patterns
 *
 * Registers the plugin's pattern category and one pattern per design.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */
class Patterns {

	/**
	 * Pattern category slug.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public const CATEGORY = 'satori-manifest';

	/**
	 * Registers the pattern category and design patterns.
	 *
	 * Hooked to 'init'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @return void
	 */
	public static function register(): void {
		register_block_pattern_category(
			self::CATEGORY,
			array( 'label' => __( 'Manifest designs', 'satori-manifest' ) )
		);

		foreach ( Design_Registry::all() as $slug => $design ) {
			$content = Design_Registry::content( $slug );

			if ( '' === $content ) {
				continue;
			}

			register_block_pattern(
				self::CATEGORY . '/' . $slug,
				array(
					'title'       => $design['title'],
					'description' => $design['description'],
					'categories'  => array( self::CATEGORY ),
					'keywords'    => array_merge( array( 'price', 'list', 'manifest' ), $design['tags'] ),
					'content'     => $content,
				)
			);
		}
	}
}
