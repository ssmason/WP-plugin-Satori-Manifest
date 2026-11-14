<?php
/**
 * Keeps a client page in step with the headings of its price list.
 *
 * Every heading in the price list (e.g. "Christmas Menu") gets, on the page:
 *   1. a heading group: a group of its own holding the Price list heading
 *      block (the banner), and
 *   2. a menu group below it: a group holding a price list block that shows
 *      the sections under that heading,
 * placed where the price list order puts them. Saving the price list (editor
 * or AI) adds, moves and removes these groups. Headings whose sections the
 * page already places itself (a page that splits its list over several
 * blocks) only get the heading group, above the group showing them.
 *
 * Only these automatic groups are touched (marked with the classes below). A
 * group the client has added content to is left where it is. A switched-off
 * heading's groups render nothing, so no empty band shows.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Manifest;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Core\Page_Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Page_Sync
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */
final class Page_Sync {

	/**
	 * Class on an automatic heading group.
	 */
	public const HEADING_CLASS = 'sm-auto-heading';

	/**
	 * Class on an automatic menu group.
	 */
	public const MENU_CLASS = 'sm-auto-menu';

	/**
	 * Guards against syncing from inside a sync.
	 *
	 * @var bool
	 */
	private static bool $running = false;

	/**
	 * Hooks: sync when a price list is saved; hide empty automatic groups.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'added_post_meta', array( self::class, 'on_meta' ), 10, 3 );
		add_action( 'updated_post_meta', array( self::class, 'on_meta' ), 10, 3 );
		add_filter( 'render_block_core/group', array( self::class, 'hide_empty' ), 10, 2 );
	}

	/**
	 * Syncs the owning client's page after its price list is saved.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  int    $meta_id   Meta id (unused).
	 * @param  int    $post_id   Post the meta belongs to.
	 * @param  string $meta_key  Meta key.
	 * @return void
	 */
	public static function on_meta( $meta_id, $post_id, $meta_key ): void {
		unset( $meta_id );

		if ( Post_Types::META_SECTIONS !== $meta_key || Post_Types::CPT_MANIFEST !== get_post_type( (int) $post_id ) ) {
			return;
		}

		$client  = (int) get_post_meta( (int) $post_id, Client_Repository::OWNER, true );
		$page_id = $client ? Client_Repository::page_id( $client ) : 0;

		if ( $page_id ) {
			self::sync( $page_id );
		}
	}

	/**
	 * Hides an automatic group whose block has nothing to show.
	 *
	 * Hooked to 'render_block_core/group'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  string              $content  Rendered group.
	 * @param  array<string,mixed> $block    Parsed block.
	 * @return string
	 */
	public static function hide_empty( $content, $block ): string {
		$class = (string) ( $block['attrs']['className'] ?? '' );

		if ( ( str_contains( $class, self::HEADING_CLASS ) || str_contains( $class, self::MENU_CLASS ) ) && '' === trim( wp_strip_all_tags( (string) $content ) ) ) {
			return '';
		}

		return (string) $content;
	}

	/**
	 * Brings a page's automatic heading and menu groups in line with its price list.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  int $page_id  Client page ID.
	 * @return bool  Whether the page changed.
	 */
	public static function sync( int $page_id ): bool {
		$manifest_id = Client_Repository::manifest_for_page( $page_id );

		if ( self::$running || ! $manifest_id ) {
			return false;
		}

		$content = (string) get_post_field( 'post_content', $page_id );
		$entries = array_values( Manifest_Repository::get_sections( $manifest_id ) );
		$updated = self::arrange( self::top_level( parse_blocks( $content ) ), $entries );
		$new     = implode( "\n\n", array_map( 'serialize_block', $updated ) ) . "\n";

		if ( trim( $new ) === trim( implode( "\n\n", array_map( 'serialize_block', self::top_level( parse_blocks( $content ) ) ) ) ) ) {
			return false;
		}

		self::$running = true;

		// Our own block markup: save it as built (kses would strip group styles for clients).
		$kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		kses_remove_filters();
		wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => wp_slash( $new ), // wp_update_post() unslashes; keep JSON escapes intact.
			)
		);
		if ( $kses ) {
			kses_init_filters();
		}

		self::$running = false;
		Block_Placement::flush();
		Page_Cache::clear( $page_id );

		return true;
	}

	/**
	 * The new list of top-level blocks for a price list.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,array<string,mixed>> $top      Top-level blocks.
	 * @param  array<int,array<string,mixed>> $entries  Price list entries (all, in order).
	 * @return array<int,array<string,mixed>>
	 */
	public static function arrange( array $top, array $entries ): array {
		$headings = array();

		foreach ( $entries as $entry ) {
			if ( Headings::is_heading( $entry ) ) {
				$headings[ (string) $entry['id'] ] = $entry;
			}
		}

		// Untouched automatic groups are rebuilt; groups the client has added to stay put.
		$kept = array();

		foreach ( $top as $block ) {
			$auto = self::auto( $block );

			if ( null === $auto || ! $auto['untouched'] ) {
				$kept[] = $block;
			}
		}

		foreach ( $headings as $id => $heading ) {
			$kept = self::place( $kept, $entries, $id, (string) ( $heading['title'] ?? '' ) );
		}

		return $kept;
	}

	/**
	 * Adds one heading's groups where they belong.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,array<string,mixed>> $top         Top-level blocks.
	 * @param  array<int,array<string,mixed>> $entries     Price list entries.
	 * @param  string                         $heading_id  Heading.
	 * @param  string                         $title       Heading title (for the editor's list view).
	 * @return array<int,array<string,mixed>>
	 */
	private static function place( array $top, array $entries, string $heading_id, string $title ): array {
		$has_heading = false;
		$has_menu    = false;

		foreach ( $top as $block ) {
			$auto = self::auto( $block );

			if ( null !== $auto && $auto['heading'] === $heading_id ) {
				$has_heading = $has_heading || 'heading' === $auto['kind'];
				$has_menu    = $has_menu || 'menu' === $auto['kind'];
			}
		}

		$owners  = Block_Placement::owners( $entries );
		$mine    = array_keys( array_filter( $owners, static fn( string $owner ): bool => $owner === $heading_id ) );
		$placed  = self::placed( $top );
		$adopted = array_values( array_filter( $mine, static fn( string $id ): bool => isset( $placed[ $id ] ) ) );
		$pair    = array();
		$at      = null;

		if ( $adopted ) {
			// The page already shows these sections: the banner goes on top of their group.
			$at = self::index_showing( $top, $entries, $adopted[0] );
		} else {
			[ $at, $template ] = self::position( $top, $entries, $heading_id );
		}

		if ( ! $has_heading ) {
			$pair[] = self::heading_group( $heading_id, $title );
		}

		if ( ! $adopted && ! $has_menu ) {
			$pair[] = self::menu_group( $heading_id, $title, $template ?? null );
		}

		if ( ! $pair ) {
			return $top;
		}

		$at = null === $at ? count( $top ) : $at;

		array_splice( $top, $at, 0, $pair );

		return $top;
	}

	/**
	 * Where a heading with its own menu goes: after whatever shows the entry
	 * just above it, else before whatever shows the entry just below it.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,array<string,mixed>> $top         Top-level blocks.
	 * @param  array<int,array<string,mixed>> $entries     Price list entries.
	 * @param  string                         $heading_id  Heading.
	 * @return array{0:int|null,1:array<string,mixed>|null}  Insert index and the group to copy the look from.
	 */
	private static function position( array $top, array $entries, string $heading_id ): array {
		$ids   = array_map( static fn( array $entry ): string => (string) ( $entry['id'] ?? '' ), $entries );
		$index = array_search( $heading_id, $ids, true );

		if ( false === $index ) {
			return array( null, null );
		}

		// Above: the nearest entry before the heading that something on the page shows.
		for ( $i = (int) $index - 1; $i >= 0; $i-- ) {
			$at = self::index_for( $top, $entries, $entries[ $i ] );

			if ( null !== $at ) {
				return array( $at + 1, $top[ $at ] );
			}
		}

		// Below: the nearest entry after the heading's own sections.
		$count = count( $entries );

		for ( $i = (int) $index + 1; $i < $count; $i++ ) {
			if ( ! Headings::is_heading( $entries[ $i ] ) && Block_Placement::owners( $entries )[ $ids[ $i ] ] === $heading_id ) {
				continue;
			}

			$at = self::index_for( $top, $entries, $entries[ $i ] );

			if ( null !== $at ) {
				return array( $at, $top[ $at ] );
			}
		}

		// Nothing placed around it: above the page's first menu, else at the end.
		$first = self::first_menu( $top );

		return array( null === $first ? null : array_search( $first, $top, true ), $first );
	}

	/**
	 * The top-level block showing an entry: a heading's groups, or the block showing a section.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,array<string,mixed>> $top      Top-level blocks.
	 * @param  array<int,array<string,mixed>> $entries  Price list entries.
	 * @param  array<string,mixed>            $entry    Entry.
	 * @return int|null
	 */
	private static function index_for( array $top, array $entries, array $entry ): ?int {
		$id = (string) ( $entry['id'] ?? '' );

		if ( ! Headings::is_heading( $entry ) ) {
			return self::index_showing( $top, $entries, $id );
		}

		$found = null;

		foreach ( $top as $index => $block ) {
			$auto = self::auto( $block );

			if ( null !== $auto && $auto['heading'] === $id ) {
				$found = $index; // The last of its groups (the menu, when it has one).
			}
		}

		return $found;
	}

	/**
	 * The top-level block whose price list block shows a section.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,array<string,mixed>> $top         Top-level blocks.
	 * @param  array<int,array<string,mixed>> $entries     Price list entries.
	 * @param  string                         $section_id  Section.
	 * @return int|null
	 */
	private static function index_showing( array $top, array $entries, string $section_id ): ?int {
		$all = Block_Placement::blocks_in( $top );

		foreach ( $top as $index => $block ) {
			foreach ( Block_Placement::blocks_in( array( $block ) ) as $price_list ) {
				$ids = array_column( Block_Placement::shown( $entries, $price_list, $all ), 'id' );

				if ( in_array( $section_id, $ids, true ) ) {
					return $index;
				}
			}
		}

		return null;
	}

	/**
	 * Sections the page places itself (named in a normal block's hidden list
	 * on a page with several normal blocks).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,array<string,mixed>> $top  Top-level blocks.
	 * @return array<string,int>
	 */
	private static function placed( array $top ): array {
		$normal = array_filter( Block_Placement::blocks_in( $top ), static fn( array $block ): bool => '' === $block['heading'] );
		$sets   = array_column( $normal, 'hidden' );

		return count( $sets ) > 1 ? array_flip( array_merge( ...$sets ) ) : array();
	}

	/**
	 * The first top-level block holding a normal price list block (to copy its look).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,array<string,mixed>> $top  Top-level blocks.
	 * @return array<string,mixed>|null
	 */
	private static function first_menu( array $top ): ?array {
		foreach ( $top as $block ) {
			if ( null === self::auto( $block ) && Block_Placement::blocks_in( array( $block ) ) ) {
				return $block;
			}
		}

		return null;
	}

	/**
	 * What an automatic group is: kind, heading and whether only our block is in it.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<string,mixed> $block  Top-level block.
	 * @return array{kind:string,heading:string,untouched:bool}|null
	 */
	private static function auto( array $block ): ?array {
		if ( 'core/group' !== ( $block['blockName'] ?? '' ) ) {
			return null;
		}

		$class = ' ' . (string) ( $block['attrs']['className'] ?? '' ) . ' ';
		$kind  = str_contains( $class, ' ' . self::HEADING_CLASS . ' ' ) ? 'heading' : ( str_contains( $class, ' ' . self::MENU_CLASS . ' ' ) ? 'menu' : '' );

		if ( '' === $kind ) {
			return null;
		}

		$inner   = (array) ( $block['innerBlocks'] ?? array() );
		$ours    = 'heading' === $kind ? 'satori-manifest/price-list-heading' : 'satori-manifest/price-list';
		$heading = '';

		foreach ( $inner as $child ) {
			if ( ( $child['blockName'] ?? '' ) === $ours ) {
				$heading = (string) ( $child['attrs']['headingId'] ?? '' );
			}
		}

		return array(
			'kind'      => $kind,
			'heading'   => $heading,
			'untouched' => 1 === count( $inner ) && '' !== $heading,
		);
	}

	/**
	 * A new heading group: a full-width group holding the heading block.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  string $heading_id  Heading.
	 * @param  string $title       Heading title.
	 * @return array<string,mixed>
	 */
	private static function heading_group( string $heading_id, string $title ): array {
		$open = '<div class="wp-block-group alignfull ' . self::HEADING_CLASS . '">';

		return array(
			'blockName'    => 'core/group',
			'attrs'        => array(
				/* translators: %s: heading title. */
				'metadata'  => array( 'name' => sprintf( __( 'Heading: %s', 'satori-manifest' ), wp_strip_all_tags( $title ) ) ),
				'align'     => 'full',
				'className' => self::HEADING_CLASS,
				'layout'    => array( 'type' => 'constrained' ),
			),
			'innerBlocks'  => array(
				array(
					'blockName'    => 'satori-manifest/price-list-heading',
					'attrs'        => array(
						'headingId' => $heading_id,
						'align'     => 'full',
					),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				),
			),
			'innerHTML'    => $open . '</div>',
			'innerContent' => array( $open, null, '</div>' ),
		);
	}

	/**
	 * A new menu group: shows the heading's sections, looking like its neighbour.
	 *
	 * Copies the neighbouring menu group's settings (background, padding, width)
	 * and its price list block's look (layout, colours, fonts), so a new menu
	 * matches the page.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  string                   $heading_id  Heading.
	 * @param  string                   $title       Heading title.
	 * @param  array<string,mixed>|null $template    Neighbouring top-level block, if any.
	 * @return array<string,mixed>
	 */
	private static function menu_group( string $heading_id, string $title, ?array $template ): array {
		$list_attrs  = array();
		$group_attrs = array( 'layout' => array( 'type' => 'constrained' ) );
		$open        = '<div class="wp-block-group">';

		if ( null !== $template ) {
			foreach ( self::find_price_list( $template ) as $found ) {
				$list_attrs = (array) ( $found['attrs'] ?? array() );
				break;
			}

			if ( 'core/group' === ( $template['blockName'] ?? '' ) ) {
				$group_attrs = (array) ( $template['attrs'] ?? array() );
				$open        = (string) ( $template['innerContent'][0] ?? $open );
			}
		}

		unset( $list_attrs['excludedSectionIds'], $group_attrs['metadata'] );
		$list_attrs['headingId'] = $heading_id;

		$classes                  = array_diff( explode( ' ', trim( (string) preg_replace( '/\s+/', ' ', (string) ( $group_attrs['className'] ?? '' ) ) ) ), array( '', self::MENU_CLASS, self::HEADING_CLASS ) );
		$classes[]                = self::MENU_CLASS;
		$group_attrs['className'] = implode( ' ', $classes );
		/* translators: %s: heading title. */
		$group_attrs['metadata'] = array( 'name' => sprintf( __( 'Menu: %s', 'satori-manifest' ), wp_strip_all_tags( $title ) ) );

		// Same opening tag as the neighbour, with our class added (the editor compares classes as a set).
		$open = (string) preg_replace( '/class="([^"]*)"/', 'class="$1 ' . self::MENU_CLASS . '"', $open, 1 );
		$open = (string) preg_replace( '/\s+' . self::HEADING_CLASS . '\b/', '', $open );

		return array(
			'blockName'    => 'core/group',
			'attrs'        => $group_attrs,
			'innerBlocks'  => array(
				array(
					'blockName'    => 'satori-manifest/price-list',
					'attrs'        => $list_attrs,
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				),
			),
			'innerHTML'    => $open . '</div>',
			'innerContent' => array( $open, null, '</div>' ),
		);
	}

	/**
	 * Price list blocks inside a block (any depth).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<string,mixed> $block  Block.
	 * @return array<int,array<string,mixed>>
	 */
	private static function find_price_list( array $block ): array {
		if ( 'satori-manifest/price-list' === ( $block['blockName'] ?? '' ) ) {
			return array( $block );
		}

		$out = array();

		foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $child ) {
			$out = array_merge( $out, self::find_price_list( $child ) );
		}

		return $out;
	}

	/**
	 * Top-level blocks without the blank chunks between them.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,array<string,mixed>> $blocks  Parsed blocks.
	 * @return array<int,array<string,mixed>>
	 */
	private static function top_level( array $blocks ): array {
		return array_values( array_filter( $blocks, static fn( array $block ): bool => null !== $block['blockName'] || '' !== trim( (string) $block['innerHTML'] ) ) );
	}
}
