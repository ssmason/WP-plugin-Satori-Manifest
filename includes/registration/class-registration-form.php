<?php
/**
 * Public registration form.
 *
 * Rendered by the satori-manifest/registration-form block. Submissions post
 * to admin-post.php (logged-in or not), are validated and spam-checked, and
 * stored as a pending application. The applicant and staff are emailed.
 * The visitor is redirected back with a status flag (post/redirect/get).
 *
 * Spam protection without third parties: nonce, hidden honeypot field,
 * minimum fill time, and a per-IP rate limit.
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
 * Class Registration_Form
 *
 * Renders and processes the registration form.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */
class Registration_Form {

	/**
	 * Form action (admin-post.php) and nonce action.
	 *
	 * @since 2.1.0
	 * @var   string
	 */
	public const ACTION = 'satori_manifest_register';

	/**
	 * Query var carrying the result back to the form.
	 *
	 * @since 2.1.0
	 * @var   string
	 */
	public const RESULT_VAR = 'sm_registration';

	/**
	 * Minimum seconds between rendering and submitting (bots are faster).
	 *
	 * @since 2.1.0
	 * @var   int
	 */
	private const MIN_SECONDS = 3;

	/**
	 * Maximum submissions per IP per hour.
	 *
	 * @since 2.1.0
	 * @var   int
	 */
	private const RATE_LIMIT = 5;

	/**
	 * Registers submission handlers.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( self::class, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Renders the form (or the result message after submitting).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  string $wrapper_attributes  Pre-escaped block wrapper attributes.
	 * @return string
	 */
	public static function render( string $wrapper_attributes = '' ): string {
		// Display-only flag set by our own redirect.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$satori_manifest_result  = isset( $_GET[ self::RESULT_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT_VAR ] ) ) : '';
		$satori_manifest_fields  = Applications::fields();
		$satori_manifest_types   = Applications::business_types();
		$satori_manifest_designs = Design_Registry::all();
		$satori_manifest_plans   = Applications::plans();
		$satori_manifest_wrapper = $wrapper_attributes;

		// Optional pre-selection via ?plan=slug (e.g. from a pricing link).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$satori_manifest_plan = isset( $_GET['plan'] ) ? sanitize_key( wp_unslash( $_GET['plan'] ) ) : '';

		ob_start();
		require SATORI_MANIFEST_PATH . 'templates/registration-form.php';
		return (string) ob_get_clean();
	}

	/**
	 * Messages shown after submitting, keyed by result code.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return array<string,array{type:string,text:string}>
	 */
	public static function messages(): array {
		return array(
			'received'  => array(
				'type' => 'success',
				'text' => __( 'Thank you! Your application is in. We review every application personally and will email you as soon as your page is ready.', 'satori-manifest' ),
			),
			'invalid'   => array(
				'type' => 'error',
				'text' => __( 'Please choose a plan, fill in your business name, your name and a valid email address, and accept the terms.', 'satori-manifest' ),
			),
			'duplicate' => array(
				'type' => 'info',
				'text' => __( 'We already have an application or an account for that email address. We will be in touch — or log in if you already have an account.', 'satori-manifest' ),
			),
			'slow-down' => array(
				'type' => 'error',
				'text' => __( 'Too many attempts. Please try again in a little while.', 'satori-manifest' ),
			),
			'expired'   => array(
				'type' => 'error',
				'text' => __( 'Your session expired. Please submit the form again.', 'satori-manifest' ),
			),
		);
	}

	/**
	 * Processes a submission. Hooked to admin_post(_nopriv)_satori_manifest_register.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return void
	 */
	public static function handle(): void {
		// Fields are read after the nonce is verified via wp_verify_nonce() below.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$return = isset( $_POST['_sm_return'] ) ? esc_url_raw( wp_unslash( $_POST['_sm_return'] ) ) : home_url( '/' );
		$return = wp_validate_redirect( $return, home_url( '/' ) );

		$nonce = isset( $_POST['_sm_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_sm_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::ACTION ) ) {
			self::finish( $return, 'expired' );
		}

		// Honeypot: humans never see or fill this field. Pretend success to bots.
		$honeypot = isset( $_POST['sm_company_url'] ) ? sanitize_text_field( wp_unslash( $_POST['sm_company_url'] ) ) : '';
		$started  = isset( $_POST['_sm_started'] ) ? absint( $_POST['_sm_started'] ) : 0;
		if ( '' !== $honeypot || time() - $started < self::MIN_SECONDS ) {
			self::finish( $return, 'received' );
		}

		if ( ! self::within_rate_limit() ) {
			self::finish( $return, 'slow-down' );
		}

		$types   = Applications::business_types();
		$plans   = Applications::plans();
		$plan    = isset( $_POST['plan'] ) ? sanitize_key( wp_unslash( $_POST['plan'] ) ) : '';
		$type    = isset( $_POST['business_type'] ) ? sanitize_key( wp_unslash( $_POST['business_type'] ) ) : '';
		$design  = isset( $_POST['design'] ) ? sanitize_key( wp_unslash( $_POST['design'] ) ) : '';
		$website = isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '';

		$data    = array(
			'business_name' => isset( $_POST['business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['business_name'] ) ) : '',
			'contact_name'  => isset( $_POST['contact_name'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_name'] ) ) : '',
			'email'         => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'phone'         => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'website'       => $website,
			'business_type' => isset( $types[ $type ] ) ? $type : 'other',
			'plan'          => isset( $plans[ $plan ] ) ? $plan : '',
			'design'        => Design_Registry::exists( $design ) ? $design : '',
			'message'       => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
		);
		$consent = ! empty( $_POST['consent'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( '' === $data['plan'] || '' === $data['business_name'] || '' === $data['contact_name'] || ! is_email( $data['email'] ) || ! $consent ) {
			self::finish( $return, 'invalid' );
		}

		if ( email_exists( $data['email'] ) || Applications::has_pending_for( $data['email'] ) ) {
			self::finish( $return, 'duplicate' );
		}

		$id = Applications::create( $data );

		if ( $id ) {
			self::notify( $id, $data );
		}

		self::finish( $return, $id ? 'received' : 'invalid' );
	}

	/**
	 * Emails the applicant and staff about a new application.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  int                  $id    Application ID.
	 * @param  array<string,string> $data  Application fields.
	 * @return void
	 */
	private static function notify( int $id, array $data ): void {
		$types  = Applications::business_types();
		$design = Design_Registry::get( $data['design'] );
		$vars   = array_merge(
			$data,
			array(
				'business_type' => $types[ $data['business_type'] ] ?? $data['business_type'],
				'plan'          => Applications::plan_title( $data['plan'] ),
				'design'        => $design ? (string) $design['title'] : __( 'No preference', 'satori-manifest' ),
				'review_url'    => Applications_Screen::url(),
			)
		);

		Applications::log_email( $id, Mailer::send( 'application-received', $data['email'], $vars ) );

		foreach ( Mailer::staff_recipients() as $recipient ) {
			Applications::log_email( $id, Mailer::send( 'application-staff-notice', $recipient, $vars ) );
		}
	}

	/**
	 * Allows RATE_LIMIT submissions per hashed IP per hour.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return bool
	 */
	private static function within_rate_limit(): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'sm_reg_' . md5( $ip . wp_salt( 'nonce' ) );

		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Redirects back to the form with a result code, anchored to the form.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  string $url     Page to return to.
	 * @param  string $result  Result code (see messages()).
	 * @return never
	 */
	private static function finish( string $url, string $result ): void {
		wp_safe_redirect( add_query_arg( self::RESULT_VAR, $result, remove_query_arg( self::RESULT_VAR, $url ) ) . '#sm-register' );
		exit;
	}
}
