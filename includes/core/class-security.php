<?php
/**
 * Security helpers.
 *
 * Centralises nonce creation, verification, and capability checks so all
 * admin form saves and AJAX handlers consume a single, auditable surface.
 * Any code that needs to create, output, or verify a plugin nonce should
 * go through this class rather than calling WP nonce functions directly.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Security
 *
 * Nonce helpers and capability checks for the Satori Manifest plugin.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */
class Security {

	/**
	 * Nonce action prefix — prepended to every action key.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private const NONCE_PREFIX = 'satori_manifest_';

	/**
	 * Shared nonce field name used across all plugin forms.
	 *
	 * Centralised here so Meta_Box and any future forms reference one constant
	 * rather than duplicating the string.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public const NONCE_FIELD = 'satori_manifest_nonce';

	/**
	 * Outputs the nonce hidden field for embedding in a standard HTML form.
	 *
	 * Uses the shared NONCE_FIELD name so the corresponding verify_form_nonce()
	 * call can locate the value in $_POST without needing to know the field name.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @param  string $action   Nonce action key (without prefix).
	 * @param  bool   $referer  Whether to also output the referer hidden field.
	 * @return void
	 */
	public static function output_form_nonce( string $action, bool $referer = true ): void {
		wp_nonce_field( self::NONCE_PREFIX . $action, self::NONCE_FIELD, $referer );
	}

	/**
	 * Verifies a nonce value from a standard HTML form submission.
	 *
	 * Unlike verify_ajax_nonce(), this returns a boolean rather than dying,
	 * giving the caller control over how to handle a failed verification.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @param  string $nonce   The nonce value to verify.
	 * @param  string $action  Nonce action key (without prefix).
	 * @return bool            True if the nonce is valid, false otherwise.
	 */
	public static function verify_form_nonce( string $nonce, string $action ): bool {
		return (bool) wp_verify_nonce( $nonce, self::NONCE_PREFIX . $action );
	}
}
