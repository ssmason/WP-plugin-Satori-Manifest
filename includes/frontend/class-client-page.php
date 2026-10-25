<?php
/**
 * Client page rendering.
 *
 * Client pages are rendered by the plugin's own full-page template
 * (templates/client-page.php) rather than the active theme, so a client's
 * page looks the same whatever theme the site runs. Theme stylesheets and
 * scripts are removed on these pages and replaced by the base page styles,
 * the selected design's stylesheet, web fonts and the brand variables.
 *
 * Users who can edit a page may preview another design by appending
 * ?sm_design={slug} to its URL (used by the Design screen previews).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client_Page
 *
 * Template routing and asset loading for client pages.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Client_Page {

	/**
	 * Page template identifier stored in _wp_page_template.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	public const TEMPLATE = 'satori-manifest-client-page';

	/**
	 * Query var used for design previews.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	public const PREVIEW_VAR = 'sm_design';

	/**
	 * Registers front-end hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'theme_page_templates', array( self::class, 'add_template' ) );
		add_filter( 'template_include', array( self::class, 'template_include' ), 999 );
		add_filter( 'body_class', array( self::class, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ), 20 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'dequeue_theme_assets' ), 999 );
		add_filter( 'show_admin_bar', array( self::class, 'hide_admin_bar_in_preview' ) );
		add_filter( 'document_title_parts', array( self::class, 'document_title' ) );
	}

	/**
	 * Adds the client page template to the page template list.
	 *
	 * Hooked to 'theme_page_templates'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  array<string,string> $templates  Template file => label.
	 * @return array<string,string>
	 */
	public static function add_template( array $templates ): array {
		$templates[ self::TEMPLATE ] = __( 'Manifest client page', 'satori-manifest' );
		return $templates;
	}

	/**
	 * Returns the current client page ID on the front end, or 0.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return int
	 */
	public static function current_page_id(): int {
		if ( is_admin() || ! is_singular( 'page' ) ) {
			return 0;
		}

		$page_id = (int) get_queried_object_id();

		return Client_Repository::is_client_page( $page_id ) ? $page_id : 0;
	}

	/**
	 * Returns the design to render: a permitted preview, or the page's design.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int $page_id  Client page ID.
	 * @return string
	 */
	public static function design_for_request( int $page_id ): string {
		// Preview is a read-only view switch restricted to editors of the page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$preview = isset( $_GET[ self::PREVIEW_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::PREVIEW_VAR ] ) ) : '';

		if ( '' !== $preview && Design_Registry::exists( $preview ) && current_user_can( 'edit_post', $page_id ) ) {
			return $preview;
		}

		return Client_Repository::design_for_page( $page_id );
	}

	/**
	 * Routes client pages to the plugin template.
	 *
	 * Hooked late to 'template_include' so it wins over theme routers.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $template  Template chosen so far.
	 * @return string
	 */
	public static function template_include( string $template ): string {
		return self::current_page_id() ? SATORI_MANIFEST_PATH . 'templates/client-page.php' : $template;
	}

	/**
	 * Adds design classes to the body of client pages.
	 *
	 * Hooked to 'body_class'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  array<int,string> $classes  Body classes.
	 * @return array<int,string>
	 */
	public static function body_class( array $classes ): array {
		$page_id = self::current_page_id();

		if ( $page_id ) {
			$brand     = Brand::for_page( $page_id, self::design_for_request( $page_id ) );
			$classes[] = 'sm-client-page';
			$classes[] = 'sm-design-' . $brand['design'];
			$classes[] = 'sm-header-' . $brand['header']['layout'];
		}

		return $classes;
	}

	/**
	 * Enqueues the page, design and font styles plus brand variables.
	 *
	 * Hooked to 'wp_enqueue_scripts'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function enqueue(): void {
		$page_id = self::current_page_id();

		if ( ! $page_id ) {
			return;
		}

		$brand  = Brand::for_page( $page_id, self::design_for_request( $page_id ) );
		$design = (array) Design_Registry::get( $brand['design'] );

		Assets::enqueue_style( 'satori-manifest-client-page', 'build/css/client-page.css' );

		if ( ! empty( $design['style_path'] ) && is_readable( $design['style_path'] ) ) {
			wp_enqueue_style(
				'satori-manifest-design',
				$design['style_url'],
				array( 'satori-manifest-client-page' ),
				(string) filemtime( $design['style_path'] )
			);
		}

		$fonts_url = Fonts::google_url( array_values( $brand['fonts'] ) );
		if ( '' !== $fonts_url ) {
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URLs must not carry a ver query.
			wp_enqueue_style( 'satori-manifest-fonts', $fonts_url, array(), null );
		}

		wp_add_inline_style(
			wp_style_is( 'satori-manifest-design', 'enqueued' ) ? 'satori-manifest-design' : 'satori-manifest-client-page',
			Brand::css_variables( $brand, 'body.sm-client-page' )
		);

		Assets::enqueue_script( 'satori-manifest-client-page', 'build/js/client-page.js' );
	}

	/**
	 * Removes theme stylesheets and scripts from client pages.
	 *
	 * Client pages are self-contained; theme CSS (resets, utility classes,
	 * typography) would otherwise leak into the design.
	 * Hooked late to 'wp_enqueue_scripts'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function dequeue_theme_assets(): void {
		if ( ! self::current_page_id() ) {
			return;
		}

		Assets::dequeue_theme_assets();
	}

	/**
	 * Hides the admin bar inside design preview iframes.
	 *
	 * Hooked to 'show_admin_bar'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  bool $show  Whether to show the admin bar.
	 * @return bool
	 */
	public static function hide_admin_bar_in_preview( bool $show ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence check only.
		return isset( $_GET[ self::PREVIEW_VAR ] ) ? false : $show;
	}

	/**
	 * Uses the brand name as the document title on client pages.
	 *
	 * Hooked to 'document_title_parts'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  array<string,string> $parts  Title parts.
	 * @return array<string,string>
	 */
	public static function document_title( array $parts ): array {
		$page_id = self::current_page_id();

		if ( $page_id ) {
			$brand          = Brand::for_page( $page_id );
			$parts['title'] = $brand['name'];
			$parts['site']  = $brand['tagline'];
			unset( $parts['tagline'] );
			$parts = array_filter( $parts );
		}

		return $parts;
	}
}
