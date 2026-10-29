<?php
/**
 * Block editor integration for client pages.
 *
 * When a client page is open in the block editor, the canvas is styled with
 * the page's design, brand colours and fonts so editing is WYSIWYG. Design
 * stylesheets are scoped to the front-end body classes; here those
 * selectors are rewritten to the editor canvas wrapper.
 *
 * Also passes the page's own manifest ID and currency to the Manifest
 * block, so a block with no manifest selected previews the client's list.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Frontend;

use SatoriManifest\Branding\Brand;
use SatoriManifest\Branding\Fonts;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Designs\Design_Registry;
use SatoriManifest\Manifest\Block;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Editor
 *
 * Editor canvas styles and block editor data for client pages.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Editor {

	/**
	 * Registers editor hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'enqueue_block_assets', array( self::class, 'enqueue_canvas_styles' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'localize_block_data' ) );
		add_filter( 'block_editor_settings_all', array( self::class, 'strip_theme_styles' ), 99, 2 );
	}

	/**
	 * Removes the active theme's editor styles when editing a client page.
	 *
	 * Mirrors Assets::dequeue_theme_assets() on the front end: client pages
	 * are styled only by the plugin, so the editor canvas shows the client's
	 * design rather than theme typography and colours. Theme styles are
	 * recognised by type ('theme') or by referencing the theme's URL.
	 * Hooked late to 'block_editor_settings_all'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.2.0
	 * @param  array<string,mixed>      $settings  Editor settings.
	 * @param  \WP_Block_Editor_Context $context   Editor context.
	 * @return array<string,mixed>
	 */
	public static function strip_theme_styles( array $settings, \WP_Block_Editor_Context $context ): array {
		if ( ! $context->post instanceof \WP_Post || ! Client_Repository::is_client_page( $context->post->ID ) || empty( $settings['styles'] ) ) {
			return $settings;
		}

		$theme_urls = array_unique( array( get_template_directory_uri(), get_stylesheet_directory_uri() ) );

		$settings['styles'] = array_values(
			array_filter(
				(array) $settings['styles'],
				static function ( $style ) use ( $theme_urls ): bool {
					if ( ! is_array( $style ) ) {
						return true;
					}

					if ( 'theme' === ( $style['__unstableType'] ?? '' ) ) {
						return false;
					}

					$haystack = (string) ( $style['css'] ?? '' ) . ' ' . (string) ( $style['baseURL'] ?? '' );

					foreach ( $theme_urls as $theme_url ) {
						if ( str_contains( $haystack, $theme_url ) ) {
							return false;
						}
					}

					return true;
				}
			)
		);

		return $settings;
	}

	/**
	 * Returns the client page currently open in the editor, or 0.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return int
	 */
	public static function current_page_id(): int {
		if ( ! is_admin() ) {
			return 0;
		}

		$post = get_post();

		return ( $post instanceof \WP_Post && Client_Repository::is_client_page( $post->ID ) ) ? $post->ID : 0;
	}

	/**
	 * Adds design, brand and font styles to the editor canvas.
	 *
	 * Hooked to 'enqueue_block_assets' (which also loads inside the iframe).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function enqueue_canvas_styles(): void {
		$page_id = self::current_page_id();

		if ( ! $page_id ) {
			return;
		}

		$brand  = Brand::for_page( $page_id );
		$design = (array) Design_Registry::get( $brand['design'] );
		$css    = self::scoped_css( SATORI_MANIFEST_PATH . 'build/css/client-page.css', 'sm-client-page' );

		if ( ! empty( $design['style_path'] ) ) {
			$css .= self::scoped_css( (string) $design['style_path'], 'sm-design-' . $brand['design'] );
		}

		$css .= Brand::css_variables( $brand, '.editor-styles-wrapper' );

		wp_register_style( 'satori-manifest-editor-canvas', false, array(), SATORI_MANIFEST_VERSION );
		wp_enqueue_style( 'satori-manifest-editor-canvas' );
		wp_add_inline_style( 'satori-manifest-editor-canvas', $css );

		$fonts_url = Fonts::google_url( array_values( $brand['fonts'] ) );
		if ( '' !== $fonts_url ) {
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URLs must not carry a ver query.
			wp_enqueue_style( 'satori-manifest-editor-fonts', $fonts_url, array(), null );
		}
	}

	/**
	 * Passes page context to the Manifest block's editor script.
	 *
	 * Hooked to 'enqueue_block_editor_assets'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function localize_block_data(): void {
		$page_id = self::current_page_id();
		$brand   = $page_id ? Brand::for_page( $page_id ) : array();
		$handle  = generate_block_asset_handle( Block::BLOCK_NAME, 'editorScript' );

		wp_add_inline_script(
			$handle,
			'window.satoriManifestEditor = ' . wp_json_encode(
				array(
					'defaultManifestId' => $page_id ? Client_Repository::manifest_for_page( $page_id ) : 0,
					'currency'          => $brand['currency'] ?? array(
						'symbol'   => '£',
						'position' => 'before',
					),
					'isClient'          => Client_Role::is_client(),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Reads a compiled stylesheet and rescopes it to the editor canvas.
	 *
	 * Front-end selectors use body classes (.sm-client-page, .sm-design-*);
	 * inside the editor the equivalent root is .editor-styles-wrapper.
	 * Header/footer rules are harmless in the editor (those elements are absent).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $path        Absolute path to a CSS file.
	 * @param  string $body_class  Body class to replace.
	 * @return string
	 */
	public static function scoped_css( string $path, string $body_class ): string {
		if ( ! is_readable( $path ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local compiled stylesheet.
		$css = (string) file_get_contents( $path );
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );

		// Content lives in .sm-main on the front end; in the editor the block
		// list root (.is-root-container) plays that role.
		return str_replace(
			array( 'body.' . $body_class, '.' . $body_class, '.sm-main' ),
			array( '.editor-styles-wrapper', '.editor-styles-wrapper', '.is-root-container' ),
			$css
		);
	}
}
