<?php
/**
 * Settings screen (administrators only).
 *
 * Portal branding (logo, colours), support and staff email addresses, the
 * payment default for approvals, plus a read-only status of the secrets kept
 * in the environment (Stripe, email).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Admin\Screens;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Billing\Stripe_Settings;
use SatoriManifest\Core\Security;
use SatoriManifest\Core\Settings;
use SatoriManifest\Mail\Mailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings_Screen
 *
 * Renders and saves the Settings screen.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Settings_Screen {

	/**
	 * Action name for admin-post.php.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	public const ACTION = 'satori_manifest_save_settings';

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Renders the screen.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage settings.', 'satori-manifest' ), 403 );
		}

		wp_enqueue_media();

		$satori_manifest_settings = Settings::all();
		$satori_manifest_logo     = $satori_manifest_settings['portal_logo_id'] ? (string) wp_get_attachment_image_url( (int) $satori_manifest_settings['portal_logo_id'], 'medium' ) : '';
		$satori_manifest_status   = array(
			'stripe_mode'    => Stripe_Settings::mode(),
			'stripe_webhook' => Stripe_Settings::has_webhook_secret(),
			'webhook_url'    => Stripe_Settings::webhook_url(),
			'mail_transport' => Mailer::transport(),
			'mail_from'      => Mailer::from_name() . ' <' . Mailer::from_email() . '>',
			'smtp_plugin'    => defined( 'WPMS_PLUGIN_VER' ),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own redirect.
		$satori_manifest_saved = isset( $_GET['sm_notice'] );

		require SATORI_MANIFEST_PATH . 'templates/admin/settings.php';
	}

	/**
	 * Saves the settings. Hooked to admin_post_satori_manifest_save_settings.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function handle(): void {
		// The nonce is read here and verified by Security::verify_form_nonce().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$nonce = isset( $_POST[ Security::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Security::NONCE_FIELD ] ) ) : '';

		if ( ! Security::verify_form_nonce( $nonce, self::ACTION ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'satori-manifest' ), 403 );
		}

		// Each value is sanitised field by field in Settings::sanitize().
		$input = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? map_deep( wp_unslash( $_POST['settings'] ), 'sanitize_text_field' ) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		Settings::save( $input );

		wp_safe_redirect( add_query_arg( 'sm_notice', 'saved', Admin_Menu::url( Admin_Menu::SETTINGS_SLUG ) ) );
		exit;
	}
}
