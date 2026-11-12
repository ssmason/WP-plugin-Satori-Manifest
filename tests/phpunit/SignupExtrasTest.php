<?php
/**
 * Tests for the sign-up extras: return from Stripe, first password, printed
 * QR codes, the optional spam check and the page cache helpers.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Billing\Checkout_Return;
use SatoriManifest\Billing\Code_Catalogue;
use SatoriManifest\Billing\Printed_Codes;
use SatoriManifest\Clients\First_Password;
use SatoriManifest\Hardening\Turnstile;

/**
 * Sign-up extras.
 */
class SignupExtrasTest extends Test_Case {

	/**
	 * Stripe must receive the session placeholder unencoded.
	 */
	public function test_success_url_keeps_the_session_placeholder(): void {
		$url = Checkout_Return::success_url( 42 );

		$this->assertStringContainsString( Checkout_Return::QUERY_VAR . '=42', $url );
		$this->assertStringEndsWith( '&session_id={CHECKOUT_SESSION_ID}', $url );
	}

	/**
	 * New clients are asked for a password until they set one.
	 */
	public function test_first_password_flag(): void {
		$user_id = $this->make_client();

		First_Password::mark_needed( $user_id );
		$this->assertTrue( First_Password::needed( $user_id ) );

		wp_update_user(
			array(
				'ID'        => $user_id,
				'user_pass' => wp_generate_password( 20 ),
			)
		);

		$this->assertFalse( First_Password::needed( $user_id ) );
	}

	/**
	 * One standard code is free at sign-up; other sizes cost the difference; later orders pay in full.
	 */
	public function test_printed_codes_pricing(): void {
		$sizes    = Code_Catalogue::sizes();
		$standard = Code_Catalogue::standard();

		$this->assertSame( 'm', $standard );
		$this->assertSame( 0.0, Code_Catalogue::codes_cost( array( 'm' => 1 ), true ) );
		$this->assertSame( round( $sizes['l']['price'] - $sizes['m']['price'], 2 ), Code_Catalogue::codes_cost( array( 'l' => 1 ), true ) );
		$this->assertSame( round( 2 * $sizes['m']['price'], 2 ), Code_Catalogue::codes_cost( array( 'm' => 3 ), true ) );
		$this->assertSame( round( 3 * $sizes['m']['price'], 2 ), Code_Catalogue::codes_cost( array( 'm' => 3 ) ) );
	}

	/**
	 * Orders are cleaned (unknown sizes dropped, total capped) and survive the round trip through Stripe metadata.
	 */
	public function test_order_round_trip(): void {
		$order = Code_Catalogue::order(
			array(
				'm'    => '2',
				'l'    => 1,
				'huge' => 5,
				'xs'   => -3,
			)
		);

		$this->assertSame(
			array(
				'm' => 2,
				'l' => 1,
			),
			$order
		);
		$this->assertSame( 'm:2,l:1', Code_Catalogue::encode( $order ) );
		$this->assertSame( $order, Code_Catalogue::decode( 'm:2,l:1' ) );
		$this->assertSame( Code_Catalogue::max(), Code_Catalogue::count( Code_Catalogue::order( array( 'm' => 9999 ) ) ) );
	}

	/**
	 * Postage is free in the EU/UK and per code elsewhere; Checkout only accepts that region's countries.
	 */
	public function test_postage_and_countries(): void {
		$this->assertSame( 0.0, Code_Catalogue::postage( 'eu', 10 ) );
		$this->assertSame( 23.0, Code_Catalogue::postage( 'ca', 3 ) );
		$this->assertSame( 0.0, Code_Catalogue::postage( 'jp', 0 ) );
		$this->assertSame( 'eu', Code_Catalogue::region( 'mars' ) );
		$this->assertStringContainsString( 'free postage', Code_Catalogue::region_label( 'eu' ) );
		$this->assertStringNotContainsString( 'per extra code', Code_Catalogue::region_label( 'jp' ) );

		$this->assertSame( array( 'CA' ), Printed_Codes::shipping_params( 'ca' )['shipping_address_collection']['allowed_countries'] );
		$this->assertContains( 'GB', Printed_Codes::shipping_params( 'eu' )['shipping_address_collection']['allowed_countries'] );
	}

	/**
	 * The staff line lists the postal address Stripe collected.
	 */
	public function test_postal_address(): void {
		$address = Printed_Codes::address_from_session(
			array(
				'collected_information' => array(
					'shipping_details' => array(
						'name'    => 'Ana García',
						'address' => array(
							'line1'       => 'Calle Mayor 1',
							'postal_code' => '29016',
							'city'        => 'Málaga',
							'country'     => 'ES',
						),
					),
				),
			)
		);

		$this->assertSame( 'Ana García, Calle Mayor 1, 29016 Málaga, ES', $address );
		$this->assertSame( '', Printed_Codes::address_from_session( array() ) );
	}

	/**
	 * Without keys the spam check is off and lets submissions through.
	 */
	public function test_turnstile_off_without_keys(): void {
		$this->assertFalse( Turnstile::is_enabled() );
		$this->assertTrue( Turnstile::verify( '' ) );
	}
}
