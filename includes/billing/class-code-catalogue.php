<?php
/**
 * Printed QR code catalogue.
 *
 * Sizes, prices, the free standard code and postage regions, read from
 * config/printed-codes.yaml. An order is a map of size slug → quantity and
 * travels between the form, Stripe metadata and staff emails as "m:2,l:1".
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Billing;

use SatoriManifest\Core\Config;
use SatoriManifest\Registration\Plans;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Code_Catalogue
 *
 * Prices printed QR code orders and their postage.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */
class Code_Catalogue {

	/**
	 * Parsed configuration (cached per request).
	 *
	 * @since 3.8.0
	 * @var   array<string,mixed>|null
	 */
	private static ?array $config = null;

	/**
	 * Sizes on offer: slug → label and price.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return array<string,array{label:string,price:float}>
	 */
	public static function sizes(): array {
		return self::config()['sizes'];
	}

	/**
	 * The size of the free code included with a new sign-up.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return string
	 */
	public static function standard(): string {
		return self::config()['standard'];
	}

	/**
	 * Most codes in one order.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return int
	 */
	public static function max(): int {
		return self::config()['max'];
	}

	/**
	 * Postage regions: slug → label, first, each and countries.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return array<string,array{label:string,first:float,each:float,countries:array<int,string>}>
	 */
	public static function regions(): array {
		return self::config()['regions'];
	}

	/**
	 * A known region slug, or the first region.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  string $region  Requested region.
	 * @return string
	 */
	public static function region( string $region ): string {
		$regions = self::regions();

		return isset( $regions[ $region ] ) ? $region : (string) array_key_first( $regions );
	}

	/**
	 * Region name with its postage, e.g. "Canada (postage €23)" or "EU or UK (free postage)".
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  string $region  Region slug.
	 * @return string
	 */
	public static function region_label( string $region ): string {
		$terms = self::regions()[ self::region( $region ) ];

		if ( $terms['first'] <= 0 && $terms['each'] <= 0 ) {
			/* translators: %s: region, e.g. "EU or UK". */
			return sprintf( __( '%s (free postage)', 'satori-manifest' ), $terms['label'] );
		}

		if ( $terms['each'] <= 0 ) {
			/* translators: 1: region, 2: postage per order. */
			return sprintf( __( '%1$s (postage %2$s)', 'satori-manifest' ), $terms['label'], Plans::format( $terms['first'] ) );
		}

		/* translators: 1: region, 2: postage for the first code, 3: postage for each further code. */
		return sprintf( __( '%1$s (postage %2$s, plus %3$s per extra code)', 'satori-manifest' ), $terms['label'], Plans::format( $terms['first'] ), Plans::format( $terms['each'] ) );
	}

	/**
	 * Builds a clean order from submitted quantities (unknown sizes dropped, total capped).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  array<string,mixed> $quantities  Size slug → quantity.
	 * @return array<string,int>
	 */
	public static function order( array $quantities ): array {
		$order = array();
		$left  = self::max();

		foreach ( array_keys( self::sizes() ) as $size ) {
			$quantity = min( $left, max( 0, (int) ( $quantities[ $size ] ?? 0 ) ) );

			if ( $quantity > 0 ) {
				$order[ $size ] = $quantity;
				$left          -= $quantity;
			}
		}

		return $order;
	}

	/**
	 * Order as "m:2,l:1".
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  array<string,int> $order  Order.
	 * @return string
	 */
	public static function encode( array $order ): string {
		$parts = array();

		foreach ( $order as $size => $quantity ) {
			$parts[] = $size . ':' . $quantity;
		}

		return implode( ',', $parts );
	}

	/**
	 * Order from "m:2,l:1" (validated again).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  string $encoded  Encoded order.
	 * @return array<string,int>
	 */
	public static function decode( string $encoded ): array {
		$quantities = array();

		foreach ( array_filter( explode( ',', $encoded ) ) as $part ) {
			$pair = explode( ':', $part, 2 );

			$quantities[ sanitize_key( $pair[0] ) ] = (int) ( $pair[1] ?? 0 );
		}

		return self::order( $quantities );
	}

	/**
	 * Number of codes in an order.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  array<string,int> $order  Order.
	 * @return int
	 */
	public static function count( array $order ): int {
		return (int) array_sum( $order );
	}

	/**
	 * Price of the codes, less the free standard code on a first order.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  array<string,int> $order      Order.
	 * @param  bool              $with_free  Whether the free standard code applies (sign-up).
	 * @return float
	 */
	public static function codes_cost( array $order, bool $with_free = false ): float {
		$sizes = self::sizes();
		$total = 0.0;

		foreach ( $order as $size => $quantity ) {
			$total += $quantity * $sizes[ $size ]['price'];
		}

		if ( $with_free && self::count( $order ) > 0 ) {
			$total -= $sizes[ self::standard() ]['price'] ?? 0.0;
		}

		return round( max( 0.0, $total ), 2 );
	}

	/**
	 * Postage for a number of codes to a region.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  string $region  Region slug.
	 * @param  int    $count   Number of codes.
	 * @return float
	 */
	public static function postage( string $region, int $count ): float {
		$terms = self::regions()[ self::region( $region ) ];

		return $count > 0 ? round( $terms['first'] + ( $count - 1 ) * $terms['each'], 2 ) : 0.0;
	}

	/**
	 * Human summary, e.g. "2 × M – 35 × 46 mm (standard), 1 × L – 40 × 52 mm".
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  array<string,int> $order  Order.
	 * @return string
	 */
	public static function describe( array $order ): string {
		$sizes = self::sizes();
		$lines = array();

		foreach ( $order as $size => $quantity ) {
			$lines[] = $quantity . ' × ' . $sizes[ $size ]['label'];
		}

		return implode( ', ', $lines );
	}

	/**
	 * Loads and normalises config/printed-codes.yaml.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return array{sizes:array<string,array{label:string,price:float}>,standard:string,max:int,regions:array<string,array{label:string,first:float,each:float,countries:array<int,string>}>}
	 */
	private static function config(): array {
		if ( null !== self::$config ) {
			return self::$config;
		}

		$raw   = Config::get( 'printed-codes.yaml' );
		$sizes = array();

		foreach ( (array) ( $raw['sizes'] ?? array() ) as $slug => $size ) {
			$sizes[ sanitize_key( (string) $slug ) ] = array(
				'label' => sanitize_text_field( (string) ( $size['label'] ?? $slug ) ),
				'price' => max( 0.0, (float) ( $size['price'] ?? 0 ) ),
			);
		}

		$regions = array();

		foreach ( (array) ( $raw['regions'] ?? array() ) as $slug => $region ) {
			$regions[ sanitize_key( (string) $slug ) ] = array(
				'label'     => sanitize_text_field( (string) ( $region['label'] ?? $slug ) ),
				'first'     => max( 0.0, (float) ( $region['first'] ?? 0 ) ),
				'each'      => max( 0.0, (float) ( $region['each'] ?? 0 ) ),
				'countries' => array_values( array_filter( array_map( static fn( $code ): string => strtoupper( substr( sanitize_key( (string) $code ), 0, 2 ) ), (array) ( $region['countries'] ?? array() ) ) ) ),
			);
		}

		$standard = sanitize_key( (string) ( $raw['standard'] ?? '' ) );

		self::$config = array(
			'sizes'    => $sizes,
			'standard' => isset( $sizes[ $standard ] ) ? $standard : (string) array_key_first( $sizes ),
			'max'      => max( 1, absint( $raw['max'] ?? 50 ) ),
			'regions'  => $regions ? $regions : array(
				'eu' => array(
					'label'     => 'EU',
					'first'     => 0.0,
					'each'      => 0.0,
					'countries' => array( 'ES' ),
				),
			),
		);

		return self::$config;
	}
}
