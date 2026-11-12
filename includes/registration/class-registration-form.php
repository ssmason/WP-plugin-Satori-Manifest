<?php
/**
 * Public registration form.
 *
 * Rendered by the satori-manifest/registration-form block. Submissions post
 * to admin-post.php and are processed by Registration_Handler; emails go
 * through Registration_Notifier. This class renders the form, its result
 * messages and finds the page that holds it.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Registration;

use SatoriManifest\Billing\Stripe_Settings;
use SatoriManifest\Core\Page_Cache;
use SatoriManifest\Designs\Design_Registry;
use SatoriManifest\Manifest\Block;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Registration_Form
 *
 * Renders the registration form and its result messages.
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
	 * Registers the page-URL cache flush (submissions: Registration_Handler).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'save_post_page', array( self::class, 'flush_page_url' ) );
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
		// The form carries a single-use nonce (and the spam check): never serve it from a page cache.
		Page_Cache::exclude_current_page();

		// Display-only flag set by our own redirect.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$satori_manifest_result  = isset( $_GET[ self::RESULT_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT_VAR ] ) ) : '';
		$satori_manifest_fields  = Applications::fields();
		$satori_manifest_types   = Applications::business_types();
		$satori_manifest_designs = Design_Registry::all();
		$satori_manifest_plans   = Plans::all();
		$satori_manifest_toggle  = Plans::has_interval_choice();
		$satori_manifest_pay_now = Stripe_Settings::is_configured();
		$satori_manifest_wrapper = $wrapper_attributes;

		// Optional pre-selection via ?plan=slug&billing=year (e.g. from a pricing link).
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$satori_manifest_plan     = isset( $_GET['plan'] ) ? sanitize_key( wp_unslash( $_GET['plan'] ) ) : '';
		$satori_manifest_interval = Plans::normalize_interval( isset( $_GET['billing'] ) ? sanitize_key( wp_unslash( $_GET['billing'] ) ) : '' );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		ob_start();
		require SATORI_MANIFEST_PATH . 'templates/registration/form.php';
		return (string) ob_get_clean();
	}

	/**
	 * Returns the URL of the first published page holding the form, or ''.
	 *
	 * Cached for a day; the cache clears whenever a page is saved.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return string
	 */
	public static function page_url(): string {
		$cached = get_transient( 'sm_registration_page' );

		if ( false !== $cached ) {
			return (string) $cached;
		}

		$url = '';

		foreach ( get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				's'              => '<!-- wp:' . Block::REGISTRATION_BLOCK_NAME,
				'search_columns' => array( 'post_content' ),
				'posts_per_page' => 5,
				'no_found_rows'  => true,
			)
		) as $page ) {
			if ( has_block( Block::REGISTRATION_BLOCK_NAME, $page ) ) {
				$url = (string) get_permalink( $page );
				break;
			}
		}

		set_transient( 'sm_registration_page', $url, DAY_IN_SECONDS );

		return $url;
	}

	/**
	 * Clears the cached registration page URL. Hooked to 'save_post_page'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function flush_page_url(): void {
		delete_transient( 'sm_registration_page' );
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
			'received'          => array(
				'type' => 'success',
				'text' => __( 'Thank you! Your application is in. We review every application personally and will email you as soon as your page is ready.', 'satori-manifest' ),
			),
			'invalid'           => array(
				'type' => 'error',
				'text' => __( 'Please choose a plan, fill in your business name, your name and a valid email address, and accept the terms.', 'satori-manifest' ),
			),
			'paid'              => array(
				'type' => 'success',
				'text' => Plans::trial_days() > 0
					? __( 'Thank you! Your free trial has started and your account is being set up now. Check your email for a link to choose your password.', 'satori-manifest' )
					: __( 'Payment received, thank you! Your account is being set up now. Check your email for a link to choose your password.', 'satori-manifest' ),
			),
			'payment-cancelled' => array(
				'type' => 'info',
				'text' => __( 'Payment was cancelled and nothing was charged. Fill in the form again whenever you are ready.', 'satori-manifest' ),
			),
			'spam-check'        => array(
				'type' => 'error',
				'text' => __( 'We could not confirm you are a person. Please try again.', 'satori-manifest' ),
			),
			'slow-down'         => array(
				'type' => 'error',
				'text' => __( 'Too many attempts. Please try again in a little while.', 'satori-manifest' ),
			),
			'expired'           => array(
				'type' => 'error',
				'text' => __( 'Your session expired. Please submit the form again.', 'satori-manifest' ),
			),
		);
	}
}
