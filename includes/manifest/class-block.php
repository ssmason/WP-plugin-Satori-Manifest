<?php
/**
 * Block registration.
 *
 * Registers the satori-manifest/price-list Gutenberg block. Rendering is
 * handled by block/render.php (referenced in block.json via the "render"
 * key) rather than a PHP render_callback, keeping this class focused solely
 * on WordPress block registration.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Manifest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Block
 *
 * Registers the price-list block type with WordPress.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */
class Block {

	/**
	 * Block name constant.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public const BLOCK_NAME = 'satori-manifest/price-list';

	/**
	 * Registration form block name.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	public const REGISTRATION_BLOCK_NAME = 'satori-manifest/registration-form';

	/**
	 * Registers the block type with WordPress.
	 *
	 * All block configuration — attributes, supports, editor script, styles,
	 * and the render template path — is declared in each blocks/{name}/block.json.
	 * Hooked to 'init'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @return void
	 */
	public static function register(): void {
		register_block_type( SATORI_MANIFEST_PATH . 'blocks/price-list/block.json' );
		register_block_type( SATORI_MANIFEST_PATH . 'blocks/registration-form/block.json' );
	}
}
