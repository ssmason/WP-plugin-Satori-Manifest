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
use SatoriManifest\Qr\Page_Qr;
use SatoriManifest\Qr\Qr_Renderer;
use SatoriManifest\Stats\Monthly_Summary;
use SatoriManifest\Stats\Page_Views;

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
		$satori_manifest_page_id = (int) $satori_manifest_summary['page_id'];
		$satori_manifest_qr_svg  = $satori_manifest_page_id ? Qr_Renderer::svg( Page_Qr::matrix( $satori_manifest_page_id ), '#0b1620', __( 'Your QR code', 'satori-manifest' ) ) : '';
		$satori_manifest_stats   = $satori_manifest_page_id ? Page_Views::last_days( $satori_manifest_page_id, 30 ) : array(
			'views' => 0,
			'scans' => 0,
		);
		$satori_manifest_wants   = Monthly_Summary::wants( Admin_Menu::current_client_id() );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own redirect.
		$satori_manifest_notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';
		require SATORI_MANIFEST_PATH . 'templates/admin/client-home.php';
	}
}
