<?php
/**
 * The result of a tool call.
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
 * Class Tool_Result
 *
 * A short message for the model to relay, plus optional structured data.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Tool_Result {

	/**
	 * Constructor.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string                   $message   Plain-language summary.
	 * @param  array<string,mixed>|null $data      Structured data, or null.
	 * @param  bool                     $is_error  Whether the call failed.
	 */
	public function __construct(
		public readonly string $message,
		public readonly ?array $data = null,
		public readonly bool $is_error = false
	) {}

	/**
	 * A successful result.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string                   $message  Summary.
	 * @param  array<string,mixed>|null $data     Structured data.
	 * @return self
	 */
	public static function ok( string $message, ?array $data = null ): self {
		return new self( $message, $data );
	}

	/**
	 * A failed result the model can recover from.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string $message  What went wrong and how to fix it.
	 * @return self
	 */
	public static function error( string $message ): self {
		return new self( $message, null, true );
	}

	/**
	 * The MCP CallToolResult shape.
	 *
	 * The data is repeated as JSON text for clients that ignore structuredContent.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		$text = $this->message;

		if ( null !== $this->data ) {
			$text .= "\n\n" . wp_json_encode( $this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}

		$result = array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $text,
				),
			),
			'isError' => $this->is_error,
		);

		if ( null !== $this->data ) {
			$result['structuredContent'] = $this->data;
		}

		return $result;
	}
}
