<?php
/**
 * WordPress core emails, branded.
 *
 * Sends WordPress's own "reset your password" email in the same branded
 * HTML as every other plugin email, so clients see one consistent look.
 * The reset link and its security are untouched — only the wrapping changes.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mail;

use SatoriManifest\Branding\Portal_Brand;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Core_Emails
 *
 * Filters core email content.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Core_Emails {

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'retrieve_password_notification_email', array( self::class, 'password_reset' ), 10, 4 );
	}

	/**
	 * Replaces the plain-text reset email with the branded template.
	 *
	 * Hooked to 'retrieve_password_notification_email'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,mixed> $email       { to, subject, message, headers }.
	 * @param  string              $key         Reset key.
	 * @param  string              $user_login  Username.
	 * @param  \WP_User            $user        User.
	 * @return array<string,mixed>
	 */
	public static function password_reset( array $email, string $key, string $user_login, \WP_User $user ): array {
		$message = Email_Builder::build(
			'password-reset',
			array(
				'contact_name' => '' !== $user->first_name ? $user->first_name : $user->display_name,
				'username'     => $user_login,
				'reset_url'    => network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user_login ), 'login' ),
			)
		);

		if ( '' === $message['subject'] ) {
			return $email;
		}

		// Core passes headers as a string or an array.
		$headers = $email['headers'] ?? array();
		$headers = array_merge(
			is_array( $headers ) ? $headers : array_filter( array( (string) $headers ) ),
			array(
				'Content-Type: text/html; charset=UTF-8',
				sprintf( 'From: %s <%s>', Mailer::from_name(), Mailer::from_email() ),
				sprintf( 'Reply-To: %s', Portal_Brand::support_email() ),
			)
		);

		return array_merge(
			$email,
			array(
				'subject' => $message['subject'],
				'message' => $message['html'],
				'headers' => $headers,
			)
		);
	}
}
