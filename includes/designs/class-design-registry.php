<?php
/**
 * Design registry.
 *
 * A design is a self-contained folder:
 *
 *   designs/{slug}/design.yaml   - title, description, palette, fonts, header layout
 *   designs/{slug}/content.html  - starter block markup inserted into the page
 *   designs/{slug}/style.scss    - design styles (compiled to build/designs/{slug}.css)
 *
 * Themes can add or replace designs by providing the same structure under
 * {theme}/satori-manifest/designs/{slug}/ with a pre-compiled style.css.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Designs;

use SatoriManifest\Core\Config;
use SatoriManifest\Core\Yaml;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Design_Registry
 *
 * Discovers designs and exposes their metadata, content and stylesheet.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Design_Registry {

	/**
	 * Palette keys every design defines (also the brand colour overrides).
	 *
	 * @since 2.0.0
	 * @var   array<int,string>
	 */
	public const PALETTE_KEYS = array( 'background', 'surface', 'text', 'muted', 'accent', 'accent_text' );

	/**
	 * Header layouts a design may default to.
	 *
	 * @since 2.0.0
	 * @var   array<int,string>
	 */
	public const HEADER_LAYOUTS = array( 'split', 'centered', 'stacked', 'hidden' );

	/**
	 * Colour modes every design offers.
	 *
	 * @since 3.8.0
	 * @var   array<int,string>
	 */
	public const COLOUR_MODES = array( 'light', 'dark' );

	/**
	 * Design kinds, in the order the Design screen groups them.
	 *
	 * @since 3.8.0
	 * @var   array<int,string>
	 */
	public const KINDS = array( 'full', 'simple' );

	/**
	 * Request cache of discovered designs.
	 *
	 * @since 2.0.0
	 * @var   array<string,array<string,mixed>>|null
	 */
	private static ?array $designs = null;

	/**
	 * Returns all designs keyed by slug, sorted by their "order" value.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return array<string,array<string,mixed>>
	 */
	public static function all(): array {
		if ( null !== self::$designs ) {
			return self::$designs;
		}

		$designs = array();

		// Plugin designs first, then theme designs (which may replace them).
		$sources = array(
			array(
				'dir' => SATORI_MANIFEST_PATH . 'designs/',
				'url' => SATORI_MANIFEST_URL . 'designs/',
				'css' => static fn( string $slug ): array => array(
					SATORI_MANIFEST_PATH . 'build/designs/' . $slug . '.css',
					SATORI_MANIFEST_URL . 'build/designs/' . $slug . '.css',
				),
			),
		);

		foreach ( array_unique( array( get_template_directory(), get_stylesheet_directory() ) ) as $theme_dir ) {
			$theme_url = get_template_directory() === $theme_dir ? get_template_directory_uri() : get_stylesheet_directory_uri();
			$base      = '/' . Config::THEME_FOLDER . '/designs/';
			$sources[] = array(
				'dir' => $theme_dir . $base,
				'url' => $theme_url . $base,
				'css' => static fn( string $slug ): array => array(
					$theme_dir . $base . $slug . '/style.css',
					$theme_url . $base . $slug . '/style.css',
				),
			);
		}

		foreach ( $sources as $source ) {
			$folders = glob( $source['dir'] . '*', GLOB_ONLYDIR );

			foreach ( $folders ? $folders : array() as $folder ) {
				$slug   = sanitize_key( basename( $folder ) );
				$design = self::load( $folder, $source['url'] . basename( $folder ), $slug, $source['css']( $slug ) );

				if ( $design ) {
					$designs[ $slug ] = $design;
				}
			}
		}

		uasort(
			$designs,
			static fn( array $a, array $b ): int => $a['order'] <=> $b['order']
		);

		/**
		 * Filters the registered designs.
		 *
		 * @since 2.0.0
		 * @param array<string,array<string,mixed>> $designs  Designs keyed by slug.
		 */
		self::$designs = (array) apply_filters( 'satori_manifest_designs', $designs );

		return self::$designs;
	}

	/**
	 * Returns one design, or null when unknown.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $slug  Design slug.
	 * @return array<string,mixed>|null
	 */
	public static function get( string $slug ): ?array {
		$designs = self::all();
		return $designs[ $slug ] ?? null;
	}

	/**
	 * Returns true when the design exists.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $slug  Design slug.
	 * @return bool
	 */
	public static function exists( string $slug ): bool {
		return '' !== $slug && null !== self::get( $slug );
	}

	/**
	 * Returns the slug of the first design (used for new clients).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return string
	 */
	public static function default_slug(): string {
		$slugs = array_keys( self::all() );
		return (string) ( $slugs[0] ?? '' );
	}

	/**
	 * Returns a design's starter block markup with placeholders replaced.
	 *
	 * Supported placeholders: {{brand_name}}, {{tagline}}, {{year}}, and for each
	 * photo in the design's images/ folder {{image_id:name}} and {{image_url:name}}
	 * (name = file name without extension). Values are escaped before substitution.
	 * Photos not in $image_ids use the design folder's copy (ID 0), as in previews.
	 *
	 * {{sections:2}} / {{sections:3-}} let a design split the price list over
	 * several blocks by position: it becomes the JSON list of the client's other
	 * section ids (the block's excludedSectionIds). Without ids nothing is hidden.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string               $slug     Design slug.
	 * @param  array<string,string> $context    Placeholder values.
	 * @param  array<string,int>    $image_ids    Media library IDs by photo name.
	 * @param  array<int,string>    $section_ids  The client's section ids, in order.
	 * @return string  Serialized block markup, empty for an unknown design.
	 */
	public static function content( string $slug, array $context = array(), array $image_ids = array(), array $section_ids = array() ): string {
		$design = self::get( $slug );

		if ( ! $design || ! is_readable( $design['content_path'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local design file.
		$markup = (string) file_get_contents( $design['content_path'] );

		$context = wp_parse_args(
			$context,
			array(
				'brand_name' => __( 'Your Business', 'satori-manifest' ),
				'tagline'    => __( 'Our services & prices', 'satori-manifest' ),
				'year'       => gmdate( 'Y' ),
			)
		);

		$replacements = array();
		foreach ( $context as $key => $value ) {
			$replacements[ '{{' . $key . '}}' ] = esc_html( (string) $value );
		}

		foreach ( $design['images'] as $name => $image ) {
			$id  = (int) ( $image_ids[ $name ] ?? 0 );
			$src = $id ? (string) wp_get_attachment_image_url( $id, 'full' ) : '';

			$replacements[ '{{image_id:' . $name . '}}' ]  = (string) $id;
			$replacements[ '{{image_url:' . $name . '}}' ] = esc_url( '' !== $src ? $src : $image['url'] );
		}

		return (string) preg_replace_callback(
			'/\{\{sections:(\d+)(-?)\}\}/',
			static function ( array $found ) use ( $section_ids ): string {
				$first = max( 1, (int) $found[1] );
				$last  = '-' === $found[2] ? count( $section_ids ) : $first;
				$shown = array_slice( $section_ids, $first - 1, $last - $first + 1 );

				return (string) wp_json_encode( array_values( $section_ids ? array_diff( $section_ids, $shown ) : array() ) );
			},
			strtr( $markup, $replacements )
		);
	}

	/**
	 * Reads one palette from YAML (unknown keys dropped, invalid colours empty).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  array<string,mixed> $raw  Palette from design.yaml.
	 * @return array<string,string>
	 */
	private static function palette( array $raw ): array {
		$palette = array();

		foreach ( self::PALETTE_KEYS as $key ) {
			$palette[ $key ] = (string) ( sanitize_hex_color( (string) ( $raw[ $key ] ?? '' ) ) ?? '' );
		}

		return $palette;
	}

	/**
	 * Lists the photos in a design's images/ folder.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  string $folder  Absolute design folder path.
	 * @param  string $url     Design folder URL.
	 * @return array<string,array{path:string,url:string}>  Keyed by file name without extension.
	 */
	private static function images( string $folder, string $url ): array {
		$files  = glob( $folder . '/images/*.{jpg,jpeg,png,webp}', GLOB_BRACE );
		$images = array();

		foreach ( $files ? $files : array() as $file ) {
			$images[ sanitize_key( pathinfo( $file, PATHINFO_FILENAME ) ) ] = array(
				'path' => $file,
				'url'  => $url . '/images/' . rawurlencode( basename( $file ) ),
			);
		}

		return $images;
	}

	/**
	 * Loads and validates one design folder.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string            $folder  Absolute folder path.
	 * @param  string            $url     Folder URL.
	 * @param  string            $url     Folder URL.
	 * @param  string            $slug    Design slug.
	 * @param  array<int,string> $css     [ absolute path, URL ] of the compiled stylesheet.
	 * @return array<string,mixed>|null   Normalised design, or null when invalid.
	 */
	private static function load( string $folder, string $url, string $slug, array $css ): ?array {
		$config_path = $folder . '/design.yaml';

		if ( '' === $slug || ! is_readable( $config_path ) ) {
			return null;
		}

		try {
			$config = Yaml::parse_file( $config_path );
		} catch ( \UnexpectedValueException $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- surfaces config mistakes without breaking the site.
			error_log( 'Satori Manifest design error: ' . $e->getMessage() );
			return null;
		}

		$mode     = in_array( $config['mode'] ?? '', self::COLOUR_MODES, true ) ? (string) $config['mode'] : 'light';
		$palette  = self::palette( (array) ( $config['palette'] ?? array() ) );
		$palettes = array();

		foreach ( self::COLOUR_MODES as $colour_mode ) {
			$palettes[ $colour_mode ] = $colour_mode === $mode || empty( $config[ 'palette_' . $colour_mode ] )
				? $palette
				: self::palette( (array) $config[ 'palette_' . $colour_mode ] );
		}

		$kind = in_array( $config['kind'] ?? '', self::KINDS, true ) ? (string) $config['kind'] : 'simple';

		$header = (string) ( $config['header'] ?? 'split' );

		return array(
			'slug'         => $slug,
			'title'        => (string) ( $config['title'] ?? ucfirst( $slug ) ),
			'description'  => (string) ( $config['description'] ?? '' ),
			'best_for'     => (string) ( $config['best_for'] ?? '' ),
			'order'        => (int) ( $config['order'] ?? 100 ),
			'tags'         => array_map( 'strval', (array) ( $config['tags'] ?? array() ) ),
			'kind'         => $kind,
			'mode'         => $mode,
			'palette'      => $palette,
			'palettes'     => $palettes,
			'fonts'        => array(
				'heading' => sanitize_key( (string) ( $config['fonts']['heading'] ?? 'system-serif' ) ),
				'body'    => sanitize_key( (string) ( $config['fonts']['body'] ?? 'system-sans' ) ),
			),
			'header'       => in_array( $header, self::HEADER_LAYOUTS, true ) ? $header : 'split',
			'content_path' => $folder . '/content.html',
			'images'       => self::images( $folder, $url ),
			'style_path'   => $css[0],
			'style_url'    => $css[1],
		);
	}
}
