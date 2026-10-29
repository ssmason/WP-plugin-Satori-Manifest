<?php
/**
 * Pricing plans.
 *
 * The plan catalogue (config/plans.yaml): titles, features and prices per
 * billing interval. Read by the registration form, the approval workflow,
 * Stripe checkout and the admin screens.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Registration;

use SatoriManifest\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plans
 *
 * Read-only access to the plan catalogue.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Plans {

	/**
	 * Default billing interval.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	public const DEFAULT_INTERVAL = 'month';

	/**
	 * Currency symbols for display.
	 *
	 * @since 3.0.0
	 * @var   array<string,string>
	 */
	private const SYMBOLS = array(
		'eur' => '€',
		'gbp' => '£',
		'usd' => '$',
	);

	/**
	 * Parsed catalogue, cached per request.
	 *
	 * @since 3.0.0
	 * @var   array<string,mixed>|null
	 */
	private static ?array $catalogue = null;

	/**
	 * Billing intervals with their labels.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return array<string,string>
	 */
	public static function intervals(): array {
		return array(
			'month' => __( 'Monthly', 'satori-manifest' ),
			'year'  => __( 'Yearly', 'satori-manifest' ),
		);
	}

	/**
	 * Returns every plan keyed by slug.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return array<string,array{title:string,tagline:string,badge:string,features:array<int,string>,prices:array<string,float>,stripe_prices:array<string,string>}>
	 */
	public static function all(): array {
		return self::catalogue()['plans'];
	}

	/**
	 * Returns one plan, or null when the slug is unknown.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $slug  Plan slug.
	 * @return array<string,mixed>|null
	 */
	public static function get( string $slug ): ?array {
		return self::all()[ $slug ] ?? null;
	}

	/**
	 * Returns a plan's display title, or the raw slug when unknown.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $slug  Plan slug.
	 * @return string
	 */
	public static function title( string $slug ): string {
		$plan = self::get( $slug );
		return $plan ? $plan['title'] : $slug;
	}

	/**
	 * Returns the lower-case ISO currency code, e.g. "eur".
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return string
	 */
	public static function currency(): string {
		return self::catalogue()['currency'];
	}

	/**
	 * Returns the billing intervals a plan can be bought on.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $slug  Plan slug.
	 * @return array<int,string>
	 */
	public static function intervals_for( string $slug ): array {
		$plan = self::get( $slug );
		return $plan ? array_keys( array_filter( $plan['prices'] ) ) : array();
	}

	/**
	 * Returns true when any plan offers more than one interval (show the toggle).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return bool
	 */
	public static function has_interval_choice(): bool {
		foreach ( array_keys( self::all() ) as $slug ) {
			if ( count( self::intervals_for( $slug ) ) > 1 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the interval, falling back to the default when unsupported.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $interval  Requested interval.
	 * @return string
	 */
	public static function normalize_interval( string $interval ): string {
		return isset( self::intervals()[ $interval ] ) ? $interval : self::DEFAULT_INTERVAL;
	}

	/**
	 * Returns a plan's price for an interval (0.0 when not offered).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $slug      Plan slug.
	 * @param  string $interval  "month" or "year".
	 * @return float
	 */
	public static function price( string $slug, string $interval ): float {
		$plan = self::get( $slug );
		return $plan ? (float) ( $plan['prices'][ $interval ] ?? 0 ) : 0.0;
	}

	/**
	 * Returns the pre-created Stripe Price ID for a plan/interval, or ''.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $slug      Plan slug.
	 * @param  string $interval  "month" or "year".
	 * @return string
	 */
	public static function stripe_price( string $slug, string $interval ): string {
		$plan = self::get( $slug );
		return $plan ? (string) ( $plan['stripe_prices'][ $interval ] ?? '' ) : '';
	}

	/**
	 * Returns how much a yearly plan saves against twelve monthly payments.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $slug  Plan slug.
	 * @return float  Amount saved per year (0 when there is no saving).
	 */
	public static function yearly_saving( string $slug ): float {
		$monthly = self::price( $slug, 'month' );
		$yearly  = self::price( $slug, 'year' );

		return ( $monthly > 0 && $yearly > 0 ) ? max( 0.0, $monthly * 12 - $yearly ) : 0.0;
	}

	/**
	 * Formats an amount in the plan currency, e.g. "€9" or "€9.50".
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  float  $amount    Amount in whole units.
	 * @param  string $currency  ISO code (defaults to the plan currency).
	 * @return string
	 */
	public static function format( float $amount, string $currency = '' ): string {
		$currency = strtolower( '' !== $currency ? $currency : self::currency() );
		$decimals = floor( $amount ) === $amount ? 0 : 2;
		$number   = number_format_i18n( $amount, $decimals );
		$symbol   = self::SYMBOLS[ $currency ] ?? '';

		return '' !== $symbol ? $symbol . $number : $number . ' ' . strtoupper( $currency );
	}

	/**
	 * Returns the label for an interval's period, e.g. "per month".
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $interval  "month" or "year".
	 * @return string
	 */
	public static function period_label( string $interval ): string {
		return 'year' === $interval ? __( 'per year', 'satori-manifest' ) : __( 'per month', 'satori-manifest' );
	}

	/**
	 * Clears the per-request cache (used by tests).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function flush(): void {
		self::$catalogue = null;
	}

	/**
	 * Parses config/plans.yaml into a normalised catalogue.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return array{currency:string,plans:array<string,array<string,mixed>>}
	 */
	private static function catalogue(): array {
		if ( null !== self::$catalogue ) {
			return self::$catalogue;
		}

		$config = Config::get( 'plans.yaml' );
		$plans  = array();

		foreach ( (array) ( $config['plans'] ?? array() ) as $slug => $plan ) {
			if ( ! is_array( $plan ) || empty( $plan['title'] ) ) {
				continue;
			}

			$prices = array();
			$stripe = array();

			foreach ( array_keys( self::intervals() ) as $interval ) {
				$prices[ $interval ] = max( 0.0, (float) ( $plan['prices'][ $interval ] ?? 0 ) );
				$stripe[ $interval ] = sanitize_text_field( (string) ( $plan['stripe_prices'][ $interval ] ?? '' ) );
			}

			$plans[ sanitize_key( (string) $slug ) ] = array(
				'title'         => (string) $plan['title'],
				'tagline'       => (string) ( $plan['tagline'] ?? '' ),
				'badge'         => (string) ( $plan['badge'] ?? '' ),
				'features'      => array_map( 'strval', (array) ( $plan['features'] ?? array() ) ),
				'prices'        => $prices,
				'stripe_prices' => $stripe,
			);
		}

		self::$catalogue = array(
			'currency' => sanitize_key( (string) ( $config['currency'] ?? 'eur' ) ),
			'plans'    => $plans,
		);

		return self::$catalogue;
	}
}
