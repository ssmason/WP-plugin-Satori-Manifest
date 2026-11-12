<?php
/**
 * Abandoned checkouts.
 *
 * A visitor who signs up on the Register form and then closes Stripe Checkout
 * leaves an application "waiting for payment". Those self-service sign-ups are
 * deleted after a week by the plugin's daily check, so they do not pile up.
 * Applications that staff approved by hand are never touched.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Registration;

use SatoriManifest\Stats\Monthly_Summary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Abandoned_Checkouts
 *
 * Removes self-service sign-ups that never paid.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */
class Abandoned_Checkouts {

	/**
	 * Application meta marking a sign-up that went straight to Stripe.
	 *
	 * @since 3.8.0
	 * @var   string
	 */
	public const SELF_CHECKOUT = 'self_checkout';

	/**
	 * Days an unpaid self-service sign-up is kept.
	 *
	 * @since 3.8.0
	 * @var   int
	 */
	public const KEEP_DAYS = 7;

	/**
	 * Hooks the clean-up into the plugin's daily check.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return void
	 */
	public static function register(): void {
		add_action( Monthly_Summary::CRON_HOOK, array( self::class, 'purge' ) );
	}

	/**
	 * Deletes unfinished self-service sign-ups for an email (the person is trying again).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  string $email  Applicant email.
	 * @return void
	 */
	public static function forget_for( string $email ): void {
		foreach ( Applications::all( 'awaiting_payment' ) as $app ) {
			if ( 0 === strcasecmp( (string) $app['email'], $email ) && '1' === Applications::get_meta( (int) $app['id'], self::SELF_CHECKOUT ) ) {
				wp_delete_post( (int) $app['id'], true );
			}
		}
	}

	/**
	 * Deletes self-service sign-ups left unpaid for longer than KEEP_DAYS.
	 *
	 * Hooked to the daily check.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  int $now  Current Unix time (for tests).
	 * @return int  Number of applications deleted.
	 */
	public static function purge( int $now = 0 ): int {
		$cutoff  = ( $now ? $now : time() ) - self::KEEP_DAYS * DAY_IN_SECONDS;
		$deleted = 0;

		foreach ( Applications::all( 'awaiting_payment' ) as $app ) {
			$created = strtotime( (string) $app['date'] );

			if (
				'1' === Applications::get_meta( (int) $app['id'], self::SELF_CHECKOUT )
				&& false !== $created
				&& $created < $cutoff
				&& wp_delete_post( (int) $app['id'], true )
			) {
				++$deleted;
			}
		}

		return $deleted;
	}
}
