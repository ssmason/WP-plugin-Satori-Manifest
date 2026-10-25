<?php
/**
 * Brevo transactional email transport.
 *
 * Sends through Brevo's HTTP API (POST https://api.brevo.com/v3/smtp/email).
 * Enabled automatically when an API key is configured:
 *
 *   BREVO_API_KEY=xkeysib-…           (.env, or define( 'BREVO_API_KEY', … ))
 *
 * Optional: BREVO_TEMPLATE_{NAME} to send a Brevo-hosted template instead of
 * the plugin's HTML, e.g. BREVO_TEMPLATE_APPLICATION_APPROVED=12. Placeholder
 * values are then passed as Brevo "params" ({{ params.username }}).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */

declare( strict_types=1 );

namespace SatoriManifest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Brevo_Transport
 *
 * Minimal Brevo API client for transactional email.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */
class Brevo_Transport {

	/**
	 * Brevo transactional email endpoint.
	 *
	 * @since 2.1.0
	 * @var   string
	 */
	private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

	/**
	 * Returns true when an API key is configured.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return bool
	 */
	public static function is_configured(): bool {
		return '' !== Mailer::setting( 'BREVO_API_KEY' );
	}

	/**
	 * Sends one email through Brevo.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  string               $to       Recipient.
	 * @param  array<string,string> $message  { subject, html, text }.
	 * @param  string               $template Optional template name (for a Brevo template ID).
	 * @param  array<string,string> $params   Optional params for a Brevo template.
	 * @return array{sent:bool,error:string}
	 */
	public static function send( string $to, array $message, string $template = '', array $params = array() ): array {
		$payload = array(
			'sender'  => array(
				'name'  => Mailer::from_name(),
				'email' => Mailer::from_email(),
			),
			'to'      => array( array( 'email' => $to ) ),
			'subject' => $message['subject'],
		);

		$template_id = (int) Mailer::setting( 'BREVO_TEMPLATE_' . strtoupper( str_replace( '-', '_', $template ) ) );

		if ( $template_id > 0 ) {
			$payload['templateId'] = $template_id;
			$payload['params']     = (object) $params;
		} else {
			$payload['htmlContent'] = $message['html'];
			$payload['textContent'] = $message['text'];
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 15,
				'headers' => array(
					'accept'       => 'application/json',
					'content-type' => 'application/json',
					'api-key'      => Mailer::setting( 'BREVO_API_KEY' ),
				),
				'body'    => (string) wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'sent'  => false,
				'error' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			return array(
				'sent'  => true,
				'error' => '',
			);
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return array(
			'sent'  => false,
			'error' => sprintf( 'Brevo %d: %s', $code, (string) ( $body['message'] ?? 'unknown error' ) ),
		);
	}
}
