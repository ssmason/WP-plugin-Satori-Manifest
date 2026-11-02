<?php
/**
 * Monthly summary email.
 *
 * Once a month (the first daily cron run of a new month) each client gets an
 * email with last month's visits and QR scans, compared with the month
 * before. Staff can switch it off for everyone under Manifest → Settings;
 * a client can switch it off for themselves on their Home screen.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Stats;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Core\Security;
use SatoriManifest\Core\Settings;
use SatoriManifest\Mail\Mailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Monthly_Summary
 *
 * Scheduling, sending and the client's opt-out.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */
class Monthly_Summary {

	/**
	 * Daily cron hook.
	 *
	 * @since 3.3.0
	 * @var   string
	 */
	public const CRON_HOOK = 'satori_manifest_daily';

	/**
	 * Option recording the last month summaries were sent for (YYYY-MM).
	 *
	 * @since 3.3.0
	 * @var   string
	 */
	private const SENT_OPTION = 'satori_manifest_summary_sent';

	/**
	 * User meta set when a client opts out.
	 *
	 * @since 3.3.0
	 * @var   string
	 */
	public const OPT_OUT_META = '_sm_summary_opt_out';

	/**
	 * Action name for admin-post.php (opt in/out).
	 *
	 * @since 3.3.0
	 * @var   string
	 */
	public const ACTION = 'satori_manifest_summary_pref';

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'schedule' ) );
		add_action( self::CRON_HOOK, array( self::class, 'maybe_send' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'save_preference' ) );
	}

	/**
	 * Schedules the daily check once. Hooked to 'init'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @return void
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Removes the schedule (plugin deactivation).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @return void
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Sends last month's summaries if they have not gone out yet.
	 *
	 * Hooked to the daily cron.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @return int  Emails sent.
	 */
	public static function maybe_send(): int {
		$month = self::previous_month( wp_date( 'Y-m' ) );

		if ( ! Settings::get( 'monthly_summary' ) || get_option( self::SENT_OPTION ) === $month ) {
			return 0;
		}

		update_option( self::SENT_OPTION, $month, false );

		return self::send_all( $month );
	}

	/**
	 * Sends the summary for a month to every client who wants it.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  string $month  Month as YYYY-MM.
	 * @return int  Emails sent.
	 */
	public static function send_all( string $month ): int {
		$sent = 0;

		foreach ( Client_Repository::all_clients() as $client ) {
			if ( self::wants( $client->ID ) && self::send( $client->ID, $month ) ) {
				++$sent;
			}
		}

		return $sent;
	}

	/**
	 * Sends one client their summary.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int    $user_id  Client user ID.
	 * @param  string $month    Month as YYYY-MM.
	 * @param  string $to       Override recipient (tests and previews).
	 * @return bool  Whether the email was sent.
	 */
	public static function send( int $user_id, string $month, string $to = '' ): bool {
		$user    = get_userdata( $user_id );
		$page_id = Client_Repository::page_id( $user_id );

		if ( ! $user || ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
			return false;
		}

		$current  = Page_Views::month( $page_id, $month );
		$previous = Page_Views::month( $page_id, self::previous_month( $month ) );
		$label    = wp_date( 'F Y', (int) strtotime( $month . '-01' ) );

		$result = Mailer::send(
			'monthly-summary',
			'' !== $to ? $to : $user->user_email,
			array(
				'contact_name'  => '' !== $user->first_name ? $user->first_name : html_entity_decode( $user->display_name, ENT_QUOTES, 'UTF-8' ),
				'business_name' => html_entity_decode( $user->display_name, ENT_QUOTES, 'UTF-8' ),
				'month'         => $label,
				'views'         => number_format_i18n( $current['views'] ),
				'scans'         => number_format_i18n( $current['scans'] ),
				'change'        => self::change_text( $current['views'], $previous['views'] ),
				'page_url'      => (string) get_permalink( $page_id ),
				'dashboard_url' => Admin_Menu::url( Admin_Menu::HOME_SLUG ),
			)
		);

		return (bool) $result['sent'];
	}

	/**
	 * Returns true when a client has not opted out.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int $user_id  Client user ID.
	 * @return bool
	 */
	public static function wants( int $user_id ): bool {
		return ! get_user_meta( $user_id, self::OPT_OUT_META, true );
	}

	/**
	 * Saves the client's opt-in/out from the Home screen.
	 *
	 * Hooked to admin_post_satori_manifest_summary_pref.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @return void
	 */
	public static function save_preference(): void {
		// The nonce is read here and verified by Security::verify_form_nonce().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$nonce = isset( $_POST[ Security::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Security::NONCE_FIELD ] ) ) : '';
		$wants = ! empty( $_POST['monthly_summary'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! Security::verify_form_nonce( $nonce, self::ACTION ) || ! Client_Role::is_client() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'satori-manifest' ), 403 );
		}

		if ( $wants ) {
			delete_user_meta( get_current_user_id(), self::OPT_OUT_META );
		} else {
			update_user_meta( get_current_user_id(), self::OPT_OUT_META, 1 );
		}

		wp_safe_redirect( add_query_arg( 'sm_notice', 'summary', Admin_Menu::url( Admin_Menu::HOME_SLUG ) ) );
		exit;
	}

	/**
	 * Describes the change in visits, e.g. "up 12% on September".
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int $current   This month's views.
	 * @param  int $previous  Last month's views.
	 * @return string
	 */
	public static function change_text( int $current, int $previous ): string {
		if ( 0 === $previous ) {
			return $current > 0 ? __( 'your first month of visits', 'satori-manifest' ) : __( 'no visits yet — make sure your QR code is on display', 'satori-manifest' );
		}

		$percent = (int) round( ( $current - $previous ) / $previous * 100 );

		if ( 0 === $percent ) {
			return __( 'about the same as the month before', 'satori-manifest' );
		}

		return $percent > 0
			/* translators: %d: percentage. */
			? sprintf( __( 'up %d%% on the month before', 'satori-manifest' ), $percent )
			/* translators: %d: percentage. */
			: sprintf( __( 'down %d%% on the month before', 'satori-manifest' ), abs( $percent ) );
	}

	/**
	 * Returns the month before a YYYY-MM month.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  string $month  Month as YYYY-MM.
	 * @return string
	 */
	public static function previous_month( string $month ): string {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m', $month );

		return $date ? $date->modify( '-1 month' )->format( 'Y-m' ) : $month;
	}
}
