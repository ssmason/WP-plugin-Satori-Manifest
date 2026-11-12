<?php
/**
 * Client isolation and restrictions.
 *
 * Server-side enforcement for client users - independent of what the admin
 * UI shows or hides (see Admin\Portal for the UI side):
 *
 *   - Clients cannot create pages, posts, manifests, patterns or menus.
 *   - Admin, REST and media queries only return the client's own content.
 *   - Theme-level REST routes and the Customizer are off-limits.
 *
 * Staff users are never affected. Menu and page-template rules live in
 * Menu_Access and Page_Template_Lock.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Clients;

use SatoriManifest\Manifest\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Access_Control
 *
 * Enforces per-client ownership rules.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Access_Control {

	/**
	 * Post types clients may never create.
	 *
	 * @since 2.0.0
	 * @var   array<int,string>
	 */
	private const NO_CREATE_TYPES = array( 'post', 'page', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_global_styles', Post_Types::CPT_MANIFEST );

	/**
	 * Post types whose admin/REST queries are restricted to the client's own posts.
	 *
	 * @since 2.0.0
	 * @var   array<int,string>
	 */
	private const OWNED_TYPES = array( 'post', 'page', 'attachment', 'wp_block', Post_Types::CPT_MANIFEST );

	/**
	 * Post types clients may not edit at all, even though edit_theme_options maps to them.
	 *
	 * @since 2.0.0
	 * @var   array<int,string>
	 */
	private const THEME_TYPES = array( 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_font_family', 'wp_font_face' );

	/**
	 * REST route prefixes clients may not call at all.
	 *
	 * @since 2.0.0
	 * @var   array<int,string>
	 */
	private const BLOCKED_ROUTES = array(
		'/wp/v2/menus',
		'/wp/v2/menu-items',
		'/wp/v2/menu-locations',
		'/wp/v2/navigation',
		'/wp/v2/sidebars',
		'/wp/v2/widgets',
		'/wp/v2/widget-types',
		'/wp/v2/settings',
		'/wp/v2/plugins',
		'/wp/v2/block-directory',
		'/wp/v2/pattern-directory',
		'/wp/v2/font-families',
		'/wp/v2/font-collections',
		'/wp/v2/posts',
	);

	/**
	 * REST route prefixes clients may read but not write.
	 *
	 * @since 2.0.0
	 * @var   array<int,string>
	 */
	private const READ_ONLY_ROUTES = array(
		'/wp/v2/templates',
		'/wp/v2/template-parts',
		'/wp/v2/global-styles',
		'/wp/v2/blocks',
		'/wp/v2/users',
		'/wp/v2/themes',
	);

	/**
	 * Registers all enforcement hooks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_insert_post_empty_content', array( self::class, 'block_new_posts' ), 10, 2 );
		add_filter( 'map_meta_cap', array( self::class, 'map_meta_cap' ), 10, 4 );
		add_action( 'pre_get_posts', array( self::class, 'restrict_queries' ) );
		add_filter( 'ajax_query_attachments_args', array( self::class, 'restrict_media_modal' ) );
		add_filter( 'rest_pre_dispatch', array( self::class, 'restrict_rest' ), 10, 3 );
		add_filter( 'rest_post_search_query', array( self::class, 'restrict_link_search' ) );
		add_filter( 'wp_link_query_args', array( self::class, 'restrict_link_search' ) );
	}

	/**
	 * Prevents clients from inserting new posts of restricted types.
	 *
	 * Catches every creation path (admin, REST, AJAX, XML-RPC) because they
	 * all go through wp_insert_post(). Updates (posts with an ID) pass.
	 * Hooked to 'wp_insert_post_empty_content'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  bool                $maybe_empty  Whether WordPress considers the post empty.
	 * @param  array<string,mixed> $postarr      Post data being inserted.
	 * @return bool  True blocks the insert.
	 */
	public static function block_new_posts( bool $maybe_empty, array $postarr ): bool {
		if ( $maybe_empty || ! empty( $postarr['ID'] ) || ! Client_Role::is_client() ) {
			return $maybe_empty;
		}

		$post_type = (string) ( $postarr['post_type'] ?? 'post' );

		return in_array( $post_type, self::NO_CREATE_TYPES, true );
	}

	/**
	 * Denies theme-level capabilities that edit_theme_options would otherwise grant.
	 *
	 * Hooked to 'map_meta_cap'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  array<int,string> $caps     Primitive caps required.
	 * @param  string            $cap      Capability being checked.
	 * @param  int               $user_id  User ID.
	 * @param  array<int,mixed>  $args     Extra arguments (e.g. post ID).
	 * @return array<int,string>
	 */
	public static function map_meta_cap( array $caps, string $cap, int $user_id, array $args ): array {
		if ( ! Client_Role::is_client( $user_id ) ) {
			return $caps;
		}

		if ( in_array( $cap, array( 'customize', 'edit_css', 'switch_themes', 'edit_theme_options_sidebar' ), true ) ) {
			return array( 'do_not_allow' );
		}

		$post_caps = array( 'edit_post', 'delete_post', 'read_post', 'publish_post' );

		if ( in_array( $cap, $post_caps, true ) && ! empty( $args[0] ) ) {
			$post_type = get_post_type( (int) $args[0] );

			if ( $post_type && in_array( $post_type, self::THEME_TYPES, true ) && 'read_post' !== $cap ) {
				return array( 'do_not_allow' );
			}
		}

		return $caps;
	}

	/**
	 * Restricts admin and REST queries to the client's own content.
	 *
	 * Front-end queries are untouched - client pages are public.
	 * Hooked to 'pre_get_posts'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  \WP_Query $query  The query being prepared.
	 * @return void
	 */
	public static function restrict_queries( \WP_Query $query ): void {
		if ( ! self::is_backend_request() || ! Client_Role::is_client() ) {
			return;
		}

		$post_types = (array) ( $query->get( 'post_type' ) ? $query->get( 'post_type' ) : 'post' );

		if ( array_intersect( $post_types, self::OWNED_TYPES ) ) {
			$query->set( 'author', get_current_user_id() );
			$query->set( 'author__in', array() );
			$query->set( 'author__not_in', array() );
		}
	}

	/**
	 * Restricts the media modal to the client's own uploads.
	 *
	 * Hooked to 'ajax_query_attachments_args'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  array<string,mixed> $query  Attachment query args.
	 * @return array<string,mixed>
	 */
	public static function restrict_media_modal( array $query ): array {
		if ( Client_Role::is_client() ) {
			$query['author'] = get_current_user_id();
		}

		return $query;
	}

	/**
	 * Restricts link search (block editor link control and classic link modal).
	 *
	 * Hooked to 'rest_post_search_query' and 'wp_link_query_args'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  array<string,mixed> $query  Query args.
	 * @return array<string,mixed>
	 */
	public static function restrict_link_search( array $query ): array {
		if ( Client_Role::is_client() ) {
			$query['author'] = get_current_user_id();
		}

		return $query;
	}

	/**
	 * Blocks REST routes clients must not use.
	 *
	 * Hooked to 'rest_pre_dispatch'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  mixed            $result   Response to short-circuit with, or null.
	 * @param  \WP_REST_Server  $server   REST server.
	 * @param  \WP_REST_Request $request  Current request.
	 * @return mixed
	 */
	public static function restrict_rest( $result, \WP_REST_Server $server, \WP_REST_Request $request ) {
		if ( null !== $result || ! Client_Role::is_client() ) {
			return $result;
		}

		$route  = untrailingslashit( $request->get_route() );
		$method = $request->get_method();

		foreach ( self::BLOCKED_ROUTES as $prefix ) {
			if ( self::route_matches( $route, $prefix ) ) {
				return self::rest_forbidden();
			}
		}

		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			foreach ( self::READ_ONLY_ROUTES as $prefix ) {
				if ( self::route_matches( $route, $prefix ) && '/wp/v2/users/me' !== $route ) {
					return self::rest_forbidden();
				}
			}
		}

		return $result;
	}

	/**
	 * Returns true for admin screens, AJAX and REST requests.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return bool
	 */
	private static function is_backend_request(): bool {
		return is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	}

	/**
	 * Returns true when a route equals a prefix or is nested under it.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $route   Request route.
	 * @param  string $prefix  Route prefix.
	 * @return bool
	 */
	private static function route_matches( string $route, string $prefix ): bool {
		return $route === $prefix || str_starts_with( $route, $prefix . '/' );
	}

	/**
	 * Builds the standard REST "forbidden" error.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return \WP_Error
	 */
	private static function rest_forbidden(): \WP_Error {
		return new \WP_Error(
			'satori_manifest_forbidden',
			__( 'Sorry, your account cannot access this.', 'satori-manifest' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
}
