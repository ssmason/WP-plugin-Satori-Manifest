<?php
/**
 * WP-CLI: demo clients.
 *
 * Usage:
 *   wp satori-manifest demo seed [--password=<password>] [--reset]
 *   wp satori-manifest demo remove
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Cli;

use SatoriManifest\Clients\Demo_Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seeds or removes the demo clients defined in config/demo/*.yaml.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Demo_Command {

	/**
	 * Creates one demo client per demo file.
	 *
	 * ## OPTIONS
	 *
	 * [--password=<password>]
	 * : Password for every demo user. Default: ManifestDemo!2026
	 *
	 * [--reset]
	 * : Remove existing demo clients first.
	 *
	 * ## EXAMPLES
	 *
	 *     wp satori-manifest demo seed
	 *     wp satori-manifest demo seed --reset
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<int,string>    $args        Positional args.
	 * @param  array<string,string> $assoc_args  Named args.
	 * @return void
	 */
	public function seed( array $args, array $assoc_args ): void {
		if ( isset( $assoc_args['reset'] ) ) {
			$this->remove();
		}

		$rows = array();

		foreach ( Demo_Seeder::seed_all( (string) ( $assoc_args['password'] ?? 'ManifestDemo!2026' ) ) as $row ) {
			if ( is_wp_error( $row ) ) {
				\WP_CLI::warning( $row->get_error_message() );
				continue;
			}
			$rows[] = $row;
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'login', 'password', 'design', 'page', 'menu' ) );
		\WP_CLI::success( sprintf( 'Seeded %d demo clients. Log in at %s', count( $rows ), wp_login_url() ) );
	}

	/**
	 * Deletes every demo client with their page, price list and menu.
	 *
	 * ## EXAMPLES
	 *
	 *     wp satori-manifest demo remove
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public function remove(): void {
		foreach ( Demo_Seeder::remove_all() as $login ) {
			\WP_CLI::log( sprintf( 'Removed %s', $login ) );
		}
	}
}
