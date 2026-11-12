<?php
/**
 * Page cache helpers.
 *
 * The plugin works with or without a page cache. When one is installed
 * (Cache Enabler, WP Super Cache, W3 Total Cache, LiteSpeed Cache…), client
 * pages are cleared from it whenever their design, colours or branding change,
 * because those are saved as page settings rather than as a post update. Pages
 * holding the registration form are never cached: its nonce and spam check are
 * single-use.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Core;

use SatoriManifest\Clients\Client_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Page_Cache
 *
 * Clears client pages from common cache plugins and keeps forms out of them.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */
class Page_Cache {

	/**
	 * Registers the clear-on-change hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return void
	 */
	public static function register(): void {
		// Branding screens save fields with ACF, which does not update the post itself.
		add_action( 'acf/save_post', array( self::class, 'clear_if_client_page' ), 20 );
	}

	/**
	 * Clears a page from the cache when it is a client page.
	 *
	 * Hooked to 'acf/save_post'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  int|string $post_id  Post ID (ACF also passes strings such as "options").
	 * @return void
	 */
	public static function clear_if_client_page( $post_id ): void {
		if ( is_numeric( $post_id ) && Client_Repository::is_client_page( (int) $post_id ) ) {
			self::clear( (int) $post_id );
		}
	}

	/**
	 * Clears one page from whichever page cache is installed.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  int $page_id  Page ID.
	 * @return void
	 */
	public static function clear( int $page_id ): void {
		// Cache Enabler.
		do_action( 'cache_enabler_clear_page_cache_by_post', $page_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Cache Enabler's own hook.

		// LiteSpeed Cache.
		do_action( 'litespeed_purge_post', $page_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's own hook.

		// WP Super Cache.
		if ( function_exists( 'wp_cache_post_change' ) ) {
			wp_cache_post_change( $page_id );
		}

		// W3 Total Cache.
		if ( function_exists( 'w3tc_flush_post' ) ) {
			w3tc_flush_post( $page_id );
		}

		/**
		 * Fires after a client page has been cleared from the page cache, for other cache plugins.
		 *
		 * @since 3.8.0
		 * @param int $page_id  Page ID.
		 */
		do_action( 'satori_manifest_page_cache_cleared', $page_id );
	}

	/**
	 * Keeps the current page out of page caches (forms with single-use tokens).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return void
	 */
	public static function exclude_current_page(): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the standard flag cache plugins read.
		}

		add_filter( 'cache_enabler_bypass_cache', '__return_true' );
	}
}
