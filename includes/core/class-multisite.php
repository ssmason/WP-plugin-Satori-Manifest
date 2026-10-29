<?php
/**
 * Multisite helpers.
 *
 * Wraps WordPress option functions so the rest of the plugin is agnostic of
 * whether it is running on a standard single-site install or a network-activated
 * multisite environment.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Core;

use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Manifest\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Multisite
 *
 * Network-aware option helpers and per-site setup for multisite installs.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */
class Multisite {

	/**
	 * Returns true when the plugin is network-activated.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @return bool
	 */
	public static function is_network_active(): bool {
		if ( ! is_multisite() ) {
			return false;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		return is_plugin_active_for_network( plugin_basename( SATORI_MANIFEST_FILE ) );
	}

	/**
	 * Runs default setup for a newly created site on a multisite network.
	 *
	 * Hooked to wpmu_new_blog.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @param  int $blog_id  The new site ID.
	 * @return void
	 */
	public static function new_site_setup( int $blog_id ): void {
		if ( ! self::is_network_active() ) {
			return;
		}

		// Flush rewrite rules for the new site so the CPT slugs register correctly.
		switch_to_blog( $blog_id );
		flush_rewrite_rules();
		restore_current_blog();
	}

	/**
	 * Deletes all plugin data for every site on the network.
	 *
	 * Called from uninstall.php during a network uninstall.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @return void
	 */
	public static function network_uninstall(): void {
		$sites = get_sites( array( 'number' => 0 ) );

		foreach ( $sites as $site ) {
			switch_to_blog( (int) $site->blog_id );
			self::delete_site_data();
			restore_current_blog();
		}
	}

	/**
	 * Deletes all plugin CPT posts, meta, and options for the current site.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @return void
	 */
	public static function delete_site_data(): void {
		// Delete all manifest CPT posts.
		$posts = get_posts(
			array(
				'post_type'      => Post_Types::CPT_MANIFEST,
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- full list required for complete uninstall.
				'post_status'    => 'any',
				'fields'         => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}

		// Delete wp_block pattern posts seeded by plugin versions before 2.0.
		// Identified by _satori_manifest_seeded_pattern meta so the lookup is
		// locale-safe — translated titles are not relied upon here.
		$pattern_posts = get_posts(
			array(
				'post_type'              => 'wp_block',
				'post_status'            => 'any',
				'fields'                 => 'ids',
				'posts_per_page'         => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- full list required for complete uninstall.
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => '_satori_manifest_seeded_pattern', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- indexed meta on small wp_block table.
				'meta_value'             => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		foreach ( $pattern_posts as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}

		// Remove the client role and staff manifest capabilities. Client users,
		// pages and menus are deliberately kept — they are customer data.
		Client_Role::remove();

		// Delete plugin options.
		delete_option( 'satori_manifest_version' );
		delete_option( 'satori_manifest_patterns_seeded' );
	}
}
