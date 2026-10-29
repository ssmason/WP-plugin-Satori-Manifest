<?php
/**
 * YAML configuration loader with theme overrides.
 *
 * Every editable configuration file (ACF field groups, fonts, demo data)
 * ships inside the plugin under config/. A theme can override any file by
 * placing a file with the same relative path under
 * {theme}/satori-manifest/ — child theme first, then parent theme, then the
 * plugin default. The plugin therefore works with any theme, while a project
 * theme can still own and edit the configuration.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Config
 *
 * Resolves and caches YAML configuration files.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Config {

	/**
	 * Folder name used for overrides inside a theme.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	public const THEME_FOLDER = 'satori-manifest';

	/**
	 * Parsed file cache keyed by absolute path.
	 *
	 * @since 2.0.0
	 * @var   array<string,array<mixed>>
	 */
	private static array $cache = array();

	/**
	 * Returns the base directories searched for configuration, highest priority first.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return array<int,string>  Absolute directory paths with trailing slashes.
	 */
	public static function base_dirs(): array {
		$dirs = array_unique(
			array(
				trailingslashit( get_stylesheet_directory() ) . self::THEME_FOLDER . '/',
				trailingslashit( get_template_directory() ) . self::THEME_FOLDER . '/',
				SATORI_MANIFEST_PATH . 'config/',
			)
		);

		/**
		 * Filters the directories searched for Satori Manifest YAML configuration.
		 *
		 * @since 2.0.0
		 * @param array<int,string> $dirs  Absolute directory paths, highest priority first.
		 */
		return (array) apply_filters( 'satori_manifest_config_dirs', array_values( $dirs ) );
	}

	/**
	 * Loads a single configuration file by relative path (e.g. "fonts.yaml").
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $relative  Path relative to a config base directory.
	 * @return array<mixed>       Parsed data, empty when no file exists.
	 */
	public static function get( string $relative ): array {
		foreach ( self::base_dirs() as $dir ) {
			$path = $dir . ltrim( $relative, '/' );
			if ( is_readable( $path ) ) {
				return self::read( $path );
			}
		}

		return array();
	}

	/**
	 * Loads every YAML file in a configuration folder, keyed by file name.
	 *
	 * A theme file replaces the plugin file with the same name; theme-only
	 * files are added. Results are sorted by file name.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $folder  Folder relative to a config base directory (e.g. "acf").
	 * @return array<string,array<mixed>>  Parsed files keyed by base name without extension.
	 */
	public static function collect( string $folder ): array {
		$files = array();

		// Lowest priority first so higher-priority directories overwrite.
		foreach ( array_reverse( self::base_dirs() ) as $dir ) {
			$matches = glob( $dir . trim( $folder, '/' ) . '/*.{yaml,yml}', GLOB_BRACE );
			foreach ( $matches ? $matches : array() as $path ) {
				$files[ pathinfo( $path, PATHINFO_FILENAME ) ] = $path;
			}
		}

		ksort( $files );

		$out = array();
		foreach ( $files as $name => $path ) {
			$out[ $name ] = self::read( $path );
		}

		return $out;
	}

	/**
	 * Parses a file once per request, logging rather than fatalling on bad YAML.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $path  Absolute file path.
	 * @return array<mixed>
	 */
	private static function read( string $path ): array {
		if ( isset( self::$cache[ $path ] ) ) {
			return self::$cache[ $path ];
		}

		try {
			$data = Yaml::parse_file( $path );
		} catch ( \UnexpectedValueException $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- surfaces config mistakes without breaking the site.
			error_log( 'Satori Manifest config error: ' . $e->getMessage() );
			$data = array();
		}

		self::$cache[ $path ] = $data;
		return $data;
	}
}
