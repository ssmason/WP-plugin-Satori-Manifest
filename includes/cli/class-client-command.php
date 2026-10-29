<?php
/**
 * WP-CLI: clients.
 *
 * Usage:
 *   wp satori-manifest client create <name> <email> [--login=] [--design=] [--password=]
 *   wp satori-manifest client list
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Cli;

use SatoriManifest\Billing\Subscriptions;
use SatoriManifest\Clients\Client_Factory;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Clients\Client_Summary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and lists clients.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Client_Command {

	/**
	 * Creates a client with their page, price list and menu.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Business name.
	 *
	 * <email>
	 * : Email address.
	 *
	 * [--login=<login>]
	 * : Username. Generated from the name when omitted.
	 *
	 * [--design=<slug>]
	 * : Starting design. See `wp satori-manifest designs`.
	 *
	 * [--password=<password>]
	 * : Password. Random when omitted.
	 *
	 * ## EXAMPLES
	 *
	 *     wp satori-manifest client create "Blue Door Barbers" hello@bluedoor.test --design=studio
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<int,string>    $args        Positional args.
	 * @param  array<string,string> $assoc_args  Named args.
	 * @return void
	 */
	public function create( array $args, array $assoc_args ): void {
		$user_id = Client_Factory::create(
			(string) ( $args[0] ?? '' ),
			(string) ( $args[1] ?? '' ),
			(string) ( $assoc_args['login'] ?? '' ),
			(string) ( $assoc_args['design'] ?? '' ),
			(string) ( $assoc_args['password'] ?? '' )
		);

		if ( is_wp_error( $user_id ) ) {
			\WP_CLI::error( $user_id->get_error_message() );
		}

		\WP_CLI::success( sprintf( 'Created client #%d — %s', $user_id, get_permalink( Client_Repository::page_id( $user_id ) ) ) );
	}

	/**
	 * Lists every client.
	 *
	 * ## EXAMPLES
	 *
	 *     wp satori-manifest client list
	 *
	 * @subcommand list
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public function list_(): void {
		$rows = array();

		foreach ( Client_Repository::all_clients() as $client ) {
			$summary = Client_Summary::for_user( $client->ID );
			$rows[]  = array(
				'id'       => $client->ID,
				'login'    => $client->user_login,
				'name'     => $summary['brand']['name'] ?? $client->display_name,
				'design'   => $summary['design']['slug'] ?? '',
				'sections' => $summary['sections'],
				'items'    => $summary['items'],
				'links'    => $summary['menu_items'],
				'billing'  => Subscriptions::status_label( $summary['subscription']['status'] ),
				'url'      => $summary['page_url'],
			);
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'login', 'name', 'design', 'sections', 'items', 'links', 'billing', 'url' ) );
	}
}
