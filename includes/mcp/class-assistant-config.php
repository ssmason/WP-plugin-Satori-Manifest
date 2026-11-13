<?php
/**
 * AI assistant settings from config/ai-assistant.yaml.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp;

use SatoriManifest\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assistant_Config
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Assistant_Config {

	/**
	 * Parsed settings (per request).
	 *
	 * @var array{enabled:bool,plans:array<int,string>,requests:int,minutes:int,history:int}|null
	 */
	private static ?array $settings = null;

	/**
	 * Whether the assistant is switched on site-wide.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return bool
	 */
	public static function enabled(): bool {
		/**
		 * Filters whether the AI assistant is available on this site.
		 *
		 * @since 3.9.0
		 * @param bool $enabled  From ai-assistant.yaml.
		 */
		return (bool) apply_filters( 'satori_manifest_ai_enabled', self::settings()['enabled'] );
	}

	/**
	 * Plan slugs that include the assistant.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return array<int,string>
	 */
	public static function plans(): array {
		return self::settings()['plans'];
	}

	/**
	 * Requests allowed per window.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return int
	 */
	public static function rate_requests(): int {
		return self::settings()['requests'];
	}

	/**
	 * Window length in seconds.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return int
	 */
	public static function rate_window(): int {
		return self::settings()['minutes'] * MINUTE_IN_SECONDS;
	}

	/**
	 * Changes kept per client.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return int
	 */
	public static function history(): int {
		return self::settings()['history'];
	}

	/**
	 * Clears the per-request cache (tests, config changes).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return void
	 */
	public static function flush(): void {
		self::$settings = null;
	}

	/**
	 * Reads and normalises the YAML.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return array{enabled:bool,plans:array<int,string>,requests:int,minutes:int,history:int}
	 */
	private static function settings(): array {
		if ( null === self::$settings ) {
			$raw = Config::get( 'ai-assistant.yaml' );

			self::$settings = array(
				'enabled'  => (bool) ( $raw['enabled'] ?? false ),
				'plans'    => array_values( array_filter( array_map( 'sanitize_key', array_map( 'strval', (array) ( $raw['plans'] ?? array() ) ) ) ) ),
				'requests' => max( 10, absint( $raw['rate_limit']['requests'] ?? 120 ) ),
				'minutes'  => max( 1, absint( $raw['rate_limit']['minutes'] ?? 5 ) ),
				'history'  => min( 200, max( 1, absint( $raw['history'] ?? 30 ) ) ),
			);
		}

		return self::$settings;
	}
}
