<?php
/**
 * Tool: list_changes - recent changes made through the assistant.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp\Tools;

use SatoriManifest\Mcp\Change_Log;
use SatoriManifest\Mcp\Server\Tool_Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class List_Changes
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class List_Changes extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'list_changes';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Recent changes', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Lists recent changes made through the AI assistant, newest first, and whether each was undone.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		return self::object(
			array(
				'limit' => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 30,
				),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,bool>
	 */
	public function annotations(): array {
		return self::read_only();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param  array<string,mixed> $args  Arguments.
	 * @return Tool_Result
	 */
	public function call( array $args ): Tool_Result {
		$changes = array_map(
			static fn( array $change ): array => array(
				'summary' => $change['summary'],
				'when'    => wp_date( 'c', $change['time'] ),
				'undone'  => $change['undone'],
			),
			Change_Log::recent( $this->store->context()->user_id, (int) ( $args['limit'] ?? 10 ) )
		);

		return Tool_Result::ok( array() === $changes ? 'No changes yet.' : sprintf( '%d recent changes.', count( $changes ) ), array( 'changes' => $changes ) );
	}
}
