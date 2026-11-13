<?php
/**
 * Tool: choose_design - switch design, optionally loading its layout.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp\Tools;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Designs\Design_Registry;
use SatoriManifest\Mcp\Change_Log;
use SatoriManifest\Mcp\Server\Tool_Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Choose_Design
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Choose_Design extends Client_Tool {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'choose_design';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Choose a design', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Switches the page to a design from list_designs, optionally in light or dark. The price list and details are kept. With use_layout: true the page layout (header area, photos, arrangement) is replaced by the design\'s ready-made one - ask the user first if they have arranged their page themselves. A brand-new page always gets the layout. Can be undone.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		return self::object(
			array(
				'design'      => array(
					'type' => 'string',
					'enum' => array_map( 'strval', array_keys( Design_Registry::all() ) ),
				),
				'colour_mode' => array(
					'type'        => 'string',
					'enum'        => Design_Registry::COLOUR_MODES,
					'description' => 'Leave out to keep the design\'s usual colours.',
				),
				'use_layout'  => array(
					'type'        => 'boolean',
					'description' => 'Replace the page layout with the design\'s ready-made layout.',
				),
			),
			array( 'design' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,bool>
	 */
	public function annotations(): array {
		return array_merge(
			parent::annotations(),
			array(
				'destructiveHint' => true,
				'idempotentHint'  => true,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param  array<string,mixed> $args  Arguments.
	 * @return Tool_Result
	 */
	public function call( array $args ): Tool_Result {
		$slug   = (string) $args['design'];
		$design = (array) Design_Registry::get( $slug );
		$fresh  = Client_Repository::page_is_untouched( $this->store->context()->page_id );
		$layout = $fresh || true === ( $args['use_layout'] ?? false );
		$mode   = (string) ( $args['colour_mode'] ?? '' );

		Change_Log::record(
			$this->store,
			$this->name(),
			sprintf( 'Switched to the %s design', (string) $design['title'] ) . ( $layout ? ' with its layout' : '' ),
			$layout ? array( Change_Log::PART_DESIGN, Change_Log::PART_CONTENT ) : array( Change_Log::PART_DESIGN ),
			fn() => $this->store->apply_design( $slug, $mode, $layout )
		);

		return Tool_Result::ok(
			sprintf( 'The page now uses %s%s. Have a look: %s', (string) $design['title'], $layout ? ' with its ready-made layout' : '', $this->store->url() )
		);
	}
}
