<?php
/**
 * Plain titles.
 *
 * WordPress stores "&" in a post title as "&amp;" when the person saving
 * cannot post unfiltered HTML — which includes clients, WP-CLI and Stripe
 * webhooks. wp-admin then shows "Fish &amp; Chips" in title fields. For
 * client pages and price lists this turns "&amp;" back into "&" on save.
 * Only that one entity is changed, so no markup can ever be re-introduced;
 * the rest of WordPress's filtering still applies.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.2.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Clients;

use SatoriManifest\Manifest\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plain_Titles
 *
 * Keeps ampersands readable in client titles.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.2.0
 */
class Plain_Titles {

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_insert_post_data', array( self::class, 'decode_ampersands' ), 20, 2 );
	}

	/**
	 * Replaces "&amp;" with "&" in the titles of pages and price lists.
	 *
	 * Pages are included even before they belong to a client, because
	 * provisioning creates the page first and links it afterwards.
	 * Hooked to 'wp_insert_post_data'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  array<string,mixed> $data     Slashed post data about to be saved.
	 * @param  array<string,mixed> $postarr  Raw post array.
	 * @return array<string,mixed>
	 */
	public static function decode_ampersands( array $data, array $postarr ): array {
		$type    = (string) ( $data['post_type'] ?? '' );
		$post_id = (int) ( $postarr['ID'] ?? 0 );

		$applies = Post_Types::CPT_MANIFEST === $type
			|| ( 'page' === $type && ( ! $post_id || Client_Repository::is_client_page( $post_id ) ) && ! empty( $data['post_author'] ) && Client_Role::is_client( (int) $data['post_author'] ) );

		if ( $applies && isset( $data['post_title'] ) ) {
			$data['post_title'] = str_replace( '&amp;', '&', (string) $data['post_title'] );
		}

		return $data;
	}
}
