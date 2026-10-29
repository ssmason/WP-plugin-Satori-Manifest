<?php
/**
 * Page title sync.
 *
 * Keeps a client page's title equal to the business name set under Branding,
 * so the browser tab and search results show the right name. The page slug
 * (and so the QR code address) never changes.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Branding;

use SatoriManifest\Clients\Client_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Page_Title_Sync
 *
 * Updates the page title after branding is saved.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Page_Title_Sync {

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'acf/save_post', array( self::class, 'sync' ), 20 );
	}

	/**
	 * Keeps the page title in step with the business name after a save.
	 *
	 * The page slug (and therefore the QR code URL) is never changed.
	 * Hooked to 'acf/save_post'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  int|string $post_id  Saved post ID.
	 * @return void
	 */
	public static function sync( $post_id ): void {
		$post_id = (int) $post_id;

		if ( ! Client_Repository::is_client_page( $post_id ) ) {
			return;
		}

		Brand::flush();
		$name = trim( (string) get_post_meta( $post_id, 'sm_brand_name', true ) );

		if ( '' !== $name && get_the_title( $post_id ) !== $name ) {
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => $name,
				)
			);
		}
	}
}
