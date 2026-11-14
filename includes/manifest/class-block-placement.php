<?php
/**
 * Where new price list entries go on a page with several price list blocks.
 *
 * A page can split its price list over several blocks (different layouts or
 * backgrounds); each block lists the entries it hides. An entry that no block
 * mentions - a heading or section added later - would otherwise show in every
 * block. Instead it shows only in the block(s) that show its neighbour: the
 * nearest entry above it in the price list that the page already places (or
 * below it, when it is at the top). So new entries appear where they were put
 * in the price list, once.
 *
 * Pages with a single price list block are unaffected.
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
	 * Hidden-id lists per post content, for this request.
	 *
	 * @var array<string,array<int,array<int,string>>>
	 */
	private static array $cache = array();

	/**
	 * The ids each price list block on a post hides, in page order.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  int $post_id  Post (client page).
	 * @return array<int,array<int,string>>
	 */
	public static function page_sets( int $post_id ): array {
		$content = (string) get_post_field( 'post_content', $post_id );
		$key     = $post_id . ':' . md5( $content );

		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		$sets = array();
		$walk = static function ( array $blocks ) use ( &$walk, &$sets ): void {
			foreach ( $blocks as $block ) {
				if ( 'satori-manifest/price-list' === ( $block['blockName'] ?? '' ) ) {
					$sets[] = self::clean_ids( (array) ( $block['attrs']['excludedSectionIds'] ?? array() ) );
				}

				$walk( (array) ( $block['innerBlocks'] ?? array() ) );
			}
		};

		$walk( parse_blocks( $content ) );

		self::$cache[ $key ] = $sets;

		return $sets;
	}

	/**
	 * The ids one block hides once new entries are placed by their neighbour.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,array<string,mixed>> $entries  Sections and headings, in order.
	 * @param  array<int,string>              $own      Ids this block hides.
	 * @param  array<int,array<int,string>>   $sets     Ids each block on the page hides.
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
	 * Forgets cached block lists (tests, or after the page changes in one request).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @return void
	 */
	public static function flush(): void {
		self::$cache = array();
	}
}
