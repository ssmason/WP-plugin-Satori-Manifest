<?php
/**
 * The client an assistant session acts for.
 *
 * Every tool gets one of these and can only reach these IDs, so a session can
 * never touch another client's page, whatever the model asks for.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp;

use SatoriManifest\Clients\Client_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client_Context
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Client_Context {

	/**
	 * Constructor.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int $user_id      Client user ID.
	 * @param  int $page_id      Their page.
	 * @param  int $manifest_id  Their price list.
	 */
	public function __construct(
		public readonly int $user_id,
		public readonly int $page_id,
		public readonly int $manifest_id
	) {}

	/**
	 * Builds the context for a client, or null when they have no page yet.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int $user_id  Client user ID.
	 * @return self|null
	 */
	public static function for_user( int $user_id ): ?self {
		$page_id     = Client_Repository::page_id( $user_id );
		$manifest_id = Client_Repository::manifest_id( $user_id );

		return $page_id && $manifest_id ? new self( $user_id, $page_id, $manifest_id ) : null;
	}
}
