<?php
/**
 * Client page template lock.
 *
 * A client page always uses the plugin's client page template: clients are
 * offered only that template, and a save that changes it is undone.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.7.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Clients;

use SatoriManifest\Frontend\Client_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Page_Template_Lock
 *
 * Keeps client pages on the client page template.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.7.0
 */
class Page_Template_Lock {

	/**
	 * Registers the hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'save_post_page', array( self::class, 'keep_page_template' ), 10, 2 );
		add_filter( 'theme_page_templates', array( self::class, 'limit_page_templates' ), 20 );
	}

	/**
	 * Re-applies the client page template if a client save changed it.
	 *
	 * Hooked to 'save_post_page'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int      $post_id  Page ID.
	 * @param  \WP_Post $post     Page object.
	 * @return void
	 */
	public static function keep_page_template( int $post_id, \WP_Post $post ): void {
		if ( 'revision' === $post->post_type || ! Client_Repository::is_client_page( $post_id ) ) {
			return;
		}

		if ( Client_Page::TEMPLATE !== get_post_meta( $post_id, '_wp_page_template', true ) ) {
			update_post_meta( $post_id, '_wp_page_template', Client_Page::TEMPLATE );
		}
	}

	/**
	 * Offers only the client page template to clients.
	 *
	 * Hooked to 'theme_page_templates'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  array<string,string> $templates  Template file => label.
	 * @return array<string,string>
	 */
	public static function limit_page_templates( array $templates ): array {
		if ( ! Client_Role::is_client() ) {
			return $templates;
		}

		return array_intersect_key( $templates, array( Client_Page::TEMPLATE => true ) );
	}
}
