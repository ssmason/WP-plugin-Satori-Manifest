<?php
/**
 * Cloudflare Turnstile spam check.
 *
 * Optional, free and privacy-friendly: usually invisible to people. Switched on
 * when TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY are in .env; without them the
 * registration form relies on its honeypot, timing check, rate limit and nonce.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Hardening;

use SatoriManifest\Core\Env;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Turnstile
 *
 * Renders and verifies the Turnstile widget.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */
class Turnstile {

	/**
	 * Form field Turnstile fills in.
	 *
	 * @since 3.8.0
	 * @var   string
	 */
	public const FIELD = 'cf-turnstile-response';

	/**
	 * Cloudflare's verification endpoint.
	 *
	 * @since 3.8.0
	 * @var   string
	 */
	private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	/**
	 * Cloudflare's widget script.
	 *
	 * @since 3.8.0
	 * @var   string
	 */
	private const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

	/**
	 * Whether both keys are set.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return Env::has( 'TURNSTILE_SITE_KEY' ) && Env::has( 'TURNSTILE_SECRET_KEY' );
	}

	/**
	 * Outputs the widget and loads Cloudflare's script (only when enabled).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return void
	 */
	public static function render(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		// Cloudflare serves the current version from a fixed URL; a version string would be ignored.
		wp_enqueue_script( 'cloudflare-turnstile', self::SCRIPT_URL, array(), null, array( 'strategy' => 'defer' ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion

		printf( '<div class="cf-turnstile" data-sitekey="%s" data-size="flexible"></div>', esc_attr( Env::get( 'TURNSTILE_SITE_KEY' ) ) );
	}

	/**
	 * Checks a submitted token with Cloudflare. Always true when Turnstile is off.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  string $token  Value of the cf-turnstile-response field.
	 * @return bool
	 */
	public static function verify( string $token ): bool {
		if ( ! self::is_enabled() ) {
			return true;
		}

		if ( '' === $token ) {
			return false;
		}

		$response = wp_remote_post(
			self::VERIFY_URL,
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => Env::get( 'TURNSTILE_SECRET_KEY' ),
					'response' => $token,
					'remoteip' => Client_Ip::get(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			// Cloudflare unreachable: let the person through; the other checks still apply.
			return true;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $body ) && ! empty( $body['success'] );
	}
}
