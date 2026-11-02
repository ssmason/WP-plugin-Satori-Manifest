<?php
/**
 * View counter.
 *
 * Client pages report each visit with a tiny background request
 * (POST /wp-json/satori-manifest/v1/view, sent by build/js/client-page.js),
 * so counting keeps working when a host caches the pages. Not counted:
 * the page's own client and staff (they would inflate the numbers while
 * editing), obvious bots, design previews, and repeat visits from the same
 * visitor to the same page within 30 minutes.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Stats;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Frontend\Client_Page;
use SatoriManifest\Hardening\Client_Ip;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class View_Counter
 *
 * REST endpoint and front-end configuration for visit counting.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */
class View_Counter {

	/**
	 * REST namespace.
	 *
	 * @since 3.3.0
	 * @var   string
	 */
	public const ROUTE_NAMESPACE = 'satori-manifest/v1';

	/**
	 * REST route.
	 *
	 * @since 3.3.0
	 * @var   string
	 */
	public const ROUTE = '/view';

	/**
	 * Seconds during which repeat visits by one visitor count once.
	 *
	 * @since 3.3.0
	 * @var   int
	 */
	private const REPEAT_WINDOW = 30 * MINUTE_IN_SECONDS;

	/**
	 * User agents that are never counted.
	 *
	 * @since 3.3.0
	 * @var   string
	 */
	private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|monitor|headless|lighthouse|facebookexternalhit|whatsapp/i';

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_route' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'configure' ), 25 );
	}

	/**
	 * Registers the REST route. Hooked to 'rest_api_init'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @return void
	 */
	public static function register_route(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'count' ),
				// Public by design: visitors are anonymous. Abuse is limited by the checks in count().
				'permission_callback' => '__return_true',
				'args'                => array(
					'page'   => array(
						'type'     => 'integer',
						'required' => true,
					),
					'source' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Records a visit. REST callback.
	 *
	 * Always answers 204, so the endpoint reveals nothing about pages.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  \WP_REST_Request $request  Request.
	 * @return \WP_REST_Response
	 */
	public static function count( \WP_REST_Request $request ): \WP_REST_Response {
		$page_id = absint( $request->get_param( 'page' ) );
		$agent   = (string) $request->get_header( 'user_agent' );

		if ( self::should_count( $page_id, $agent ) ) {
			$key = 'sm_view_' . md5( $page_id . '|' . Client_Ip::hash( 'views' ) );

			if ( false === get_transient( $key ) ) {
				set_transient( $key, 1, self::REPEAT_WINDOW );
				$source = sanitize_key( (string) $request->get_param( 'source' ) );
				Page_Views::record( $page_id, Page_Views::SOURCE_QR === $source );
			}
		}

		return new \WP_REST_Response( null, 204 );
	}

	/**
	 * Returns true for a published client page and a visitor that looks human.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int    $page_id  Page ID.
	 * @param  string $agent    User-Agent header.
	 * @return bool
	 */
	public static function should_count( int $page_id, string $agent ): bool {
		return $page_id > 0
			&& '' !== $agent
			&& ! preg_match( self::BOT_PATTERN, $agent )
			&& 'publish' === get_post_status( $page_id )
			&& Client_Repository::is_client_page( $page_id );
	}

	/**
	 * Tells the client page script where and whether to report the visit.
	 *
	 * Staff and the page's own client are skipped. Hooked to 'wp_enqueue_scripts'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @return void
	 */
	public static function configure(): void {
		$page_id = Client_Page::current_page_id();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence check for the design preview.
		if ( ! $page_id || isset( $_GET[ Client_Page::PREVIEW_VAR ] ) || current_user_can( 'edit_post', $page_id ) ) {
			return;
		}

		wp_add_inline_script(
			'satori-manifest-client-page',
			'window.satoriManifestStats = ' . wp_json_encode(
				array(
					'url'       => esc_url_raw( rest_url( self::ROUTE_NAMESPACE . self::ROUTE ) ),
					'page'      => $page_id,
					'sourceVar' => Page_Views::SOURCE_VAR,
				)
			) . ';',
			'before'
		);
	}
}
