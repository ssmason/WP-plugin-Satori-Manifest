<?php
/**
 * Transactional email.
 *
 * Every email the plugin sends goes through Mailer::send():
 *
 *   1. The message is built from a YAML template in config/emails/{name}.yaml
 *      (subject, heading, body, optional button) with {{placeholders}}, and
 *      wrapped in templates/email/layout.php (see Email_Builder).
 *   2. It is delivered by the active transport:
 *        - "brevo"   when a Brevo API key is configured (see Brevo_Transport),
 *        - "wp_mail" otherwise (or whatever SMTP plugin the site uses).
 *      Override with the 'satori_manifest_mail_transport' filter.
 *   3. The result is returned so callers can log it (applications keep a
 *      per-application email history).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mail;

use SatoriManifest\Branding\Portal_Brand;
use SatoriManifest\Core\Env;
use SatoriManifest\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mailer
 *
 * Builds and sends templated emails.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */
class Mailer {

	/**
	 * Sends a templated email.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  string               $template  Template name (config/emails/{template}.yaml).
	 * @param  string               $to        Recipient email address.
	 * @param  array<string,string> $vars      Placeholder values (unescaped).
	 * @return array{template:string,to:string,subject:string,transport:string,sent:bool,error:string,time:int}
	 */
	public static function send( string $template, string $to, array $vars = array() ): array {
		$message   = Email_Builder::build( $template, $vars );
		$transport = self::transport();
		$result    = array(
			'template'  => $template,
			'to'        => $to,
			'subject'   => $message['subject'],
			'transport' => $transport,
			'sent'      => false,
			'error'     => '',
			'time'      => time(),
		);

		if ( '' === $message['subject'] || ! is_email( $to ) ) {
			$result['error'] = 'Missing template or invalid recipient.';
			return self::log( $result );
		}

		/**
		 * Short-circuits delivery (e.g. to queue emails or use another API).
		 *
		 * Return an array with "sent" (bool) and optionally "error" (string)
		 * to skip the built-in transports.
		 *
		 * @since 2.1.0
		 * @param array|null           $pre       Null to continue.
		 * @param array<string,string> $message   { subject, html, text }.
		 * @param string               $to        Recipient.
		 * @param string               $template  Template name.
		 */
		$pre = apply_filters( 'satori_manifest_pre_send_email', null, $message, $to, $template );

		if ( is_array( $pre ) ) {
			$result['sent']  = ! empty( $pre['sent'] );
			$result['error'] = (string) ( $pre['error'] ?? '' );
			return self::log( $result );
		}

		if ( 'brevo' === $transport ) {
			$delivery = Brevo_Transport::send( $to, $message, $template, array_map( 'strval', $vars ) );
		} else {
			$delivery = array(
				'sent'  => wp_mail(
					$to,
					$message['subject'],
					$message['html'],
					array(
						'Content-Type: text/html; charset=UTF-8',
						sprintf( 'From: %s <%s>', self::from_name(), self::from_email() ),
						sprintf( 'Reply-To: %s', Portal_Brand::support_email() ),
					)
				),
				'error' => '',
			);

			if ( ! $delivery['sent'] ) {
				$delivery['error'] = 'wp_mail() returned false — check the site mail configuration.';
			}
		}

		$result['sent']  = (bool) $delivery['sent'];
		$result['error'] = (string) $delivery['error'];

		return self::log( $result );
	}

	/**
	 * Returns the active transport name.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return string  "brevo" or "wp_mail".
	 */
	public static function transport(): string {
		$transport = Brevo_Transport::is_configured() ? 'brevo' : 'wp_mail';

		/**
		 * Filters the email transport ("brevo" or "wp_mail").
		 *
		 * @since 2.1.0
		 * @param string $transport  Transport name.
		 */
		return (string) apply_filters( 'satori_manifest_mail_transport', $transport );
	}

	/**
	 * Returns the sender email address.
	 *
	 * Set SATORI_MANIFEST_MAIL_FROM (constant or environment variable) to
	 * override; defaults to the site admin email.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return string
	 */
	public static function from_email(): string {
		$from = Env::get( 'SATORI_MANIFEST_MAIL_FROM' );
		return is_email( $from ) ? $from : (string) get_option( 'admin_email' );
	}

	/**
	 * Returns the sender name (SATORI_MANIFEST_MAIL_FROM_NAME or the site title).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return string
	 */
	public static function from_name(): string {
		$name = Env::get( 'SATORI_MANIFEST_MAIL_FROM_NAME' );
		return '' !== $name ? $name : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * Returns the addresses that receive staff notifications.
	 *
	 * Manifest → Settings "Notify staff at", else the site admin email.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return array<int,string>
	 */
	public static function staff_recipients(): array {
		/**
		 * Filters who is notified about new applications.
		 *
		 * @since 2.1.0
		 * @param array<int,string> $emails  Email addresses.
		 */
		$configured = array_filter( array_map( 'trim', explode( ',', (string) Settings::get( 'staff_emails' ) ) ), 'is_email' );
		$emails     = $configured ? $configured : array( (string) get_option( 'admin_email' ) );

		return array_values( array_filter( (array) apply_filters( 'satori_manifest_staff_recipients', $emails ), 'is_email' ) );
	}

	/**
	 * Logs a failed send in debug mode and returns the result unchanged.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  array<string,mixed> $result  Send result.
	 * @return array<string,mixed>
	 */
	private static function log( array $result ): array {
		if ( ! $result['sent'] && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug-only delivery diagnostics.
			error_log( sprintf( 'Satori Manifest email "%s" to %s not sent via %s: %s', $result['template'], $result['to'], $result['transport'], $result['error'] ) );
		}

		/**
		 * Fires after every email attempt.
		 *
		 * @since 2.1.0
		 * @param array<string,mixed> $result  Send result.
		 */
		do_action( 'satori_manifest_email_sent', $result );

		return $result;
	}
}
