<?php
/**
 * Registration form submissions.
 *
 * Handles posts from the registration form (admin-post.php, logged-in or
 * not): validates and spam-checks them, stores a pending application and
 * redirects back with a result code (post/redirect/get).
 *
 * Spam protection without third parties: nonce, hidden honeypot field,
 * minimum fill time, and a per-IP rate limit. A repeat registration gets the
 * same answer as a new one, so the form never reveals which emails exist.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.7.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Registration;

use SatoriManifest\Billing\Checkout;
use SatoriManifest\Billing\Code_Catalogue;
use SatoriManifest\Billing\Printed_Codes;
use SatoriManifest\Billing\Stripe_Settings;
use SatoriManifest\Core\Security;
use SatoriManifest\Designs\Design_Registry;
use SatoriManifest\Hardening\Client_Ip;
use SatoriManifest\Hardening\Turnstile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Registration_Handler
 *
 * Processes registration form submissions.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.7.0
 */
class Registration_Handler {

	/**
	 * Minimum seconds between rendering and submitting (bots are faster).
	 *
	 * @since 3.7.0
	 * @var   int
	 */
	private const MIN_SECONDS = 3;

	/**
	 * Maximum submissions per IP per hour.
	 *
	 * @since 3.7.0
	 * @var   int
	 */
	private const RATE_LIMIT = 5;

	/**
	 * Registers the submission handlers.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.7.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_nopriv_' . Registration_Form::ACTION, array( self::class, 'handle' ) );
		add_action( 'admin_post_' . Registration_Form::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Processes a submission. Hooked to admin_post(_nopriv)_satori_manifest_register.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return void
	 */
	public static function handle(): void {
		// Fields are read after the nonce is verified via Security::verify_form_nonce() below.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$return = isset( $_POST['_sm_return'] ) ? esc_url_raw( wp_unslash( $_POST['_sm_return'] ) ) : home_url( '/' );
		$return = wp_validate_redirect( $return, home_url( '/' ) );

		$nonce = isset( $_POST[ Security::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Security::NONCE_FIELD ] ) ) : '';
		if ( ! Security::verify_form_nonce( $nonce, Registration_Form::ACTION ) ) {
			self::finish( $return, 'expired' );
		}

		// Honeypot: humans never see or fill this field. Pretend success to bots.
		$honeypot = isset( $_POST['sm_company_url'] ) ? sanitize_text_field( wp_unslash( $_POST['sm_company_url'] ) ) : '';
		$started  = isset( $_POST['_sm_started'] ) ? absint( $_POST['_sm_started'] ) : 0;
		if ( '' !== $honeypot || time() - $started < self::MIN_SECONDS ) {
			self::finish( $return, 'received' );
		}

		if ( ! self::within_rate_limit() ) {
			self::finish( $return, 'slow-down' );
		}

		$token = isset( $_POST[ Turnstile::FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Turnstile::FIELD ] ) ) : '';
		if ( ! Turnstile::verify( $token ) ) {
			self::finish( $return, 'spam-check' );
		}

		$types   = Applications::business_types();
		$plans   = Plans::all();
		$plan    = isset( $_POST['plan'] ) ? sanitize_key( wp_unslash( $_POST['plan'] ) ) : '';
		$type    = isset( $_POST['business_type'] ) ? sanitize_key( wp_unslash( $_POST['business_type'] ) ) : '';
		$design  = isset( $_POST['design'] ) ? sanitize_key( wp_unslash( $_POST['design'] ) ) : '';
		$website = isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '';
		$billing = isset( $_POST['billing'] ) ? sanitize_key( wp_unslash( $_POST['billing'] ) ) : '';

		$data    = array(
			'business_name' => isset( $_POST['business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['business_name'] ) ) : '',
			'contact_name'  => isset( $_POST['contact_name'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_name'] ) ) : '',
			'email'         => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'phone'         => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'website'       => $website,
			'business_type' => isset( $types[ $type ] ) ? $type : 'other',
			'plan'          => isset( $plans[ $plan ] ) ? $plan : '',
			'interval'      => in_array( $billing, Plans::intervals_for( $plan ), true ) ? $billing : Plans::DEFAULT_INTERVAL,
			'design'        => Design_Registry::exists( $design ) ? $design : '',
			'codes'         => Code_Catalogue::encode( Printed_Codes::order_from_input( isset( $_POST[ Printed_Codes::FIELD ] ) ? array_map( 'intval', (array) wp_unslash( $_POST[ Printed_Codes::FIELD ] ) ) : array() ) ),
			'ship_region'   => Code_Catalogue::region( isset( $_POST[ Printed_Codes::REGION_FIELD ] ) ? sanitize_key( wp_unslash( $_POST[ Printed_Codes::REGION_FIELD ] ) ) : '' ),
			'message'       => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
		);
		$consent = ! empty( $_POST['consent'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( '' === $data['plan'] || '' === $data['business_name'] || '' === $data['contact_name'] || ! is_email( $data['email'] ) || ! $consent ) {
			self::finish( $return, 'invalid' );
		}

		// Same answer as a new application, so the form never reveals which
		// emails are registered; the address owner gets an email instead.
		if ( email_exists( $data['email'] ) || Applications::has_pending_for( $data['email'] ) ) {
			Registration_Notifier::account_exists( $data );
			self::finish( $return, 'received' );
		}

		// A second try with the same email replaces the earlier unfinished sign-up.
		Abandoned_Checkouts::forget_for( $data['email'] );

		$id = Applications::create( $data );

		if ( ! $id ) {
			self::finish( $return, 'invalid' );
		}

		Applications::set_meta( $id, 'return_url', $return );

		// With Stripe connected the applicant pays straight away; the account is
		// created by the Stripe webhook once the payment goes through.
		if ( Stripe_Settings::is_configured() ) {
			$app = Applications::get( $id );

			if ( $app && 'awaiting_payment' === Application_Approver::approve( $app, '', '', false, true ) ) {
				Applications::set_meta( $id, Abandoned_Checkouts::SELF_CHECKOUT, '1' );
				wp_safe_redirect( Checkout::payment_url( $id ) );
				exit;
			}
		}

		// No Stripe: the application waits for staff approval.
		Registration_Notifier::application_received( $id, $data );
		self::finish( $return, 'received' );
	}

	/**
	 * Allows RATE_LIMIT submissions per hashed IP per hour.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return bool
	 */
	private static function within_rate_limit(): bool {
		$key = 'sm_reg_' . Client_Ip::hash( 'registration' );

		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Redirects back to the form with a result code, anchored to the form.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  string $url     Page to return to.
	 * @param  string $result  Result code (see messages()).
	 * @return never
	 */
	private static function finish( string $url, string $result ): void {
		wp_safe_redirect( add_query_arg( Registration_Form::RESULT_VAR, $result, remove_query_arg( Registration_Form::RESULT_VAR, $url ) ) . '#sm-register' );
		exit;
	}
}
