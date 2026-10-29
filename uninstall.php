<?php
/**
 * Plugin uninstall routine.
 *
 * Runs when the plugin is deleted via the WordPress admin. Removes all CPT
 * posts, post meta, and plugin options. On multisite, cleans every site on
 * the network.
 *
 * This file is executed directly by WordPress — it must check WP_UNINSTALL_PLUGIN
 * before doing anything.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/manifest/class-post-types.php';
require_once __DIR__ . '/includes/clients/class-client-role.php';
require_once __DIR__ . '/includes/core/class-multisite.php';

if ( is_multisite() ) {
	SatoriManifest\Core\Multisite::network_uninstall();
} else {
	// delete_site_data() removes CPT posts, meta, and all plugin options
	// including satori_manifest_version and satori_manifest_patterns_seeded.
	SatoriManifest\Core\Multisite::delete_site_data();
}
