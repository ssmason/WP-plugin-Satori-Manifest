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

		Qr_Sign::save(
			$page_id,
			array(
				'size'     => isset( $_POST['size'] ) ? sanitize_key( wp_unslash( $_POST['size'] ) ) : '',
				'title'    => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'subtitle' => isset( $_POST['subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['subtitle'] ) ) : '',
				'colour'   => isset( $_POST['colour'] ) ? sanitize_hex_color( wp_unslash( $_POST['colour'] ) ) : '',
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		wp_safe_redirect( add_query_arg( 'sm_notice', 'saved', Admin_Menu::url( Admin_Menu::QR_SLUG, Client_Repository::client_for_page( $page_id ) ) ) );
		exit;
	}
}
