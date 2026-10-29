<?php
/**
 * Stripe settings.
 *
 * Stripe keys live in the environment (.env / wp-config constants), never in
 * the database:
 *
 *   STRIPE_SECRET_KEY      sk_test_… or sk_live_… (a restricted rk_… key also works)
 *   STRIPE_WEBHOOK_SECRET  whsec_… from the webhook endpoint in the Stripe dashboard
 *
 * Billing switches itself on when STRIPE_SECRET_KEY is present.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Billing;

use SatoriManifest\Core\Env;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Stripe_Settings
 *
 * Read-only access to the Stripe configuration.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Stripe_Settings {

	/**
	 * Returns true when a Stripe secret key is configured.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return bool
	 */
	public static function is_configured(): bool {
		/**
		 * Filters whether Stripe billing is enabled.
		 *
		 * @since 3.0.0
		 * @param bool $enabled  True when STRIPE_SECRET_KEY is set.
		 */
		return (bool) apply_filters( 'satori_manifest_billing_enabled', '' !== self::secret_key() );
	}

	/**
	 * Returns true when webhook signatures can be verified.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return bool
	 */
	public static function has_webhook_secret(): bool {
		return '' !== self::webhook_secret();
	}

	/**
	 * Returns the secret API key.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return string
	 */
	public static function secret_key(): string {
		return Env::get( 'STRIPE_SECRET_KEY' );
	}

	/**
	 * Returns the webhook signing secret.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return string
	 */
	public static function webhook_secret(): string {
		return Env::get( 'STRIPE_WEBHOOK_SECRET' );
	}

	/**
	 * Returns "test", "live" or "" (not configured), read from the key prefix.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return string
	 */
	public static function mode(): string {
		$key = self::secret_key();

		if ( '' === $key ) {
			return '';
		}

		return str_contains( substr( $key, 0, 8 ), '_live' ) ? 'live' : 'test';
	}

	/**
	 * Returns the URL to register as the webhook endpoint in Stripe.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return string
	 */
	public static function webhook_url(): string {
		return rest_url( Webhooks::ROUTE_NAMESPACE . Webhooks::ROUTE );
	}

	/**
	 * Returns the Stripe dashboard base URL for the current mode.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return string
	 */
	public static function dashboard_url(): string {
		return 'test' === self::mode() ? 'https://dashboard.stripe.com/test' : 'https://dashboard.stripe.com';
	}
}
