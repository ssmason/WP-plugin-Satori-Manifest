<?php
/**
 * Which price list entries each price list block on a page shows.
 *
 * A client page can hold several price list blocks:
 *   - normal blocks, each listing the sections it hides (excludedSectionIds), so
 *     one page can mix layouts and backgrounds;
 *   - heading menu blocks (headingId set), created by Page_Sync, each showing the
 *     sections under one heading of the price list (e.g. "Christmas Menu").
 *
 * Headings themselves are never drawn inside a menu: the "Price list heading"
 * block draws them, in a group of their own above the menu (see Page_Sync).
 *
 * Rules:
 *   - "Placed" sections are the ones a page with two or more normal blocks
 *     mentions in an exclusion list: the author decided where they go.
 *   - A heading's menu block shows the heading's sections that are not placed.
 *   - A normal block shows what it does not hide, except sections of a heading
 *     that has its own menu block (unless placed), and except new entries that
 *     belong next to a neighbour shown by another block.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Manifest;

use SatoriManifest\Core\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Block_Placement
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */
final class Block_Placement {

	/**
	 * Price list blocks per post content, for this request.
	 *
	 * @var array<string,array<int,array{hidden:array<int,string>,heading:string}>>
	 */
	private static array $cache = array();

	/**
	 * The price list blocks on a post, in page order.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  int $post_id  Post (client page).
	 * @return array<int,array{hidden:array<int,string>,heading:string}>
	 */
	public static function page_blocks( int $post_id ): array {
		$content = (string) get_post_field( 'post_content', $post_id );
		$key     = $post_id . ':' . md5( $content );

		if ( ! isset( self::$cache[ $key ] ) ) {
			self::$cache[ $key ] = self::blocks_in( parse_blocks( $content ) );
		}

		return self::$cache[ $key ];
	}

	/**
	 * The price list blocks in parsed blocks (any depth), in order.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,array<string,mixed>> $blocks  Parsed blocks.
	 * @return array<int,array{hidden:array<int,string>,heading:string}>
	 */
	public static function blocks_in( array $blocks ): array {
		$out = array();

		foreach ( $blocks as $block ) {
			if ( 'satori-manifest/price-list' === ( $block['blockName'] ?? '' ) ) {
				$out[] = self::describe( (array) ( $block['attrs'] ?? array() ) );
			}

			$out = array_merge( $out, self::blocks_in( (array) ( $block['innerBlocks'] ?? array() ) ) );
		}

		return $out;
	}

	/**
	 * A price list block's placement attributes.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<string,mixed> $attributes  Block attributes.
	 * @return array{hidden:array<int,string>,heading:string}
	 */
	public static function describe( array $attributes ): array {
		return array(
			'hidden'  => self::clean_ids( (array) ( $attributes['excludedSectionIds'] ?? array() ) ),
			'heading' => Sanitizer::section_id( (string) ( $attributes['headingId'] ?? '' ) ),
		);
	}

	/**
	 * The sections one block shows (headings are never included).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,array<string,mixed>>                            $entries  Visible sections and headings, in order.
	 * @param  array{hidden:array<int,string>,heading:string}            $block    This block (describe()).
	 * @param  array<int,array{hidden:array<int,string>,heading:string}> $blocks   Every price list block on the page.
	 * @return array<int,array<string,mixed>>
	 */
	public static function shown( array $entries, array $block, array $blocks ): array {
		$entries = array_values( $entries );
		$owner   = self::owners( $entries );
		$normal  = array_values( array_filter( $blocks, static fn( array $b ): bool => '' === $b['heading'] ) );
		$sets    = array_column( $normal, 'hidden' );
		$placed  = count( $sets ) > 1 ? array_flip( array_merge( ...$sets ) ) : array();
		$managed = array_flip( array_filter( array_column( $blocks, 'heading' ) ) );
		$hidden  = '' === $block['heading'] ? self::hidden( $entries, $block['hidden'], $sets ) : $block['hidden'];
		$out     = array();

		foreach ( $entries as $entry ) {
			$id = (string) ( $entry['id'] ?? '' );

			if ( Headings::is_heading( $entry ) || in_array( $id, $hidden, true ) ) {
				continue;
			}

			$heading = $owner[ $id ] ?? '';

			if ( '' !== $block['heading'] ) {
				if ( $heading === $block['heading'] && ! isset( $placed[ $id ] ) ) {
					$out[] = $entry;
				}
				continue;
			}

			if ( '' !== $heading && isset( $managed[ $heading ] ) && ! isset( $placed[ $id ] ) ) {
				continue;
			}

			$out[] = $entry;
		}

		return $out;
	}

	/**
	 * Section id => the heading it sits under ('' for none).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,array<string,mixed>> $entries  Sections and headings, in order.
	 * @return array<string,string>
	 */
	public static function owners( array $entries ): array {
		$owner   = array();
		$heading = '';

		foreach ( $entries as $entry ) {
			if ( Headings::is_heading( $entry ) ) {
				$heading = (string) ( $entry['id'] ?? '' );
				continue;
			}

			$owner[ (string) ( $entry['id'] ?? '' ) ] = $heading;
		}

		return $owner;
	}

	/**
	 * The ids a normal block hides once new entries are placed by their neighbour.
	 *
	 * On a page with several normal blocks, an entry no block mentions shows only
	 * in the block(s) that show its nearest placed neighbour above it (or below it,
	 * at the top), so it appears once, where it was put in the price list.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,array<string,mixed>> $entries  Sections and headings, in order.
	 * @param  array<int,string>              $own      Ids this block hides.
	 * @param  array<int,array<int,string>>   $sets     Ids each normal block on the page hides.
	 * @return array<int,string>
	 */
	public static function hidden( array $entries, array $own, array $sets ): array {
		if ( count( $sets ) < 2 ) {
			return $own;
		}

		$known  = array_flip( array_merge( ...$sets ) );
		$ids    = array_map( static fn( array $entry ): string => (string) ( $entry['id'] ?? '' ), array_values( $entries ) );
		$hidden = $own;

		foreach ( $ids as $index => $id ) {
			if ( '' === $id || isset( $known[ $id ] ) ) {
				continue;
			}

			$anchor = self::anchor( $ids, $index, $known );

			if ( null !== $anchor && in_array( $anchor, $own, true ) ) {
				$hidden[] = $id;
			}
		}

		return $hidden;
	}

	/**
	 * The nearest placed entry above (else below) a new one.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,string> $ids    Entry ids in order.
	 * @param  int               $index  The new entry.
	 * @param  array<string,int> $known  Placed ids (as keys).
	 * @return string|null
	 */
	private static function anchor( array $ids, int $index, array $known ): ?string {
		for ( $i = $index - 1; $i >= 0; $i-- ) {
			if ( isset( $known[ $ids[ $i ] ] ) ) {
				return $ids[ $i ];
			}
		}

		$count = count( $ids );

		for ( $i = $index + 1; $i < $count; $i++ ) {
			if ( isset( $known[ $ids[ $i ] ] ) ) {
				return $ids[ $i ];
			}
		}

		return null;
	}

	/**
	 * Valid section ids only.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,mixed> $ids  Raw ids.
	 * @return array<int,string>
	 */
	public static function clean_ids( array $ids ): array {
		return array_values( array_filter( array_map( static fn( $id ): string => Sanitizer::section_id( (string) $id ), $ids ) ) );
	}

	/**
	 * Forgets cached block lists.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @return void
	 */
	public static function flush(): void {
		self::$cache = array();
	}
}
