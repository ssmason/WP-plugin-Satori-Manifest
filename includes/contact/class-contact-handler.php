<?php
/**
 * Contact form submissions.
 *
 * Spam protection matches the registration form: nonce, hidden honeypot,
 * minimum fill time, a per-visitor rate limit and the optional Turnstile
 * check (skipped for logged-in clients). Messages go to the staff
 * recipients (Manifest → Settings → "Notify staff at") with Reply-To set
 * to the sender, so staff can answer straight from their inbox.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Contact;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Core\Security;
use SatoriManifest\Hardening\Client_Ip;
use SatoriManifest\Hardening\Turnstile;
use SatoriManifest\Mail\Mailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Contact_Handler
 *
 * Validates contact messages and emails them to staff.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */
class Contact_Handler {

	/**
	 * Messages allowed per visitor per hour.
	 *
	 * @since 3.8.0
	 * @var   int
	 */
	private const RATE_LIMIT = 5;

	/**
	 * Seconds a person needs at least to fill in the form.
	 *
	 * @since 3.8.0
	 * @var   int
	 */
	private const MIN_SECONDS = 3;

	/**
	 * Longest message accepted (characters).
	 *
	 * @since 3.8.0
	 * @var   int
	 */
	private const MAX_LENGTH = 5000;

	/**
	 * Hooks the submit handler.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_nopriv_' . Contact_Form::ACTION, array( self::class, 'handle' ) );
		add_action( 'admin_post_' . Contact_Form::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Processes a submission. Hooked to admin_post(_nopriv)_satori_manifest_contact.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return void
	 */
	public static function handle(): void {
		// Fields are read after the nonce is verified via Security::verify_form_nonce() below.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$return = isset( $_POST['_sm_return'] ) ? esc_url_raw( wp_unslash( $_POST['_sm_return'] ) ) : home_url( '/' );
		$return = wp_validate_redirect( $return, home_url( '/' ) );

		$nonce = isset( $_POST[ Security::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Security::NONCE_FIELD ] ) ) : '';
		if ( ! Security::verify_form_nonce( $nonce, Contact_Form::ACTION ) ) {
			self::finish( $return, 'expired' );
		}

		// Honeypot and fill time: pretend success to bots.
		$honeypot = isset( $_POST['sm_company_url'] ) ? sanitize_text_field( wp_unslash( $_POST['sm_company_url'] ) ) : '';
		$started  = isset( $_POST['_sm_started'] ) ? absint( $_POST['_sm_started'] ) : 0;
		if ( '' !== $honeypot || time() - $started < self::MIN_SECONDS ) {
			self::finish( $return, 'sent' );
		}

		if ( ! self::within_rate_limit() ) {
			self::finish( $return, 'slow-down' );
		}

		$user = wp_get_current_user();

		if ( ! $user->exists() ) {
			$token = isset( $_POST[ Turnstile::FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Turnstile::FIELD ] ) ) : '';
			if ( ! Turnstile::verify( $token ) ) {
				self::finish( $return, 'spam-check' );
			}
		}

		$data = array(
			'name'    => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'email'   => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'message' => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		self::finish( $return, self::send( $data, $user->exists() ? (int) $user->ID : 0 ) );
	}

	/**
	 * Emails a message to every staff recipient.
	 *
	 * For a logged-in user the name and email come from their account and
	 * their business and page are added.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  array{name:string,email:string,message:string} $data     Sanitised fields.
	 * @param  int                                            $user_id  Logged-in user, or 0.
	 * @return string  Result code: sent, invalid or failed.
	 */
	public static function send( array $data, int $user_id = 0 ): string {
		$user = $user_id > 0 ? get_userdata( $user_id ) : false;
		$vars = array(
			'source'        => __( 'Website contact form', 'satori-manifest' ),
			'name'          => trim( $data['name'] ),
			'email'         => trim( $data['email'] ),
			'message'       => trim( mb_substr( $data['message'], 0, self::MAX_LENGTH ) ),
			'business_name' => '',
			'page_url'      => '',
		);

		if ( $user ) {
			$vars['source'] = Client_Role::is_client( $user_id ) ? __( 'Client dashboard', 'satori-manifest' ) : __( 'Dashboard', 'satori-manifest' );
			$vars['name']   = (string) $user->display_name;
			$vars['email']  = (string) $user->user_email;

			if ( Client_Role::is_client( $user_id ) ) {
				$vars['business_name'] = (string) $user->display_name;
				$page_id               = Client_Repository::page_id( $user_id );
				$vars['page_url']      = $page_id ? (string) get_permalink( $page_id ) : '';
			}
		}

		if ( '' === $vars['name'] || ! is_email( $vars['email'] ) || '' === $vars['message'] ) {
			return 'invalid';
		}

		$sent = false;

		foreach ( Mailer::staff_recipients() as $recipient ) {
			$sent = Mailer::send( 'contact-message', $recipient, $vars, $vars['email'] )['sent'] || $sent;
		}

		return $sent ? 'sent' : 'failed';
	}

	/**
	 * Allows a few messages per visitor per hour.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return bool
	 */
	private static function within_rate_limit(): bool {
		$key   = 'sm_contact_' . Client_Ip::hash( 'contact' );
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
	 * @since  3.8.0
	 * @param  string $url     Page to return to.
	 * @param  string $result  Result code (see Contact_Form::messages()).
	 * @return never
	 */
	private static function finish( string $url, string $result ): void {
		wp_safe_redirect( add_query_arg( Contact_Form::RESULT_VAR, $result, remove_query_arg( Contact_Form::RESULT_VAR, $url ) ) . '#sm-contact' );
		exit;
	}
}
