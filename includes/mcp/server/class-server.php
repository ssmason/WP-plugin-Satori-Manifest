<?php
/**
 * MCP server: JSON-RPC 2.0 dispatch for tools and prompts.
 *
 * Transport-agnostic and stateless: give it a request body, get back the
 * response to send (or null for notifications). Knows nothing about
 * WordPress users or price lists - the endpoint supplies the tools.
 *
 * Supported: initialize, ping, tools/list, tools/call, prompts/list,
 * prompts/get and notifications (acknowledged, no reply).
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
 * Class Server
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Server {

	/**
	 * Protocol versions understood, newest first.
	 */
	public const PROTOCOL_VERSIONS = array( '2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05' );

	/**
	 * Tools by name.
	 *
	 * @var array<string,Tool>
	 */
	private array $tools = array();

	/**
	 * Prompts by name.
	 *
	 * @var array<string,Prompt>
	 */
	private array $prompts = array();

	/**
	 * Constructor.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string $name          Server machine name.
	 * @param  string $title         Display name.
	 * @param  string $version       Server version.
	 * @param  string $instructions  Guidance for the model, sent on initialize.
	 */
	public function __construct(
		private readonly string $name,
		private readonly string $title,
		private readonly string $version,
		private readonly string $instructions = ''
	) {}

	/**
	 * Adds a tool.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  Tool $tool  Tool.
	 * @return self
	 */
	public function add_tool( Tool $tool ): self {
		$this->tools[ $tool->name() ] = $tool;

		return $this;
	}

	/**
	 * Adds a prompt.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  Prompt $prompt  Prompt.
	 * @return self
	 */
	public function add_prompt( Prompt $prompt ): self {
		$this->prompts[ $prompt->name ] = $prompt;

		return $this;
	}

	/**
	 * Names of the registered tools.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return array<int,string>
	 */
	public function tool_names(): array {
		return array_keys( $this->tools );
	}

	/**
	 * Handles a raw request body.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string $body  JSON-RPC message (or batch, for older clients).
	 * @return array<mixed>|null  Response, or null when nothing is owed.
	 */
	public function handle( string $body ): ?array {
		$message = json_decode( $body, true );

		if ( ! is_array( $message ) ) {
			return self::error_response( null, Protocol_Error::PARSE_ERROR, 'Parse error: the body is not valid JSON.' );
		}

		if ( array() !== $message && array_is_list( $message ) ) {
			$responses = array_values( array_filter( array_map( array( $this, 'handle_message' ), $message ) ) );

			return array() !== $responses ? $responses : null;
		}

		return $this->handle_message( $message );
	}

	/**
	 * Handles one decoded message.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  mixed $message  Decoded message.
	 * @return array<string,mixed>|null
	 */
	public function handle_message( $message ): ?array {
		if ( ! is_array( $message ) || '2.0' !== ( $message['jsonrpc'] ?? '' ) || ! is_string( $message['method'] ?? null ) ) {
			// Responses from the client (to requests we never send) are ignored.
			return is_array( $message ) && ( isset( $message['result'] ) || isset( $message['error'] ) ) && ! isset( $message['method'] )
				? null
				: self::error_response( $message['id'] ?? null, Protocol_Error::INVALID_REQUEST, 'Invalid request.' );
		}

		$has_id = array_key_exists( 'id', $message );
		$id     = $message['id'] ?? null;
		$params = is_array( $message['params'] ?? null ) ? $message['params'] : array();

		if ( ! $has_id ) {
			// Notifications (initialized, cancelled…) need no reply.
			return null;
		}

		try {
			return array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'result'  => $this->dispatch( $message['method'], $params ),
			);
		} catch ( Protocol_Error $e ) {
			return self::error_response( $id, $e->getCode(), $e->getMessage() );
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- unexpected failure; details stay in the server log.
			error_log( 'Satori Manifest MCP error: ' . $e->getMessage() );

			return self::error_response( $id, Protocol_Error::INTERNAL_ERROR, 'Internal error.' );
		}
	}

	/**
	 * Routes a request to its handler.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string              $method  Method.
	 * @param  array<string,mixed> $params  Params.
	 * @return array<string,mixed>
	 * @throws Protocol_Error When the method is unknown.
	 */
	private function dispatch( string $method, array $params ): array {
		return match ( $method ) {
			'initialize'   => $this->initialize( $params ),
			'ping'         => array(),
			'tools/list'   => array( 'tools' => array_values( array_map( array( self::class, 'describe_tool' ), $this->tools ) ) ),
			'tools/call'   => $this->call_tool( $params ),
			'prompts/list' => array( 'prompts' => array_values( array_map( static fn( Prompt $prompt ): array => $prompt->describe(), $this->prompts ) ) ),
			'prompts/get'  => $this->get_prompt( $params ),
			default        => throw new Protocol_Error( 'Method not found.', Protocol_Error::METHOD_NOT_FOUND ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- JSON-RPC message, not HTML.
		};
	}

	/**
	 * The initialize result: agreed protocol version, capabilities, server info.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<string,mixed> $params  Client params.
	 * @return array<string,mixed>
	 */
	private function initialize( array $params ): array {
		$requested = (string) ( $params['protocolVersion'] ?? '' );

		$result = array(
			'protocolVersion' => in_array( $requested, self::PROTOCOL_VERSIONS, true ) ? $requested : self::PROTOCOL_VERSIONS[0],
			'capabilities'    => array(
				'tools'   => array( 'listChanged' => false ),
				'prompts' => array( 'listChanged' => false ),
			),
			'serverInfo'      => array(
				'name'    => $this->name,
				'title'   => $this->title,
				'version' => $this->version,
			),
		);

		if ( '' !== $this->instructions ) {
			$result['instructions'] = $this->instructions;
		}

		return $result;
	}

	/**
	 * Runs a tool.
	 *
	 * Bad arguments and Tool_Error come back as a tool result with isError,
	 * so the model can read the message and try again.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<string,mixed> $params  { name, arguments }.
	 * @return array<string,mixed>
	 * @throws Protocol_Error When the tool does not exist.
	 */
	private function call_tool( array $params ): array {
		$tool = $this->tools[ (string) ( $params['name'] ?? '' ) ] ?? null;

		if ( null === $tool ) {
			throw new Protocol_Error( 'Unknown tool.', Protocol_Error::INVALID_PARAMS ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- JSON-RPC message, not HTML.
		}

		$args   = is_array( $params['arguments'] ?? null ) ? $params['arguments'] : array();
		$errors = Schema_Validator::errors( $args, $tool->input_schema() );

		if ( array() !== $errors ) {
			return Tool_Result::error( 'The request was not valid: ' . implode( ' ', $errors ) )->to_array();
		}

		try {
			return $tool->call( $args )->to_array();
		} catch ( Tool_Error $e ) {
			return Tool_Result::error( $e->getMessage() )->to_array();
		}
	}

	/**
	 * Fills in a prompt.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<string,mixed> $params  { name, arguments }.
	 * @return array<string,mixed>
	 * @throws Protocol_Error When the prompt does not exist.
	 */
	private function get_prompt( array $params ): array {
		$prompt = $this->prompts[ (string) ( $params['name'] ?? '' ) ] ?? null;

		if ( null === $prompt ) {
			throw new Protocol_Error( 'Unknown prompt.', Protocol_Error::INVALID_PARAMS ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- JSON-RPC message, not HTML.
		}

		return $prompt->render( is_array( $params['arguments'] ?? null ) ? $params['arguments'] : array() );
	}

	/**
	 * The tools/list entry for a tool.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  Tool $tool  Tool.
	 * @return array<string,mixed>
	 */
	private static function describe_tool( Tool $tool ): array {
		return array(
			'name'        => $tool->name(),
			'title'       => $tool->title(),
			'description' => $tool->description(),
			'inputSchema' => $tool->input_schema(),
			'annotations' => array_merge( array( 'title' => $tool->title() ), $tool->annotations() ),
		);
	}

	/**
	 * A JSON-RPC error response.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  mixed  $id       Request id.
	 * @param  int    $code     Error code.
	 * @param  string $message  Message.
	 * @return array<string,mixed>
	 */
	private static function error_response( $id, int $code, string $message ): array {
		return array(
			'jsonrpc' => '2.0',
			'id'      => is_int( $id ) || is_string( $id ) ? $id : null,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}
}
