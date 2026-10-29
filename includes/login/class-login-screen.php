<?php
/**
 * Branded login screen.
 *
 * Restyles wp-login.php with the portal logo and colours (Manifest →
 * Settings), links the logo to the home page, and adds a short welcome plus
 * a "Register your business" link. Pure CSS over the core form, so password
 * reset, two-factor plugins and the like keep working.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Login;

use SatoriManifest\Admin\Assets;
use SatoriManifest\Branding\Portal_Brand;
use SatoriManifest\Registration\Registration_Form;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Login_Screen
 *
 * Hooks that brand wp-login.php.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Login_Screen {

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'login_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_filter( 'login_headerurl', array( self::class, 'logo_url' ) );
		add_filter( 'login_headertext', array( self::class, 'logo_text' ) );
		add_filter( 'login_message', array( self::class, 'welcome' ) );
		add_action( 'login_footer', array( self::class, 'footer' ) );
		add_filter( 'login_body_class', array( self::class, 'body_class' ) );
	}

	/**
	 * Enqueues the login stylesheet with the portal palette.
	 *
	 * Hooked to 'login_enqueue_scripts'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function enqueue(): void {
		wp_enqueue_style( 'satori-manifest-login-fonts', 'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Inter:wght@400;600&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URLs are versioned by their query.
		Assets::enqueue_style( 'satori-manifest-login', 'build/css/login.css', array( 'satori-manifest-login-fonts' ) );
		wp_add_inline_style( 'satori-manifest-login', Portal_Brand::css_variables() );
	}

	/**
	 * Points the logo at the home page instead of wordpress.org.
	 *
	 * Hooked to 'login_headerurl'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return string
	 */
	public static function logo_url(): string {
		return home_url( '/' );
	}

	/**
	 * Uses the site name as the logo's accessible text.
	 *
	 * Hooked to 'login_headertext'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return string
	 */
	public static function logo_text(): string {
		return Portal_Brand::name();
	}

	/**
	 * Adds a short welcome above the plain login form.
	 *
	 * Other actions (lost password, reset) keep WordPress's own messages.
	 * Hooked to 'login_message'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $message  Existing message HTML.
	 * @return string
	 */
	public static function welcome( $message ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of the login screen mode.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';

		if ( 'login' !== $action || '' !== trim( (string) $message ) ) {
			return (string) $message;
		}

		return '<div class="sm-login-welcome"><h1>' . esc_html__( 'Welcome back', 'satori-manifest' ) . '</h1><p>' . esc_html__( 'Log in to update your price list, branding and design.', 'satori-manifest' ) . '</p></div>';
	}

	/**
	 * Adds a "Register your business" link under the form.
	 *
	 * Hooked to 'login_footer'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function footer(): void {
		$register = Registration_Form::page_url();

		if ( '' === $register ) {
			return;
		}

		printf(
			'<p class="sm-login-register">%1$s <a href="%2$s">%3$s</a></p>',
			esc_html__( 'New here?', 'satori-manifest' ),
			esc_url( $register ),
			esc_html__( 'Register your business', 'satori-manifest' )
		);
	}

	/**
	 * Adds a body class for the stylesheet.
	 *
	 * Hooked to 'login_body_class'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<int,string> $classes  Body classes.
	 * @return array<int,string>
	 */
	public static function body_class( array $classes ): array {
		$classes[] = 'sm-login';
		return $classes;
	}
}
