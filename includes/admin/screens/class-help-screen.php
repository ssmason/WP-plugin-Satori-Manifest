<?php
/**
 * Help screen.
 *
 * The single place a client goes to learn how to set up their page: a short
 * checklist that links to each screen, a few questions and answers, and
 * how to reach support.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Admin\Screens;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Branding\Portal_Brand;
use SatoriManifest\Clients\Client_Summary;
use SatoriManifest\Help\Help_Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Help_Screen
 *
 * Renders the Help screen.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Help_Screen {

	/**
	 * Renders the screen.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function render(): void {
		$satori_manifest_client_id = Admin_Menu::current_client_id();
		$satori_manifest_help      = Help_Content::document( $satori_manifest_client_id );
		$satori_manifest_support   = Portal_Brand::support_email();
		$satori_manifest_page_url  = $satori_manifest_client_id ? (string) Client_Summary::for_user( $satori_manifest_client_id )['page_url'] : '';

		require SATORI_MANIFEST_PATH . 'templates/admin/help.php';
	}
}
