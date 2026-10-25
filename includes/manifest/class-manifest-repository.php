<?php
/**
 * Manifest data access.
 *
 * Provides read-only access to manifest CPT data. Separates data retrieval
 * from CPT registration (class-post-types.php) following single responsibility.
 * All callers needing manifest data should go through this class rather than
 * querying post meta directly.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Manifest_Repository
 *
 * Read-only data access layer for the sm_manifest CPT.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */
class Manifest_Repository {

	/**
	 * Returns all published manifest posts ordered by sort order then title.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @return \WP_Post[]  Array of WP_Post objects.
	 */
	public static function get_all(): array {
		// meta_query uses OR/NOT EXISTS so manifests without a sort-order meta
		// (e.g. created via WP-CLI or imported) are included rather than silently
		// dropped by an implicit INNER JOIN.
		return get_posts(
			array(
				'post_type'      => Post_Types::CPT_MANIFEST,
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- full list required; no pagination context.
				'post_status'    => 'publish',
				'orderby'        => array(
					'meta_value_num' => 'ASC',
					'title'          => 'ASC',
				),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- OR/NOT EXISTS needed to include posts without order meta.
					'relation' => 'OR',
					array(
						'key'     => Post_Types::META_ORDER,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => Post_Types::META_ORDER,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
	}

	/**
	 * Returns the decoded sections array for a given manifest post.
	 *
	 * Each element shape:
	 * { title, description, sort_order, items: [ { label, description, price, price_prefix, badge, sort_order } ] }
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @param  int $post_id  The manifest post ID.
	 * @return array<int,array<string,mixed>>  Array of section data arrays, empty on miss.
	 */
	public static function get_sections( int $post_id ): array {
		$raw = get_post_meta( $post_id, Post_Types::META_SECTIONS, true );

		if ( empty( $raw ) ) {
			return array();
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Returns placeholder sections for editor and pattern previews.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return array<int,array<string,mixed>>
	 */
	public static function demo_sections(): array {
		$item = static fn( string $label, string $description, string $price, string $prefix = '', string $badge = '' ): array => array(
			'label'        => $label,
			'description'  => $description,
			'price'        => $price,
			'price_prefix' => $prefix,
			'badge'        => $badge,
		);

		return array(
			array(
				'title'       => __( 'Signature services', 'satori-manifest' ),
				'description' => __( 'Our most-loved treatments.', 'satori-manifest' ),
				'items'       => array(
					$item( __( 'Service name', 'satori-manifest' ), __( 'A short description of the service', 'satori-manifest' ), '45', __( 'from', 'satori-manifest' ), __( 'Popular', 'satori-manifest' ) ),
					$item( __( 'Service name', 'satori-manifest' ), '', '60' ),
					$item( __( 'Service name', 'satori-manifest' ), __( 'A short description of the service', 'satori-manifest' ), '25', __( 'from', 'satori-manifest' ) ),
				),
			),
			array(
				'title'       => __( 'Another section', 'satori-manifest' ),
				'description' => '',
				'items'       => array(
					$item( __( 'Service name', 'satori-manifest' ), '', '35' ),
					$item( __( 'Service name', 'satori-manifest' ), __( 'A short description of the service', 'satori-manifest' ), '50', '', __( 'New', 'satori-manifest' ) ),
				),
			),
		);
	}
}
