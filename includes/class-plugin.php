<?php
/**
 * Core plugin bootstrap class.
 *
 * Registers all hooks and boots sub-components. Implements a singleton to
 * ensure only one instance is ever loaded.
 *
 * Folder map (includes/) — one folder, one namespace:
 *   core/            Core\         YAML, config, env, settings, security, sanitizing, migrations
 *   manifest/        Manifest\     price-list post type, meta box, blocks and block patterns
 *   clients/         Clients\      client role, provisioning, factory, summary and isolation rules
 *   designs/         Designs\      design registry and applier
 *   branding/        Branding\     client brand settings, fonts, icons; portal brand
 *   registration/    Registration\ plans, applications, approval workflow, sign-up form
 *   billing/         Billing\      Stripe checkout, webhooks, subscriptions, payments log
 *   mail/            Mail\         templated email, transports, branded core emails
 *   fields/          Fields\       ACF field groups loaded from YAML
 *   frontend/        Frontend\     client page template, embed mode and block editor canvas
 *   login/           Login\        branded wp-login.php
 *   pdf/             Pdf\          dependency-free PDF writer and the price-list PDF download
 *   help/            Help\         getting-started content and contextual help tabs
 *   hardening/       Hardening\    login throttling, XML-RPC off, no username leaks
 *   admin/           Admin\        menus and assets
 *   admin/screens/   Admin\Screens\ one class per admin screen
 *   admin/portal/    Admin\Portal\  what clients may see and do in wp-admin
 *   cli/             Cli\          WP-CLI commands
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Admin\Assets;
use SatoriManifest\Admin\Portal\Client_Chrome;
use SatoriManifest\Admin\Portal\Editor_Lockdown;
use SatoriManifest\Admin\Portal\Screen_Guard;
use SatoriManifest\Admin\Screens\Applications_Screen;
use SatoriManifest\Admin\Screens\Branding_Screen;
use SatoriManifest\Admin\Screens\Client_Home;
use SatoriManifest\Admin\Screens\Clients_Screen;
use SatoriManifest\Admin\Screens\Design_Screen;
use SatoriManifest\Admin\Screens\Payments_Screen;
use SatoriManifest\Admin\Screens\Settings_Screen;
use SatoriManifest\Billing\Billing_Portal;
use SatoriManifest\Billing\Checkout;
use SatoriManifest\Billing\Payments;
use SatoriManifest\Billing\Webhooks;
use SatoriManifest\Branding\Page_Title_Sync;
use SatoriManifest\Cli\Billing_Command;
use SatoriManifest\Cli\Client_Command;
use SatoriManifest\Cli\Demo_Command;
use SatoriManifest\Cli\Designs_Command;
use SatoriManifest\Cli\Email_Command;
use SatoriManifest\Clients\Access_Control;
use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Clients\Plain_Titles;
use SatoriManifest\Clients\Provisioner;
use SatoriManifest\Core\Migrations;
use SatoriManifest\Core\Multisite;
use SatoriManifest\Fields\Field_Loader;
use SatoriManifest\Frontend\Client_Page;
use SatoriManifest\Frontend\Editor;
use SatoriManifest\Frontend\Embed;
use SatoriManifest\Hardening\Login_Throttle;
use SatoriManifest\Hardening\Site_Hardening;
use SatoriManifest\Help\Help_Tabs;
use SatoriManifest\Login\Login_Screen;
use SatoriManifest\Mail\Core_Emails;
use SatoriManifest\Pdf\Pdf_Download;
use SatoriManifest\Manifest\Block;
use SatoriManifest\Manifest\Meta_Box;
use SatoriManifest\Manifest\Patterns;
use SatoriManifest\Manifest\Post_Types;
use SatoriManifest\Registration\Applications;
use SatoriManifest\Registration\Registration_Form;

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

		$this->register_manifest();
		$this->register_clients();
		$this->register_registration_and_billing();
		$this->register_front_end();
		$this->register_admin();
		$this->register_hardening();
		$this->register_cli();

		if ( is_multisite() ) {
			add_action( 'wpmu_new_blog', array( Multisite::class, 'new_site_setup' ) );
		}
	}

	/**
	 * Manifest: post types, the price-list block, patterns and the editor meta box.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	private function register_manifest(): void {
		add_action( 'init', array( Post_Types::class, 'register' ) );
		add_action( 'init', array( Migrations::class, 'maybe_run' ), 20 );
		add_action( 'init', array( Block::class, 'register' ) );
		add_action( 'init', array( Patterns::class, 'register' ) );
		add_action( 'add_meta_boxes', array( Meta_Box::class, 'register' ) );
		add_action( 'save_post_' . Post_Types::CPT_MANIFEST, array( Meta_Box::class, 'save' ) );
		add_action( 'acf/init', array( Field_Loader::class, 'register' ) );
	}

	/**
	 * Clients: role, provisioning, server-side isolation and branding sync.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	private function register_clients(): void {
		add_action( 'init', array( Client_Role::class, 'maybe_sync' ) );
		add_action( 'user_register', array( Provisioner::class, 'on_user_register' ), 20 );
		add_action( 'set_user_role', array( Provisioner::class, 'on_set_role' ), 20, 2 );
		add_action( 'delete_user', array( Provisioner::class, 'on_delete_user' ) );
		Access_Control::register();
		Plain_Titles::register();
		Page_Title_Sync::register();
	}

	/**
	 * Sign-up and payments: applications, the registration form, Stripe and email.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	private function register_registration_and_billing(): void {
		add_action( 'init', array( Applications::class, 'register' ) );
		add_action( 'init', array( Payments::class, 'register' ) );
		add_action( 'rest_api_init', array( Webhooks::class, 'register_route' ) );
		Registration_Form::register();
		Checkout::register();
		Billing_Portal::register();
		Core_Emails::register();
	}

	/**
	 * Security hardening: login throttling, XML-RPC off, no username leaks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return void
	 */
	private function register_hardening(): void {
		Login_Throttle::register();
		Site_Hardening::register();
	}

	/**
	 * Front end: client pages, the block editor canvas and the login screen.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	private function register_front_end(): void {
		Client_Page::register();
		Embed::register();
		Pdf_Download::register();
		Editor::register();
		Login_Screen::register();
	}

	/**
	 * Admin: menus, screens, assets, help and the client lockdown.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	private function register_admin(): void {
		add_action( 'admin_menu', array( Admin_Menu::class, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( Assets::class, 'enqueue_admin' ) );
		add_action( 'load-index.php', array( Client_Home::class, 'redirect_dashboard' ) );
		add_action( 'admin_post_' . Applications_Screen::ACTION, array( Applications_Screen::class, 'handle' ) );
		add_action( 'admin_post_' . Design_Screen::ACTION, array( Design_Screen::class, 'handle' ) );
		add_action( 'admin_post_' . Clients_Screen::ACTION, array( Clients_Screen::class, 'handle' ) );
		Branding_Screen::register();
		Settings_Screen::register();
		Payments_Screen::register();
		Help_Tabs::register();

		// Client portal: what clients may see and do in wp-admin.
		Screen_Guard::register();
		Client_Chrome::register();
		Editor_Lockdown::register();
	}

	/**
	 * WP-CLI commands under "wp satori-manifest".
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	private function register_cli(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'satori-manifest demo', Demo_Command::class );
		\WP_CLI::add_command( 'satori-manifest client', Client_Command::class );
		\WP_CLI::add_command( 'satori-manifest designs', Designs_Command::class );
		\WP_CLI::add_command( 'satori-manifest email', Email_Command::class );
		\WP_CLI::add_command( 'satori-manifest billing', Billing_Command::class );
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
