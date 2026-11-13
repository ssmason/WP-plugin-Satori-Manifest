<?php
/**
 * Connection keys for the AI assistant.
 *
 * Each client has at most one key. It works only on the assistant endpoint -
 * it is not a WordPress password and cannot log in or reach any other API.
 * Only a keyed hash is stored; the key itself is shown once, when created.
 *
 * Format: smai_{user id}_{40 hex characters}.
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
 * Class Tokens
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Tokens {

	/**
	 * User meta: { hash, created, last_used }.
	 */
	public const META = '_sm_ai_token';

	/**
	 * Pattern of a well-formed key.
	 */
	public const PATTERN = 'smai_[0-9]+_[a-f0-9]{40}';

	/**
	 * Seconds between "last used" writes.
	 */
	private const TOUCH_INTERVAL = 300;

	/**
	 * Creates a new key for a client, replacing any old one.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int $user_id  Client user ID.
	 * @return string  The key - show it once, it cannot be read back.
	 */
	public static function issue( int $user_id ): string {
		$secret = bin2hex( random_bytes( 20 ) );

		update_user_meta(
			$user_id,
			self::META,
			array(
				'hash'      => self::hash( $user_id, $secret ),
				'created'   => time(),
				'last_used' => 0,
			)
		);

		return 'smai_' . $user_id . '_' . $secret;
	}

	/**
	 * Returns the client a key belongs to, or 0.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string $key  Presented key.
	 * @return int
	 */
	public static function verify( string $key ): int {
		if ( ! preg_match( '/^smai_([0-9]+)_([a-f0-9]{40})$/', $key, $parts ) ) {
			return 0;
		}

		$user_id = (int) $parts[1];
		$saved   = self::saved( $user_id );

		if ( null === $saved || ! hash_equals( $saved['hash'], self::hash( $user_id, $parts[2] ) ) ) {
			return 0;
		}

		if ( time() - $saved['last_used'] > self::TOUCH_INTERVAL ) {
			$saved['last_used'] = time();
			update_user_meta( $user_id, self::META, $saved );
		}

		return $user_id;
	}

	/**
	 * Deletes a client's key (disconnects every AI app using it).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int $user_id  Client user ID.
	 * @return void
	 */
	public static function revoke( int $user_id ): void {
		delete_user_meta( $user_id, self::META );
	}

	/**
	 * Key details for display (never the key), or null when there is none.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int $user_id  Client user ID.
	 * @return array{created:int,last_used:int}|null
	 */
	public static function info( int $user_id ): ?array {
		$saved = self::saved( $user_id );

		return null === $saved ? null : array(
			'created'   => $saved['created'],
			'last_used' => $saved['last_used'],
		);
	}

	/**
	 * The stored record, or null.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int $user_id  Client user ID.
	 * @return array{hash:string,created:int,last_used:int}|null
	 */
	private static function saved( int $user_id ): ?array {
		$saved = $user_id ? get_user_meta( $user_id, self::META, true ) : null;

		if ( ! is_array( $saved ) || empty( $saved['hash'] ) ) {
			return null;
		}

		return array(
			'hash'      => (string) $saved['hash'],
			'created'   => (int) ( $saved['created'] ?? 0 ),
			'last_used' => (int) ( $saved['last_used'] ?? 0 ),
		);
	}

	/**
	 * Keyed hash of a secret, bound to the user.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int    $user_id  User ID.
	 * @param  string $secret   Secret part of the key.
	 * @return string
	 */
	private static function hash( int $user_id, string $secret ): string {
		return hash_hmac( 'sha256', $user_id . '|' . $secret, wp_salt( 'auth' ) );
	}
}
