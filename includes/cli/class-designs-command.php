<?php
/**
 * WP-CLI: designs.
 *
 * Usage:
 *   wp satori-manifest designs
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Cli;

use SatoriManifest\Designs\Design_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists the registered designs and whether their CSS is built.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Designs_Command {

	/**
	 * Lists the registered designs.
	 *
	 * ## EXAMPLES
	 *
	 *     wp satori-manifest designs
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public function __invoke(): void {
		$rows = array();

		foreach ( Design_Registry::all() as $slug => $design ) {
			$rows[] = array(
				'slug'     => $slug,
				'title'    => $design['title'],
				'header'   => $design['header'],
				'fonts'    => $design['fonts']['heading'] . ' / ' . $design['fonts']['body'],
				'compiled' => is_readable( $design['style_path'] ) ? 'yes' : 'NO — run npm run build',
			);
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'slug', 'title', 'header', 'fonts', 'compiled' ) );
	}
}
