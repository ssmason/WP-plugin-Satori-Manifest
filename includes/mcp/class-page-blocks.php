<?php
/**
 * The client's own page content, for the AI assistant: groups and the blocks inside them.
 *
 * The assistant can add, change and remove blocks inside the page's groups
 * (text, headings, images, buttons…), never the price list blocks or the
 * automatic heading banners - those come from the price list.
 *
 * Addressing: top-level blocks are "g1", "g2"… in page order; the blocks
 * directly inside a group are "g2.1", "g2.2"…. The assistant reads the layout
 * first (get_page_layout), so the numbers are current.
 *
 * Common blocks are built here from simple fields, so they always match the
 * editor's own markup; any other allowed block can be given as block markup.
 * Saving runs as the client, so WordPress's own filters (no scripts) apply.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp;

use SatoriManifest\Admin\Portal\Editor_Lockdown;
use SatoriManifest\Mcp\Server\Tool_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Page_Blocks
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */
final class Page_Blocks {

	/**
	 * Blocks only the price list may place.
	 */
	public const MANAGED = array( 'satori-manifest/price-list', 'satori-manifest/price-list-heading' );

	/**
	 * Block types the simple builder knows.
	 */
	public const SIMPLE = array( 'paragraph', 'heading', 'list', 'image', 'button', 'separator', 'spacer', 'quote' );

	/**
	 * Inline HTML allowed in text fields.
	 */
	private const INLINE = array(
		'strong' => array(),
		'em'     => array(),
		'br'     => array(),
		'a'      => array( 'href' => true ),
	);

	/**
	 * Constructor.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  Page_Store $store  The client's page.
	 */
	public function __construct( private readonly Page_Store $store ) {}

	/**
	 * The page's groups and the blocks inside them.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @return array<int,array<string,mixed>>
	 */
	public function layout(): array {
		$out = array();

		foreach ( $this->top() as $n => $block ) {
			$entry = array(
				'id'      => 'g' . ( $n + 1 ),
				'type'    => $block['blockName'],
				'name'    => (string) ( $block['attrs']['metadata']['name'] ?? '' ),
				'holds'   => $this->holds( $block ),
				'summary' => $this->summary( $block ),
			);

			if ( 'core/group' === $block['blockName'] ) {
				$entry['blocks'] = array();

				foreach ( array_values( (array) $block['innerBlocks'] ) as $m => $inner ) {
					$entry['blocks'][] = array(
						'id'       => 'g' . ( $n + 1 ) . '.' . ( $m + 1 ),
						'type'     => (string) $inner['blockName'],
						'summary'  => $this->summary( $inner ),
						'editable' => ! in_array( $inner['blockName'], self::MANAGED, true ),
					);
				}
			}

			$out[] = $entry;
		}

		return $out;
	}

	/**
	 * Adds a block inside a group.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  string              $group     Group id ("g2").
	 * @param  array<string,mixed> $spec      Block to build (see build()).
	 * @param  string              $where     'top', 'bottom', 'before' or 'after'.
	 * @param  string              $relative  Block id for before/after ("g2.1").
	 * @return string  The new block's id.
	 * @throws Tool_Error When the group or position is not valid.
	 */
	public function add( string $group, array $spec, string $where = 'bottom', string $relative = '' ): string {
		$top   = $this->top();
		$index = $this->group_index( $top, $group );
		$new   = $this->build( $spec );
		$inner = array_values( (array) $top[ $index ]['innerBlocks'] );
		$at    = 'top' === $where ? 0 : count( $inner );

		if ( in_array( $where, array( 'before', 'after' ), true ) ) {
			[ $g, $m ] = $this->path( $relative );

			if ( $g !== $index || ! isset( $inner[ $m ] ) ) {
				throw new Tool_Error( sprintf( '"%s" is not a block inside %s. Call get_page_layout for the current ids.', $relative, $group ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
			}

			$at = 'before' === $where ? $m : $m + 1;
		}

		array_splice( $inner, $at, 0, $new );
		$top[ $index ] = $this->with_inner( $top[ $index ], $inner );
		$this->save( $top, sprintf( 'Added %s to %s', $spec['type'] ?? 'a block', $this->label( $top[ $index ], $group ) ) );

		return $group . '.' . ( $at + 1 );
	}

	/**
	 * Replaces a block inside a group.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  string              $id    Block id ("g2.1").
	 * @param  array<string,mixed> $spec  New block (see build()).
	 * @return void
	 */
	public function update( string $id, array $spec ): void {
		$top         = $this->top();
		[ $g, $m ]   = $this->editable( $top, $id );
		$inner       = array_values( (array) $top[ $g ]['innerBlocks'] );
		$replace     = $this->build( $spec );
		$inner[ $m ] = $replace[0];

		if ( count( $replace ) > 1 ) {
			array_splice( $inner, $m + 1, 0, array_slice( $replace, 1 ) );
		}

		$top[ $g ] = $this->with_inner( $top[ $g ], $inner );
		$this->save( $top, sprintf( 'Changed a block in %s', $this->label( $top[ $g ], 'g' . ( $g + 1 ) ) ) );
	}

	/**
	 * Removes a block from inside a group.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  string $id  Block id ("g2.1").
	 * @return void
	 */
	public function remove( string $id ): void {
		$top       = $this->top();
		[ $g, $m ] = $this->editable( $top, $id );
		$inner     = array_values( (array) $top[ $g ]['innerBlocks'] );

		array_splice( $inner, $m, 1 );
		$top[ $g ] = $this->with_inner( $top[ $g ], $inner );
		$this->save( $top, sprintf( 'Removed a block from %s', $this->label( $top[ $g ], 'g' . ( $g + 1 ) ) ) );
	}

	/**
	 * Builds block(s) from a spec.
	 *
	 * Simple types: paragraph {text, align}, heading {text, level, align},
	 * list {items, ordered}, image {image_id, alt, caption}, button {text, url},
	 * separator, spacer {height}, quote {text, citation}. Or markup {markup}:
	 * serialized blocks, any type the client may use.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<string,mixed> $spec  Spec.
	 * @return array<int,array<string,mixed>>  Parsed block(s).
	 * @throws Tool_Error When the spec is not valid.
	 */
	public function build( array $spec ): array {
		$type   = (string) ( $spec['type'] ?? '' );
		$markup = 'markup' === $type ? (string) ( $spec['markup'] ?? '' ) : $this->simple( $type, $spec );
		$blocks = array_values( array_filter( parse_blocks( $markup ), static fn( array $block ): bool => null !== $block['blockName'] ) );

		if ( array() === $blocks ) {
			throw new Tool_Error( 'No block found in that markup. Use block comments, e.g. <!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->.' );
		}

		$this->check_allowed( $blocks );

		return $blocks;
	}

	/**
	 * Markup for a simple block, matching what the editor saves.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  string              $type  Simple type.
	 * @param  array<string,mixed> $spec  Fields.
	 * @return string
	 * @throws Tool_Error When a required field is missing.
	 */
	private function simple( string $type, array $spec ): string {
		$text  = wp_kses( trim( (string) ( $spec['text'] ?? '' ) ), self::INLINE );
		$align = in_array( $spec['align'] ?? '', array( 'left', 'center', 'right' ), true ) ? (string) $spec['align'] : '';

		switch ( $type ) {
			case 'paragraph':
				$this->need( $text, 'text' );
				return '' === $align
					? "<!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->"
					: '<!-- wp:paragraph {"style":{"typography":{"textAlign":"' . $align . '"}}} -->' . "\n" . '<p class="has-text-align-' . $align . '">' . $text . "</p>\n<!-- /wp:paragraph -->";

			case 'heading':
				$this->need( $text, 'text' );
				$level = max( 2, min( 4, (int) ( $spec['level'] ?? 2 ) ) );
				$attrs = array_filter(
					array(
						'level'     => 2 === $level ? null : $level,
						'textAlign' => '' === $align ? null : $align,
					)
				);
				return '<!-- wp:heading' . ( $attrs ? ' ' . wp_json_encode( $attrs ) : '' ) . " -->\n<h{$level} class=\"wp-block-heading" . ( '' !== $align ? ' has-text-align-' . $align : '' ) . "\">{$text}</h{$level}>\n<!-- /wp:heading -->";

			case 'list':
				$items = array_values( array_filter( array_map( fn( $item ): string => wp_kses( trim( (string) $item ), self::INLINE ), (array) ( $spec['items'] ?? array() ) ) ) );
				$this->need( implode( '', $items ), 'items' );
				$tag  = ! empty( $spec['ordered'] ) ? 'ol' : 'ul';
				$li   = implode( "\n\n", array_map( static fn( string $item ): string => "<!-- wp:list-item -->\n<li>{$item}</li>\n<!-- /wp:list-item -->", $items ) );
				$open = ! empty( $spec['ordered'] ) ? '<!-- wp:list {"ordered":true} -->' : '<!-- wp:list -->';
				return "{$open}\n<{$tag} class=\"wp-block-list\">{$li}</{$tag}>\n<!-- /wp:list -->";

			case 'image':
				$id  = (int) ( $spec['image_id'] ?? 0 );
				$src = $this->image_url( $id );
				$alt = esc_attr( sanitize_text_field( (string) ( $spec['alt'] ?? '' ) ) );
				$cap = wp_kses( trim( (string) ( $spec['caption'] ?? '' ) ), self::INLINE );
				return '<!-- wp:image {"id":' . $id . ',"sizeSlug":"large","linkDestination":"none"} -->' . "\n"
					. '<figure class="wp-block-image size-large"><img src="' . esc_url( $src ) . '" alt="' . $alt . '" class="wp-image-' . $id . '"/>'
					. ( '' !== $cap ? '<figcaption class="wp-element-caption">' . $cap . '</figcaption>' : '' )
					. "</figure>\n<!-- /wp:image -->";

			case 'button':
				$this->need( $text, 'text' );
				$url = esc_url( (string) ( $spec['url'] ?? '' ) );
				$this->need( $url, 'url' );
				return "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\"><!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"{$url}\">{$text}</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons -->";

			case 'separator':
				return "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->";

			case 'spacer':
				$height = max( 8, min( 240, (int) ( $spec['height'] ?? 40 ) ) ) . 'px';
				return '<!-- wp:spacer {"height":"' . $height . '"} -->' . "\n" . '<div style="height:' . $height . '" aria-hidden="true" class="wp-block-spacer"></div>' . "\n<!-- /wp:spacer -->";

			case 'quote':
				$this->need( $text, 'text' );
				$cite = wp_kses( trim( (string) ( $spec['citation'] ?? '' ) ), self::INLINE );
				return "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->" . ( '' !== $cite ? "<cite>{$cite}</cite>" : '' ) . "</blockquote>\n<!-- /wp:quote -->";
		}

		throw new Tool_Error( sprintf( 'Unknown block type "%s". Use one of: %s, or markup.', $type, implode( ', ', self::SIMPLE ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
	}

	/**
	 * The client's own images (media library), newest first.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @return array<int,array<string,mixed>>
	 */
	public function images(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'author'         => $this->store->context()->user_id,
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return array_map(
			static fn( \WP_Post $post ): array => array(
				'image_id' => $post->ID,
				'title'    => $post->post_title,
				'alt'      => (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
				'url'      => (string) wp_get_attachment_image_url( $post->ID, 'medium' ),
			),
			$posts
		);
	}

	/**
	 * Large image address for one of the client's own images.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  int $id  Attachment id.
	 * @return string
	 * @throws Tool_Error When it is not one of the client's images.
	 */
	private function image_url( int $id ): string {
		$post = $id ? get_post( $id ) : null;

		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type || ! wp_attachment_is_image( $id ) || (int) $post->post_author !== $this->store->context()->user_id ) {
			throw new Tool_Error( 'Use the image_id of one of the client\'s own images (list_images). New photos are uploaded in the dashboard under Media.' );
		}

		return (string) wp_get_attachment_image_url( $id, 'large' );
	}

	/**
	 * Refuses blocks the client may not use, and the price list's own blocks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,array<string,mixed>> $blocks  Parsed blocks.
	 * @return void
	 * @throws Tool_Error When a block is not allowed.
	 */
	private function check_allowed( array $blocks ): void {
		$allowed = Editor_Lockdown::allowed_blocks( true );
		$allowed = is_array( $allowed ) ? $allowed : array();

		foreach ( $blocks as $block ) {
			$name = (string) $block['blockName'];

			if ( in_array( $name, self::MANAGED, true ) ) {
				throw new Tool_Error( 'Price lists and heading banners come from the price list itself; change them with the price list tools.' );
			}

			if ( ! in_array( $name, $allowed, true ) ) {
				throw new Tool_Error( sprintf( 'The block "%s" is not available on this page.', $name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
			}

			$this->check_allowed( (array) $block['innerBlocks'] );
		}
	}

	/**
	 * Saves the page (as the client) and records the change for undo.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,array<string,mixed>> $top      Top-level blocks.
	 * @param  string                         $summary  Change summary.
	 * @return void
	 */
	private function save( array $top, string $summary ): void {
		$content = implode( "\n\n", array_map( 'serialize_block', $top ) ) . "\n";

		Change_Log::record(
			$this->store,
			'page_blocks',
			$summary,
			array( Change_Log::PART_CONTENT ),
			function () use ( $content ): bool {
				$this->store->restore_content( $content );

				return true;
			}
		);
	}

	/**
	 * Top-level blocks of the page (blank chunks dropped).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @return array<int,array<string,mixed>>
	 */
	private function top(): array {
		return array_values( array_filter( parse_blocks( $this->store->content() ), static fn( array $block ): bool => null !== $block['blockName'] ) );
	}

	/**
	 * Index of a group by id.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,array<string,mixed>> $top    Top-level blocks.
	 * @param  string                         $group  "g2".
	 * @return int
	 * @throws Tool_Error When it is not a group.
	 */
	private function group_index( array $top, string $group ): int {
		$index = preg_match( '/^g(\d+)$/', $group, $m ) ? (int) $m[1] - 1 : -1;

		if ( ! isset( $top[ $index ] ) || 'core/group' !== $top[ $index ]['blockName'] ) {
			throw new Tool_Error( sprintf( '"%s" is not a group on the page. Call get_page_layout for the current ids.', $group ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
		}

		return $index;
	}

	/**
	 * Group and block index from "g2.3".
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  string $id  Block id.
	 * @return array{0:int,1:int}
	 * @throws Tool_Error When the id is malformed.
	 */
	private function path( string $id ): array {
		if ( ! preg_match( '/^g(\d+)\.(\d+)$/', $id, $m ) ) {
			throw new Tool_Error( sprintf( '"%s" is not a block id like "g2.1". Call get_page_layout.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
		}

		return array( (int) $m[1] - 1, (int) $m[2] - 1 );
	}

	/**
	 * A block the assistant may change or remove.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<int,array<string,mixed>> $top  Top-level blocks.
	 * @param  string                         $id   Block id.
	 * @return array{0:int,1:int}
	 * @throws Tool_Error When it does not exist or is managed by the price list.
	 */
	private function editable( array $top, string $id ): array {
		[ $g, $m ] = $this->path( $id );
		$inner     = isset( $top[ $g ] ) && 'core/group' === $top[ $g ]['blockName'] ? array_values( (array) $top[ $g ]['innerBlocks'] ) : array();

		if ( ! isset( $inner[ $m ] ) ) {
			throw new Tool_Error( sprintf( 'There is no block "%s". Call get_page_layout for the current ids.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
		}

		if ( in_array( $inner[ $m ]['blockName'], self::MANAGED, true ) ) {
			throw new Tool_Error( 'That block comes from the price list; change it with the price list tools.' );
		}

		return array( $g, $m );
	}

	/**
	 * A group with new inner blocks (keeps its own opening and closing markup).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<string,mixed>            $group  Group block.
	 * @param  array<int,array<string,mixed>> $inner  Inner blocks.
	 * @return array<string,mixed>
	 */
	private function with_inner( array $group, array $inner ): array {
		$content = (array) $group['innerContent'];
		$open    = (string) ( $content[0] ?? '<div class="wp-block-group">' );
		$last    = end( $content );
		$close   = is_string( $last ) && '' !== $last ? $last : '</div>';

		$group['innerBlocks']  = array_values( $inner );
		$group['innerContent'] = array_merge( array( $open ), array_fill( 0, count( $inner ), null ), array( $close ) );

		// Blank lines between inner blocks, as the editor writes them.
		if ( count( $inner ) > 1 ) {
			$spaced = array( $open );
			foreach ( array_keys( $inner ) as $i ) {
				if ( $i > 0 ) {
					$spaced[] = "\n\n";
				}
				$spaced[] = null;
			}
			$spaced[]              = $close;
			$group['innerContent'] = $spaced;
		}

		$group['innerHTML'] = $open . $close;

		return $group;
	}

	/**
	 * What a top-level block holds: price list, heading banner, or own content.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<string,mixed> $block  Block.
	 * @return string
	 */
	private function holds( array $block ): string {
		$names = $this->names( $block );

		if ( in_array( 'satori-manifest/price-list-heading', $names, true ) ) {
			return 'heading banner (from the price list)';
		}

		return in_array( 'satori-manifest/price-list', $names, true ) ? 'price list menu' : 'own content';
	}

	/**
	 * Every block name inside a block, itself included.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<string,mixed> $block  Block.
	 * @return array<int,string>
	 */
	private function names( array $block ): array {
		$names = array( (string) $block['blockName'] );

		foreach ( (array) $block['innerBlocks'] as $inner ) {
			$names = array_merge( $names, $this->names( $inner ) );
		}

		return $names;
	}

	/**
	 * A short text summary of a block.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<string,mixed> $block  Block.
	 * @return string
	 */
	private function summary( array $block ): string {
		if ( 'satori-manifest/price-list-heading' === $block['blockName'] ) {
			return 'banner: heading ' . (string) ( $block['attrs']['headingId'] ?? '' );
		}

		if ( 'satori-manifest/price-list' === $block['blockName'] ) {
			return 'price list';
		}

		$text = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( serialize_block( $block ) ) ) );

		return mb_substr( $text, 0, 80 );
	}

	/**
	 * A readable name for a group in change summaries.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  array<string,mixed> $group  Group.
	 * @param  string              $id     Its id.
	 * @return string
	 */
	private function label( array $group, string $id ): string {
		$name = (string) ( $group['attrs']['metadata']['name'] ?? '' );

		return '' !== $name ? '"' . $name . '"' : $id;
	}

	/**
	 * Requires a field.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @param  string $value  Value.
	 * @param  string $field  Field name.
	 * @return void
	 * @throws Tool_Error When empty.
	 */
	private function need( string $value, string $field ): void {
		if ( '' === trim( $value ) ) {
			throw new Tool_Error( sprintf( '%s is required for this block.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
		}
	}
}
