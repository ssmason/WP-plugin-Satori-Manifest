<?php
/**
 * Contextual help tabs.
 *
 * Adds a short "Help" tab (WordPress's own Help drop-down, top right) to each
 * screen a client uses, plus a link to the full Help screen. Out of the way
 * until needed, so the screens themselves stay uncluttered.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Help;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Branding\Portal_Brand;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Manifest\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Help_Tabs
 *
 * Maps the current admin screen to its help entry.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Help_Tabs {

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'current_screen', array( self::class, 'add' ), 20 );
	}

	/**
	 * Adds the help tab and sidebar to the current screen.
	 *
	 * Hooked to 'current_screen'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  \WP_Screen $screen  Current screen.
	 * @return void
	 */
	public static function add( \WP_Screen $screen ): void {
		$key = self::key_for( $screen );
		$tab = '' !== $key ? Help_Content::tab( $key ) : null;

		if ( ! $tab ) {
			return;
		}

		$screen->add_help_tab(
			array(
				'id'      => 'satori-manifest-help',
				'title'   => $tab['title'],
				'content' => '<p>' . esc_html( $tab['text'] ) . '</p>',
			)
		);

		$support = Portal_Brand::support_email();

		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'Need more?', 'satori-manifest' ) . '</strong></p>'
			. '<p><a href="' . esc_url( Admin_Menu::url( Admin_Menu::HELP_SLUG ) ) . '">' . esc_html__( 'Getting started guide', 'satori-manifest' ) . '</a></p>'
			. '<p><a href="' . esc_url( 'mailto:' . $support ) . '">' . esc_html( $support ) . '</a></p>'
		);
	}

	/**
	 * Returns the help key for a screen, or '' when it has none.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  \WP_Screen $screen  Screen.
	 * @return string
	 */
	public static function key_for( \WP_Screen $screen ): string {
		$by_hook = array(
			Admin_Menu::hook( Admin_Menu::HOME_SLUG )     => 'home',
			Admin_Menu::hook( Admin_Menu::DESIGN_SLUG )   => 'design',
			Admin_Menu::hook( Admin_Menu::BRANDING_SLUG ) => 'branding',
			Admin_Menu::hook( Admin_Menu::BILLING_SLUG )  => 'billing',
		);

		unset( $by_hook[''] );

		if ( isset( $by_hook[ $screen->id ] ) ) {
			return $by_hook[ $screen->id ];
		}

		if ( 'nav-menus' === $screen->base && Client_Role::is_client() ) {
			return 'menus';
		}

		if ( 'post' === $screen->base && Post_Types::CPT_MANIFEST === $screen->post_type ) {
			return 'prices';
		}

		if ( 'post' === $screen->base && 'page' === $screen->post_type ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only lookup.
			$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
			return $post_id && Client_Repository::is_client_page( $post_id ) ? 'page' : '';
		}

		return '';
	}
}
