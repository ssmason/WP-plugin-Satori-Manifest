<?php
/**
 * Data migrations.
 *
 * Runs once per plugin version on 'init'. Each migration is idempotent and
 * keyed by the version that introduced it, so upgrades from any earlier
 * version apply every step in order.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.2.0
 */

declare( strict_types=1 );

namespace SatoriManifest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Migrations
 *
 * Versioned, idempotent data upgrades.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.2.0
 */
class Migrations {

	/**
	 * Option storing the last data version migrated to.
	 *
	 * @since 2.2.0
	 * @var   string
	 */
	private const OPTION = 'satori_manifest_data_version';

	/**
	 * Runs any migrations newer than the stored data version.
	 *
	 * Hooked to 'init'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.2.0
	 * @return void
	 */
	public static function maybe_run(): void {
		$current = (string) get_option( self::OPTION, '0' );

		if ( version_compare( $current, SATORI_MANIFEST_VERSION, '>=' ) ) {
			return;
		}

		if ( version_compare( $current, '2.2.0', '<' ) ) {
			self::add_section_ids();
		}

		update_option( self::OPTION, SATORI_MANIFEST_VERSION, false );
	}

	/**
	 * 2.2.0 — gives every manifest section a permanent id.
	 *
	 * Re-saving through Sanitizer assigns ids to sections that lack one and
	 * leaves existing ids untouched.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.2.0
	 * @return void
	 */
	private static function add_section_ids(): void {
		$manifest_ids = get_posts(
			array(
				'post_type'      => Post_Types::CPT_MANIFEST,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- one-off migration over all manifests.
				'no_found_rows'  => true,
			)
		);

		foreach ( $manifest_ids as $manifest_id ) {
			$sections = Manifest_Repository::get_sections( (int) $manifest_id );

			if ( empty( $sections ) ) {
				continue;
			}

			update_post_meta(
				(int) $manifest_id,
				Post_Types::META_SECTIONS,
				wp_slash( (string) wp_json_encode( Sanitizer::sanitize_sections( $sections ) ) )
			);
		}
	}
}
