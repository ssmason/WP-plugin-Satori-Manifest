<?php
/**
 * Navigation menu support.
 *
 * Every client edits their header links under Appearance → Menus, and
 * WordPress only offers that screen when the active theme declares menu
 * support. The plugin declares it itself, so client navigation works with
 * any theme — including one that registers no menus.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Clients;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu_Support
 *
 * Turns on WordPress navigation menus.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */
class Menu_Support {

	/**
	 * Registers hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'after_setup_theme', array( self::class, 'add_support' ), 99 );
	}

	/**
	 * Declares menu support. Hooked late to 'after_setup_theme'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @return void
	 */
	public static function add_support(): void {
		add_theme_support( 'menus' );
	}
}
