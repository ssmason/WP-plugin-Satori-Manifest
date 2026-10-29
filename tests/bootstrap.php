<?php
/**
 * PHPUnit bootstrap — boots the WordPress test suite, then the plugin.
 *
 * Uses the wp-phpunit/wp-phpunit package (a Composer dev dependency) and
 * tests/wp-tests-config.php. Run inside the PHP container:
 *
 *   docker exec -w /var/www/html/web/app/plugins/satori-manifest qr-code-manager-php-1 vendor/bin/phpunit
 *
 * @package SatoriManifest
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- names required by the WordPress test suite.

$satori_manifest_plugin = dirname( __DIR__ );

require_once $satori_manifest_plugin . '/vendor/autoload.php';

define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $satori_manifest_plugin . '/vendor/yoast/phpunit-polyfills' );

$satori_manifest_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
$satori_manifest_tests_dir = $satori_manifest_tests_dir ? $satori_manifest_tests_dir : $satori_manifest_plugin . '/vendor/wp-phpunit/wp-phpunit';

require_once $satori_manifest_tests_dir . '/includes/functions.php';

/**
 * Loads the plugin before WordPress finishes booting.
 *
 * @return void
 */
function satori_manifest_tests_load_plugin(): void {
	require dirname( __DIR__ ) . '/satori-manifest.php';
}
tests_add_filter( 'muplugins_loaded', 'satori_manifest_tests_load_plugin' );

require $satori_manifest_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/support/class-test-case.php';
