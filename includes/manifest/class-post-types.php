<?php
/**
 * Custom post type registration.
 *
 * Registers the sm_manifest CPT and its associated post meta fields.
 * This class is responsible only for WordPress registration — data access
 * is handled by Manifest_Repository (class-manifest-repository.php).
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
 * Class Post_Types
 *
 * Registers the sm_manifest CPT and its post meta with WordPress.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */
class Post_Types {

	/**
	 * CPT slug constant.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public const CPT_MANIFEST = 'sm_manifest';

	/**
	 * Meta key for storing sections (each containing items) as JSON.
	 *
	 * Each element shape:
	 * { "title": string, "description": string, "sort_order": int,
	 *   "items": [ { label, description, price, price_prefix, badge, sort_order } ] }
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public const META_SECTIONS = '_satori_manifest_sections';

	/**
	 * Meta key for manifest sort order (used for admin list ordering).
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public const META_ORDER = '_satori_manifest_order';

	/**
	 * Singular capability base for the manifest CPT.
	 *
	 * Manifests use their own capability set so clients can edit their own
	 * manifest without being able to create new ones or touch other clients'.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	public const CAP_SINGULAR = 'sm_manifest';

	/**
	 * Plural capability base for the manifest CPT.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	public const CAP_PLURAL = 'sm_manifests';

	/**
	 * Returns every primitive capability used by the manifest CPT.
	 *
	 * Granted in full to administrators and editors by Client_Role::sync_caps().
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return array<int,string>
	 */
	public static function all_caps(): array {
		$plural = self::CAP_PLURAL;

		return array(
			"edit_{$plural}",
			"edit_others_{$plural}",
			"edit_published_{$plural}",
			"edit_private_{$plural}",
			"publish_{$plural}",
			"read_private_{$plural}",
			"delete_{$plural}",
			"delete_others_{$plural}",
			"delete_published_{$plural}",
			"delete_private_{$plural}",
			"create_{$plural}",
		);
	}

	/**
	 * Registers the manifest CPT and its associated post meta.
	 *
	 * Hooked to 'init'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @return void
	 */
	public static function register(): void {
		self::register_manifest_cpt();
		self::register_meta();
	}

	/**
	 * Registers the sm_manifest CPT.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @return void
	 */
	private static function register_manifest_cpt(): void {
		$labels = array(
			'name'               => __( 'Manifests', 'satori-manifest' ),
			'singular_name'      => __( 'Manifest', 'satori-manifest' ),
			'add_new'            => __( 'Add New Manifest', 'satori-manifest' ),
			'add_new_item'       => __( 'Add New Manifest', 'satori-manifest' ),
			'edit_item'          => __( 'Edit Manifest', 'satori-manifest' ),
			'new_item'           => __( 'New Manifest', 'satori-manifest' ),
			'view_item'          => __( 'View Manifest', 'satori-manifest' ),
			'search_items'       => __( 'Search Manifests', 'satori-manifest' ),
			'not_found'          => __( 'No manifests found.', 'satori-manifest' ),
			'not_found_in_trash' => __( 'No manifests found in Trash.', 'satori-manifest' ),
			'menu_name'          => __( 'Manifests', 'satori-manifest' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => 'satori-manifest',
			'show_in_rest'       => true,
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => array( self::CAP_SINGULAR, self::CAP_PLURAL ),
			'map_meta_cap'       => true,
			'capabilities'       => array(
				// Separate create cap: clients may edit their manifest but never add another.
				'create_posts' => 'create_' . self::CAP_PLURAL,
			),
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'custom-fields' ),
			// A client's manifest is removed with the client unless reassigned.
			'delete_with_user'   => true,
		);

		register_post_type( self::CPT_MANIFEST, $args );
	}

	/**
	 * Registers post meta fields for the manifest CPT.
	 *
	 * Meta is registered with show_in_rest: true so the block editor can
	 * read section data for the live editor preview.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @return void
	 */
	private static function register_meta(): void {
		register_post_meta(
			self::CPT_MANIFEST,
			self::META_SECTIONS,
			array(
				'single'            => true,
				'type'              => 'string',
				'show_in_rest'      => true,
				'auth_callback'     => static function ( bool $allowed, string $meta_key, int $object_id ): bool {
					return current_user_can( 'edit_post', $object_id );
				},
				// sanitize_text_field must not be used here — it strips backslashes
				// and angle brackets, which corrupts JSON escape sequences and
				// encoded characters. Decode, sanitize through Sanitizer (the same
				// path as the meta-box save), then re-encode as clean JSON.
				'sanitize_callback' => static function ( $value ): string {
					if ( ! is_string( $value ) || '' === $value ) {
						return (string) wp_json_encode( array() );
					}
					$decoded = json_decode( $value, true );
					if ( ! is_array( $decoded ) ) {
						return (string) wp_json_encode( array() );
					}
					return (string) wp_json_encode( Sanitizer::sanitize_sections( $decoded ) );
				},
			)
		);

		register_post_meta(
			self::CPT_MANIFEST,
			self::META_ORDER,
			array(
				'single'            => true,
				'type'              => 'integer',
				'show_in_rest'      => true,
				'auth_callback'     => static function ( bool $allowed, string $meta_key, int $object_id ): bool {
					return current_user_can( 'edit_post', $object_id );
				},
				'sanitize_callback' => 'absint',
			)
		);
	}
}
