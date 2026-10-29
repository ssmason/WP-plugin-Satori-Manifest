<?php
/**
 * Asset enqueue management.
 *
 * All compiled assets live in build/ (see webpack.config.js). This class
 * provides versioned enqueue helpers and loads the admin assets:
 *
 *   - build/css/admin.css + build/js/manifest-admin.js on the manifest edit screen.
 *   - build/css/admin.css on the plugin's own screens (home, design, branding, clients).
 *
 * Block editor and front-end block styles are registered via block/block.json.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Admin;

use SatoriManifest\Branding\Portal_Brand;
use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Manifest\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets
 *
 * Versioned enqueue helpers and admin asset loading.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */
class Assets {

	/**
	 * Enqueues a plugin stylesheet, versioned by file modification time.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string            $handle    Style handle.
	 * @param  string            $relative  Path relative to the plugin root.
	 * @param  array<int,string> $deps      Dependencies.
	 * @return void
	 */
	public static function enqueue_style( string $handle, string $relative, array $deps = array() ): void {
		wp_enqueue_style( $handle, SATORI_MANIFEST_URL . $relative, $deps, self::version( $relative ) );
	}

	/**
	 * Enqueues a plugin script in the footer, versioned by file modification time.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string            $handle    Script handle.
	 * @param  string            $relative  Path relative to the plugin root.
	 * @param  array<int,string> $deps      Dependencies.
	 * @return void
	 */
	public static function enqueue_script( string $handle, string $relative, array $deps = array() ): void {
		wp_enqueue_script(
			$handle,
			SATORI_MANIFEST_URL . $relative,
			$deps,
			self::version( $relative ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Returns a cache-busting version for a plugin file.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $relative  Path relative to the plugin root.
	 * @return string
	 */
	public static function version( string $relative ): string {
		$path = SATORI_MANIFEST_PATH . $relative;
		return file_exists( $path ) ? (string) filemtime( $path ) : SATORI_MANIFEST_VERSION;
	}

	/**
	 * Enqueues admin assets on plugin screens.
	 *
	 * Hooked to 'admin_enqueue_scripts'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @param  string $hook_suffix  The current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_admin( string $hook_suffix ): void {
		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen ) {
			return;
		}

		$is_manifest_edit = in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) && Post_Types::CPT_MANIFEST === $screen->post_type;
		$is_plugin_screen = str_contains( (string) $screen->id, 'satori-manifest' ) || ( 'edit' === $screen->base && Post_Types::CPT_MANIFEST === $screen->post_type );

		if ( $is_manifest_edit || $is_plugin_screen || Client_Role::is_client() ) {
			self::enqueue_style( 'satori-manifest-admin', 'build/css/admin.css', array( 'dashicons' ) );
			wp_add_inline_style( 'satori-manifest-admin', Portal_Brand::css_variables() );
		}

		if ( Admin_Menu::hook( Admin_Menu::HOME_SLUG ) === $hook_suffix ) {
			self::enqueue_script( 'satori-manifest-client-home', 'build/js/client-home.js', array( 'wp-i18n' ) );
			wp_set_script_translations( 'satori-manifest-client-home', 'satori-manifest', SATORI_MANIFEST_PATH . 'languages' );
		}

		if ( Admin_Menu::hook( Admin_Menu::SETTINGS_SLUG ) === $hook_suffix ) {
			self::enqueue_script( 'satori-manifest-settings', 'build/js/admin-settings.js', array( 'wp-i18n', 'media-editor' ) );
			wp_set_script_translations( 'satori-manifest-settings', 'satori-manifest', SATORI_MANIFEST_PATH . 'languages' );
		}

		if ( $is_manifest_edit ) {
			self::enqueue_script( 'satori-manifest-manifest-admin', 'build/js/manifest-admin.js', array( 'wp-i18n' ) );
			wp_set_script_translations( 'satori-manifest-manifest-admin', 'satori-manifest', SATORI_MANIFEST_PATH . 'languages' );
		}
	}

	/**
	 * Dequeues every stylesheet and script served from the active theme.
	 *
	 * Used on plugin-rendered pages (client and site pages) so theme CSS
	 * cannot leak into them.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return void
	 */
	public static function dequeue_theme_assets(): void {
		$theme_urls = array_unique( array( get_template_directory_uri(), get_stylesheet_directory_uri() ) );

		foreach ( array( wp_styles(), wp_scripts() ) as $dependencies ) {
			foreach ( $dependencies->queue as $handle ) {
				$src = (string) ( $dependencies->registered[ $handle ]->src ?? '' );

				foreach ( $theme_urls as $theme_url ) {
					if ( '' !== $src && str_starts_with( $src, $theme_url ) ) {
						$dependencies->dequeue( $handle );
					}
				}
			}
		}
	}
}
