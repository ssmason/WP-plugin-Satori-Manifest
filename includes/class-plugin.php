<?php
/**
 * Core plugin bootstrap class.
 *
 * Registers all hooks and boots sub-components. Implements a singleton to
 * ensure only one instance is ever loaded.
 *
 * Folder map (includes/):
 *   core/      YAML, config, security and sanitizing helpers
 *   manifest/  price-list CPT, meta box, block and block patterns
 *   clients/   client role, provisioning, data access and isolation rules
 *   designs/   design registry
 *   site/      public site pages (home, register) — template, permissions, installer
 *   registration/ public registration form, applications and their admin screen
 *   mail/      templated email with wp_mail / Brevo transports
 *   fields/    ACF field groups loaded from YAML
 *   frontend/  client page template, brand settings, fonts, icons, editor styles
 *   admin/     admin menus, client lockdown and plugin screens
 *   cli/       WP-CLI commands
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * Main bootstrap — wires all sub-components together via WordPress hooks.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var   Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Private constructor — use Plugin::instance().
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Returns the singleton instance.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers all WordPress hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Manifest.
		add_action( 'init', array( Post_Types::class, 'register' ) );
		add_action( 'init', array( Block::class, 'register' ) );
		add_action( 'init', array( Patterns::class, 'register' ) );
		add_action( 'add_meta_boxes', array( Meta_Box::class, 'register' ) );
		add_action( 'save_post_' . Post_Types::CPT_MANIFEST, array( Meta_Box::class, 'save' ) );

		// Clients.
		add_action( 'init', array( Client_Role::class, 'maybe_sync' ) );
		add_action( 'user_register', array( Provisioner::class, 'on_user_register' ), 20 );
		add_action( 'set_user_role', array( Provisioner::class, 'on_set_role' ), 20, 2 );
		add_action( 'delete_user', array( Provisioner::class, 'on_delete_user' ) );
		Access_Control::register();

		// Registration & email.
		add_action( 'init', array( Applications::class, 'register' ) );
		Registration_Form::register();

		// Fields.
		add_action( 'acf/init', array( Field_Loader::class, 'register' ) );

		// Front end and editor.
		Client_Page::register();
		Site_Page::register();
		Editor::register();

		// Admin.
		add_action( 'admin_menu', array( Admin_Menu::class, 'register' ) );
		add_action( 'admin_menu', array( Applications_Screen::class, 'register_menu' ), 11 );
		add_action( 'admin_post_' . Applications_Screen::ACTION, array( Applications_Screen::class, 'handle' ) );
		add_action( 'admin_enqueue_scripts', array( Assets::class, 'enqueue_admin' ) );
		add_action( 'load-index.php', array( Client_Home::class, 'redirect_dashboard' ) );
		add_action( 'admin_post_' . Design_Screen::ACTION, array( Design_Screen::class, 'handle' ) );
		add_action( 'admin_post_' . Clients_Screen::ACTION, array( Clients_Screen::class, 'handle' ) );
		Admin_Lockdown::register();
		Branding_Screen::register();

		// CLI.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'satori-manifest', Cli::class );
		}

		if ( is_multisite() ) {
			add_action( 'wpmu_new_blog', array( Multisite::class, 'new_site_setup' ) );
		}
	}

	/**
	 * Loads the plugin text domain for translations.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'satori-manifest',
			false,
			dirname( plugin_basename( SATORI_MANIFEST_FILE ) ) . '/languages'
		);
	}

	/**
	 * Runs on plugin activation.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @return void
	 */
	public static function activate(): void {
		Post_Types::register();
		Client_Role::sync_caps();
		flush_rewrite_rules();
		update_option( 'satori_manifest_version', SATORI_MANIFEST_VERSION );
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
