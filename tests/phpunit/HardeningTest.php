<?php
/**
 * Tests for security hardening and form saving.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Core\Security;
use SatoriManifest\Hardening\Login_Throttle;
use SatoriManifest\Hardening\Site_Hardening;
use SatoriManifest\Manifest\Manifest_Repository;
use SatoriManifest\Manifest\Meta_Box;

/**
 * Login throttling, no username leaks, XML-RPC off, safe price-list saving.
 */
class HardeningTest extends Test_Case {

	/**
	 * Clears request state.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_POST = array();
		Login_Throttle::clear();
		parent::tear_down();
	}

	/**
	 * After the allowed failures, even a correct password is refused until the lockout ends.
	 */
	public function test_login_lockout(): void {
		$user_id = self::factory()->user->create(
			array(
				'user_login' => 'throttled',
				'user_pass'  => 'right-password',
			)
		);

		for ( $i = 0; $i < Login_Throttle::MAX_ATTEMPTS; $i++ ) {
			$this->assertWPError( wp_authenticate( 'throttled', 'wrong' ) );
		}

		$locked = wp_authenticate( 'throttled', 'right-password' );
		$this->assertWPError( $locked );
		$this->assertSame( 'satori_manifest_locked', $locked->get_error_code() );

		Login_Throttle::clear();
		$this->assertSame( $user_id, wp_authenticate( 'throttled', 'right-password' )->ID );
	}

	/**
	 * Login errors do not reveal whether the username exists.
	 */
	public function test_generic_login_errors(): void {
		$errors = Site_Hardening::generic_login_errors( new \WP_Error( 'invalid_username', 'The username bob is not registered.' ) );

		$this->assertSame( array( 'satori_manifest_login_failed' ), $errors->get_error_codes() );
		$this->assertStringNotContainsString( 'bob', $errors->get_error_message() );

		// Other errors (e.g. an empty password) are left alone.
		$other = Site_Hardening::generic_login_errors( new \WP_Error( 'empty_password', 'Empty.' ) );
		$this->assertSame( array( 'empty_password' ), $other->get_error_codes() );
	}

	/**
	 * Visitors cannot list users over REST; administrators can.
	 */
	public function test_rest_user_list_is_private(): void {
		wp_set_current_user( 0 );
		$this->assertArrayNotHasKey( '/wp/v2/users', Site_Hardening::restrict_user_endpoints( array( '/wp/v2/users' => array() ) ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertArrayHasKey( '/wp/v2/users', Site_Hardening::restrict_user_endpoints( array( '/wp/v2/users' => array() ) ) );
	}

	/**
	 * XML-RPC is switched off.
	 */
	public function test_xmlrpc_disabled(): void {
		$this->assertFalse( apply_filters( 'xmlrpc_enabled', true ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
	}

	/**
	 * The price-list editor keeps quotes and backslashes intact when saving.
	 */
	public function test_price_list_save_keeps_quotes(): void {
		$user_id     = $this->make_client( 'Quote Pizza' );
		$manifest_id = Client_Repository::manifest_id( $user_id );
		wp_set_current_user( $user_id );

		$sections = array(
			array(
				'title' => 'Pizza',
				'items' => array(
					array(
						'label' => '12" Margherita',
						'price' => '9.50',
					),
				),
			),
		);

		$_POST[ Security::NONCE_FIELD ] = wp_create_nonce( 'satori_manifest_' . Meta_Box::NONCE_ACTION );
		$_POST[ Meta_Box::POST_FIELD ]  = wp_slash( (string) wp_json_encode( $sections ) );

		Meta_Box::save( $manifest_id );

		$saved = Manifest_Repository::get_sections( $manifest_id );
		$this->assertSame( '12" Margherita', $saved[0]['items'][0]['label'] );
	}

	/**
	 * Without a valid nonce nothing is saved.
	 */
	public function test_price_list_save_needs_nonce(): void {
		$user_id     = $this->make_client( 'No Nonce' );
		$manifest_id = Client_Repository::manifest_id( $user_id );
		wp_set_current_user( $user_id );

		$_POST[ Security::NONCE_FIELD ] = 'forged';
		$_POST[ Meta_Box::POST_FIELD ]  = wp_slash( (string) wp_json_encode( array( array( 'title' => 'Hacked' ) ) ) );

		Meta_Box::save( $manifest_id );

		$this->assertNotSame( 'Hacked', Manifest_Repository::get_sections( $manifest_id )[0]['title'] ?? '' );
	}
}
