<?php
/**
 * Client factory.
 *
 * The one place a client account is created — used by the staff Clients
 * screen, application approval, payments and WP-CLI alike.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Clients;

use SatoriManifest\Designs\Design_Applier;
use SatoriManifest\Designs\Design_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client_Factory
 *
 * Creates client users. Assigning the client role triggers Provisioner,
 * which creates the page, manifest and menu; the factory then applies the
 * chosen design.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Client_Factory {

	/**
	 * Creates a client user and provisions their page, manifest and menu.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $name      Business name.
	 * @param  string $email     Email address.
	 * @param  string $login     Username (generated from the name when empty).
	 * @param  string $design    Design slug (the default design when unknown).
	 * @param  string $password  Password (random when empty).
	 * @return int|\WP_Error  New user ID or error.
	 */
	public static function create( string $name, string $email, string $login = '', string $design = '', string $password = '' ) {
		if ( '' === $name || ! is_email( $email ) ) {
			return new \WP_Error( 'satori_manifest_invalid', __( 'Please enter a business name and a valid email address.', 'satori-manifest' ) );
		}

		if ( '' === $login ) {
			$login = self::suggest_username( $name );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => '' !== $password ? $password : wp_generate_password( 24, true, true ),
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
			Design_Applier::apply( $page_id, $design, true );
		}

		return $user_id;
	}

	/**
	 * Suggests a free username from a business name ("Blue Door" → "bluedoor").
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
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
}
