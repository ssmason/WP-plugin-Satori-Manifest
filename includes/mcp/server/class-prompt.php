<?php
/**
 * An MCP prompt: a ready-made request the user can pick in their AI app.
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
 * Class Prompt
 *
 * The text may contain {argument} placeholders, filled from prompts/get.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Prompt {

	/**
	 * Constructor.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string                                                         $name         Machine name.
	 * @param  string                                                         $title        Display name.
	 * @param  string                                                         $description  What it is for.
	 * @param  string                                                         $text         Message text with {placeholders}.
	 * @param  array<int,array{name:string,description:string,required:bool}> $arguments    Arguments.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $title,
		public readonly string $description,
		public readonly string $text,
		public readonly array $arguments = array()
	) {}

	/**
	 * The prompts/list entry.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return array<string,mixed>
	 */
	public function describe(): array {
		return array(
			'name'        => $this->name,
			'title'       => $this->title,
			'description' => $this->description,
			'arguments'   => $this->arguments,
		);
	}

	/**
	 * The prompts/get result.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<string,mixed> $args  Argument values.
	 * @return array<string,mixed>
	 * @throws Protocol_Error When a required argument is missing.
	 */
	public function render( array $args ): array {
		$replace = array();

		foreach ( $this->arguments as $argument ) {
			$value = trim( (string) ( $args[ $argument['name'] ] ?? '' ) );

			if ( '' === $value && $argument['required'] ) {
				throw new Protocol_Error( 'Missing prompt argument: ' . esc_html( $argument['name'] ), Protocol_Error::INVALID_PARAMS ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped; JSON-RPC message, not HTML.
			}

			$replace[ '{' . $argument['name'] . '}' ] = $value;
		}

		return array(
			'description' => $this->description,
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => array(
						'type' => 'text',
						'text' => trim( strtr( $this->text, $replace ) ),
					),
				),
			),
		);
	}
}
