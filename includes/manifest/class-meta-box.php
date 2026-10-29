<?php
/**
 * Meta box registration and save handler.
 *
 * Registers the "Sections & Items" meta box on the sm_manifest edit screen
 * and handles the form save. Input sanitization is delegated to Sanitizer
 * and nonce handling to Security, keeping this class focused on WordPress
 * meta box wiring only.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Manifest;

use SatoriManifest\Branding\Brand;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Core\Sanitizer;
use SatoriManifest\Core\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Meta_Box
 *
 * Registers and saves the sections meta box on the manifest CPT edit screen.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */
class Meta_Box {

	/**
	 * Nonce action key (without prefix) for the sections save.
	 *
	 * Passed to Security::output_form_nonce() and Security::verify_form_nonce().
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public const NONCE_ACTION = 'save_sections';

	/**
	 * POST field name for the serialised sections JSON.
	 *
	 * The hidden input is populated by manifest-admin.js before the WP form submits.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public const POST_FIELD = 'satori_manifest_sections';

	/**
	 * Registers the meta box with WordPress.
	 *
	 * Hooked to 'add_meta_boxes'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @return void
	 */
	public static function register(): void {
		add_meta_box(
			'satori_manifest_sections',
			__( 'Sections & Items', 'satori-manifest' ),
			array( self::class, 'render' ),
			Post_Types::CPT_MANIFEST,
			'normal',
			'high'
		);

		add_meta_box(
			'satori_manifest_legend',
			__( 'How it works', 'satori-manifest' ),
			array( self::class, 'render_legend' ),
			Post_Types::CPT_MANIFEST,
			'side',
			'default'
		);
	}

	/**
	 * Renders the "How it works" help box in the side column.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @return void
	 */
	public static function render_legend(): void {
		$tips = array(
			array( 'dashicons-category', __( 'Sections group your items — e.g. "Cuts", "Colour", "Treatments".', 'satori-manifest' ) ),
			array( 'dashicons-sort', __( 'Use the arrows to reorder sections and items.', 'satori-manifest' ) ),
			array( 'dashicons-heading', __( 'Leave a price empty to turn an item into a sub-heading.', 'satori-manifest' ) ),
			array( 'dashicons-tag', __( 'Badges like "Popular" or "New" highlight an item.', 'satori-manifest' ) ),
			array( 'dashicons-saved', __( 'Click Update to publish your changes.', 'satori-manifest' ) ),
		);
		?>
		<ul class="sm-tips">
			<?php foreach ( $tips as $tip ) : ?>
				<li><span class="dashicons <?php echo esc_attr( $tip[0] ); ?>" aria-hidden="true"></span><span><?php echo esc_html( $tip[1] ); ?></span></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Renders the Sections & Items meta box HTML.
	 *
	 * Outputs the nonce field via Security, then loads the view template.
	 * The template receives $satori_manifest_sections (array) and outputs:
	 *   - a hidden field that manifest-admin.js serialises into before submit
	 *   - the sections list rendered from saved data
	 *   - <template> elements manifest-admin.js clones when adding sections/items
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @param  \WP_Post $post  The current post object.
	 * @return void
	 */
	public static function render( \WP_Post $post ): void {
		$satori_manifest_sections   = Manifest_Repository::get_sections( $post->ID );
		$satori_manifest_post_field = self::POST_FIELD;
		$satori_manifest_currency   = self::currency_for( $post->ID );
		Security::output_form_nonce( self::NONCE_ACTION );
		require SATORI_MANIFEST_PATH . 'templates/admin/meta-box-sections.php';
	}

	/**
	 * Returns the currency settings of the client page that owns a manifest.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int $manifest_id  Manifest post ID.
	 * @return array{symbol:string,position:string}
	 */
	private static function currency_for( int $manifest_id ): array {
		$client_id = (int) get_post_meta( $manifest_id, Client_Repository::OWNER, true );
		$page_id   = $client_id ? Client_Repository::page_id( $client_id ) : 0;

		if ( ! $page_id ) {
			return array(
				'symbol'   => '£',
				'position' => 'before',
			);
		}

		$brand = Brand::for_page( $page_id );
		return array(
			'symbol'   => (string) $brand['currency']['symbol'],
			'position' => (string) $brand['currency']['position'],
		);
	}

	/**
	 * Saves the sections JSON when the manifest post is saved.
	 *
	 * Hooked to 'save_post_{CPT_MANIFEST}'. Skips autosaves, verifies the
	 * nonce via Security, checks capability, then delegates sanitization to
	 * Sanitizer before persisting to post meta.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  1.0.0
	 * @param  int $post_id  The post being saved.
	 * @return void
	 */
	public static function save( int $post_id ): void {
		// Skip autosave — the form has not been submitted in this case.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Nonce is verified via Security::verify_form_nonce() below.
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$nonce = isset( $_POST[ Security::NONCE_FIELD ] )
			? sanitize_text_field( wp_unslash( (string) $_POST[ Security::NONCE_FIELD ] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! Security::verify_form_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		// Capability check.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Read the raw JSON. Only wp_unslash() here — sanitize_text_field() would
		// strip angle-bracket characters and corrupt the JSON before decoding.
		// Field-level sanitization is handled by Sanitizer after decode.
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw = isset( $_POST[ self::POST_FIELD ] )
			? wp_unslash( (string) $_POST[ self::POST_FIELD ] )
			: '[]';
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$decoded = json_decode( $raw, true );

		$sections = is_array( $decoded ) ? Sanitizer::sanitize_sections( $decoded ) : array();

		// update_post_meta() unslashes its value; slash the JSON so escaped quotes survive.
		update_post_meta( $post_id, Post_Types::META_SECTIONS, wp_slash( (string) wp_json_encode( $sections ) ) );
	}
}
