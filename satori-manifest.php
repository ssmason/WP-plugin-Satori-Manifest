<?php
/**
 * Plugin Name: Satori Manifest
 * Plugin URI:  https://github.com/ssmason/WP-Plugin-manifest
 * Description: Hosted price-list pages for QR-code clients. Each client gets one branded page, a manifest of sections and items, five starter designs and a slimmed-down wp-admin. No external PHP dependencies.
 * Version:     3.2.0
 * Author:      Stephen Mason (Satori Digital)
 * Author URI:  https://satori-digital.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: satori-manifest
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.1
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'SATORI_MANIFEST_VERSION', '3.2.0' );
define( 'SATORI_MANIFEST_FILE', __FILE__ );
define( 'SATORI_MANIFEST_PATH', plugin_dir_path( __FILE__ ) );
define( 'SATORI_MANIFEST_URL', plugin_dir_url( __FILE__ ) );
define( 'SATORI_MANIFEST_SLUG', 'satori-manifest' );
define( 'SATORI_MANIFEST_PREFIX', 'satori_manifest_' );

/*
 * Autoloader.
 *
 * Each sub-namespace maps to a folder under includes/, WordPress-style:
 *
 *   SatoriManifest\Plugin                      → includes/class-plugin.php
 *   SatoriManifest\Billing\Stripe_Client       → includes/billing/class-stripe-client.php
 *   SatoriManifest\Admin\Screens\Help_Screen   → includes/admin/screens/class-help-screen.php
 *
 * Keeps the plugin free of Composer at runtime so it can be dropped into any
 * WordPress install.
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'SatoriManifest\\';

		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$parts  = explode( '\\', substr( $class_name, strlen( $prefix ) ) );
		$file   = 'class-' . strtolower( str_replace( '_', '-', (string) array_pop( $parts ) ) ) . '.php';
		$folder = strtolower( str_replace( '_', '-', implode( '/', $parts ) ) );
		$path   = SATORI_MANIFEST_PATH . 'includes/' . ( '' !== $folder ? $folder . '/' : '' ) . $file;

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

/**
 * Returns the main plugin instance.
 *
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 * @package SatoriManifest
 * @return  \SatoriManifest\Plugin
 */
function satori_manifest(): \SatoriManifest\Plugin {
	return \SatoriManifest\Plugin::instance();
}

// Activation hook.
register_activation_hook(
	__FILE__,
	function (): void {
		\SatoriManifest\Plugin::activate();
	}
);

// Deactivation hook.
register_deactivation_hook(
	__FILE__,
	function (): void {
		\SatoriManifest\Plugin::deactivate();
	}
);

// Boot.
satori_manifest();
