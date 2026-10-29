<?php
/**
 * Embed mode for client pages.
 *
 * A client can show their price list on their own website with an iframe
 * (see Clients\Embed_Code). With ?sm_embed=1 the client page drops its own
 * header and footer — the host site has its own — hides the admin bar, asks
 * search engines not to index the framed copy, and reports its height to the
 * host page so the frame grows to fit.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.1.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Frontend;

use SatoriManifest\Admin\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Embed
 *
 * Detects and prepares embedded (iframed) client pages.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.1.0
 */
class Embed {

	/**
	 * Query var that switches a client page to embed mode.
	 *
	 * @since 3.1.0
	 * @var   string
	 */
	public const QUERY_VAR = 'sm_embed';

	/**
	 * Message type posted to the host page with the content height.
	 *
	 * @since 3.1.0
	 * @var   string
	 */
	public const MESSAGE_TYPE = 'satori-manifest:height';

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.1.0
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'body_class', array( self::class, 'body_class' ) );
		add_filter( 'show_admin_bar', array( self::class, 'hide_admin_bar' ) );
		add_filter( 'wp_robots', array( self::class, 'robots' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ), 30 );
	}

	/**
	 * Returns true when the current request is an embedded client page.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.1.0
	 * @return bool
	 */
	public static function is_active(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display mode flag only.
		return isset( $_GET[ self::QUERY_VAR ] ) && Client_Page::current_page_id() > 0;
	}

	/**
	 * Returns the embed URL for a client page.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.1.0
	 * @param  int $page_id  Client page ID.
	 * @return string
	 */
	public static function url( int $page_id ): string {
		return add_query_arg( self::QUERY_VAR, '1', (string) get_permalink( $page_id ) );
	}

	/**
	 * Adds the sm-embed body class. Hooked to 'body_class'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.1.0
	 * @param  array<int,string> $classes  Body classes.
	 * @return array<int,string>
	 */
	public static function body_class( array $classes ): array {
		if ( self::is_active() ) {
			$classes[] = 'sm-embed';
		}

		return $classes;
	}

	/**
	 * Hides the admin bar inside the frame. Hooked to 'show_admin_bar'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.1.0
	 * @param  bool $show  Whether to show the admin bar.
	 * @return bool
	 */
	public static function hide_admin_bar( bool $show ): bool {
		// Checked directly: current_page_id() needs the main query, which is not ready this early.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence check only.
		return isset( $_GET[ self::QUERY_VAR ] ) ? false : $show;
	}

	/**
	 * Keeps the framed copy out of search results (the normal page is indexed).
	 *
	 * Hooked to 'wp_robots'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.1.0
	 * @param  array<string,bool|string> $robots  Robots directives.
	 * @return array<string,bool|string>
	 */
	public static function robots( array $robots ): array {
		if ( self::is_active() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}

		return $robots;
	}

	/**
	 * Loads the height reporter in embed mode. Hooked to 'wp_enqueue_scripts'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.1.0
	 * @return void
	 */
	public static function enqueue(): void {
		if ( ! self::is_active() ) {
			return;
		}

		Assets::enqueue_script( 'satori-manifest-embed', 'build/js/embed.js' );
		wp_add_inline_script( 'satori-manifest-embed', 'window.satoriManifestEmbed = ' . wp_json_encode( array( 'type' => self::MESSAGE_TYPE ) ) . ';', 'before' );
	}
}
