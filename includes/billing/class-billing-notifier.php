<?php
/**
 * Billing emails.
 *
 * Sends the client billing emails (receipts, failed payments, cancellations)
 * and the staff billing notice through the Mailer.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.7.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Billing;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Admin\Screens\Payments_Screen;
use SatoriManifest\Mail\Mailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Billing_Notifier
 *
 * Emails clients and staff about billing events.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.7.0
 */
class Billing_Notifier {

	/**
	 * Emails a client using one of the billing templates.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @param  string               $template  Template name.
	 * @param  int                  $user_id   Client user ID.
	 * @param  array<string,string> $vars      Extra placeholders.
	 * @return void
	 */
	public static function client( string $template, int $user_id, array $vars = array() ): void {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		Mailer::send(
			$template,
			$user->user_email,
			array_merge(
				array(
					'contact_name'  => '' !== $user->first_name ? $user->first_name : $user->display_name,
					'business_name' => $user->display_name,
					'billing_url'   => Admin_Menu::url( Admin_Menu::BILLING_SLUG ),
				),
				$vars
			)
		);
	}

	/**
	 * Emails every staff recipient about a billing event.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @param  string $event          What happened.
	 * @param  string $business_name  Client business name.
	 * @param  string $email          Client email.
	 * @param  string $details        One-line details.
	 * @return void
	 */
	public static function staff( string $event, string $business_name, string $email, string $details ): void {
		foreach ( Mailer::staff_recipients() as $recipient ) {
			Mailer::send(
				'staff-billing-notice',
				$recipient,
				array(
					'event'         => $event,
					'business_name' => $business_name,
					'email'         => $email,
					'details'       => $details,
					'review_url'    => Payments_Screen::url(),
				)
			);
		}
	}
}
