<?php
/**
 * WP-CLI: visit statistics.
 *
 * Usage:
 *   wp satori-manifest stats list [--days=<days>]
 *   wp satori-manifest stats send-summary [--month=<YYYY-MM>] [--client=<id>] [--to=<email>]
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Cli;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Stats\Monthly_Summary;
use SatoriManifest\Stats\Page_Views;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows visit counts and sends monthly summaries on demand.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */
class Stats_Command {

	/**
	 * Lists visits and QR scans per client.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<days>]
	 * : Period in days, ending today. Default: 30
	 *
	 * ## EXAMPLES
	 *
	 *     wp satori-manifest stats list --days=7
	 *
	 * @subcommand list
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  array<int,string>    $args        Positional args.
	 * @param  array<string,string> $assoc_args  Named args.
	 * @return void
	 */
	public function list_( array $args, array $assoc_args ): void {
		$days = max( 1, absint( $assoc_args['days'] ?? 30 ) );
		$rows = array();

		foreach ( Client_Repository::all_clients() as $client ) {
			$page_id = Client_Repository::page_id( $client->ID );
			$totals  = $page_id ? Page_Views::last_days( $page_id, $days ) : array(
				'views' => 0,
				'scans' => 0,
			);
			$rows[]  = array(
				'id'      => $client->ID,
				'client'  => html_entity_decode( $client->display_name, ENT_QUOTES, 'UTF-8' ),
				'visits'  => $totals['views'],
				'scans'   => $totals['scans'],
				'summary' => Monthly_Summary::wants( $client->ID ) ? 'on' : 'off',
			);
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'client', 'visits', 'scans', 'summary' ) );
	}

	/**
	 * Sends the monthly summary now (normally sent automatically on the 1st).
	 *
	 * ## OPTIONS
	 *
	 * [--month=<month>]
	 * : Month as YYYY-MM. Default: last month.
	 *
	 * [--client=<id>]
	 * : Only this client.
	 *
	 * [--to=<email>]
	 * : Send to this address instead (with --client), e.g. to preview.
	 *
	 * ## EXAMPLES
	 *
	 *     wp satori-manifest stats send-summary --client=21 --to=me@example.com
	 *
	 * @subcommand send-summary
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  array<int,string>    $args        Positional args.
	 * @param  array<string,string> $assoc_args  Named args.
	 * @return void
	 */
	public function send_summary( array $args, array $assoc_args ): void {
		$month = (string) ( $assoc_args['month'] ?? Monthly_Summary::previous_month( wp_date( 'Y-m' ) ) );

		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			\WP_CLI::error( 'Use --month=YYYY-MM.' );
		}

		if ( ! empty( $assoc_args['client'] ) ) {
			$sent = Monthly_Summary::send( absint( $assoc_args['client'] ), $month, sanitize_email( (string) ( $assoc_args['to'] ?? '' ) ) );
			$sent ? \WP_CLI::success( 'Summary sent.' ) : \WP_CLI::warning( 'Not sent (no published page, or email delivery failed).' );
			return;
		}

		\WP_CLI::success( sprintf( 'Sent %d summaries for %s.', Monthly_Summary::send_all( $month ), $month ) );
	}
}
