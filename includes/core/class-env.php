<?php
/**
 * Environment settings.
 *
 * Reads secrets and deployment settings (API keys, sender addresses) from a
 * PHP constant or an environment variable — never from the database, so
 * credentials stay out of backups and out of wp-admin.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Env
 *
 * Constant-or-environment lookups.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Env {

	/**
	 * Returns a setting from a PHP constant or an environment variable.
	 *
	 * A constant wins over an environment variable of the same name. Bedrock
	 * exposes .env values through both, so either works there.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $name  Constant / variable name, e.g. STRIPE_SECRET_KEY.
	 * @return string  Trimmed value, or '' when unset.
	 */
	public static function get( string $name ): string {
		if ( defined( $name ) ) {
			return trim( (string) constant( $name ) );
		}

		$value = getenv( $name );

		if ( false === $value && isset( $_ENV[ $name ] ) ) {
			$value = (string) $_ENV[ $name ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- server configuration, not user input.
		}

		return false === $value ? '' : trim( (string) $value );
	}

	/**
	 * Returns true when a setting has a non-empty value.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $name  Constant / variable name.
	 * @return bool
	 */
	public static function has( string $name ): bool {
		return '' !== self::get( $name );
	}
}
