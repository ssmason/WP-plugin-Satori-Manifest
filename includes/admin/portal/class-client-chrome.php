<?php
/**
 * Client admin chrome.
 *
 * Trims wp-admin for clients: the menu, admin bar, third-party notices and
 * footer, so the dashboard shows only what a client needs.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Admin\Portal;

use SatoriManifest\Branding\Portal_Brand;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Manifest\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client_Chrome
 *
 * Menu, admin bar, notices and footer for clients.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Client_Chrome {

	/**
	 * Registers hooks. Each callback checks the user is a client.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'admin_body_class', array( self::class, 'body_class' ) );
		add_action( 'admin_menu', array( self::class, 'trim_menu' ), 999 );
		add_filter( 'parent_file', array( self::class, 'highlight_menu' ) );
		add_action( 'admin_bar_menu', array( self::class, 'trim_admin_bar' ), 999 );
		add_action( 'in_admin_header', array( self::class, 'silence_notices' ), 999 );
		add_filter( 'admin_footer_text', array( self::class, 'footer_text' ) );
		add_filter( 'update_footer', array( self::class, 'footer_version' ), 20 );
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

		foreach ( array( 'wp-logo', 'site-name', 'comments', 'new-content', 'updates', 'customize', 'site-editor', 'search', 'edit', 'view', 'preview', 'wpseo-menu' ) as $node ) {
			$bar->remove_node( $node );
		}

		$bar->add_node(
			array(
				'id'    => 'sm-home',
				'title' => '<span class="ab-icon dashicons dashicons-store" aria-hidden="true"></span>' . esc_html__( 'My price list', 'satori-manifest' ),
				'href'  => Screen_Guard::home_url(),
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
			/* translators: %s: site name. */
			? esc_html( sprintf( __( '%s — your price list, your brand.', 'satori-manifest' ), Portal_Brand::name() ) )
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
}
