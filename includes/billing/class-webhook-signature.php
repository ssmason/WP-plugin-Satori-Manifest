<?php
/**
 * Stripe webhook signature check.
 *
 * Verifies the Stripe-Signature header (t=…,v1=…) against the raw request
 * body with the endpoint's signing secret, rejecting stale requests.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.7.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Billing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webhook_Signature
 *
 * Verifies that a webhook request really came from Stripe.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.7.0
 */
class Webhook_Signature {

	/**
	 * Seconds a signed request stays valid (Stripe's recommended tolerance).
	 *
	 * @since 3.7.0
	 * @var   int
	 */
	public const TOLERANCE = 300;

	/**
	 * Checks a Stripe-Signature header against the payload.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @param  string $payload  Raw request body.
	 * @param  string $header   Stripe-Signature header.
	 * @param  string $secret   Webhook signing secret.
	 * @param  int    $now      Current timestamp.
	 * @return bool
	 */
	public static function verify( string $payload, string $header, string $secret, int $now ): bool {
		if ( '' === $secret || '' === $header ) {
			return false;
		}

		$timestamp  = 0;
		$signatures = array();

		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );

			if ( 2 !== count( $pair ) ) {
				continue;
			}

			if ( 't' === $pair[0] ) {
				$timestamp = (int) $pair[1];
			} elseif ( 'v1' === $pair[0] ) {
				$signatures[] = $pair[1];
			}
		}

		if ( ! $timestamp || ! $signatures || abs( $now - $timestamp ) > self::TOLERANCE ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );

		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}

		return false;
	}
}
