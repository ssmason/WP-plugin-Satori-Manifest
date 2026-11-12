<?php
/**
 * Media importer.
 *
 * Adds an image file shipped with the plugin (design photos, demo assets) to
 * the media library, owned by a client and attached to their page, so it
 * shows in that client's media picker. Importing the same file for the same
 * page again returns the existing attachment instead of a copy.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Media_Importer
 *
 * Imports local image files into the media library.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */
class Media_Importer {

	/**
	 * Attachment meta holding the source file, used to avoid duplicates.
	 *
	 * @since 3.8.0
	 * @var   string
	 */
	public const SOURCE_META = '_satori_manifest_source';

	/**
	 * Imports a file, or returns the attachment already made from it.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  string $path       Absolute path to the image.
	 * @param  int    $user_id    Owner of the attachment.
	 * @param  int    $parent_id  Post the attachment belongs to (0 for none).
	 * @return int  Attachment ID, or 0 when the file is missing or the import fails.
	 */
	public static function import( string $path, int $user_id, int $parent_id = 0 ): int {
		if ( ! is_readable( $path ) ) {
			return 0;
		}

		$source   = self::source_key( $path );
		$existing = self::find( $source, $user_id, $parent_id );

		if ( $existing ) {
			return $existing;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Sideloading moves the file, so hand it a temporary copy.
		$tmp = wp_tempnam( basename( $path ) );
		if ( ! copy( $path, $tmp ) ) {
			return 0;
		}

		$attachment_id = media_handle_sideload(
			array(
				'name'     => basename( $path ),
				'tmp_name' => $tmp,
			),
			$parent_id,
			null,
			array( 'post_author' => $user_id )
		);

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			return 0;
		}

		update_post_meta( (int) $attachment_id, self::SOURCE_META, $source );

		return (int) $attachment_id;
	}

	/**
	 * Finds an attachment already imported from the same file.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  string $source     Source key.
	 * @param  int    $user_id    Owner.
	 * @param  int    $parent_id  Parent post.
	 * @return int  Attachment ID, 0 when none.
	 */
	private static function find( string $source, int $user_id, int $parent_id ): int {
		$found = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'author'         => $user_id,
				'post_parent'    => $parent_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => self::SOURCE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one small lookup when a design is applied.
				'meta_value'     => $source, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- as above.
			)
		);

		return $found ? (int) $found[0] : 0;
	}

	/**
	 * The file's path relative to the plugin or theme, so the key survives a move.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  string $path  Absolute path.
	 * @return string
	 */
	private static function source_key( string $path ): string {
		foreach ( array( SATORI_MANIFEST_PATH, trailingslashit( WP_CONTENT_DIR ) ) as $base ) {
			if ( str_starts_with( $path, $base ) ) {
				return substr( $path, strlen( $base ) );
			}
		}

		return basename( $path );
	}
}
