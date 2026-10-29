<?php
/**
 * Plugin settings.
 *
 * Non-secret settings edited under Manifest → Settings, stored in one option.
 * Secrets (Stripe, Brevo keys) never live here — see Core\Env.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 *
 * Typed access to the satori_manifest_settings option.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Settings {

	/**
	 * Option name.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	public const OPTION = 'satori_manifest_settings';

	/**
	 * Default values.
	 *
	 * Keys:
	 * portal_logo_id   Logo on the login screen, client dashboard and emails (0 = site logo).
	 * accent_color     Buttons and highlights in the portal and emails.
	 * dark_color       Headings, login background and email header.
	 * support_email    Shown to clients in Help and emails (blank = admin email).
	 * staff_emails     Who is told about applications and payments (blank = admin email).
	 * require_payment  Pre-ticks "Take payment before creating the account" on approval.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'portal_logo_id'  => 0,
			'accent_color'    => '#f2a65a',
			'dark_color'      => '#0b1620',
			'support_email'   => '',
			'staff_emails'    => '',
			'require_payment' => true,
		);
	}

	/**
	 * Returns every setting merged over the defaults.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Returns one setting.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $key  Setting key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		return self::all()[ $key ] ?? null;
	}

	/**
	 * Sanitises and saves submitted settings.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,mixed> $input  Raw (unslashed) values.
	 * @return void
	 */
	public static function save( array $input ): void {
		update_option( self::OPTION, self::sanitize( $input ), false );
	}

	/**
	 * Sanitises settings input.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,mixed> $input  Raw values.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ): array {
		$defaults = self::defaults();
		$emails   = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) ( $input['staff_emails'] ?? '' ) ) ) ), 'is_email' );
		$accent   = sanitize_hex_color( (string) ( $input['accent_color'] ?? '' ) );
		$dark     = sanitize_hex_color( (string) ( $input['dark_color'] ?? '' ) );

		return array(
			'portal_logo_id'  => absint( $input['portal_logo_id'] ?? 0 ),
			'accent_color'    => $accent ? $accent : $defaults['accent_color'],
			'dark_color'      => $dark ? $dark : $defaults['dark_color'],
			'support_email'   => is_email( (string) ( $input['support_email'] ?? '' ) ) ? sanitize_email( (string) $input['support_email'] ) : '',
			'staff_emails'    => implode( ', ', $emails ),
			'require_payment' => ! empty( $input['require_payment'] ),
		);
	}
}
