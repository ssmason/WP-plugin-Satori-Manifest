<?php
/**
 * Slimmed-down wp-admin for clients.
 *
 * The UI side of client isolation (server-side rules live in
 * Access_Control). Clients get an allow-list of screens — everything else
 * redirects to their Home screen — a trimmed admin bar, no update or
 * third-party notices, and a block editor limited to blocks that cannot
 * reach other clients' content.
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
 * Class Admin_Lockdown
 *
 * Restricts the admin experience for client users.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Admin_Lockdown {

	/**
	 * Admin screen bases clients may open (plugin screens are added separately).
	 *
	 * @since 2.0.0
	 * @var   array<int,string>
	 */
	private const ALLOWED_BASES = array( 'post', 'upload', 'media', 'profile', 'nav-menus', 'async-upload' );

	/**
	 * Blocks that could expose or edit content outside the client's page.
	 *
	 * @since 2.0.0
	 * @var   array<int,string>
	 */
	private const DISALLOWED_BLOCKS = array(
		'core/archives',
		'core/block',
		'core/calendar',
		'core/categories',
		'core/comments',
		'core/latest-comments',
		'core/latest-posts',
		'core/loginout',
		'core/navigation',
		'core/page-list',
		'core/pattern',
		'core/post-author',
		'core/post-comments-form',
		'core/post-navigation-link',
		'core/query',
		'core/rss',
		'core/search',
		'core/shortcode',
		'core/html',
		'core/tag-cloud',
		'core/template-part',
		'satori-manifest/registration-form',
	);

	/**
	 * Registers lockdown hooks. Each callback checks the user is a client.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'trim_menu' ), 999 );
		add_action( 'current_screen', array( self::class, 'guard_screen' ) );
		add_filter( 'parent_file', array( self::class, 'highlight_menu' ) );
		add_action( 'admin_bar_menu', array( self::class, 'trim_admin_bar' ), 999 );
		add_action( 'in_admin_header', array( self::class, 'silence_notices' ), 999 );
		add_filter( 'login_redirect', array( self::class, 'login_redirect' ), 10, 3 );
		add_filter( 'admin_footer_text', array( self::class, 'footer_text' ) );
		add_filter( 'update_footer', array( self::class, 'footer_version' ), 20 );
		add_filter( 'allowed_block_types_all', array( self::class, 'allowed_blocks' ), 20 );
		add_filter( 'block_editor_settings_all', array( self::class, 'editor_settings' ), 20 );
		add_filter( 'should_load_remote_block_patterns', array( self::class, 'remote_patterns' ) );
		add_action( 'add_meta_boxes', array( self::class, 'trim_meta_boxes' ), 999 );
		add_action( 'admin_init', array( self::class, 'trim_post_type_support' ) );
		add_filter( 'admin_body_class', array( self::class, 'body_class' ) );
	}

	/**
	 * Adds a body class so admin CSS can tidy client-only chrome.
	 *
	 * Hooked to 'admin_body_class'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $classes  Space-separated body classes.
	 * @return string
	 */
	public static function body_class( $classes ): string {
		return Client_Role::is_client() ? $classes . ' is-satori-client' : (string) $classes;
	}

	/**
	 * Returns the client Home screen URL.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return string
	 */
	public static function home_url(): string {
		return admin_url( 'admin.php?page=' . Admin_Menu::HOME_SLUG );
	}

	/**
	 * Removes core menus clients do not need.
	 *
	 * Hooked late to 'admin_menu'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function trim_menu(): void {
		if ( ! Client_Role::is_client() ) {
			return;
		}

		foreach ( array( 'index.php', 'edit.php', 'edit.php?post_type=page', 'edit-comments.php', 'tools.php', 'edit.php?post_type=' . Post_Types::CPT_MANIFEST ) as $menu ) {
			remove_menu_page( $menu );
		}

		global $submenu;

		// Appearance: keep only Menus.
		foreach ( (array) ( $submenu['themes.php'] ?? array() ) as $item ) {
			if ( 'nav-menus.php' !== ( $item[2] ?? '' ) ) {
				remove_submenu_page( 'themes.php', (string) $item[2] );
			}
		}
	}

	/**
	 * Redirects clients away from any screen not on the allow-list.
	 *
	 * Post edit screens are allowed only for the client's own page and
	 * manifest; core capability checks also enforce this.
	 * Hooked to 'current_screen'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  \WP_Screen $screen  Current screen.
	 * @return void
	 */
	public static function guard_screen( \WP_Screen $screen ): void {
		if ( ! Client_Role::is_client() ) {
			return;
		}

		$plugin_screens = array_filter(
			array(
				Admin_Menu::hook( Admin_Menu::HOME_SLUG ),
				Admin_Menu::hook( Admin_Menu::DESIGN_SLUG ),
				Admin_Menu::hook( Admin_Menu::BRANDING_SLUG ),
			)
		);

		if ( in_array( $screen->id, $plugin_screens, true ) ) {
			return;
		}

		if ( in_array( $screen->base, self::ALLOWED_BASES, true ) ) {
			if ( 'post' !== $screen->base || self::is_own_post_screen( $screen ) ) {
				return;
			}
		}

		wp_safe_redirect( self::home_url() );
		exit;
	}

	/**
	 * Highlights the right client menu item on the page/manifest edit screens.
	 *
	 * Hooked to 'parent_file'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $parent_file  Parent menu slug.
	 * @return string
	 */
	public static function highlight_menu( string $parent_file ): string {
		$screen = get_current_screen();

		if ( ! Client_Role::is_client() || ! $screen instanceof \WP_Screen || 'post' !== $screen->base ) {
			return $parent_file;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only menu highlighting.
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		return $post_id ? 'post.php?post=' . $post_id . '&action=edit' : $parent_file;
	}

	/**
	 * Trims the admin bar for clients and adds a "View my page" link.
	 *
	 * Hooked late to 'admin_bar_menu'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  \WP_Admin_Bar $bar  Admin bar.
	 * @return void
	 */
	public static function trim_admin_bar( \WP_Admin_Bar $bar ): void {
		if ( ! Client_Role::is_client() ) {
			return;
		}

		foreach ( array( 'wp-logo', 'site-name', 'comments', 'new-content', 'updates', 'customize', 'site-editor', 'search', 'edit', 'view', 'preview' ) as $node ) {
			$bar->remove_node( $node );
		}

		$bar->add_node(
			array(
				'id'    => 'sm-home',
				'title' => '<span class="ab-icon dashicons dashicons-store" aria-hidden="true"></span>' . esc_html__( 'My price list', 'satori-manifest' ),
				'href'  => self::home_url(),
			)
		);

		$page_id = Client_Repository::page_id( get_current_user_id() );

		if ( $page_id ) {
			$bar->add_node(
				array(
					'id'    => 'sm-view-page',
					'title' => esc_html__( 'View page', 'satori-manifest' ),
					'href'  => get_permalink( $page_id ),
					'meta'  => array( 'target' => '_blank' ),
				)
			);
		}
	}

	/**
	 * Removes update nags and third-party notices for clients.
	 *
	 * Plugin screens render their own notices inside the page body.
	 * Hooked late to 'in_admin_header' (just before notices print).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function silence_notices(): void {
		if ( Client_Role::is_client() ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
			remove_all_actions( 'network_admin_notices' );
		}
	}

	/**
	 * Sends clients to their Home screen after logging in.
	 *
	 * Hooked to 'login_redirect'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string             $redirect_to  Requested redirect.
	 * @param  string             $requested    Original requested redirect.
	 * @param  \WP_User|\WP_Error $user         Logged-in user or error.
	 * @return string
	 */
	public static function login_redirect( string $redirect_to, string $requested, $user ): string {
		if ( $user instanceof \WP_User && Client_Role::is_client( $user->ID ) ) {
			return self::home_url();
		}

		return $redirect_to;
	}

	/**
	 * Replaces the admin footer text for clients.
	 *
	 * Hooked to 'admin_footer_text'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $text  Footer text.
	 * @return string
	 */
	public static function footer_text( $text ): string {
		return Client_Role::is_client()
			? esc_html__( 'Satori Manifest — your price list, your brand.', 'satori-manifest' )
			: (string) $text;
	}

	/**
	 * Hides the WordPress version in the footer for clients.
	 *
	 * Hooked to 'update_footer'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $text  Footer version text.
	 * @return string
	 */
	public static function footer_version( $text ): string {
		return Client_Role::is_client() ? '' : (string) $text;
	}

	/**
	 * Removes blocks that could reach content beyond the client's page.
	 *
	 * Hooked to 'allowed_block_types_all'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  bool|array<int,string> $allowed  Allowed block names, or true for all.
	 * @return bool|array<int,string>
	 */
	public static function allowed_blocks( $allowed ) {
		if ( ! Client_Role::is_client() ) {
			return $allowed;
		}

		$names = is_array( $allowed ) ? $allowed : array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() );

		return array_values(
			array_filter(
				$names,
				static fn( string $name ): bool => ! in_array( $name, self::DISALLOWED_BLOCKS, true ) && ! str_starts_with( $name, 'core/post-' ) && ! str_starts_with( $name, 'core/query-' ) && ! str_starts_with( $name, 'core/comment' )
			)
		);
	}

	/**
	 * Tightens block editor settings for clients.
	 *
	 * Disables the code editor (raw HTML) and limits the pattern inserter to
	 * the plugin's design patterns.
	 * Hooked to 'block_editor_settings_all'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  array<string,mixed> $settings  Editor settings.
	 * @return array<string,mixed>
	 */
	public static function editor_settings( array $settings ): array {
		if ( ! Client_Role::is_client() ) {
			return $settings;
		}

		$settings['codeEditingEnabled'] = false;

		if ( isset( $settings['__experimentalBlockPatterns'] ) && is_array( $settings['__experimentalBlockPatterns'] ) ) {
			$settings['__experimentalBlockPatterns'] = array_values(
				array_filter(
					$settings['__experimentalBlockPatterns'],
					static fn( $pattern ): bool => is_array( $pattern ) && in_array( Patterns::CATEGORY, (array) ( $pattern['categories'] ?? array() ), true )
				)
			);
		}

		return $settings;
	}

	/**
	 * Disables remote pattern directory patterns for clients.
	 *
	 * Hooked to 'should_load_remote_block_patterns'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  bool $load  Whether to load remote patterns.
	 * @return bool
	 */
	public static function remote_patterns( bool $load ): bool {
		return Client_Role::is_client() ? false : $load;
	}

	/**
	 * Removes meta boxes clients do not need on their page and manifest.
	 *
	 * Hooked late to 'add_meta_boxes'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function trim_meta_boxes(): void {
		if ( ! Client_Role::is_client() ) {
			return;
		}

		foreach ( array( 'page', Post_Types::CPT_MANIFEST ) as $post_type ) {
			foreach ( array( 'postcustom', 'slugdiv', 'authordiv', 'commentsdiv', 'commentstatusdiv', 'revisionsdiv' ) as $box ) {
				remove_meta_box( $box, $post_type, 'normal' );
				remove_meta_box( $box, $post_type, 'side' );
				remove_meta_box( $box, $post_type, 'advanced' );
			}
		}
	}

	/**
	 * Removes comment and author panels from the page editor for clients.
	 *
	 * Hooked to 'admin_init'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function trim_post_type_support(): void {
		if ( ! Client_Role::is_client() ) {
			return;
		}

		foreach ( array( 'comments', 'trackbacks', 'author', 'page-attributes' ) as $feature ) {
			remove_post_type_support( 'page', $feature );
		}
	}

	/**
	 * Returns true when a post screen is for the client's own page or manifest.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  \WP_Screen $screen  Current screen.
	 * @return bool
	 */
	private static function is_own_post_screen( \WP_Screen $screen ): bool {
		// phpcs:disable WordPress.Security.NonceVerification
		$post_id = 0;
		if ( isset( $_GET['post'] ) ) {
			$post_id = absint( $_GET['post'] );
		} elseif ( isset( $_POST['post_ID'] ) ) {
			$post_id = absint( $_POST['post_ID'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification

		if ( ! $post_id || ! in_array( $screen->post_type, array( 'page', Post_Types::CPT_MANIFEST ), true ) ) {
			return false;
		}

		$user_id = get_current_user_id();

		return in_array( $post_id, array( Client_Repository::page_id( $user_id ), Client_Repository::manifest_id( $user_id ) ), true );
	}
}
