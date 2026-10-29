<?php
/**
 * Client block editor lockdown.
 *
 * Limits what clients can do in the block editor: no blocks that reach other
 * content, no code editor, only the plugin's design patterns, and a tidy
 * sidebar.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Admin\Portal;

use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Manifest\Patterns;
use SatoriManifest\Manifest\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Editor_Lockdown
 *
 * Block editor restrictions for clients.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Editor_Lockdown {

	/**
	 * Blocks that could expose or edit content outside the client's page.
	 *
	 * @since 2.0.0
	 * @var   array<int,string>
	 */
	private const DISALLOWED_BLOCKS = array(
		'core/archives',
		'core/block',
		'core/calendar',
		'core/categories',
		'core/comments',
		'core/latest-comments',
		'core/latest-posts',
		'core/loginout',
		'core/navigation',
		'core/page-list',
		'core/pattern',
		'core/post-author',
		'core/post-comments-form',
		'core/post-navigation-link',
		'core/query',
		'core/rss',
		'core/search',
		'core/shortcode',
		'core/html',
		'core/tag-cloud',
		'core/template-part',
		'satori-manifest/registration-form',
	);

	/**
	 * Registers hooks. Each callback checks the user is a client.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'allowed_block_types_all', array( self::class, 'allowed_blocks' ), 20 );
		add_filter( 'block_editor_settings_all', array( self::class, 'editor_settings' ), 20 );
		add_filter( 'should_load_remote_block_patterns', array( self::class, 'remote_patterns' ) );
		add_action( 'add_meta_boxes', array( self::class, 'trim_meta_boxes' ), 999 );
		add_action( 'admin_init', array( self::class, 'trim_post_type_support' ) );
		add_filter( 'wpseo_accessible_post_types', array( self::class, 'hide_seo_tools' ) );
	}

	/**
	 * Hides Yoast SEO's editor panels from clients.
	 *
	 * Search settings for client pages are managed by staff; clients get a
	 * simpler editor. Only applies in wp-admin, so the public SEO output of
	 * client pages is unaffected.
	 * Hooked to 'wpseo_accessible_post_types'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,string>|mixed $post_types  Post types Yoast edits.
	 * @return array<string,string>|mixed
	 */
	public static function hide_seo_tools( $post_types ) {
		return ( is_admin() && Client_Role::is_client() ) ? array() : $post_types;
	}

	/**
	 * Removes blocks that could reach content beyond the client's page.
	 *
	 * Hooked to 'allowed_block_types_all'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  bool|array<int,string> $allowed  Allowed block names, or true for all.
	 * @return bool|array<int,string>
	 */
	public static function allowed_blocks( $allowed ) {
		if ( ! Client_Role::is_client() ) {
			return $allowed;
		}

		$names = is_array( $allowed ) ? $allowed : array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() );

		return array_values(
			array_filter(
				$names,
				static fn( string $name ): bool => ! in_array( $name, self::DISALLOWED_BLOCKS, true ) && ! str_starts_with( $name, 'core/post-' ) && ! str_starts_with( $name, 'core/query-' ) && ! str_starts_with( $name, 'core/comment' )
			)
		);
	}

	/**
	 * Tightens block editor settings for clients.
	 *
	 * Disables the code editor (raw HTML) and limits the pattern inserter to
	 * the plugin's design patterns.
	 * Hooked to 'block_editor_settings_all'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  array<string,mixed> $settings  Editor settings.
	 * @return array<string,mixed>
	 */
	public static function editor_settings( array $settings ): array {
		if ( ! Client_Role::is_client() ) {
			return $settings;
		}

		$settings['codeEditingEnabled'] = false;

		if ( isset( $settings['__experimentalBlockPatterns'] ) && is_array( $settings['__experimentalBlockPatterns'] ) ) {
			$settings['__experimentalBlockPatterns'] = array_values(
				array_filter(
					$settings['__experimentalBlockPatterns'],
					static fn( $pattern ): bool => is_array( $pattern ) && in_array( Patterns::CATEGORY, (array) ( $pattern['categories'] ?? array() ), true )
				)
			);
		}

		return $settings;
	}

	/**
	 * Disables remote pattern directory patterns for clients.
	 *
	 * Hooked to 'should_load_remote_block_patterns'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  bool $load  Whether to load remote patterns.
	 * @return bool
	 */
	public static function remote_patterns( bool $load ): bool {
		return Client_Role::is_client() ? false : $load;
	}

	/**
	 * Removes meta boxes clients do not need on their page and manifest.
	 *
	 * Hooked late to 'add_meta_boxes'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function trim_meta_boxes(): void {
		if ( ! Client_Role::is_client() ) {
			return;
		}

		foreach ( array( 'page', Post_Types::CPT_MANIFEST ) as $post_type ) {
			foreach ( array( 'postcustom', 'slugdiv', 'authordiv', 'commentsdiv', 'commentstatusdiv', 'revisionsdiv', 'wpseo_meta' ) as $box ) {
				remove_meta_box( $box, $post_type, 'normal' );
				remove_meta_box( $box, $post_type, 'side' );
				remove_meta_box( $box, $post_type, 'advanced' );
			}
		}
	}

	/**
	 * Removes comment and author panels from the page editor for clients.
	 *
	 * Hooked to 'admin_init'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function trim_post_type_support(): void {
		if ( ! Client_Role::is_client() ) {
			return;
		}

		foreach ( array( 'comments', 'trackbacks', 'author', 'page-attributes' ) as $feature ) {
			remove_post_type_support( 'page', $feature );
		}
	}
}
