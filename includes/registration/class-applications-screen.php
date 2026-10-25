<?php
/**
 * Applications screen (administrators only).
 *
 * Lists registrations with their details and email history. Approving one:
 *   1. creates the client user (username from the form screen, strong
 *      random password) — which provisions their page, manifest and menu;
 *   2. applies the chosen design and pre-fills branding from the application;
 *   3. emails the client their username and a secure "set your password"
 *      link (passwords are never sent by email).
 * Declining marks the application and can optionally email the applicant.
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
 * Class Applications_Screen
 *
 * Admin UI and actions for applications.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */
class Applications_Screen {

	/**
	 * Screen slug.
	 *
	 * @since 2.1.0
	 * @var   string
	 */
	public const SLUG = 'satori-manifest-applications';

	/**
	 * Action name for admin-post.php.
	 *
	 * @since 2.1.0
	 * @var   string
	 */
	public const ACTION = 'satori_manifest_application';

	/**
	 * Returns the screen URL, optionally filtered by status.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  string $status  Status filter.
	 * @return string
	 */
	public static function url( string $status = '' ): string {
		$args = array( 'page' => self::SLUG );

		if ( '' !== $status ) {
			$args['status'] = $status;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Registers the submenu (with a pending-count badge). Hooked to 'admin_menu'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return void
	 */
	public static function register_menu(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$pending = Applications::pending_count();
		$label   = __( 'Applications', 'satori-manifest' );

		if ( $pending ) {
			$label .= sprintf( ' <span class="awaiting-mod">%d</span>', $pending );
		}

		add_submenu_page(
			Admin_Menu::MENU_SLUG,
			__( 'Applications', 'satori-manifest' ),
			$label,
			'manage_options',
			self::SLUG,
			array( self::class, 'render' ),
			1
		);
	}

	/**
	 * Renders the screen.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage applications.', 'satori-manifest' ), 403 );
		}

		// Display-only filters and flags.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$satori_manifest_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'pending';
		$satori_manifest_notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';
		$satori_manifest_error  = isset( $_GET['sm_error'] ) ? sanitize_text_field( wp_unslash( $_GET['sm_error'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $satori_manifest_status, array_merge( Applications::STATUSES, array( 'all' ) ), true ) ) {
			$satori_manifest_status = 'pending';
		}

		$satori_manifest_apps    = Applications::all( 'all' === $satori_manifest_status ? '' : $satori_manifest_status );
		$satori_manifest_counts  = array(
			'pending'  => count( Applications::all( 'pending' ) ),
			'approved' => count( Applications::all( 'approved' ) ),
			'declined' => count( Applications::all( 'declined' ) ),
		);
		$satori_manifest_types   = Applications::business_types();
		$satori_manifest_designs = Design_Registry::all();

		require SATORI_MANIFEST_PATH . 'admin/views/applications-screen.php';
	}

	/**
	 * Handles approve / decline / delete. Hooked to admin_post_satori_manifest_application.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return void
	 */
	public static function handle(): void {
		// The nonce is read here and verified by Security::verify_form_nonce().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$nonce = isset( $_POST[ Security::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Security::NONCE_FIELD ] ) ) : '';

		if ( ! Security::verify_form_nonce( $nonce, self::ACTION ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'satori-manifest' ), 403 );
		}

		$id       = isset( $_POST['application_id'] ) ? absint( $_POST['application_id'] ) : 0;
		$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$login    = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ), true ) : '';
		$design   = isset( $_POST['design'] ) ? sanitize_key( wp_unslash( $_POST['design'] ) ) : '';
		$reason   = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
		$notify   = ! empty( $_POST['notify'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$app = Applications::get( $id );

		if ( ! $app ) {
			self::back( 'pending', '', __( 'That application no longer exists.', 'satori-manifest' ) );
		}

		if ( 'approve' === $decision ) {
			$result = self::approve( $app, $login, $design, $notify );
			if ( is_wp_error( $result ) ) {
				self::back( 'pending', '', $result->get_error_message() );
			}
			self::back( 'approved', 'approved' );
		}

		if ( 'decline' === $decision ) {
			Applications::set_status( $id, 'declined' );
			if ( $notify ) {
				Applications::log_email(
					$id,
					Mailer::send(
						'application-declined',
						(string) $app['email'],
						array(
							'contact_name'  => (string) $app['contact_name'],
							'business_name' => (string) $app['business_name'],
							'reason'        => $reason,
						)
					)
				);
			}
			self::back( 'declined', 'declined' );
		}

		if ( 'delete' === $decision && 'pending' !== $app['status'] ) {
			wp_delete_post( $id, true );
			self::back( 'all', 'deleted' );
		}

		self::back( 'pending' );
	}

	/**
	 * Approves an application: creates and provisions the client and emails them.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  array<string,mixed> $app     Application.
	 * @param  string              $login   Username (suggested one when empty).
	 * @param  string              $design  Design slug (application preference when empty).
	 * @param  bool                $notify  Whether to send the welcome email.
	 * @return int|\WP_Error  Client user ID or error.
	 */
	public static function approve( array $app, string $login, string $design, bool $notify ) {
		if ( 'pending' !== $app['status'] ) {
			return new \WP_Error( 'satori_manifest_decided', __( 'This application has already been decided.', 'satori-manifest' ) );
		}

		$login  = '' !== $login ? $login : self::suggest_username( (string) $app['business_name'] );
		$design = Design_Registry::exists( $design ) ? $design : (string) $app['design'];

		if ( username_exists( $login ) ) {
			/* translators: %s: username. */
			return new \WP_Error( 'satori_manifest_username', sprintf( __( 'The username "%s" is taken — choose another.', 'satori-manifest' ), $login ) );
		}

		$user_id = Clients_Screen::create_client( (string) $app['business_name'], (string) $app['email'], $login, $design, wp_generate_password( 24, true, true ) );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$names = explode( ' ', trim( (string) $app['contact_name'] ), 2 );
		wp_update_user(
			array(
				'ID'         => $user_id,
				'first_name' => $names[0],
				'last_name'  => $names[1] ?? '',
			)
		);

		// Pre-fill branding with what the applicant told us.
		$page_id = Client_Repository::page_id( $user_id );
		if ( $page_id ) {
			foreach ( array(
				'sm_footer_phone'   => (string) $app['phone'],
				'sm_footer_email'   => (string) $app['email'],
				'sm_social_website' => (string) $app['website'],
			) as $field => $value ) {
				if ( '' !== $value ) {
					Brand::set( $page_id, $field, $value );
				}
			}
		}

		Applications::set_status( (int) $app['id'], 'approved', $user_id );

		if ( $notify ) {
			$user = get_userdata( $user_id );
			$key  = $user ? get_password_reset_key( $user ) : '';

			Applications::log_email(
				(int) $app['id'],
				Mailer::send(
					'application-approved',
					(string) $app['email'],
					array(
						'contact_name'     => (string) ( $names[0] ?? '' ),
						'business_name'    => (string) $app['business_name'],
						'username'         => $login,
						'set_password_url' => ( $user && ! is_wp_error( $key ) )
							? network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( (string) $key ) . '&login=' . rawurlencode( $user->user_login ), 'login' )
							: wp_lostpassword_url(),
						'page_url'         => $page_id ? (string) get_permalink( $page_id ) : '',
					)
				)
			);
		}

		return $user_id;
	}

	/**
	 * Suggests a free username from a business name.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  string $business_name  Business name.
	 * @return string
	 */
	public static function suggest_username( string $business_name ): string {
		$base = sanitize_user( str_replace( '-', '', sanitize_title( $business_name ) ), true );
		$base = '' !== $base ? substr( $base, 0, 40 ) : 'client';
		$name = $base;

		$suffix = 2;
		while ( username_exists( $name ) ) {
			$name = $base . $suffix;
			++$suffix;
		}

		return $name;
	}

	/**
	 * Redirects back to the screen.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  string $status  Tab to show.
	 * @param  string $notice  Notice code.
	 * @param  string $error   Error message.
	 * @return void
	 */
	private static function back( string $status, string $notice = '', string $error = '' ): void {
		$url = self::url( $status );

		if ( '' !== $notice ) {
			$url = add_query_arg( 'sm_notice', $notice, $url );
		}

		if ( '' !== $error ) {
			$url = add_query_arg( 'sm_error', rawurlencode( $error ), $url );
		}

		wp_safe_redirect( $url );
		exit;
	}
}
