<?php
/**
 * WP-CLI commands.
 *
 * Usage:
 *   wp satori-manifest demo seed [--password=<password>] [--reset]
 *   wp satori-manifest demo remove
 *   wp satori-manifest client create <name> <email> [--login=<login>] [--design=<slug>] [--password=<password>]
 *   wp satori-manifest client list
 *   wp satori-manifest designs
 *   wp satori-manifest site install [--reset]
 *   wp satori-manifest email test <template> <to>
 *
 * Demo clients are defined in config/demo/*.yaml (overridable from a theme).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage Satori Manifest clients, designs and demo data.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Cli {

	/**
	 * User meta flag marking demo clients.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	private const DEMO_FLAG = 'satori_manifest_demo';

	/**
	 * Seeds or removes demo clients.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : "seed" or "remove".
	 *
	 * [--password=<password>]
	 * : Password for every demo user. Default: ManifestDemo!2026
	 *
	 * [--reset]
	 * : Remove existing demo clients before seeding.
	 *
	 * ## EXAMPLES
	 *
	 *     wp satori-manifest demo seed
	 *     wp satori-manifest demo seed --reset --password=secret
	 *     wp satori-manifest demo remove
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  array<int,string>    $args        Positional args.
	 * @param  array<string,string> $assoc_args  Named args.
	 * @return void
	 */
	public function demo( array $args, array $assoc_args ): void {
		$action = $args[0] ?? '';

		if ( 'remove' === $action ) {
			$this->remove_demo();
			return;
		}

		if ( 'seed' !== $action ) {
			\WP_CLI::error( 'Usage: wp satori-manifest demo <seed|remove>' );
		}

		if ( isset( $assoc_args['reset'] ) ) {
			$this->remove_demo();
		}

		$password = (string) ( $assoc_args['password'] ?? 'ManifestDemo!2026' );
		$rows     = array();

		foreach ( Config::collect( 'demo' ) as $file => $data ) {
			$rows[] = $this->seed_client( $file, $data, $password );
		}

		\WP_CLI\Utils\format_items( 'table', array_filter( $rows ), array( 'login', 'password', 'design', 'page', 'menu' ) );
		\WP_CLI::success( sprintf( 'Seeded %d demo clients. Log in at %s', count( array_filter( $rows ) ), wp_login_url() ) );
	}

	/**
	 * Creates or lists clients.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : "create" or "list".
	 *
	 * [<name>]
	 * : Business name (create).
	 *
	 * [<email>]
	 * : Email address (create).
	 *
	 * [--login=<login>]
	 * : Username. Generated from the name when omitted.
	 *
	 * [--design=<slug>]
	 * : Starting design. See `wp satori-manifest designs`.
	 *
	 * [--password=<password>]
	 * : Password. Random when omitted.
	 *
	 * ## EXAMPLES
	 *
	 *     wp satori-manifest client create "Blue Door Barbers" hello@bluedoor.test --design=studio
	 *     wp satori-manifest client list
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  array<int,string>    $args        Positional args.
	 * @param  array<string,string> $assoc_args  Named args.
	 * @return void
	 */
	public function client( array $args, array $assoc_args ): void {
		$action = $args[0] ?? '';

		if ( 'list' === $action ) {
			$rows = array();
			foreach ( Client_Repository::all_clients() as $client ) {
				$summary = Client_Home::summary( $client->ID );
				$rows[]  = array(
					'id'       => $client->ID,
					'login'    => $client->user_login,
					'name'     => $summary['brand']['name'] ?? $client->display_name,
					'design'   => $summary['design']['slug'] ?? '',
					'sections' => $summary['sections'],
					'items'    => $summary['items'],
					'links'    => $summary['menu_items'],
					'url'      => $summary['page_url'],
				);
			}
			\WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'login', 'name', 'design', 'sections', 'items', 'links', 'url' ) );
			return;
		}

		if ( 'create' !== $action || empty( $args[1] ) || empty( $args[2] ) ) {
			\WP_CLI::error( 'Usage: wp satori-manifest client create <name> <email> [--login=] [--design=] [--password=]' );
		}

		$user_id = Clients_Screen::create_client(
			(string) $args[1],
			(string) $args[2],
			(string) ( $assoc_args['login'] ?? '' ),
			(string) ( $assoc_args['design'] ?? '' ),
			(string) ( $assoc_args['password'] ?? '' )
		);

		if ( is_wp_error( $user_id ) ) {
			\WP_CLI::error( $user_id->get_error_message() );
		}

		\WP_CLI::success( sprintf( 'Created client #%d — %s', $user_id, get_permalink( Client_Repository::page_id( $user_id ) ) ) );
	}

	/**
	 * Installs the public site pages (Home + Register) and sets the front page.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : "install".
	 *
	 * [--reset]
	 * : Rewrite the pages' content from the plugin templates (a revision is kept).
	 *
	 * ## EXAMPLES
	 *
	 *     wp satori-manifest site install
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  array<int,string>    $args        Positional args.
	 * @param  array<string,string> $assoc_args  Named args.
	 * @return void
	 */
	public function site( array $args, array $assoc_args ): void {
		if ( 'install' !== ( $args[0] ?? '' ) ) {
			\WP_CLI::error( 'Usage: wp satori-manifest site install [--reset]' );
		}

		$ids = Site_Installer::install( isset( $assoc_args['reset'] ) );

		foreach ( $ids as $key => $id ) {
			\WP_CLI::log( sprintf( '%-9s #%d  %s', $key, $id, get_permalink( $id ) ) );
		}

		\WP_CLI::success( 'Site pages ready. Home is the front page.' );
	}

	/**
	 * Sends a test email from a template.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : "test".
	 *
	 * <template>
	 * : Template name, e.g. application-approved.
	 *
	 * <to>
	 * : Recipient email address.
	 *
	 * ## EXAMPLES
	 *
	 *     wp satori-manifest email test application-received me@example.com
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  array<int,string> $args  Positional args.
	 * @return void
	 */
	public function email( array $args ): void {
		if ( 'test' !== ( $args[0] ?? '' ) || empty( $args[1] ) || empty( $args[2] ) ) {
			\WP_CLI::error( 'Usage: wp satori-manifest email test <template> <to>' );
		}

		$result = Mailer::send(
			(string) $args[1],
			(string) $args[2],
			array(
				'contact_name'     => 'Sam',
				'business_name'    => 'Test Business',
				'username'         => 'testbusiness',
				'set_password_url' => wp_lostpassword_url(),
				'page_url'         => home_url( '/' ),
				'review_url'       => Applications_Screen::url(),
			)
		);

		if ( $result['sent'] ) {
			\WP_CLI::success( sprintf( 'Sent "%s" via %s.', $result['subject'], $result['transport'] ) );
			return;
		}

		\WP_CLI::warning( sprintf( 'Not sent via %s: %s', $result['transport'], $result['error'] ) );
	}

	/**
	 * Lists the registered designs.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public function designs(): void {
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

	/**
	 * Creates one demo client from its YAML definition.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string              $file      Demo file name (for messages).
	 * @param  array<string,mixed> $data      Parsed YAML.
	 * @param  string              $password  Password to set.
	 * @return array<string,string>|null  Table row, or null when skipped.
	 */
	private function seed_client( string $file, array $data, string $password ): ?array {
		$user  = (array) ( $data['user'] ?? array() );
		$login = sanitize_user( (string) ( $user['login'] ?? $file ), true );

		if ( username_exists( $login ) ) {
			\WP_CLI::warning( sprintf( '%s already exists — skipped (use --reset to recreate).', $login ) );
			return null;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => (string) ( $user['email'] ?? $login . '@example.test' ),
				'user_pass'    => $password,
				'display_name' => (string) ( $user['name'] ?? $login ),
				'nickname'     => (string) ( $user['name'] ?? $login ),
				'role'         => Client_Role::ROLE,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			\WP_CLI::warning( sprintf( '%s: %s', $file, $user_id->get_error_message() ) );
			return null;
		}

		update_user_meta( $user_id, self::DEMO_FLAG, 1 );

		// Role assignment provisioned the basics; replace with the demo content.
		$page_id     = Client_Repository::page_id( $user_id );
		$manifest_id = Client_Repository::manifest_id( $user_id );
		$menu_id     = Client_Repository::menu_id( $user_id );

		foreach ( (array) ( $data['brand'] ?? array() ) as $field => $value ) {
			Brand::set( $page_id, sanitize_key( (string) $field ), is_bool( $value ) ? (int) $value : (string) $value );
		}

		// Match the Branding screen: page title follows the business name (slug unchanged).
		Branding_Screen::after_save( $page_id );

		if ( ! empty( $data['sections'] ) && is_array( $data['sections'] ) ) {
			update_post_meta( $manifest_id, Post_Types::META_SECTIONS, wp_slash( (string) wp_json_encode( Sanitizer::sanitize_sections( $data['sections'] ) ) ) );
		}

		$design = (string) ( $data['design'] ?? '' );
		if ( Design_Registry::exists( $design ) ) {
			Design_Screen::apply( $page_id, $design, true );
		}

		foreach ( (array) ( $data['menu'] ?? array() ) as $position => $link ) {
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'    => (string) ( $link['label'] ?? '' ),
					'menu-item-url'      => esc_url_raw( (string) ( $link['url'] ?? '' ) ),
					'menu-item-status'   => 'publish',
					'menu-item-type'     => 'custom',
					'menu-item-position' => $position + 1,
				)
			);
		}

		return array(
			'login'    => $login,
			'password' => $password,
			'design'   => $design,
			'page'     => (string) get_permalink( $page_id ),
			'menu'     => empty( $data['menu'] ) ? 'none (standalone)' : count( (array) $data['menu'] ) . ' links',
		);
	}

	/**
	 * Deletes every demo client with their page, manifest and menu.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	private function remove_demo(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		$users = get_users(
			array(
				'meta_key'   => self::DEMO_FLAG, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- CLI-only, small user table.
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		foreach ( $users as $user ) {
			// Deleting without reassignment also deletes their page and manifest.
			wp_delete_user( $user->ID );
			\WP_CLI::log( sprintf( 'Removed %s', $user->user_login ) );
		}
	}
}
