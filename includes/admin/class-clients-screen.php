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

namespace SatoriManifest;

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
			$summary                = Client_Home::summary( $client->ID );
			$satori_manifest_rows[] = $summary;
		}

		$satori_manifest_designs = Design_Registry::all();

		// Display-only flags set by our own redirect.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$satori_manifest_notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';
		$satori_manifest_error  = isset( $_GET['sm_error'] ) ? sanitize_text_field( wp_unslash( $_GET['sm_error'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		require SATORI_MANIFEST_PATH . 'admin/views/clients-screen.php';
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

		$result = self::create_client( $name, $email, $login, $design );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'sm_error', rawurlencode( $result->get_error_message() ), Admin_Menu::url( Admin_Menu::MENU_SLUG ) ) );
			exit;
		}

		if ( $notify ) {
			wp_new_user_notification( $result, null, 'user' );
		}

		wp_safe_redirect( add_query_arg( 'sm_notice', 'added', Admin_Menu::url( Admin_Menu::MENU_SLUG ) ) );
		exit;
	}

	/**
	 * Creates a client user and provisions their page, manifest and menu.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $name      Business name.
	 * @param  string $email     Email address.
	 * @param  string $login     Username (generated from the name when empty).
	 * @param  string $design    Design slug.
	 * @param  string $password  Password (random when empty).
	 * @return int|\WP_Error  New user ID or error.
	 */
	public static function create_client( string $name, string $email, string $login = '', string $design = '', string $password = '' ) {
		if ( '' === $name || ! is_email( $email ) ) {
			return new \WP_Error( 'satori_manifest_invalid', __( 'Please enter a business name and a valid email address.', 'satori-manifest' ) );
		}

		if ( '' === $login ) {
			$login = sanitize_user( str_replace( '-', '', sanitize_title( $name ) ), true );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => '' !== $password ? $password : wp_generate_password( 20 ),
				'display_name' => $name,
				'nickname'     => $name,
				'role'         => Client_Role::ROLE,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Role assignment already provisioned with defaults; apply the chosen design.
		$page_id = Client_Repository::page_id( $user_id );
		if ( $page_id && Design_Registry::exists( $design ) ) {
			Design_Screen::apply( $page_id, $design, true );
		}

		return $user_id;
	}
}
