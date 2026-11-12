<?php
/**
 * Examples bar on demo pages.
 *
 * Demo client pages are the live examples linked from the marketing site.
 * On those pages only, a small bar along the bottom links to every other
 * example, so visitors can compare designs without going back. Designs come
 * first, then the full-page layouts (and any extra demo pages, such as the
 * complete Harbour Kitchen example).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Frontend;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Clients\Demo_Seeder;
use SatoriManifest\Designs\Design_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Example_Bar
 *
 * Renders the examples bar on demo client pages.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */
class Example_Bar {

	/**
	 * Registers the footer output.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_footer', array( self::class, 'render' ), 5 );
	}

	/**
	 * Prints the bar on demo pages (not when embedded).
	 *
	 * Hooked to 'wp_footer'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return void
	 */
	public static function render(): void {
		$page_id = Client_Page::current_page_id();

		if ( ! $page_id || Embed::is_active() || ! self::is_demo_page( $page_id ) ) {
			return;
		}

		/**
		 * Filters whether the examples bar shows on a demo page.
		 *
		 * @since 3.8.0
		 * @param bool $show     Whether to show the bar.
		 * @param int  $page_id  Demo page ID.
		 */
		if ( ! apply_filters( 'satori_manifest_show_example_bar', true, $page_id ) ) {
			return;
		}

		$satori_manifest_groups  = self::groups();
		$satori_manifest_current = $page_id;

		require SATORI_MANIFEST_PATH . 'templates/client-page/example-bar.php';
	}

	/**
	 * Whether the page belongs to a demo client.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  int $page_id  Client page ID.
	 * @return bool
	 */
	public static function is_demo_page( int $page_id ): bool {
		$user_id = Client_Repository::client_for_page( $page_id );

		return $user_id > 0 && (bool) get_user_meta( $user_id, Demo_Seeder::DEMO_FLAG, true );
	}

	/**
	 * Demo pages grouped for the bar.
	 *
	 * One page per design (the earliest demo using it), in design order:
	 * simple designs under "Designs", full-page designs under "Full-page
	 * layouts". Remaining demo pages are added to the layouts group by title.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return array<string,array{label:string,links:array<int,array{id:int,label:string,url:string}>}>
	 */
	public static function groups(): array {
		$pages = array();

		foreach ( get_users(
			array(
				'meta_key' => Demo_Seeder::DEMO_FLAG, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- few demo users.
				'orderby'  => 'ID',
				'order'    => 'ASC',
				'fields'   => 'ID',
			)
		) as $user_id ) {
			$page_id = (int) Client_Repository::page_id( (int) $user_id );

			if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
				$pages[] = $page_id;
			}
		}

		$groups = array(
			'simple' => array(
				'label' => __( 'Designs', 'satori-manifest' ),
				'links' => array(),
			),
			'full'   => array(
				'label' => __( 'Full-page layouts', 'satori-manifest' ),
				'links' => array(),
			),
		);
		$used   = array();

		foreach ( Design_Registry::all() as $slug => $design ) {
			foreach ( $pages as $page_id ) {
				if ( ! in_array( $page_id, $used, true ) && Client_Repository::design_for_page( $page_id ) === $slug ) {
					$group                       = 'full' === ( $design['kind'] ?? '' ) ? 'full' : 'simple';
					$used[]                      = $page_id;
					$groups[ $group ]['links'][] = self::link( $page_id, (string) $design['title'] );
					break;
				}
			}
		}

		foreach ( array_diff( $pages, $used ) as $page_id ) {
			$groups['full']['links'][] = self::link( $page_id, get_the_title( $page_id ) );
		}

		return array_filter( $groups, static fn( array $group ): bool => array() !== $group['links'] );
	}

	/**
	 * One link row.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  int    $page_id  Page ID.
	 * @param  string $label    Link text.
	 * @return array{id:int,label:string,url:string}
	 */
	private static function link( int $page_id, string $label ): array {
		return array(
			'id'    => $page_id,
			'label' => $label,
			'url'   => (string) get_permalink( $page_id ),
		);
	}
}
