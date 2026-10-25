<?php
/**
 * PHPUnit bootstrap — loads the WordPress test suite then the plugin.
 *
 * Wp-env sets WP_TESTS_DIR to /tmp/wordpress-tests-lib inside the
 * tests-cli container. The polyfills path must be defined before the
 * WP bootstrap runs so it can locate the Yoast PHPUnit Polyfills.
 *
 * @package SatoriManifest
 */

declare( strict_types=1 );

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- standard WP test bootstrap pattern.
$tests_dir  = getenv( 'WP_TESTS_DIR' );
$tests_dir  = $tests_dir ? $tests_dir : '/tmp/wordpress-tests-lib';
$plugin_dir = dirname( __DIR__ );
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if ( ! file_exists( $tests_dir . '/includes/functions.php' ) ) {
	fprintf( STDERR, "Could not find WP test suite at %s\n", $tests_dir ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- required name defined by WP test suite.
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $plugin_dir . '/vendor/yoast/phpunit-polyfills' );

require_once $tests_dir . '/includes/functions.php';

/**
 * Loads the plugin before the WP test environment boots.
 *
 * @return void
 */
function satori_manifest_load_plugin(): void {
	require dirname( __DIR__ ) . '/satori-manifest.php';
}
tests_add_filter( 'muplugins_loaded', 'satori_manifest_load_plugin' );

require $tests_dir . '/includes/bootstrap.php';
