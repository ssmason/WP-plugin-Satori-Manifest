<?php
/**
 * Tests for client creation and isolation.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Clients\Client_Factory;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Clients\Client_Summary;

/**
 * Client_Factory, provisioning and Access_Control.
 */
class ClientsTest extends Test_Case {

	/**
	 * A new client gets a page, a price list and a menu.
	 */
	public function test_client_is_provisioned(): void {
		$user_id = $this->make_client( 'Blue Door Barbers' );

		$this->assertTrue( Client_Role::is_client( $user_id ) );
		$this->assertGreaterThan( 0, Client_Repository::page_id( $user_id ) );
		$this->assertGreaterThan( 0, Client_Repository::manifest_id( $user_id ) );
		$this->assertGreaterThan( 0, Client_Repository::menu_id( $user_id ) );
		$this->assertSame( 'studio', Client_Repository::design_for_page( Client_Repository::page_id( $user_id ) ) );
	}

	/**
	 * Suggested usernames are unique.
	 */
	public function test_suggest_username_is_unique(): void {
		$this->assertSame( 'bluedoor', Client_Factory::suggest_username( 'Blue Door' ) );

		$this->make_client( 'Blue Door' );

		$this->assertSame( 'bluedoor2', Client_Factory::suggest_username( 'Blue Door' ) );
	}

	/**
	 * Invalid input is refused.
	 */
	public function test_invalid_input(): void {
		$this->assertWPError( Client_Factory::create( '', 'x@example.org' ) );
		$this->assertWPError( Client_Factory::create( 'Name', 'not-an-email' ) );
	}

	/**
	 * A client can edit their own page but never someone else's.
	 */
	public function test_clients_are_isolated(): void {
		$alice = $this->make_client( 'Alice Hair' );
		$bob   = $this->make_client( 'Bob Nails' );

		$this->assertTrue( user_can( $alice, 'edit_post', Client_Repository::page_id( $alice ) ) );
		$this->assertFalse( user_can( $alice, 'edit_post', Client_Repository::page_id( $bob ) ) );
		$this->assertFalse( user_can( $alice, 'edit_post', Client_Repository::manifest_id( $bob ) ) );
		$this->assertFalse( user_can( $alice, 'delete_post', Client_Repository::page_id( $alice ) ) );
	}

	/**
	 * Clients cannot create new posts or pages.
	 */
	public function test_clients_cannot_create_content(): void {
		wp_set_current_user( $this->make_client( 'Carol Cafe' ) );

		$id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Sneaky',
				'post_status' => 'draft',
			)
		);

		$this->assertSame( 0, $id );
	}

	/**
	 * The summary describes the client at a glance.
	 */
	public function test_summary(): void {
		$summary = Client_Summary::for_user( $this->make_client( 'Dan Deli' ) );

		$this->assertSame( 'Studio', $summary['design']['title'] );
		$this->assertSame( 'none', $summary['subscription']['status'] );
		$this->assertStringContainsString( 'http', $summary['page_url'] );
	}

	/**
	 * Names with "&" are stored as plain text, even with no one logged in (CLI, webhooks).
	 */
	public function test_titles_are_plain_text(): void {
		wp_set_current_user( 0 );
		$user_id = $this->make_client( 'Fish & Chips' );

		$this->assertSame( 'Fish & Chips price list', get_post_field( 'post_title', Client_Repository::manifest_id( $user_id ) ) );
		$this->assertSame( 'Fish & Chips', get_post_field( 'post_title', Client_Repository::page_id( $user_id ) ) );
	}
}
