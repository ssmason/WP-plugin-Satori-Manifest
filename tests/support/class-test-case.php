<?php
/**
 * Shared test helpers.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Clients\Client_Factory;
use SatoriManifest\Registration\Applications;
use SatoriManifest\Registration\Plans;

/**
 * Base test case: each test runs in a database transaction that is rolled
 * back afterwards (WP_UnitTestCase), plus a few plugin-specific helpers.
 */
abstract class Test_Case extends \WP_UnitTestCase {

	/**
	 * Resets per-request caches and mail before each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		Plans::flush();
		reset_phpmailer_instance();
	}

	/**
	 * Creates a client and returns their user ID.
	 *
	 * @param  string $name  Business name.
	 * @return int
	 */
	protected function make_client( string $name = 'Test Barbers' ): int {
		$user_id = Client_Factory::create( $name, sanitize_title( $name ) . '@example.test', '', 'studio' );
		$this->assertIsInt( $user_id );

		return $user_id;
	}

	/**
	 * Creates a pending application and returns it.
	 *
	 * @param  array<string,string> $overrides  Field overrides.
	 * @return array<string,mixed>
	 */
	protected function make_application( array $overrides = array() ): array {
		$id = Applications::create(
			array_merge(
				array(
					'business_name' => 'Copper Kettle',
					'contact_name'  => 'Priya Shah',
					'email'         => 'priya@copperkettle.test',
					'phone'         => '0114 555 0144',
					'website'       => 'https://copperkettle.test',
					'business_type' => 'cafe',
					'plan'          => 'self-managed',
					'interval'      => 'year',
					'design'        => 'mono',
					'message'       => '',
				),
				$overrides
			)
		);

		return Applications::get( $id );
	}

	/**
	 * Returns the subjects of every email sent during the test.
	 *
	 * @return array<int,string>
	 */
	protected function sent_subjects(): array {
		$mailer = tests_retrieve_phpmailer_instance();

		return array_map( static fn( array $sent ): string => (string) $sent['subject'], $mailer->mock_sent );
	}
}
