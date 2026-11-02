<?php
/**
 * Visitor IP address.
 *
 * Used for login throttling, the registration rate limit and de-duplicating
 * page views. Behind Cloudflare or another proxy every request appears to
 * come from the proxy, so Manifest → Settings → "Site is behind" tells this
 * class which header holds the real visitor address. Only choose a proxy
 * that really sits in front of the site: the header can be faked otherwise.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Hardening;

use SatoriManifest\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client_Ip
 *
 * Resolves the visitor's IP address.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */
class Client_Ip {

	/**
	 * Proxy choices and the request header each one sets.
	 *
	 * @since 3.3.0
	 * @var   array<string,string>
	 */
	public const PROXY_HEADERS = array(
		'none'       => 'REMOTE_ADDR',
		'cloudflare' => 'HTTP_CF_CONNECTING_IP',
		'forwarded'  => 'HTTP_X_FORWARDED_FOR',
	);

	/**
	 * Returns the visitor's IP address ('' when unknown).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @return string
	 */
	public static function get(): string {
		$proxy  = (string) Settings::get( 'proxy' );
		$header = self::PROXY_HEADERS[ $proxy ] ?? 'REMOTE_ADDR';
		$value  = isset( $_SERVER[ $header ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) : '';

		// X-Forwarded-For can list several hops; the first is the visitor.
		$ip = trim( explode( ',', $value )[0] );

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		}

		/**
		 * Filters the visitor IP address.
		 *
		 * @since 3.2.0
		 * @param string $ip  Resolved address.
		 */
		return (string) apply_filters( 'satori_manifest_client_ip', $ip );
	}

	/**
	 * Returns a salted hash of the visitor IP, for keys that must not store IPs.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  string $context  What the hash is for (keeps hashes unrelated across features).
	 * @return string
	 */
	public static function hash( string $context ): string {
		return md5( $context . '|' . self::get() . '|' . wp_salt( 'auth' ) );
	}
}
