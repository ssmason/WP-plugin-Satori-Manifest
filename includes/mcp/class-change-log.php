<?php
/**
 * History of assistant changes, with undo.
 *
 * Before each change the parts it touches are captured (price list, page
 * details, design, page layout) and stored as one private post per change,
 * authored by the client. Undo restores the newest change that has not been
 * undone yet. Only the latest few changes per client are kept.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Change_Log
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Change_Log {

	/**
	 * Post type holding one change each.
	 */
	public const POST_TYPE = 'sm_ai_change';

	/**
	 * Post meta: set when a change has been undone.
	 */
	public const META_UNDONE = '_sm_ai_undone';

	/**
	 * The price list.
	 */
	public const PART_PRICE_LIST = 'price_list';

	/**
	 * Name, tagline, contact details…
	 */
	public const PART_DETAILS = 'details';

	/**
	 * Design and colour mode.
	 */
	public const PART_DESIGN = 'design';

	/**
	 * The page's block layout.
	 */
	public const PART_CONTENT = 'content';

	/**
	 * Registers the post type (no UI; staff and clients see it on the AI screens).
	 *
	 * Hooked to 'init'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return void
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'           => __( 'AI changes', 'satori-manifest' ),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'rewrite'         => false,
				'query_var'       => false,
				'can_export'      => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Runs a change and records what it replaced.
	 *
	 * Nothing is recorded when the change throws.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  Page_Store        $store    Client's page.
	 * @param  string            $tool     Tool name.
	 * @param  string            $summary  Plain-language summary ("Raised cocktails by 5%").
	 * @param  array<int,string> $parts    PART_ constants the change touches.
	 * @param  callable          $change   Does the work; its return value is passed back.
	 * @return mixed
	 */
	public static function record( Page_Store $store, string $tool, string $summary, array $parts, callable $change ) {
		$before = self::capture( $store, $parts );
		$result = $change();

		$id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'private',
				'post_author'  => $store->context()->user_id,
				'post_parent'  => $store->context()->page_id,
				'post_title'   => mb_substr( sanitize_text_field( $summary ), 0, 200 ),
				'post_excerpt' => sanitize_key( $tool ),
				'post_content' => wp_slash( (string) wp_json_encode( $before ) ),
			),
			true
		);

		if ( ! is_wp_error( $id ) ) {
			self::prune( $store->context()->user_id );
		}

		return $result;
	}

	/**
	 * Recent changes, newest first.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int $user_id  Client user ID.
	 * @param  int $limit    How many.
	 * @return array<int,array{id:int,summary:string,tool:string,time:int,undone:bool}>
	 */
	public static function recent( int $user_id, int $limit = 10 ): array {
		return array_map(
			static fn( \WP_Post $post ): array => array(
				'id'      => (int) $post->ID,
				'summary' => (string) $post->post_title,
				'tool'    => (string) $post->post_excerpt,
				'time'    => (int) get_post_timestamp( $post ),
				'undone'  => (bool) get_post_meta( $post->ID, self::META_UNDONE, true ),
			),
			self::query( $user_id, $limit )
		);
	}

	/**
	 * Undoes the newest change not yet undone.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  Page_Store $store  Client's page.
	 * @return string|null  Summary of the change undone, or null when there is nothing to undo.
	 */
	public static function undo_last( Page_Store $store ): ?string {
		foreach ( self::query( $store->context()->user_id, Assistant_Config::history() ) as $post ) {
			if ( get_post_meta( $post->ID, self::META_UNDONE, true ) ) {
				continue;
			}

			$before = json_decode( (string) $post->post_content, true );

			self::restore( $store, is_array( $before ) ? $before : array() );
			update_post_meta( $post->ID, self::META_UNDONE, time() );

			return (string) $post->post_title;
		}

		return null;
	}

	/**
	 * Deletes every change of a client (when the client is deleted).
	 *
	 * Hooked to 'delete_user'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int $user_id  User ID.
	 * @return void
	 */
	public static function forget( int $user_id ): void {
		foreach ( self::query( $user_id, -1, 'ids' ) as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	/**
	 * Captures the parts a change will touch.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  Page_Store        $store  Client's page.
	 * @param  array<int,string> $parts  Parts.
	 * @return array<string,mixed>
	 */
	private static function capture( Page_Store $store, array $parts ): array {
		$out = array();

		foreach ( array_unique( $parts ) as $part ) {
			$out[ $part ] = match ( $part ) {
				self::PART_PRICE_LIST => $store->sections(),
				self::PART_DETAILS    => $store->details(),
				self::PART_DESIGN     => $store->design(),
				self::PART_CONTENT    => $store->content(),
				default               => null,
			};
		}

		return array_filter( $out, static fn( $value ): bool => null !== $value );
	}

	/**
	 * Writes captured parts back.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  Page_Store          $store   Client's page.
	 * @param  array<string,mixed> $before  Output of capture().
	 * @return void
	 */
	private static function restore( Page_Store $store, array $before ): void {
		// Design first: applying a design can change the layout, which is restored after.
		if ( isset( $before[ self::PART_DESIGN ] ) && is_array( $before[ self::PART_DESIGN ] ) ) {
			$store->restore_design( (string) ( $before[ self::PART_DESIGN ]['design'] ?? '' ), (string) ( $before[ self::PART_DESIGN ]['mode'] ?? '' ) );
		}

		if ( isset( $before[ self::PART_CONTENT ] ) && is_string( $before[ self::PART_CONTENT ] ) ) {
			$store->restore_content( $before[ self::PART_CONTENT ] );
		}

		if ( isset( $before[ self::PART_PRICE_LIST ] ) && is_array( $before[ self::PART_PRICE_LIST ] ) ) {
			$store->save_sections( $before[ self::PART_PRICE_LIST ] );
		}

		if ( isset( $before[ self::PART_DETAILS ] ) && is_array( $before[ self::PART_DETAILS ] ) ) {
			$store->save_details( $before[ self::PART_DETAILS ] );
		}
	}

	/**
	 * Keeps only the newest changes.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int $user_id  Client user ID.
	 * @return void
	 */
	private static function prune( int $user_id ): void {
		$ids = self::query( $user_id, -1, 'ids' );

		foreach ( array_slice( $ids, Assistant_Config::history() ) as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	/**
	 * A client's changes, newest first.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int    $user_id  Client user ID.
	 * @param  int    $limit    How many (-1 for all).
	 * @param  string $fields   '' for posts, 'ids' for IDs.
	 * @return array<int,mixed>
	 */
	private static function query( int $user_id, int $limit, string $fields = '' ): array {
		if ( ! $user_id ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'private',
				'author'           => $user_id,
				'posts_per_page'   => $limit, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded by the history setting.
				'orderby'          => 'ID',
				'order'            => 'DESC',
				'fields'           => $fields,
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
	}
}
