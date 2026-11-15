<?php
/**
 * Support tickets module: wires storage, screens and clean-up.
 *
 * Clients raise and follow tickets under Support in their dashboard; staff
 * answer them under Manifest → Support tickets. See Tickets for the rules.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.12.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Support;

use SatoriManifest\Support\Admin\Client_Tickets_Screen;
use SatoriManifest\Support\Admin\Staff_Tickets_Screen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Support
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.12.0
 */
final class Support {

	/**
	 * Registers the module's hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( Tickets::class, 'register_post_types' ) );
		add_action( 'delete_user', array( Tickets::class, 'forget' ) );

		Client_Tickets_Screen::register();
		Staff_Tickets_Screen::register();
	}
}
