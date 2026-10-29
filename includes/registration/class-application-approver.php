<?php
/**
 * Application approver.
 *
 * The decision workflow for applications:
 *
 *   pending ──approve──▶ awaiting_payment ──paid (Stripe webhook)──▶ approved
 *      │        └──────── approve without payment ────────────────▶ approved
 *      └──decline──▶ declined
 *
 * "approved" always means the client account exists. Activation is
 * idempotent, so a repeated Stripe webhook never creates a second account.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Registration;

use SatoriManifest\Billing\Checkout;
use SatoriManifest\Billing\Stripe_Settings;
use SatoriManifest\Branding\Brand;
use SatoriManifest\Clients\Client_Factory;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Clients\Set_Password_Link;
use SatoriManifest\Designs\Design_Registry;
use SatoriManifest\Mail\Mailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Application_Approver
 *
 * Approves, activates and declines applications, and sends the emails that go
 * with each step.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Application_Approver {

	/**
	 * Approves a pending application.
	 *
	 * With payment required (and Stripe configured) the applicant is emailed a
	 * payment link and the account is created once they have paid. Otherwise
	 * the account is created straight away.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,mixed> $app              Application (Applications::get()).
	 * @param  string              $login            Username (suggested one when empty).
	 * @param  string              $design           Design slug (application preference when empty).
	 * @param  bool                $notify           Whether to email the applicant.
	 * @param  bool                $require_payment  Whether to take payment before creating the account.
	 * @return string|\WP_Error  New status ("awaiting_payment" or "approved") or error.
	 */
	public static function approve( array $app, string $login, string $design, bool $notify, bool $require_payment ) {
		if ( 'pending' !== $app['status'] ) {
			return new \WP_Error( 'satori_manifest_decided', __( 'This application has already been decided.', 'satori-manifest' ) );
		}

		$login  = '' !== $login ? $login : Client_Factory::suggest_username( (string) $app['business_name'] );
		$design = Design_Registry::exists( $design ) ? $design : (string) $app['design'];

		if ( username_exists( $login ) ) {
			/* translators: %s: username. */
			return new \WP_Error( 'satori_manifest_username', sprintf( __( 'The username "%s" is taken — choose another.', 'satori-manifest' ), $login ) );
		}

		$id = (int) $app['id'];
		Applications::set_meta( $id, 'approved_login', $login );
		Applications::set_meta( $id, 'approved_design', $design );

		if ( $require_payment && Stripe_Settings::is_configured() && Plans::get( (string) $app['plan'] ) ) {
			Applications::set_status( $id, 'awaiting_payment' );

			if ( $notify ) {
				self::send_payment_link( $app );
			}

			return 'awaiting_payment';
		}

		$user_id = self::activate( $id, $notify );

		return is_wp_error( $user_id ) ? $user_id : 'approved';
	}

	/**
	 * Creates the client account for an approved application.
	 *
	 * Safe to call more than once: an application that is already active
	 * returns its existing client.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  int  $id      Application ID.
	 * @param  bool $notify  Whether to send the welcome email.
	 * @return int|\WP_Error  Client user ID or error.
	 */
	public static function activate( int $id, bool $notify = true ) {
		$app = Applications::get( $id );

		if ( ! $app ) {
			return new \WP_Error( 'satori_manifest_missing', __( 'That application no longer exists.', 'satori-manifest' ) );
		}

		if ( 'approved' === $app['status'] && $app['user_id'] ) {
			return (int) $app['user_id'];
		}

		if ( ! in_array( $app['status'], array( 'pending', 'awaiting_payment' ), true ) ) {
			return new \WP_Error( 'satori_manifest_decided', __( 'This application has already been decided.', 'satori-manifest' ) );
		}

		$login = Applications::get_meta( $id, 'approved_login' );
		$login = ( '' !== $login && ! username_exists( $login ) ) ? $login : Client_Factory::suggest_username( (string) $app['business_name'] );

		$design = Applications::get_meta( $id, 'approved_design' );
		$design = '' !== $design ? $design : (string) $app['design'];

		$user_id = Client_Factory::create( (string) $app['business_name'], (string) $app['email'], $login, $design );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$names = explode( ' ', trim( (string) $app['contact_name'] ), 2 );
		wp_update_user(
			array(
				'ID'         => $user_id,
				'first_name' => $names[0],
				'last_name'  => $names[1] ?? '',
			)
		);

		self::prefill_branding( $user_id, $app );
		Applications::set_status( $id, 'approved', $user_id );

		if ( $notify ) {
			self::send_welcome( $id, $user_id );
		}

		return $user_id;
	}

	/**
	 * Declines an application, optionally emailing the reason.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,mixed> $app     Application.
	 * @param  string              $reason  Optional reason shown to the applicant.
	 * @param  bool                $notify  Whether to email the applicant.
	 * @return void
	 */
	public static function decline( array $app, string $reason, bool $notify ): void {
		Applications::set_status( (int) $app['id'], 'declined' );

		if ( ! $notify ) {
			return;
		}

		Applications::log_email(
			(int) $app['id'],
			Mailer::send(
				'application-declined',
				(string) $app['email'],
				array(
					'contact_name'  => self::first_name( $app ),
					'business_name' => (string) $app['business_name'],
					'reason'        => $reason,
				)
			)
		);
	}

	/**
	 * Emails (or re-emails) the payment link for an approved application.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,mixed> $app  Application.
	 * @return array<string,mixed>  Mailer::send() result.
	 */
	public static function send_payment_link( array $app ): array {
		$plan     = (string) $app['plan'];
		$interval = Plans::normalize_interval( (string) $app['interval'] );
		$result   = Mailer::send(
			'application-approved-payment',
			(string) $app['email'],
			array(
				'contact_name'  => self::first_name( $app ),
				'business_name' => (string) $app['business_name'],
				'plan'          => Plans::title( $plan ),
				'billing'       => Plans::intervals()[ $interval ],
				'price'         => Plans::format( Plans::price( $plan, $interval ) ) . ' ' . Plans::period_label( $interval ),
				'payment_url'   => Checkout::payment_url( (int) $app['id'] ),
			)
		);

		Applications::log_email( (int) $app['id'], $result );

		return $result;
	}

	/**
	 * Sends the welcome email with the set-password link.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  int $id       Application ID.
	 * @param  int $user_id  Client user ID.
	 * @return void
	 */
	public static function send_welcome( int $id, int $user_id ): void {
		$app  = Applications::get( $id );
		$user = get_userdata( $user_id );

		if ( ! $app || ! $user ) {
			return;
		}

		$page_id = Client_Repository::page_id( $user_id );

		Applications::log_email(
			$id,
			Mailer::send(
				'application-approved',
				(string) $app['email'],
				array(
					'contact_name'     => self::first_name( $app ),
					'business_name'    => (string) $app['business_name'],
					'username'         => $user->user_login,
					'plan'             => Plans::title( (string) $app['plan'] ),
					'set_password_url' => Set_Password_Link::for_user( $user_id ),
					'page_url'         => $page_id ? (string) get_permalink( $page_id ) : '',
				)
			)
		);
	}

	/**
	 * Pre-fills the client's branding with what they told us on the form.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  int                 $user_id  Client user ID.
	 * @param  array<string,mixed> $app      Application.
	 * @return void
	 */
	private static function prefill_branding( int $user_id, array $app ): void {
		$page_id = Client_Repository::page_id( $user_id );

		if ( ! $page_id ) {
			return;
		}

		$values = array(
			'sm_footer_phone'   => (string) $app['phone'],
			'sm_footer_email'   => (string) $app['email'],
			'sm_social_website' => (string) $app['website'],
		);

		foreach ( $values as $field => $value ) {
			if ( '' !== $value ) {
				Brand::set( $page_id, $field, $value );
			}
		}
	}

	/**
	 * Returns the applicant's first name.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,mixed> $app  Application.
	 * @return string
	 */
	private static function first_name( array $app ): string {
		return explode( ' ', trim( (string) $app['contact_name'] ), 2 )[0];
	}
}
