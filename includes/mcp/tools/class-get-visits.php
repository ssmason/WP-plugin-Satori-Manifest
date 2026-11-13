<?php
/**
 * Tool: get_visits - page visits and QR scans.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp\Tools;

use SatoriManifest\Mcp\Server\Tool_Result;
use SatoriManifest\Stats\Page_Views;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Get_Visits
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Get_Visits extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'get_visits';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Visits and scans', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'How many people opened the page, and how many of those scanned the QR code, over the last 7, 30 or 90 days, compared with the period before.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		return self::object(
			array(
				'days' => array(
					'type' => 'integer',
					'enum' => array( 7, 30, 90 ),
				),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,bool>
	 */
	public function annotations(): array {
		return self::read_only();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param  array<string,mixed> $args  Arguments.
	 * @return Tool_Result
	 */
	public function call( array $args ): Tool_Result {
		$days    = (int) ( $args['days'] ?? 30 );
		$page_id = $this->store->context()->page_id;
		$now     = Page_Views::last_days( $page_id, $days );
		$before  = Page_Views::totals(
			$page_id,
			wp_date( 'Y-m-d', time() - ( 2 * $days - 1 ) * DAY_IN_SECONDS ),
			wp_date( 'Y-m-d', time() - $days * DAY_IN_SECONDS )
		);

		return Tool_Result::ok(
			sprintf( 'Last %d days: %d visits, %d from QR scans.', $days, $now['views'], $now['scans'] ),
			array(
				'days'          => $days,
				'visits'        => $now['views'],
				'qr_scans'      => $now['scans'],
				'previous'      => array(
					'visits'   => $before['views'],
					'qr_scans' => $before['scans'],
				),
				'counting_note' => 'Bots, the owner and repeat visits within 30 minutes are not counted.',
			)
		);
	}
}
