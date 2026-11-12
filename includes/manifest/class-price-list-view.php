<?php
/**
 * Price list block - view data.
 *
 * Turns the satori-manifest/price-list block's attributes into what its
 * template (blocks/price-list/render.php) needs: display options, wrapper
 * attributes (classes and CSS variables), the currency and the sections to
 * show. The template only outputs markup.
 *
 * Manifest resolution:
 *   - manifestIds set        → those manifests.
 *   - excludedSectionIds set → those sections (by permanent id) are hidden, so
 *                              several blocks can show different slices with
 *                              different layouts; new sections show by default.
 *   - manifestIds empty      → on a client page, that client's own manifest.
 *   - On a client page only manifests owned by that client are rendered,
 *     so a block can never display another client's prices.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.7.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Manifest;

use SatoriManifest\Branding\Brand;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Core\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Price_List_View
 *
 * Prepares the price list block for rendering.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.7.0
 */
class Price_List_View {

	/**
	 * Font weights allowed in the block's typography settings.
	 *
	 * @since 3.7.0
	 * @var   array<int,string>
	 */
	private const WEIGHTS = array( '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold', 'lighter', 'bolder' );

	/**
	 * Custom colour attributes and the CSS variables they set.
	 *
	 * @since 3.7.0
	 * @var   array<string,string>
	 */
	private const CUSTOM_COLOURS = array(
		'customBgColor'     => '--sm-custom-bg',
		'customAccentColor' => '--sm-custom-accent',
		'customTitleColor'  => '--sm-custom-title',
		'customCardColor'   => '--sm-custom-card',
	);

	/**
	 * Builds the view data, or null when there is nothing to show.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @param  array<string,mixed> $attributes  Block attributes.
	 * @param  int                 $post_id     Post the block is rendered in.
	 * @return array{options:array<string,mixed>,wrapper:array<string,string>,currency:array<string,string>,manifests:array<int,array<int,array<string,mixed>>>}|null
	 */
	public static function prepare( array $attributes, int $post_id ): ?array {
		$client_id = Client_Repository::client_for_page( $post_id );
		$manifests = self::manifests( $attributes, $client_id );

		// No manifest to show: a styled demo in editor/REST previews, nothing on the front end.
		if ( ! $manifests ) {
			if ( ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
				return null;
			}

			$manifests = array( Manifest_Repository::demo_sections() );
		}

		return array(
			'options'   => self::options( $attributes ),
			'wrapper'   => self::wrapper( $attributes ),
			'currency'  => $client_id
				? Brand::for_page( $post_id )['currency']
				: array(
					'symbol'   => '',
					'position' => 'before',
				),
			'manifests' => $manifests,
		);
	}

	/**
	 * Returns a readable, unique anchor for a section title ("Hot drinks" → hot-drinks).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @param  string            $title  Section title.
	 * @param  array<string,int> $seen   Anchors used so far on this block (updated).
	 * @return string  Anchor, '' for an empty title.
	 */
	public static function anchor( string $title, array &$seen ): string {
		$anchor = sanitize_title( $title );

		if ( '' === $anchor ) {
			return '';
		}

		$seen[ $anchor ] = ( $seen[ $anchor ] ?? 0 ) + 1;

		return $seen[ $anchor ] > 1 ? $anchor . '-' . $seen[ $anchor ] : $anchor;
	}

	/**
	 * Display switches used by the template.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @param  array<string,mixed> $attributes  Block attributes.
	 * @return array<string,mixed>
	 */
	private static function options( array $attributes ): array {
		return array(
			'prices'         => self::flag( $attributes, 'showPrices' ),
			'descriptions'   => self::flag( $attributes, 'showDescriptions' ),
			'section_intros' => self::flag( $attributes, 'showSectionDescriptions' ),
			'badges'         => self::flag( $attributes, 'showBadges' ),
			'prefix'         => isset( $attributes['pricePrefix'] ) ? sanitize_text_field( (string) $attributes['pricePrefix'] ) : '',
		);
	}

	/**
	 * Wrapper attributes: layout/scheme classes and the CSS variables.
	 *
	 * Values are sanitised here; get_block_wrapper_attributes() escapes them.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @param  array<string,mixed> $attributes  Block attributes.
	 * @return array<string,string>
	 */
	private static function wrapper( array $attributes ): array {
		$layout   = isset( $attributes['layout'] ) ? sanitize_key( (string) $attributes['layout'] ) : 'single-column';
		$scheme   = isset( $attributes['colorScheme'] ) ? sanitize_key( (string) $attributes['colorScheme'] ) : 'default';
		$title_bg = isset( $attributes['titleBgColor'] ) ? (string) sanitize_hex_color( (string) $attributes['titleBgColor'] ) : '';

		$classes = array_filter(
			array(
				'satori-manifest-price-list',
				'is-layout-' . $layout,
				'is-scheme-' . $scheme,
				self::flag( $attributes, 'showBackground' ) ? '' : 'has-no-background',
				'' !== $title_bg ? 'has-title-bg' : '',
				self::flag( $attributes, 'cardPadding' ) ? '' : 'has-no-card-padding',
				self::flag( $attributes, 'titlePadding' ) ? '' : 'has-no-title-padding',
				self::flag( $attributes, 'showItemBorder' ) ? '' : 'has-no-item-border',
			)
		);

		$vars = array();

		if ( 'custom' === $scheme ) {
			foreach ( self::CUSTOM_COLOURS as $attribute => $property ) {
				$value = isset( $attributes[ $attribute ] ) ? sanitize_hex_color( (string) $attributes[ $attribute ] ) : '';
				if ( $value ) {
					$vars[ $property ] = $value;
				}
			}
		}

		$vars['--sm-title-bg']     = $title_bg;
		$vars['--sm-title-font']   = self::font_family( $attributes, 'titleFontFamily' );
		$vars['--sm-title-size']   = self::font_size( $attributes, 'titleFontSize' );
		$vars['--sm-title-weight'] = self::font_weight( $attributes, 'titleFontWeight' );
		$vars['--sm-item-font']    = self::font_family( $attributes, 'itemFontFamily' );
		$vars['--sm-item-size']    = self::font_size( $attributes, 'itemFontSize' );
		$vars['--sm-item-weight']  = self::font_weight( $attributes, 'itemFontWeight' );

		$style = '';
		foreach ( array_filter( $vars, static fn( string $value ): bool => '' !== $value ) as $property => $value ) {
			$style .= $property . ':' . $value . ';';
		}

		return '' === $style
			? array( 'class' => implode( ' ', $classes ) )
			: array(
				'class' => implode( ' ', $classes ),
				'style' => $style,
			);
	}

	/**
	 * Sections to show, grouped per manifest.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @param  array<string,mixed> $attributes  Block attributes.
	 * @param  int                 $client_id   Client owning the page (0 when none).
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	private static function manifests( array $attributes, int $client_id ): array {
		$ids = isset( $attributes['manifestIds'] ) && is_array( $attributes['manifestIds'] )
			? array_values( array_filter( array_map( 'absint', $attributes['manifestIds'] ) ) )
			: array();

		if ( ! $ids && $client_id ) {
			$own = Client_Repository::manifest_id( $client_id );
			$ids = $own ? array( $own ) : array();
		}

		$hidden = isset( $attributes['excludedSectionIds'] ) && is_array( $attributes['excludedSectionIds'] )
			? array_values( array_filter( array_map( static fn( $id ): string => Sanitizer::section_id( (string) $id ), $attributes['excludedSectionIds'] ) ) )
			: array();

		$manifests = array();

		foreach ( $ids as $id ) {
			$manifest = get_post( $id );

			if (
				! $manifest instanceof \WP_Post
				|| Post_Types::CPT_MANIFEST !== $manifest->post_type
				|| 'publish' !== $manifest->post_status
				|| ( $client_id && (int) get_post_meta( $id, Client_Repository::OWNER, true ) !== $client_id )
			) {
				continue;
			}

			$sections = array_values(
				array_filter(
					Manifest_Repository::get_sections( $id ),
					static fn( $section ): bool => ! in_array( (string) ( $section['id'] ?? '' ), $hidden, true )
				)
			);

			if ( $sections ) {
				$manifests[] = $sections;
			}
		}

		return $manifests;
	}

	/**
	 * Reads a boolean attribute that defaults to true.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @param  array<string,mixed> $attributes  Block attributes.
	 * @param  string              $name        Attribute name.
	 * @return bool
	 */
	private static function flag( array $attributes, string $name ): bool {
		return isset( $attributes[ $name ] ) ? (bool) $attributes[ $name ] : true;
	}

	/**
	 * A font family safe inside a style attribute (letters, digits, spaces, commas, hyphens, apostrophes).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @param  array<string,mixed> $attributes  Block attributes.
	 * @param  string              $name        Attribute name.
	 * @return string
	 */
	private static function font_family( array $attributes, string $name ): string {
		return isset( $attributes[ $name ] ) ? (string) preg_replace( '/[^a-zA-Z0-9\s,\'\-]/', '', (string) $attributes[ $name ] ) : '';
	}

	/**
	 * A font size in px, '' when unset.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @param  array<string,mixed> $attributes  Block attributes.
	 * @param  string              $name        Attribute name.
	 * @return string
	 */
	private static function font_size( array $attributes, string $name ): string {
		$size = isset( $attributes[ $name ] ) ? absint( $attributes[ $name ] ) : 0;

		return $size > 0 ? $size . 'px' : '';
	}

	/**
	 * A numeric or keyword font weight, '' when unset or unknown.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @param  array<string,mixed> $attributes  Block attributes.
	 * @param  string              $name        Attribute name.
	 * @return string
	 */
	private static function font_weight( array $attributes, string $name ): string {
		$weight = isset( $attributes[ $name ] ) ? (string) $attributes[ $name ] : '';

		return in_array( $weight, self::WEIGHTS, true ) ? $weight : '';
	}
}
