<?php
/**
 * Client screen: QR builder - design a printable QR sign (small or large).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Admin\Screens;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Core\Security;
use SatoriManifest\Qr\Qr_Sign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Qr_Builder_Screen
 *
 * Reads input, saves through Qr_Sign and renders templates/admin/qr-builder.php.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */
final class Qr_Builder_Screen {

	/**
	 * Form action (admin-post.php) and nonce action.
	 */
	public const ACTION = 'satori_manifest_qr_sign';

	/**
	 * Ajax action for the live preview.
	 */
	public const PREVIEW_ACTION = 'satori_manifest_qr_preview';

	/**
	 * Renders the screen.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @return void
	 */
	public static function render(): void {
		$satori_manifest_client_id = Admin_Menu::current_client_id();
		$satori_manifest_page_id   = $satori_manifest_client_id ? Client_Repository::page_id( $satori_manifest_client_id ) : 0;

		if ( ! $satori_manifest_page_id ) {
			echo '<div class="wrap sm-admin"><p>' . esc_html__( 'Your page has not been set up yet.', 'satori-manifest' ) . '</p></div>';
			return;
		}

		$satori_manifest_settings = Qr_Sign::settings( $satori_manifest_page_id );
		$satori_manifest_previews = array();

		// Both sizes are rendered so the toggle switches instantly; the form updates them as you type.
		foreach ( Qr_Sign::SIZES as $satori_manifest_size ) {
			$satori_manifest_previews[ $satori_manifest_size ] = Qr_Sign::svg( $satori_manifest_page_id, array_merge( $satori_manifest_settings, array( 'size' => $satori_manifest_size ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own redirect.
		$satori_manifest_notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';

		require SATORI_MANIFEST_PATH . 'templates/admin/qr-builder.php';
	}

	/**
	 * Saves the sign. Hooked to admin_post_satori_manifest_qr_sign.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.11.0
	 * @return void
	 */
	public static function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified with Security::verify_form_nonce() first.
		$nonce = isset( $_POST[ Security::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Security::NONCE_FIELD ] ) ) : '';

		if ( ! Security::verify_form_nonce( $nonce, self::ACTION ) ) {
			wp_die( esc_html__( 'This link has expired. Please go back and try again.', 'satori-manifest' ), 403 );
		}

		$page_id = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;

		if ( ! Client_Repository::is_client_page( $page_id ) || ! current_user_can( 'edit_post', $page_id ) ) {
			wp_die( esc_html__( 'Sorry, you cannot change this sign.', 'satori-manifest' ), 403 );
		}

		Qr_Sign::save( $page_id, self::input() );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		wp_safe_redirect( add_query_arg( 'sm_notice', 'saved', Admin_Menu::url( Admin_Menu::QR_SLUG, Client_Repository::client_for_page( $page_id ) ) ) );
		exit;
	}

	/**
	 * Live preview: the sign as SVG for the form as it stands (not saved).
	 * Hooked to wp_ajax_satori_manifest_qr_preview; the form's own nonce.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @return void
	 */
	public static function preview(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified with Security::verify_form_nonce() first.
		$nonce   = isset( $_POST[ Security::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Security::NONCE_FIELD ] ) ) : '';
		$page_id = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;

		if ( ! Security::verify_form_nonce( $nonce, self::ACTION ) || ! Client_Repository::is_client_page( $page_id ) || ! current_user_can( 'edit_post', $page_id ) ) {
			wp_send_json_error( null, 403 );
		}

		wp_send_json_success( array( 'svg' => Qr_Sign::svg( $page_id, Qr_Sign::clean( self::input(), $page_id ) ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * The sign fields from the posted form (Qr_Sign::clean() does the rest).
	 * Callers verify the nonce first.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @return array<string,string>
	 */
	private static function input(): array {
		$input = array();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by the callers.
		foreach ( array( 'size', 'title', 'subtitle', 'colour', 'border', 'corners', 'footer', 'footer_colour', 'footer_font' ) as $key ) {
			$input[ $key ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $input;
	}
}
