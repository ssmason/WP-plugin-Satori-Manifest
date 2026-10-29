<?php
/**
 * WordPress test-suite configuration.
 *
 * Runs the tests against the project's own database but in separate
 * "wptests_" tables, which the suite creates and empties on every run — real
 * site data (wp_ tables) is never touched. Database credentials are read from
 * the Bedrock .env at the project root, so nothing secret lives in this file.
 *
 * @package SatoriManifest
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- constants and globals required by the WordPress test suite.

$satori_manifest_root = dirname( __DIR__, 5 );

/*
 * Minimal .env reader (KEY=value, optional quotes, # comments). The project's
 * own Dotenv package is not loaded on purpose: the root vendor/ ships a
 * different PHPUnit, which would clash with this suite's PHPUnit 9.
 */
$satori_manifest_dotenv = array();
$satori_manifest_file   = $satori_manifest_root . '/.env';

if ( is_readable( $satori_manifest_file ) ) {
	foreach ( (array) file( $satori_manifest_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $satori_manifest_line ) {
		if ( preg_match( '/^\s*(?:export\s+)?([A-Z0-9_]+)\s*=\s*(.*)$/', (string) $satori_manifest_line, $satori_manifest_match ) ) {
			$satori_manifest_value                               = trim( preg_replace( '/\s+#.*$/', '', $satori_manifest_match[2] ) );
			$satori_manifest_dotenv[ $satori_manifest_match[1] ] = trim( $satori_manifest_value, '"\'' );
		}
	}
}

$satori_manifest_env = static function ( string $name, string $fallback = '' ) use ( $satori_manifest_dotenv ): string {
	$value = getenv( $name );
	if ( false !== $value && '' !== $value ) {
		return (string) $value;
	}
	return $satori_manifest_dotenv[ $name ] ?? $fallback;
};

if ( '' !== $satori_manifest_env( 'DATABASE_URL' ) ) {
	$satori_manifest_dsn = (array) parse_url( $satori_manifest_env( 'DATABASE_URL' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- runs before WordPress loads.
	define( 'DB_NAME', ltrim( (string) ( $satori_manifest_dsn['path'] ?? '' ), '/' ) );
	define( 'DB_USER', (string) ( $satori_manifest_dsn['user'] ?? '' ) );
	define( 'DB_PASSWORD', (string) ( $satori_manifest_dsn['pass'] ?? '' ) );
	define( 'DB_HOST', (string) ( $satori_manifest_dsn['host'] ?? 'localhost' ) . ( isset( $satori_manifest_dsn['port'] ) ? ':' . $satori_manifest_dsn['port'] : '' ) );
} else {
	define( 'DB_NAME', $satori_manifest_env( 'DB_NAME' ) );
	define( 'DB_USER', $satori_manifest_env( 'DB_USER' ) );
	define( 'DB_PASSWORD', $satori_manifest_env( 'DB_PASSWORD' ) );
	define( 'DB_HOST', $satori_manifest_env( 'DB_HOST', 'localhost' ) );
}

define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the test suite reads this global.

define( 'ABSPATH', $satori_manifest_root . '/web/wp/' );
define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_DEBUG', true );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Satori Manifest Tests' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
