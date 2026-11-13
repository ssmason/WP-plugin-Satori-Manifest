<?php
/**
 * Tests for the AI assistant (MCP) module.
 *
 * @package SatoriManifest
 */

namespace SatoriManifest\Tests;

use SatoriManifest\Billing\Subscriptions;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Mcp\Access;
use SatoriManifest\Mcp\Assistant_Config;
use SatoriManifest\Mcp\Change_Log;
use SatoriManifest\Mcp\Client_Context;
use SatoriManifest\Mcp\Endpoint;
use SatoriManifest\Mcp\Page_Store;
use SatoriManifest\Mcp\Price_List;
use SatoriManifest\Mcp\Prices;
use SatoriManifest\Mcp\Server\Schema_Validator;
use SatoriManifest\Mcp\Server\Server;
use SatoriManifest\Mcp\Server\Tool_Error;
use SatoriManifest\Mcp\Tokens;
use SatoriManifest\Mcp\Toolbox;

/**
 * AI assistant.
 */
class AiAssistantTest extends Test_Case {

	/**
	 * Resets the module's settings cache.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		Assistant_Config::flush();
	}

	/**
	 * Prices from models come in many shapes.
	 */
	public function test_parse_prices(): void {
		$this->assertSame( '5.50', Prices::parse( 5.5 ) );
		$this->assertSame( '5.50', Prices::parse( '5.50' ) );
		$this->assertSame( '5.50', Prices::parse( '€5,50' ) );
		$this->assertSame( '5.50', Prices::parse( '5,50 €' ) );
		$this->assertSame( '1250.00', Prices::parse( '1.250,00' ) );
		$this->assertSame( '1250.00', Prices::parse( '1,250' ) );
		$this->assertSame( '12.00', Prices::parse( 12 ) );
		$this->assertSame( '', Prices::parse( null ) );
		$this->assertSame( '', Prices::parse( '' ) );

		$this->expectException( Tool_Error::class );
		Prices::parse( 'market price' );
	}

	/**
	 * Percent and fixed changes, with rounding; empty prices stay empty.
	 */
	public function test_adjust_prices(): void {
		$this->assertSame( '5.25', Prices::adjust( '5.00', 5, null ) );
		$this->assertSame( '5.50', Prices::adjust( '5.00', 5, null, 0.5 ) );
		$this->assertSame( '4.50', Prices::adjust( '5.00', null, -0.5 ) );
		$this->assertSame( '0.00', Prices::adjust( '0.20', null, -1 ) );
		$this->assertSame( '', Prices::adjust( '', 10, null ) );
	}

	/**
	 * The validator reports missing, unknown, wrong-type and out-of-range values.
	 */
	public function test_schema_validator(): void {
		$schema = array(
			'type'                 => 'object',
			'properties'           => array(
				'name'  => array(
					'type'      => 'string',
					'maxLength' => 3,
				),
				'round' => array(
					'type' => 'number',
					'enum' => array( 0, 0.5, 1 ),
				),
				'list'  => array(
					'type'     => 'array',
					'maxItems' => 1,
					'items'    => array( 'type' => 'integer' ),
				),
			),
			'required'             => array( 'name' ),
			'additionalProperties' => false,
		);

		$this->assertSame(
			array(),
			Schema_Validator::errors(
				array(
					'name'  => 'abc',
					'round' => 1.0,
					'list'  => array( 2 ),
				),
				$schema
			)
		);

		$errors = Schema_Validator::errors(
			array(
				'round' => 0.3,
				'list'  => array( 'x', 2 ),
				'extra' => true,
			),
			$schema
		);

		$this->assertContains( 'arguments.name is required.', $errors );
		$this->assertContains( 'arguments.extra is not a known field.', $errors );
		$this->assertContains( 'arguments.list can have at most 1 entries.', $errors );
		$this->assertContains( 'arguments.list[0] must be integer.', $errors );
		$this->assertCount( 5, $errors );
	}

	/**
	 * Protocol basics: version agreement, notifications, unknown methods, bad JSON.
	 */
	public function test_server_protocol(): void {
		$server = new Server( 'test', 'Test', '1.0' );

		$init = $server->handle( '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18"}}' );
		$this->assertSame( '2025-06-18', $init['result']['protocolVersion'] );

		$old = $server->handle( '{"jsonrpc":"2.0","id":2,"method":"initialize","params":{"protocolVersion":"1999-01-01"}}' );
		$this->assertSame( Server::PROTOCOL_VERSIONS[0], $old['result']['protocolVersion'] );

		$this->assertNull( $server->handle( '{"jsonrpc":"2.0","method":"notifications/initialized"}' ) );
		$this->assertSame( -32601, $server->handle( '{"jsonrpc":"2.0","id":3,"method":"nope"}' )['error']['code'] );
		$this->assertSame( -32700, $server->handle( 'not json' )['error']['code'] );
		$this->assertSame( -32602, $server->handle( '{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"missing"}}' )['error']['code'] );
	}

	/**
	 * Keys verify for their owner only, and stop working when revoked.
	 */
	public function test_tokens(): void {
		$client = $this->make_client( 'Key Barbers' );
		$other  = $this->make_client( 'Other Barbers' );
		$key    = Tokens::issue( $client );

		$this->assertSame( $client, Tokens::verify( $key ) );
		$this->assertSame( 0, Tokens::verify( str_replace( 'smai_' . $client . '_', 'smai_' . $other . '_', $key ) ) );
		$this->assertSame( 0, Tokens::verify( substr( $key, 0, -1 ) . ( str_ends_with( $key, 'a' ) ? 'b' : 'a' ) ) );
		$this->assertStringNotContainsString( substr( $key, -40 ), (string) wp_json_encode( get_user_meta( $client, Tokens::META, true ) ) );

		Tokens::revoke( $client );
		$this->assertSame( 0, Tokens::verify( $key ) );
	}

	/**
	 * Off by default; on by plan or staff; staff "off" wins and disconnects.
	 */
	public function test_access_rules(): void {
		$client = $this->make_client( 'Plan Barbers' );

		$this->assertFalse( Access::allowed( $client ) );

		Subscriptions::save(
			$client,
			array(
				'plan'   => 'managed',
				'status' => 'active',
			)
		);
		$this->assertTrue( Access::allowed( $client ) );

		Tokens::issue( $client );
		Access::set_mode( $client, Access::MODE_OFF );
		$this->assertFalse( Access::allowed( $client ) );
		$this->assertNull( Tokens::info( $client ) );

		$staff_made = $this->make_client( 'Staff Made' );
		Access::set_mode( $staff_made, Access::MODE_ON );
		$this->assertTrue( Access::allowed( $staff_made ) );
	}

	/**
	 * Setting up a list, editing items, and undo step by step.
	 */
	public function test_tools_and_undo(): void {
		$client = $this->make_client( 'Tool Bar' );
		$store  = new Page_Store( Client_Context::for_user( $client ) );

		$set_up = $this->tool( $store, 'set_up_price_list' );

		$set_up->call(
			array(
				'replace_existing' => true,
				'sections'         => array(
					array(
						'title' => 'Drinks',
						'items' => array(
							array(
								'label' => 'Guinness',
								'price' => '€5,00',
							),
							array(
								'label' => 'Coke',
								'price' => 2.5,
							),
						),
					),
					array(
						'title' => 'Food',
						'items' => array(
							array( 'label' => 'Mains' ),
							array(
								'label' => 'Burger',
								'price' => 12,
							),
						),
					),
				),
			)
		);

		$this->assertSame( array( 'Drinks', 'Food' ), array_column( $store->sections(), 'title' ) );
		$this->assertSame( '', $store->sections()[1]['items'][0]['price'] );

		// Replacing a list that has items needs the user's say-so.
		try {
			$set_up->call(
				array(
					'sections' => array(
						array(
							'title' => 'Other',
							'items' => array(),
						),
					),
				)
			);
			$this->fail( 'Expected a Tool_Error.' );
		} catch ( Tool_Error $e ) {
			$this->assertStringContainsString( 'replace_existing', $e->getMessage() );
		}

		$this->tool( $store, 'update_items' )->call(
			array(
				'changes' => array(
					array(
						'item' => 'guinness',
						'set'  => array( 'price' => 5.5 ),
					),
				),
			)
		);
		$this->assertSame( '5.50', $store->sections()[0]['items'][0]['price'] );

		$this->tool( $store, 'adjust_prices' )->call( array( 'amount' => 1 ) );
		$this->assertSame( '6.50', $store->sections()[0]['items'][0]['price'] );
		$this->assertSame( '', $store->sections()[1]['items'][0]['price'] );

		Change_Log::undo_last( $store );
		$this->assertSame( '5.50', $store->sections()[0]['items'][0]['price'] );

		Change_Log::undo_last( $store );
		$this->assertSame( '5.00', $store->sections()[0]['items'][0]['price'] );

		$this->assertCount( 3, Change_Log::recent( $client ) );
	}

	/**
	 * Names shared by two sections need a section id.
	 */
	public function test_ambiguous_items(): void {
		$sections = array(
			array(
				'id'    => 's_00000001',
				'title' => 'Lunch',
				'items' => array( array( 'label' => 'Soup' ) ),
			),
			array(
				'id'    => 's_00000002',
				'title' => 'Dinner',
				'items' => array( array( 'label' => 'Soup' ) ),
			),
		);

		$this->assertSame( array( 1, 0 ), Price_List::find_item( $sections, 'Soup', 's_00000002' ) );

		$this->expectException( Tool_Error::class );
		Price_List::find_item( $sections, 'Soup' );
	}

	/**
	 * Over HTTP: bad key 401, not allowed 403, allowed 200 on the client's own page only.
	 */
	public function test_endpoint(): void {
		$client = $this->make_client( 'Http Barbers' );
		$other  = $this->make_client( 'Neighbour Barbers' );
		$key    = Tokens::issue( $client );
		$body   = '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"get_page","arguments":{}}}';

		$this->assertSame( 401, $this->post( 'smai_1_' . str_repeat( 'a', 40 ), $body )->get_status() );
		$this->assertSame( 403, $this->post( $key, $body )->get_status() );

		Access::set_mode( $client, Access::MODE_ON );
		$response = $this->post( $key, $body );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( get_permalink( Client_Repository::page_id( $client ) ), $response->get_data()['result']['structuredContent']['url'] );
		$this->assertNotSame( get_permalink( Client_Repository::page_id( $other ) ), $response->get_data()['result']['structuredContent']['url'] );
	}

	/**
	 * Finds a tool by name.
	 *
	 * @param  Page_Store $store  Client's page.
	 * @param  string     $name   Tool name.
	 * @return \SatoriManifest\Mcp\Server\Tool
	 */
	private function tool( Page_Store $store, string $name ): \SatoriManifest\Mcp\Server\Tool {
		foreach ( Toolbox::tools( $store ) as $tool ) {
			if ( $tool->name() === $name ) {
				return $tool;
			}
		}

		$this->fail( 'No tool ' . $name );
	}

	/**
	 * Posts an MCP message with a key in the address.
	 *
	 * @param  string $key   Connection key.
	 * @param  string $body  JSON body.
	 * @return \WP_REST_Response
	 */
	private function post( string $key, string $body ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/' . Endpoint::REST_NAMESPACE . Endpoint::ROUTE . '/' . $key );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( $body );

		return rest_get_server()->dispatch( $request );
	}
}
