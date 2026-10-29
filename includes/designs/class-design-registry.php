<?php
/**
 * Design registry.
 *
 * A design is a self-contained folder:
 *
 *   designs/{slug}/design.yaml   — title, description, palette, fonts, header layout
 *   designs/{slug}/content.html  — starter block markup inserted into the page
 *   designs/{slug}/style.scss    — design styles (compiled to build/designs/{slug}.css)
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
				$design = self::load( $folder, $slug, $source['css']( $slug ) );

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
	 * Supported placeholders: {{brand_name}}, {{tagline}}, {{year}}.
	 * Values are HTML-escaped before substitution.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string               $slug     Design slug.
	 * @param  array<string,string> $context  Placeholder values.
	 * @return string  Serialized block markup, empty for an unknown design.
	 */
	public static function content( string $slug, array $context = array() ): string {
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

		return strtr( $markup, $replacements );
	}

	/**
	 * Loads and validates one design folder.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string            $folder  Absolute folder path.
	 * @param  string            $slug    Design slug.
	 * @param  array<int,string> $css     [ absolute path, URL ] of the compiled stylesheet.
	 * @return array<string,mixed>|null   Normalised design, or null when invalid.
	 */
	private static function load( string $folder, string $slug, array $css ): ?array {
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

		$palette = array();
		foreach ( self::PALETTE_KEYS as $key ) {
			$palette[ $key ] = (string) ( sanitize_hex_color( (string) ( $config['palette'][ $key ] ?? '' ) ) ?? '' );
		}

		$header = (string) ( $config['header'] ?? 'split' );

		return array(
			'slug'         => $slug,
			'title'        => (string) ( $config['title'] ?? ucfirst( $slug ) ),
			'description'  => (string) ( $config['description'] ?? '' ),
			'best_for'     => (string) ( $config['best_for'] ?? '' ),
			'order'        => (int) ( $config['order'] ?? 100 ),
			'tags'         => array_map( 'strval', (array) ( $config['tags'] ?? array() ) ),
			'palette'      => $palette,
			'fonts'        => array(
				'heading' => sanitize_key( (string) ( $config['fonts']['heading'] ?? 'system-serif' ) ),
				'body'    => sanitize_key( (string) ( $config['fonts']['body'] ?? 'system-sans' ) ),
			),
			'header'       => in_array( $header, self::HEADER_LAYOUTS, true ) ? $header : 'split',
			'content_path' => $folder . '/content.html',
			'style_path'   => $css[0],
			'style_url'    => $css[1],
		);
	}
}
