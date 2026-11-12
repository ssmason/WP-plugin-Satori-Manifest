<?php
/**
 * Sends WordPress's own emails through Brevo.
 *
 * Plugin emails already use Brevo when it is configured (see Mailer). This
 * catches everything else that calls wp_mail() - password resets from the
 * login screen, two-factor codes, admin notices, other plugins - so one
 * API key covers all site email and no SMTP setup is needed. Messages with
 * attachments are left to wp_mail().
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mail;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Mail_Relay
 *
 * Short-circuits wp_mail() and hands the message to Brevo_Transport.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */
class WP_Mail_Relay {

	/**
	 * Hooks into wp_mail().
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'pre_wp_mail', array( self::class, 'relay' ), 10, 2 );
	}

	/**
	 * Sends the message through Brevo, or lets wp_mail() carry on.
	 *
	 * Hooked to 'pre_wp_mail'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  null|bool           $short_circuit  Earlier filter result.
	 * @param  array<string,mixed> $atts           wp_mail() arguments: to, subject, message, headers, attachments.
	 * @return null|bool  Null to continue with wp_mail(), otherwise whether it was sent.
	 */
	public static function relay( $short_circuit, array $atts ) {
		if ( null !== $short_circuit || ! Brevo_Transport::is_configured() || ! empty( $atts['attachments'] ) ) {
			return $short_circuit;
		}

		$recipients = self::recipients( $atts['to'] ?? array() );

		if ( ! $recipients ) {
			return $short_circuit;
		}

		$message = self::message( (string) ( $atts['subject'] ?? '' ), (string) ( $atts['message'] ?? '' ), self::headers( $atts['headers'] ?? array() ) );
		$errors  = array();

		// One message per recipient, so recipients never see each other's address.
		foreach ( $recipients as $to ) {
			$delivery = Brevo_Transport::send( $to, $message );

			if ( ! $delivery['sent'] ) {
				$errors[] = $delivery['error'];
			}
		}

		if ( $errors ) {
			do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', implode( '; ', $errors ), $atts ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
			return false;
		}

		do_action( 'wp_mail_succeeded', $atts ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
		return true;
	}

	/**
	 * Valid addresses from wp_mail()'s "to" (string, comma list or array; "Name <email>" allowed).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  mixed $to  Recipients.
	 * @return array<int,string>
	 */
	public static function recipients( $to ): array {
		$list = is_array( $to ) ? $to : explode( ',', (string) $to );
		$out  = array();

		foreach ( $list as $entry ) {
			$entry = trim( (string) $entry );

			if ( preg_match( '/<([^>]+)>/', $entry, $match ) ) {
				$entry = $match[1];
			}

			$email = sanitize_email( $entry );

			if ( is_email( $email ) ) {
				$out[] = $email;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Lower-cased header name → value from wp_mail()'s headers.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  mixed $headers  String (one per line) or array.
	 * @return array<string,string>
	 */
	public static function headers( $headers ): array {
		$lines = is_array( $headers ) ? $headers : explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
		$out   = array();

		foreach ( $lines as $line ) {
			$pair = explode( ':', (string) $line, 2 );

			if ( 2 === count( $pair ) ) {
				$out[ strtolower( trim( $pair[0] ) ) ] = trim( $pair[1] );
			}
		}

		return $out;
	}

	/**
	 * Subject, HTML and plain-text bodies (and Reply-To) for Brevo.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  string               $subject  Subject.
	 * @param  string               $body     Body as given to wp_mail().
	 * @param  array<string,string> $headers  Parsed headers.
	 * @return array<string,string>
	 */
	public static function message( string $subject, string $body, array $headers ): array {
		$content_type = (string) apply_filters( 'wp_mail_content_type', $headers['content-type'] ?? 'text/plain' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
		$is_html      = false !== stripos( $content_type, 'text/html' );

		$message = array(
			'subject' => wp_specialchars_decode( $subject, ENT_QUOTES ),
			'html'    => $is_html ? $body : '<div style="font-family:sans-serif;white-space:pre-wrap">' . make_clickable( esc_html( $body ) ) . '</div>',
			'text'    => $is_html ? wp_strip_all_tags( $body ) : $body,
		);

		$reply_to = self::recipients( $headers['reply-to'] ?? '' );

		if ( $reply_to ) {
			$message['reply_to'] = $reply_to[0];
		}

		return $message;
	}
}
