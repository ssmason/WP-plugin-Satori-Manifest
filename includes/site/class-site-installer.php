<?php
/**
 * Public site installer.
 *
 * Creates the Home and Register site pages from the block markup in
 * templates/site-content/, sets Home as the static front page, and records
 * the page IDs. Idempotent: existing pages are kept unless $reset is true
 * (which rewrites their content; WordPress keeps the old version as a
 * revision).
 *
 * Run with: wp satori-manifest site install [--reset]
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
 * Class Site_Installer
 *
 * Creates and locates the public site pages.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */
class Site_Installer {

	/**
	 * Site pages: key => [ option name, title, slug, content file ].
	 *
	 * @since 2.1.0
	 * @var   array<string,array{0:string,1:string,2:string,3:string}>
	 */
	private const PAGES = array(
		'home'     => array( 'satori_manifest_home_page_id', 'Home', 'home', 'home.html' ),
		'register' => array( 'satori_manifest_register_page_id', 'Register', 'register', 'register.html' ),
	);

	/**
	 * Creates (or refreshes) the site pages and sets the front page.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  bool $reset  Rewrite existing page content from the templates.
	 * @return array<string,int>  Page IDs keyed by page key.
	 */
	public static function install( bool $reset = false ): array {
		$ids = array();

		// Register first so Home can link to it.
		foreach ( array( 'register', 'home' ) as $key ) {
			$ids[ $key ] = self::ensure_page( $key, $reset );
		}

		if ( $ids['home'] ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $ids['home'] );
		}

		return $ids;
	}

	/**
	 * Returns a site page ID, or 0.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  string $key  "home" or "register".
	 * @return int
	 */
	public static function page_id( string $key ): int {
		if ( ! isset( self::PAGES[ $key ] ) ) {
			return 0;
		}

		$id = (int) get_option( self::PAGES[ $key ][0] );

		return ( $id && 'page' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) ? $id : 0;
	}

	/**
	 * Returns the Register page URL, or '' before installation.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return string
	 */
	public static function register_url(): string {
		$id = self::page_id( 'register' );
		return $id ? (string) get_permalink( $id ) : '';
	}

	/**
	 * Creates or refreshes one site page.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  string $key    Page key.
	 * @param  bool   $reset  Rewrite content when the page exists.
	 * @return int  Page ID.
	 */
	private static function ensure_page( string $key, bool $reset ): int {
		list( $option, $title, $slug, $file ) = self::PAGES[ $key ];

		$existing = self::page_id( $key );

		if ( $existing && ! $reset ) {
			return $existing;
		}

		$postarr = array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => wp_slash( self::content( $file ) ),
			'meta_input'   => array( '_wp_page_template' => Site_Page::TEMPLATE ),
		);

		if ( $existing ) {
			$postarr['ID'] = $existing;
			$id            = wp_update_post( $postarr, true );
		} else {
			$postarr['post_name']   = $slug;
			$postarr['post_author'] = self::admin_author();
			$id                     = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $id ) ) {
			return 0;
		}

		update_option( $option, (int) $id );

		return (int) $id;
	}

	/**
	 * Builds page content from a template with links filled in.
	 *
	 * Placeholders: {{register_url}}, {{login_url}}, {{asset_url}} and
	 * {{example_{design}}} — a live client page using that design, or the
	 * #designs anchor when none exists yet.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  string $file  Content file name.
	 * @return string
	 */
	private static function content( string $file ): string {
		$path = SATORI_MANIFEST_PATH . 'templates/site-content/' . $file;

		if ( ! is_readable( $path ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local template file.
		$markup = (string) file_get_contents( $path );

		$replacements = array(
			'{{register_url}}' => esc_url( self::register_url() ),
			'{{login_url}}'    => esc_url( wp_login_url() ),
			'{{asset_url}}'    => esc_url( SATORI_MANIFEST_URL . 'assets/' ),
		);

		foreach ( array_keys( Design_Registry::all() ) as $slug ) {
			$replacements[ '{{example_' . $slug . '}}' ] = esc_url( self::example_url( $slug ) );
		}

		return strtr( $markup, $replacements );
	}

	/**
	 * Returns a published client page using the given design, or the #designs anchor.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  string $design  Design slug.
	 * @return string
	 */
	private static function example_url( string $design ): string {
		foreach ( Client_Repository::all_clients() as $client ) {
			$page_id = Client_Repository::page_id( $client->ID );

			if ( $page_id && 'publish' === get_post_status( $page_id ) && Client_Repository::design_for_page( $page_id ) === $design ) {
				return (string) get_permalink( $page_id );
			}
		}

		return '#designs';
	}

	/**
	 * Returns the first administrator's ID (site pages are owned by staff).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return int
	 */
	private static function admin_author(): int {
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);

		return (int) ( $admins[0] ?? 0 );
	}
}
