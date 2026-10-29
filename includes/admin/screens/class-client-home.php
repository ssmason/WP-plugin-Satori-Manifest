<?php
/**
 * Client Home screen.
 *
 * The landing page after login: page status, the QR destination URL and
 * shortcuts to every task a client can perform.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Admin\Screens;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Admin\Portal\Screen_Guard;
use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Clients\Client_Summary;
use SatoriManifest\Clients\Embed_Code;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client_Home
 *
 * Renders the client dashboard.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Client_Home {

	/**
	 * Redirects the core Dashboard to the Home screen for clients.
	 *
	 * Hooked to 'load-index.php'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function redirect_dashboard(): void {
		if ( Client_Role::is_client() ) {
			wp_safe_redirect( Screen_Guard::home_url() );
			exit;
		}
	}

	/**
	 * Renders the Home screen.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function render(): void {
		$satori_manifest_summary = Client_Summary::for_user( Admin_Menu::current_client_id() );
		$satori_manifest_embed   = Embed_Code::for_page( (int) $satori_manifest_summary['page_id'] );
		require SATORI_MANIFEST_PATH . 'templates/admin/client-home.php';
	}
}
