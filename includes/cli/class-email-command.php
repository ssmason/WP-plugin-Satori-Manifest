<?php
/**
 * WP-CLI: email.
 *
 * Usage:
 *   wp satori-manifest email test <template> <to>
 *   wp satori-manifest email preview <template> [--file=<path>]
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Cli;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Admin\Screens\Applications_Screen;
use SatoriManifest\Mail\Email_Builder;
use SatoriManifest\Mail\Mailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends or previews the plugin's emails.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Email_Command {

	/**
	 * Sends a test email from a template, with sample values.
	 *
	 * ## OPTIONS
	 *
	 * <template>
	 * : Template name, e.g. application-approved (see config/emails/).
	 *
	 * <to>
	 * : Recipient email address.
	 *
	 * ## EXAMPLES
	 *
	 *     wp satori-manifest email test application-received me@example.com
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<int,string> $args  Positional args.
	 * @return void
	 */
	public function test( array $args ): void {
		$result = Mailer::send( (string) $args[0], (string) $args[1], self::sample_vars() );

		if ( $result['sent'] ) {
			\WP_CLI::success( sprintf( 'Sent "%s" via %s.', $result['subject'], $result['transport'] ) );
			return;
		}

		\WP_CLI::warning( sprintf( 'Not sent via %s: %s', $result['transport'], $result['error'] ) );
	}

	/**
	 * Writes a template's HTML to a file so it can be opened in a browser.
	 *
	 * ## OPTIONS
	 *
	 * <template>
	 * : Template name.
	 *
	 * [--file=<path>]
	 * : Where to write the HTML. Default: prints to the terminal.
	 *
	 * ## EXAMPLES
	 *
	 *     wp satori-manifest email preview payment-receipt --file=/tmp/receipt.html
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<int,string>    $args        Positional args.
	 * @param  array<string,string> $assoc_args  Named args.
	 * @return void
	 */
	public function preview( array $args, array $assoc_args ): void {
		$message = Email_Builder::build( (string) $args[0], self::sample_vars() );

		if ( '' === $message['subject'] ) {
			\WP_CLI::error( 'Unknown template.' );
		}

		if ( empty( $assoc_args['file'] ) ) {
			\WP_CLI::log( $message['html'] );
			return;
		}

		file_put_contents( (string) $assoc_args['file'], $message['html'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI developer output.
		\WP_CLI::success( sprintf( '"%s" written to %s', $message['subject'], $assoc_args['file'] ) );
	}

	/**
	 * Sample placeholder values for tests and previews.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return array<string,string>
	 */
	private static function sample_vars(): array {
		return array(
			'contact_name'     => 'Sam',
			'business_name'    => 'Test Business',
			'email'            => 'sam@example.com',
			'phone'            => '+34 600 000 000',
			'website'          => 'https://example.com',
			'business_type'    => 'Barber',
			'design'           => 'Studio',
			'message'          => 'Looking forward to it.',
			'username'         => 'testbusiness',
			'plan'             => 'Self-managed',
			'billing'          => 'Monthly',
			'price'            => '€9 per month',
			'amount'           => '€9',
			'number'           => 'INV-0001',
			'event'            => 'New subscription',
			'details'          => 'Self-managed — €9 per month',
			'reason'           => '',
			'set_password_url' => wp_lostpassword_url(),
			'reset_url'        => wp_lostpassword_url(),
			'payment_url'      => home_url( '/?sm_pay=0&key=sample' ),
			'invoice_url'      => 'https://invoice.stripe.com/',
			'billing_url'      => Admin_Menu::url( Admin_Menu::BILLING_SLUG ),
			'page_url'         => home_url( '/test-business/' ),
			'review_url'       => Applications_Screen::url(),
		);
	}
}
