<?php
/**
 * Registration emails.
 *
 * Emails the applicant and staff about a new application, and tells the
 * owner of an already-registered email that someone registered again.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.7.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Registration;

use SatoriManifest\Admin\Screens\Applications_Screen;
use SatoriManifest\Designs\Design_Registry;
use SatoriManifest\Mail\Mailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Registration_Notifier
 *
 * Sends the registration emails.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.7.0
 */
class Registration_Notifier {

	/**
	 * Emails the applicant and staff about a new application.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @param  int                  $id    Application ID.
	 * @param  array<string,string> $data  Application fields.
	 * @return void
	 */
	public static function application_received( int $id, array $data ): void {
		$types  = Applications::business_types();
		$design = Design_Registry::get( $data['design'] );
		$vars   = array_merge(
			$data,
			array(
				'contact_name'  => explode( ' ', trim( $data['contact_name'] ), 2 )[0],
				'full_name'     => $data['contact_name'],
				'business_type' => $types[ $data['business_type'] ] ?? $data['business_type'],
				'plan'          => Plans::title( $data['plan'] ),
				'billing'       => Plans::intervals()[ $data['interval'] ] ?? '',
				'price'         => Plans::format( Plans::price( $data['plan'], $data['interval'] ) ) . ' ' . Plans::period_label( $data['interval'] ),
				'design'        => $design ? (string) $design['title'] : __( 'No preference', 'satori-manifest' ),
				'review_url'    => Applications_Screen::url(),
			)
		);

		Applications::log_email( $id, Mailer::send( 'application-received', $data['email'], $vars ) );

		foreach ( Mailer::staff_recipients() as $recipient ) {
			Applications::log_email( $id, Mailer::send( 'application-staff-notice', $recipient, $vars ) );
		}
	}

	/**
	 * Tells the owner of an already-registered email that someone registered again.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @param  array<string,string> $data  Submitted fields.
	 * @return void
	 */
	public static function account_exists( array $data ): void {
		$has_account = (bool) email_exists( $data['email'] );

		Mailer::send(
			'account-exists',
			$data['email'],
			array(
				'contact_name'      => explode( ' ', trim( $data['contact_name'] ), 2 )[0],
				'has_account'       => $has_account
					? __( 'You already have an account, so there is nothing more to do. Just log in.', 'satori-manifest' )
					: __( 'We already have your application and will be in touch soon.', 'satori-manifest' ),
				'lost_password_url' => wp_lostpassword_url(),
			)
		);
	}
}
