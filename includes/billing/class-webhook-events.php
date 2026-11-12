<?php
/**
 * Record of handled Stripe events.
 *
 * Stripe may deliver an event more than once; the latest 200 event IDs are
 * kept so each one is handled at most once.
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
 * Class Webhook_Events
 *
 * Remembers which Stripe events have been handled.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.7.0
 */
class Webhook_Events {

	/**
	 * Option holding recently handled event IDs.
	 *
	 * @since 3.7.0
	 * @var   string
	 */
	public const OPTION = 'satori_manifest_stripe_events';

	/**
	 * How many event IDs are kept.
	 *
	 * @since 3.7.0
	 * @var   int
	 */
	private const KEEP = 200;

	/**
	 * Returns true when an event ID has been handled already.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @param  string $event_id  Stripe event ID.
	 * @return bool
	 */
	public static function seen( string $event_id ): bool {
		return in_array( $event_id, (array) get_option( self::OPTION, array() ), true );
	}

	/**
	 * Remembers a handled event ID.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @param  string $event_id  Stripe event ID.
	 * @return void
	 */
	public static function remember( string $event_id ): void {
		$ids   = (array) get_option( self::OPTION, array() );
		$ids[] = $event_id;

		update_option( self::OPTION, array_slice( array_unique( $ids ), -self::KEEP ), false );
	}
}
