<?php
/**
 * Page views.
 *
 * Daily visit counts for client pages, stored on the page as
 * array( 'YYYY-MM-DD' => array( views, scans ) ). A "scan" is a visit that
 * arrived through the QR code (?src=qr); every scan is also a view. Only
 * counts are kept — no IP addresses or other personal data. Days older
 * than RETENTION_DAYS are dropped.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Stats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Page_Views
 *
 * Storage and totals for page visit counts.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */
class Page_Views {

	/**
	 * Page meta key holding the daily counts.
	 *
	 * @since 3.3.0
	 * @var   string
	 */
	public const META = '_sm_views';

	/**
	 * Query var marking where a visit came from.
	 *
	 * @since 3.3.0
	 * @var   string
	 */
	public const SOURCE_VAR = 'src';

	/**
	 * SOURCE_VAR value used by the printed QR code.
	 *
	 * @since 3.3.0
	 * @var   string
	 */
	public const SOURCE_QR = 'qr';

	/**
	 * Days of history kept.
	 *
	 * @since 3.3.0
	 * @var   int
	 */
	public const RETENTION_DAYS = 400;

	/**
	 * Adds one visit for today.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int  $page_id  Client page ID.
	 * @param  bool $scan     Whether the visit came from the QR code.
	 * @return void
	 */
	public static function record( int $page_id, bool $scan ): void {
		$days  = self::days( $page_id );
		$today = wp_date( 'Y-m-d' );
		$count = $days[ $today ] ?? array( 0, 0 );

		$days[ $today ] = array( (int) $count[0] + 1, (int) $count[1] + ( $scan ? 1 : 0 ) );

		$cutoff = wp_date( 'Y-m-d', time() - self::RETENTION_DAYS * DAY_IN_SECONDS );
		$days   = array_filter( $days, static fn( $day ): bool => $day >= $cutoff, ARRAY_FILTER_USE_KEY );

		update_post_meta( $page_id, self::META, $days );
	}

	/**
	 * Returns views and scans between two dates (inclusive, YYYY-MM-DD).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int    $page_id  Client page ID.
	 * @param  string $from     First day.
	 * @param  string $to       Last day.
	 * @return array{views:int,scans:int}
	 */
	public static function totals( int $page_id, string $from, string $to ): array {
		$totals = array(
			'views' => 0,
			'scans' => 0,
		);

		foreach ( self::days( $page_id ) as $day => $count ) {
			if ( $day >= $from && $day <= $to ) {
				$totals['views'] += (int) $count[0];
				$totals['scans'] += (int) $count[1];
			}
		}

		return $totals;
	}

	/**
	 * Returns views and scans for the last N days, including today.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int $page_id  Client page ID.
	 * @param  int $days     Number of days.
	 * @return array{views:int,scans:int}
	 */
	public static function last_days( int $page_id, int $days ): array {
		return self::totals( $page_id, wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS ), wp_date( 'Y-m-d' ) );
	}

	/**
	 * Returns views and scans for a calendar month.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int    $page_id  Client page ID.
	 * @param  string $month    Month as YYYY-MM.
	 * @return array{views:int,scans:int}
	 */
	public static function month( int $page_id, string $month ): array {
		return self::totals( $page_id, $month . '-01', $month . '-31' );
	}

	/**
	 * Returns the stored daily counts.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int $page_id  Client page ID.
	 * @return array<string,array<int,int>>
	 */
	private static function days( int $page_id ): array {
		$days = get_post_meta( $page_id, self::META, true );

		return is_array( $days ) ? $days : array();
	}
}
