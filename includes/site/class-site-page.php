<?php
/**
 * Public site pages (home, register).
 *
 * Pages using the "Satori site page" template are the business's own
 * marketing pages. Like client pages they are rendered by the plugin (not
 * the theme), with a site header/footer and the landing stylesheet. Their
 * content is ordinary blocks, editable by administrators only.
 *
 * Also labels pages in the admin Pages list ("Client page — Willow & Rose",
 * "Site page") so staff can see at a glance who owns what.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */

declare( strict_types=1 );

namespace SatoriManifest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Site_Page
 *
 * Template, assets and permissions for public site pages.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */
class Site_Page {

	/**
	 * Page template identifier.
	 *
	 * @since 2.1.0
	 * @var   string
	 */
	public const TEMPLATE = 'satori-manifest-site-page';

	/**
	 * Nav menu location for the site header.
	 *
	 * @since 2.1.0
	 * @var   string
	 */
	public const MENU_LOCATION = 'satori-manifest-site';

	/**
	 * Fonts used by the site pages (config/fonts.yaml slugs).
	 *
	 * @since 2.1.0
	 * @var   array<int,string>
	 */
	private const FONTS = array( 'fraunces', 'manrope' );

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'after_setup_theme', array( self::class, 'register_menu_location' ), 20 );
		add_filter( 'theme_page_templates', array( self::class, 'add_template' ) );
		add_filter( 'template_include', array( self::class, 'template_include' ), 999 );
		add_filter( 'body_class', array( self::class, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ), 20 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'dequeue_theme_assets' ), 999 );
		add_filter( 'map_meta_cap', array( self::class, 'admins_only' ), 10, 4 );
		add_filter( 'display_post_states', array( self::class, 'post_states' ), 10, 2 );
		add_action( 'enqueue_block_assets', array( self::class, 'enqueue_editor_canvas' ) );
	}

	/**
	 * Registers the site header menu location.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return void
	 */
	public static function register_menu_location(): void {
		register_nav_menus( array( self::MENU_LOCATION => __( 'Satori site header', 'satori-manifest' ) ) );
	}

	/**
	 * Adds the site page template to the template list.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  array<string,string> $templates  Templates.
	 * @return array<string,string>
	 */
	public static function add_template( array $templates ): array {
		$templates[ self::TEMPLATE ] = __( 'Satori site page', 'satori-manifest' );
		return $templates;
	}

	/**
	 * Returns true when the post is a site page.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  int $post_id  Post ID.
	 * @return bool
	 */
	public static function is_site_page( int $post_id ): bool {
		return $post_id > 0 && 'page' === get_post_type( $post_id ) && self::TEMPLATE === get_post_meta( $post_id, '_wp_page_template', true );
	}

	/**
	 * Returns the current front-end site page ID, or 0.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return int
	 */
	public static function current_page_id(): int {
		if ( is_admin() || ! is_singular( 'page' ) ) {
			return 0;
		}

		$page_id = (int) get_queried_object_id();

		return self::is_site_page( $page_id ) ? $page_id : 0;
	}

	/**
	 * Routes site pages to the plugin template.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  string $template  Template so far.
	 * @return string
	 */
	public static function template_include( string $template ): string {
		return self::current_page_id() ? SATORI_MANIFEST_PATH . 'templates/site-page.php' : $template;
	}

	/**
	 * Adds the site page body class.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  array<int,string> $classes  Body classes.
	 * @return array<int,string>
	 */
	public static function body_class( array $classes ): array {
		if ( self::current_page_id() ) {
			$classes[] = 'sm-site-page';
		}

		return $classes;
	}

	/**
	 * Enqueues the site stylesheet, fonts and script.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return void
	 */
	public static function enqueue(): void {
		if ( ! self::current_page_id() ) {
			return;
		}

		Assets::enqueue_style( 'satori-manifest-site', 'build/css/site.css' );

		$fonts_url = Fonts::google_url( self::FONTS );
		if ( '' !== $fonts_url ) {
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URLs must not carry a ver query.
			wp_enqueue_style( 'satori-manifest-fonts', $fonts_url, array(), null );
		}

		Assets::enqueue_script( 'satori-manifest-client-page', 'build/js/client-page.js' );
	}

	/**
	 * Removes theme assets from site pages (same approach as client pages).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return void
	 */
	public static function dequeue_theme_assets(): void {
		if ( ! self::current_page_id() ) {
			return;
		}

		Assets::dequeue_theme_assets();
	}

	/**
	 * Styles the editor canvas when editing a site page.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return void
	 */
	public static function enqueue_editor_canvas(): void {
		$post = is_admin() ? get_post() : null;

		if ( ! $post instanceof \WP_Post || ! self::is_site_page( $post->ID ) ) {
			return;
		}

		wp_register_style( 'satori-manifest-site-canvas', false, array(), SATORI_MANIFEST_VERSION );
		wp_enqueue_style( 'satori-manifest-site-canvas' );
		wp_add_inline_style( 'satori-manifest-site-canvas', Editor::scoped_css( SATORI_MANIFEST_PATH . 'build/css/site.css', 'sm-site-page' ) );

		$fonts_url = Fonts::google_url( self::FONTS );
		if ( '' !== $fonts_url ) {
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URLs must not carry a ver query.
			wp_enqueue_style( 'satori-manifest-site-fonts', $fonts_url, array(), null );
		}
	}

	/**
	 * Restricts editing and deleting site pages to administrators.
	 *
	 * Hooked to 'map_meta_cap'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  array<int,string> $caps     Required primitive caps.
	 * @param  string            $cap      Capability checked.
	 * @param  int               $user_id  User ID.
	 * @param  array<int,mixed>  $args     Arguments (post ID first).
	 * @return array<int,string>
	 */
	public static function admins_only( array $caps, string $cap, int $user_id, array $args ): array {
		if ( in_array( $cap, array( 'edit_post', 'delete_post' ), true ) && ! empty( $args[0] ) && self::is_site_page( (int) $args[0] ) ) {
			$caps[] = 'manage_options';
		}

		return $caps;
	}

	/**
	 * Labels client and site pages in the Pages list.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  array<string,string> $states  Post states.
	 * @param  \WP_Post             $post    Post.
	 * @return array<string,string>
	 */
	public static function post_states( array $states, \WP_Post $post ): array {
		if ( 'page' !== $post->post_type ) {
			return $states;
		}

		if ( self::is_site_page( $post->ID ) ) {
			$states['satori_site'] = __( 'Site page (admins only)', 'satori-manifest' );
		}

		$client_id = Client_Repository::client_for_page( $post->ID );

		if ( $client_id ) {
			$client = get_userdata( $client_id );
			$design = Design_Registry::get( Client_Repository::design_for_page( $post->ID ) );

			$states['satori_client'] = sprintf(
				/* translators: 1: client login, 2: design name. */
				__( 'Client page — %1$s · %2$s', 'satori-manifest' ),
				$client ? $client->user_login : '?',
				$design ? $design['title'] : ''
			);
		}

		return $states;
	}
}
