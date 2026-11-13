<?php
/**
 * A JSON-RPC protocol error.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp\Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Protocol_Error
 *
 * Carries a JSON-RPC error code (see the constants).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Protocol_Error extends \RuntimeException {

	public const PARSE_ERROR = -32700;

	public const INVALID_REQUEST = -32600;

	public const METHOD_NOT_FOUND = -32601;

	public const INVALID_PARAMS = -32602;

	public const INTERNAL_ERROR = -32603;
}
