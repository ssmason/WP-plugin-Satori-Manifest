<?php
/**
 * Contact form.
 *
 * Rendered by the satori-manifest/contact-form block on a public page and as
 * a small panel on the client dashboard. Submissions post to admin-post.php
 * and are handled by Contact_Handler, which emails the staff recipients.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Contact;

use SatoriManifest\Core\Page_Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Contact_Form
 *
 * Renders the contact form and its result messages.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */
class Contact_Form {

	/**
	 * Form action (admin-post.php) and nonce action.
	 *
	 * @since 3.8.0
	 * @var   string
	 */
	public const ACTION = 'satori_manifest_contact';

	/**
	 * Query var carrying the result back to the form.
	 *
	 * @since 3.8.0
	 * @var   string
	 */
	public const RESULT_VAR = 'sm_contact';

	/**
	 * Block name.
	 *
	 * @since 3.8.0
	 * @var   string
	 */
	public const BLOCK_NAME = 'satori-manifest/contact-form';

	/**
	 * Registers the block.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_block' ) );
	}

	/**
	 * Registers the block type. Hooked to 'init'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return void
	 */
	public static function register_block(): void {
		register_block_type( SATORI_MANIFEST_PATH . 'blocks/contact-form/block.json' );
	}

	/**
	 * Renders the public form (or the result message after sending).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  string $wrapper_attributes  Pre-escaped block wrapper attributes.
	 * @return string
	 */
	public static function render( string $wrapper_attributes = '' ): string {
		// The form carries a single-use nonce: never serve it from a page cache.
		Page_Cache::exclude_current_page();

		$satori_manifest_result  = self::result();
		$satori_manifest_wrapper = $wrapper_attributes;
		$satori_manifest_user    = wp_get_current_user();

		ob_start();
		require SATORI_MANIFEST_PATH . 'templates/contact/form.php';
		return (string) ob_get_clean();
	}

	/**
	 * Result code from our own redirect, or ''.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return string
	 */
	public static function result(): string {
		// Display-only flag set by Contact_Handler's redirect.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET[ self::RESULT_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT_VAR ] ) ) : '';
	}

	/**
	 * Result messages: code → { type, text }.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return array<string,array{type:string,text:string}>
	 */
	public static function messages(): array {
		return array(
			'sent'       => array(
				'type' => 'success',
				'text' => __( 'Thanks, your message is on its way. We usually reply within one working day.', 'satori-manifest' ),
			),
			'invalid'    => array(
				'type' => 'error',
				'text' => __( 'Please add your name, a valid email address and a message.', 'satori-manifest' ),
			),
			'expired'    => array(
				'type' => 'error',
				'text' => __( 'The form expired. Please try again.', 'satori-manifest' ),
			),
			'slow-down'  => array(
				'type' => 'error',
				'text' => __( 'You have sent several messages already. Please try again later.', 'satori-manifest' ),
			),
			'spam-check' => array(
				'type' => 'error',
				'text' => __( 'We could not confirm you are not a robot. Please try again.', 'satori-manifest' ),
			),
			'failed'     => array(
				'type' => 'error',
				'text' => __( 'Sorry, your message could not be sent. Please email us instead.', 'satori-manifest' ),
			),
		);
	}
}
