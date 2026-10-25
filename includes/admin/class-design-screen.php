<?php
/**
 * Design screen.
 *
 * Lets a client (or staff, for a chosen client) pick one of the registered
 * designs. Each design card shows a live preview of the client's own page
 * rendered in that design. Applying a design either keeps the page content
 * (styles change only) or replaces it with the design's starter layout —
 * WordPress keeps a revision, so a replacement can be undone.
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
 * Class Design_Screen
 *
 * Renders the design picker and applies the chosen design.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Design_Screen {

	/**
	 * Action name for admin-post.php.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	public const ACTION = 'satori_manifest_apply_design';

	/**
	 * Renders the screen.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function render(): void {
		$satori_manifest_client_id = Admin_Menu::current_client_id();
		$satori_manifest_page_id   = $satori_manifest_client_id ? Client_Repository::page_id( $satori_manifest_client_id ) : 0;
		$satori_manifest_current   = $satori_manifest_page_id ? Client_Repository::design_for_page( $satori_manifest_page_id ) : '';
		$satori_manifest_designs   = Design_Registry::all();
		$satori_manifest_clients   = Client_Role::is_client() ? array() : Client_Repository::all_clients();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own redirect.
		$satori_manifest_notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';

		require SATORI_MANIFEST_PATH . 'admin/views/design-screen.php';
	}

	/**
	 * Applies a design. Hooked to 'admin_post_satori_manifest_apply_design'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function handle(): void {
		// The nonce is read here and verified by Security::verify_form_nonce().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$nonce = isset( $_POST[ Security::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Security::NONCE_FIELD ] ) ) : '';

		if ( ! Security::verify_form_nonce( $nonce, self::ACTION ) ) {
			wp_die( esc_html__( 'This link has expired. Please go back and try again.', 'satori-manifest' ), 403 );
		}

		$page_id = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;
		$design  = isset( $_POST['design'] ) ? sanitize_key( wp_unslash( $_POST['design'] ) ) : '';
		$mode    = isset( $_POST['mode'] ) && 'replace' === sanitize_key( wp_unslash( $_POST['mode'] ) ) ? 'replace' : 'keep';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$client_id = Client_Repository::client_for_page( $page_id );

		if ( ! $client_id || ! current_user_can( 'edit_post', $page_id ) || ! Design_Registry::exists( $design ) ) {
			wp_die( esc_html__( 'Sorry, you cannot change this design.', 'satori-manifest' ), 403 );
		}

		self::apply( $page_id, $design, 'replace' === $mode );

		wp_safe_redirect(
			add_query_arg(
				'sm_notice',
				'replace' === $mode ? 'replaced' : 'applied',
				Admin_Menu::url( Admin_Menu::DESIGN_SLUG, $client_id )
			)
		);
		exit;
	}

	/**
	 * Sets a page's design and optionally replaces its content.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int    $page_id          Client page ID.
	 * @param  string $design           Design slug.
	 * @param  bool   $replace_content  Whether to load the design's starter content.
	 * @return void
	 */
	public static function apply( int $page_id, string $design, bool $replace_content ): void {
		update_post_meta( $page_id, Client_Repository::PAGE_DESIGN, $design );
		Brand::flush();

		if ( ! $replace_content ) {
			return;
		}

		$brand = Brand::for_page( $page_id, $design );

		wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => wp_slash(
					Design_Registry::content(
						$design,
						array_filter(
							array(
								'brand_name' => (string) $brand['name'],
								'tagline'    => (string) $brand['tagline'],
							)
						)
					)
				),
			)
		);
	}
}
