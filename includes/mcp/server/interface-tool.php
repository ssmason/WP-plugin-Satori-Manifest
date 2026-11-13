<?php
/**
 * An MCP tool.
 *
 * The protocol layer (this folder) knows nothing about price lists: anything
 * that implements Tool can be served. Tools describe themselves (name, input
 * schema, hints) and do their work in call().
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
 * Interface Tool
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
interface Tool {

	/**
	 * Unique machine name (letters, digits, underscores).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return string
	 */
	public function name(): string;

	/**
	 * Short human-readable name shown by AI apps.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return string
	 */
	public function title(): string;

	/**
	 * What the tool does and when to use it (read by the AI model).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return string
	 */
	public function description(): string;

	/**
	 * JSON Schema (object) for the arguments.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return array<string,mixed>
	 */
	public function input_schema(): array;

	/**
	 * MCP behaviour hints: readOnlyHint, destructiveHint, idempotentHint, openWorldHint.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return array<string,bool>
	 */
	public function annotations(): array;

	/**
	 * Runs the tool with arguments already validated against input_schema().
	 *
	 * Throw Tool_Error for problems the user or model can fix (bad section,
	 * nothing matched); anything else is reported as an internal error.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<string,mixed> $args  Arguments.
	 * @return Tool_Result
	 * @throws Tool_Error When the request cannot be carried out.
	 */
	public function call( array $args ): Tool_Result;
}
