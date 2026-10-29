<?php
/**
 * Stripe API client.
 *
 * A minimal HTTP client for the handful of Stripe endpoints the plugin uses,
 * built on the WordPress HTTP API so the plugin needs no Stripe SDK.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Billing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Stripe_Client
 *
 * Sends form-encoded requests to api.stripe.com and decodes the JSON reply.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Stripe_Client {

	/**
	 * API base URL.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	private const API = 'https://api.stripe.com/v1/';

	/**
	 * Pinned API version, so Stripe changes never alter responses unexpectedly.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	private const API_VERSION = '2024-06-20';

	/**
	 * Calls the Stripe API.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string              $method  HTTP method (GET or POST).
	 * @param  string              $path    Endpoint path, e.g. "checkout/sessions".
	 * @param  array<string,mixed> $params  Parameters (nested arrays are encoded Stripe-style).
	 * @return array<string,mixed>|\WP_Error  Decoded response object or error.
	 */
	public static function request( string $method, string $path, array $params = array() ) {
		if ( ! Stripe_Settings::is_configured() ) {
			return new \WP_Error( 'satori_manifest_stripe_off', __( 'Stripe is not configured.', 'satori-manifest' ) );
		}

		$url  = self::API . ltrim( $path, '/' );
		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 20,
			'headers' => array(
				'Authorization'  => 'Bearer ' . Stripe_Settings::secret_key(),
				'Stripe-Version' => self::API_VERSION,
			),
		);

		if ( 'GET' === $args['method'] ) {
			$url = $params ? $url . '?' . http_build_query( $params ) : $url;
		} else {
			$args['body'] = http_build_query( $params );
		}

		/**
		 * Short-circuits a Stripe request (used by tests and local simulation).
		 *
		 * Return a decoded response array or a WP_Error to skip the HTTP call.
		 *
		 * @since 3.0.0
		 * @param array|\WP_Error|null $pre     Null to continue.
		 * @param string               $method  HTTP method.
		 * @param string               $path    Endpoint path.
		 * @param array<string,mixed>  $params  Parameters.
		 */
		$pre = apply_filters( 'satori_manifest_pre_stripe_request', null, $args['method'], $path, $params );

		if ( null !== $pre ) {
			return $pre;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 || ! is_array( $body ) ) {
			$message = is_array( $body ) ? (string) ( $body['error']['message'] ?? '' ) : '';
			return new \WP_Error( 'satori_manifest_stripe_error', '' !== $message ? $message : __( 'Stripe returned an unexpected response.', 'satori-manifest' ), array( 'status' => $code ) );
		}

		return $body;
	}

	/**
	 * Converts an amount in whole units to Stripe's smallest unit (cents).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  float $amount  Amount, e.g. 9.5.
	 * @return int  e.g. 950.
	 */
	public static function to_minor_units( float $amount ): int {
		return (int) round( $amount * 100 );
	}

	/**
	 * Converts Stripe's smallest unit back to whole units.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  int $minor  Amount in cents.
	 * @return float
	 */
	public static function from_minor_units( int $minor ): float {
		return $minor / 100;
	}
}
