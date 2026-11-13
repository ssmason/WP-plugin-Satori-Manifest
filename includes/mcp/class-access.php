<?php
/**
 * Who may use the AI assistant.
 *
 * A client may connect when the assistant is enabled and either staff have
 * switched it on for them, or (staff setting "plan") their plan includes it
 * and the subscription is in good standing. Staff can always switch it off.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp;

use SatoriManifest\Billing\Subscriptions;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Clients\Client_Role;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Access
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Access {

	/**
	 * User meta: staff setting ("plan", "on" or "off").
	 */
	public const META = '_sm_ai_access';

	/**
	 * Follow the client's plan.
	 */
	public const MODE_PLAN = 'plan';

	/**
	 * Always allowed.
	 */
	public const MODE_ON = 'on';

	/**
	 * Never allowed.
	 */
	public const MODE_OFF = 'off';

	/**
	 * The staff setting for a client.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int $user_id  Client user ID.
	 * @return string
	 */
	public static function mode( int $user_id ): string {
		$mode = (string) get_user_meta( $user_id, self::META, true );

		return in_array( $mode, array( self::MODE_ON, self::MODE_OFF ), true ) ? $mode : self::MODE_PLAN;
	}

	/**
	 * Saves the staff setting.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int    $user_id  Client user ID.
	 * @param  string $mode     One of the MODE_ constants.
	 * @return void
	 */
	public static function set_mode( int $user_id, string $mode ): void {
		if ( self::MODE_PLAN === $mode || ! in_array( $mode, array( self::MODE_ON, self::MODE_OFF ), true ) ) {
			delete_user_meta( $user_id, self::META );
		} else {
			update_user_meta( $user_id, self::META, $mode );
		}

		// Switching off ends any live connection straight away.
		if ( ! self::allowed( $user_id ) ) {
			Tokens::revoke( $user_id );
		}
	}

	/**
	 * Whether the client's plan includes the assistant (and is paid up).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int $user_id  Client user ID.
	 * @return bool
	 */
	public static function plan_includes( int $user_id ): bool {
		$subscription = Subscriptions::for_user( $user_id );

		return in_array( (string) $subscription['plan'], Assistant_Config::plans(), true )
			&& Subscriptions::in_good_standing( (string) $subscription['status'] );
	}

	/**
	 * Whether a client may use the assistant right now.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int $user_id  Client user ID.
	 * @return bool
	 */
	public static function allowed( int $user_id ): bool {
		if ( ! $user_id || ! Assistant_Config::enabled() || ! Client_Role::is_client( $user_id ) || ! Client_Repository::page_id( $user_id ) ) {
			return false;
		}

		$mode    = self::mode( $user_id );
		$allowed = self::MODE_ON === $mode || ( self::MODE_PLAN === $mode && self::plan_includes( $user_id ) );

		/**
		 * Filters whether a client may use the AI assistant.
		 *
		 * @since 3.9.0
		 * @param bool $allowed  Result of the staff setting and plan.
		 * @param int  $user_id  Client user ID.
		 */
		return (bool) apply_filters( 'satori_manifest_ai_allowed', $allowed, $user_id );
	}
}
