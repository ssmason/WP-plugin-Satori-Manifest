<?php
/**
 * The assistant's HTTP endpoint (MCP Streamable HTTP, JSON responses).
 *
 *   POST /wp-json/satori-manifest/v1/mcp/{key}           key in the address
 *   POST /wp-json/satori-manifest/v1/mcp                 Authorization: Bearer {key}
 *
 * The key in the address is what most AI apps need (one link to paste) and
 * survives hosts that strip the Authorization header. Each request is
 * stateless: the key decides the client, Access decides whether they may
 * use it, and the request runs as that client with only the assistant's
 * tools available. GET and DELETE (server streams, sessions) are not offered.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Endpoint
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Endpoint {

	/**
	 * REST namespace.
	 */
	public const REST_NAMESPACE = 'satori-manifest/v1';

	/**
	 * REST route.
	 */
	public const ROUTE = '/mcp';

	/**
	 * Largest request body accepted (bytes).
	 */
	public const MAX_BODY = 1048576;

	/**
	 * Registers the routes. Hooked to 'rest_api_init'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return void
	 */
	public static function register_routes(): void {
		$args = array(
			'methods'             => array( 'POST', 'GET', 'DELETE' ),
			'callback'            => array( self::class, 'handle' ),
			// Authentication is the connection key, checked in handle() so failures get MCP-friendly answers.
			'permission_callback' => '__return_true',
		);

		register_rest_route( self::REST_NAMESPACE, self::ROUTE, $args );
		register_rest_route( self::REST_NAMESPACE, self::ROUTE . '/(?P<key>' . Tokens::PATTERN . ')', $args );
	}

	/**
	 * The connection address for a key.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string $key  Connection key.
	 * @return string
	 */
	public static function url( string $key = '' ): string {
		return rest_url( self::REST_NAMESPACE . self::ROUTE . ( '' !== $key ? '/' . $key : '' ) );
	}

	/**
	 * Handles a request.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  \WP_REST_Request $request  Request.
	 * @return \WP_REST_Response
	 */
	public static function handle( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! Assistant_Config::enabled() ) {
			return self::fail( 404, 'The AI assistant is not available on this site.' );
		}

		if ( 'POST' !== $request->get_method() ) {
			$response = self::fail( 405, 'Send MCP messages with POST.' );
			$response->header( 'Allow', 'POST' );

			return $response;
		}

		$user_id = Tokens::verify( self::key( $request ) );

		if ( ! $user_id ) {
			$response = self::fail( 401, 'This connection link is not valid. Create a new one in your dashboard under AI assistant.' );
			$response->header( 'WWW-Authenticate', 'Bearer realm="' . esc_attr( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) . '"' );

			return $response;
		}

		$context = Client_Context::for_user( $user_id );

		if ( null === $context || ! Access::allowed( $user_id ) ) {
			return self::fail( 403, 'The AI assistant is not switched on for this account. Please contact us to add it.' );
		}

		$wait = self::throttle( $user_id );

		if ( $wait > 0 ) {
			$response = self::fail( 429, 'Too many requests. Please wait a few minutes and try again.' );
			$response->header( 'Retry-After', (string) $wait );

			return $response;
		}

		$body = $request->get_body();

		if ( strlen( $body ) > self::MAX_BODY ) {
			return self::fail( 413, 'The request is too large.' );
		}

		// Run as the client, so WordPress's own rules (no scripts in content, own page only) apply too.
		wp_set_current_user( $user_id );

		$reply = Toolbox::server( $context )->handle( $body );

		return null === $reply ? new \WP_REST_Response( null, 202 ) : new \WP_REST_Response( $reply, 200 );
	}

	/**
	 * Sends assistant responses as-is (an empty body for 202).
	 *
	 * Hooked to 'rest_pre_serve_request'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  bool              $served   Whether the request has already been served.
	 * @param  \WP_HTTP_Response $result   Result to send.
	 * @param  \WP_REST_Request  $request  Request.
	 * @return bool
	 */
	public static function serve( bool $served, \WP_HTTP_Response $result, \WP_REST_Request $request ): bool {
		if ( $served || ! str_starts_with( $request->get_route(), '/' . self::REST_NAMESPACE . self::ROUTE ) ) {
			return $served;
		}

		if ( 202 === $result->get_status() ) {
			return true;
		}

		echo wp_json_encode( $result->get_data(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response body.

		return true;
	}

	/**
	 * The key from the address or the Authorization header.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  \WP_REST_Request $request  Request.
	 * @return string
	 */
	private static function key( \WP_REST_Request $request ): string {
		// Only the address counts, never a "key" field in the body or query string.
		$key = (string) ( $request->get_url_params()['key'] ?? '' );

		if ( '' !== $key ) {
			return $key;
		}

		$header = (string) $request->get_header( 'authorization' );

		return preg_match( '/^Bearer\s+(\S+)$/i', trim( $header ), $match ) ? $match[1] : '';
	}

	/**
	 * Counts a request; returns seconds to wait when over the limit, else 0.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int $user_id  Client user ID.
	 * @return int
	 */
	private static function throttle( int $user_id ): int {
		$key    = 'sm_ai_rate_' . $user_id;
		$window = Assistant_Config::rate_window();
		$now    = time();
		$state  = get_transient( $key );
		$state  = is_array( $state ) && $now - (int) $state['start'] < $window ? $state : array(
			'start' => $now,
			'count' => 0,
		);

		if ( (int) $state['count'] >= Assistant_Config::rate_requests() ) {
			return max( 1, $window - ( $now - (int) $state['start'] ) );
		}

		++$state['count'];
		set_transient( $key, $state, $window );

		return 0;
	}

	/**
	 * A plain error response (JSON-RPC shaped, so MCP clients can show it).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int    $status   HTTP status.
	 * @param  string $message  Message.
	 * @return \WP_REST_Response
	 */
	private static function fail( int $status, string $message ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'jsonrpc' => '2.0',
				'id'      => null,
				'error'   => array(
					'code'    => -32001,
					'message' => $message,
				),
			),
			$status
		);
	}
}
