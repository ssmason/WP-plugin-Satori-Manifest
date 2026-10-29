<?php
/**
 * Clients screen (staff only).
 *
 * Overview of every client with links to their page, price list, design
 * and branding, plus a quick "add client" form that creates the user and
 * provisions their page, manifest and menu in one step.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Admin\Screens;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Clients\Client_Factory;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Clients\Client_Summary;
use SatoriManifest\Clients\Set_Password_Link;
use SatoriManifest\Core\Security;
use SatoriManifest\Designs\Design_Registry;
use SatoriManifest\Mail\Mailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Clients_Screen
 *
 * Lists clients and handles the add-client form.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Clients_Screen {

	/**
	 * Action name for admin-post.php (add a client).
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	public const ACTION = 'satori_manifest_add_client';

	/**
	 * Renders the screen.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function render(): void {
		$satori_manifest_rows = array();

		foreach ( Client_Repository::all_clients() as $client ) {
			$summary                = Client_Summary::for_user( $client->ID );
			$satori_manifest_rows[] = $summary;
		}

		$satori_manifest_designs = Design_Registry::all();

		// Display-only flags set by our own redirect.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$satori_manifest_notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';
		$satori_manifest_error  = isset( $_GET['sm_error'] ) ? sanitize_text_field( wp_unslash( $_GET['sm_error'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		require SATORI_MANIFEST_PATH . 'templates/admin/clients.php';
	}

	/**
	 * Creates a client. Hooked to 'admin_post_satori_manifest_add_client'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function handle(): void {
		// The nonce is read here and verified by Security::verify_form_nonce().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$nonce = isset( $_POST[ Security::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Security::NONCE_FIELD ] ) ) : '';

		if ( ! Security::verify_form_nonce( $nonce, self::ACTION ) || ! current_user_can( 'create_users' ) ) {
			wp_die( esc_html__( 'Sorry, you cannot add clients.', 'satori-manifest' ), 403 );
		}

		$name   = isset( $_POST['business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['business_name'] ) ) : '';
		$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$login  = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ), true ) : '';
		$design = isset( $_POST['design'] ) ? sanitize_key( wp_unslash( $_POST['design'] ) ) : '';
		$notify = ! empty( $_POST['notify'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$result = Client_Factory::create( $name, $email, $login, $design );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'sm_error', rawurlencode( $result->get_error_message() ), Admin_Menu::url( Admin_Menu::MENU_SLUG ) ) );
			exit;
		}

		if ( $notify ) {
			self::send_welcome( (int) $result, $name );
		}

		wp_safe_redirect( add_query_arg( 'sm_notice', 'added', Admin_Menu::url( Admin_Menu::MENU_SLUG ) ) );
		exit;
	}

	/**
	 * Emails a hand-added client their set-password link.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  int    $user_id        Client user ID.
	 * @param  string $business_name  Business name.
	 * @return void
	 */
	private static function send_welcome( int $user_id, string $business_name ): void {
		$user    = get_userdata( $user_id );
		$page_id = Client_Repository::page_id( $user_id );

		if ( ! $user ) {
			return;
		}

		Mailer::send(
			'account-created',
			$user->user_email,
			array(
				'contact_name'     => $business_name,
				'business_name'    => $business_name,
				'username'         => $user->user_login,
				'set_password_url' => Set_Password_Link::for_user( $user_id ),
				'page_url'         => $page_id ? (string) get_permalink( $page_id ) : '',
			)
		);
	}
}
