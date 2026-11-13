<?php
/**
 * Reads and writes the parts of a client's page the assistant may change.
 *
 * The single place assistant writes happen: the price list (through the
 * plugin's own Sanitizer), page details (through Brand::set), the design
 * (through Design_Applier) and the page content (kept for undo). Every
 * write clears the page from the page cache.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp;

use SatoriManifest\Branding\Brand;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Core\Page_Cache;
use SatoriManifest\Core\Sanitizer;
use SatoriManifest\Designs\Design_Applier;
use SatoriManifest\Manifest\Manifest_Repository;
use SatoriManifest\Manifest\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Page_Store
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Page_Store {

	/**
	 * Page details the assistant may edit: name => [ meta key, kind ].
	 *
	 * Kinds: text, textarea, email, url, position.
	 */
	public const DETAILS = array(
		'name'              => array( 'sm_brand_name', 'text' ),
		'tagline'           => array( 'sm_brand_tagline', 'text' ),
		'currency_symbol'   => array( 'sm_currency_symbol', 'text' ),
		'currency_position' => array( 'sm_currency_position', 'position' ),
		'address'           => array( 'sm_footer_address', 'textarea' ),
		'opening_hours'     => array( 'sm_footer_hours', 'textarea' ),
		'phone'             => array( 'sm_footer_phone', 'text' ),
		'email'             => array( 'sm_footer_email', 'email' ),
		'footer_note'       => array( 'sm_footer_note', 'text' ),
		'website'           => array( 'sm_social_website', 'url' ),
		'instagram'         => array( 'sm_social_instagram', 'url' ),
		'facebook'          => array( 'sm_social_facebook', 'url' ),
		'tiktok'            => array( 'sm_social_tiktok', 'url' ),
		'button_label'      => array( 'sm_header_cta_label', 'text' ),
		'button_link'       => array( 'sm_header_cta_url', 'url' ),
	);

	/**
	 * Constructor.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  Client_Context $context  Client.
	 */
	public function __construct( private readonly Client_Context $context ) {}

	/**
	 * The client.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return Client_Context
	 */
	public function context(): Client_Context {
		return $this->context;
	}

	/**
	 * The stored sections.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return array<int,array<string,mixed>>
	 */
	public function sections(): array {
		return Manifest_Repository::get_sections( $this->context->manifest_id );
	}

	/**
	 * Saves the whole price list (sanitised; new sections get permanent IDs).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<int,array<string,mixed>> $sections  Sections.
	 * @return array<int,array<string,mixed>>  What was stored.
	 */
	public function save_sections( array $sections ): array {
		$clean = Sanitizer::sanitize_sections( $sections );

		// update_post_meta() unslashes; slash the JSON so escaped quotes survive.
		update_post_meta( $this->context->manifest_id, Post_Types::META_SECTIONS, wp_slash( (string) wp_json_encode( $clean ) ) );
		Page_Cache::clear( $this->context->page_id );

		return $clean;
	}

	/**
	 * Current page details by assistant name.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return array<string,string>
	 */
	public function details(): array {
		$out = array();

		foreach ( self::DETAILS as $name => $field ) {
			$value        = get_post_meta( $this->context->page_id, $field[0], true );
			$out[ $name ] = is_scalar( $value ) ? (string) $value : '';
		}

		// Show what the page actually uses where a field falls back to a default.
		$brand                    = Brand::for_page( $this->context->page_id );
		$out['name']              = (string) $brand['name'];
		$out['currency_symbol']   = (string) $brand['currency']['symbol'];
		$out['currency_position'] = (string) $brand['currency']['position'];

		return $out;
	}

	/**
	 * Saves page details (only the names given; unknown names are ignored).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<string,mixed> $values  Name => value ('' clears).
	 * @return array<string,string>  What was stored, by name.
	 */
	public function save_details( array $values ): array {
		$saved = array();

		foreach ( array_intersect_key( $values, self::DETAILS ) as $name => $value ) {
			[ $key, $kind ] = self::DETAILS[ $name ];
			$clean          = self::clean_detail( (string) $value, $kind );
			$saved[ $name ] = $clean;

			Brand::set( $this->context->page_id, $key, $clean );
		}

		if ( array() !== $saved ) {
			Page_Cache::clear( $this->context->page_id );
		}

		return $saved;
	}

	/**
	 * Current design slug and colour mode.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return array{design:string,mode:string}
	 */
	public function design(): array {
		$design = Client_Repository::design_for_page( $this->context->page_id );

		return array(
			'design' => $design,
			'mode'   => Client_Repository::mode_for_page( $this->context->page_id, $design ),
		);
	}

	/**
	 * Applies a design, optionally loading its ready-made layout.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string $design   Valid design slug.
	 * @param  string $mode     'light', 'dark' or '' to keep.
	 * @param  bool   $layout   Replace the page layout with the design's.
	 * @return void
	 */
	public function apply_design( string $design, string $mode, bool $layout ): void {
		Design_Applier::apply( $this->context->page_id, $design, $layout, $mode );
	}

	/**
	 * Restores a design and colour mode exactly (undo), without touching content.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string $design  Design slug.
	 * @param  string $mode    Colour mode.
	 * @return void
	 */
	public function restore_design( string $design, string $mode ): void {
		Design_Applier::apply( $this->context->page_id, $design, false, $mode );
	}

	/**
	 * The page's block content.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return string
	 */
	public function content(): string {
		return (string) get_post_field( 'post_content', $this->context->page_id, 'raw' );
	}

	/**
	 * Restores the page's block content (undo).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string $content  Block content captured earlier by this class.
	 * @return void
	 */
	public function restore_content( string $content ): void {
		wp_update_post(
			array(
				'ID'           => $this->context->page_id,
				'post_content' => wp_slash( $content ),
			)
		);
		Page_Cache::clear( $this->context->page_id );
	}

	/**
	 * The live page address.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return string
	 */
	public function url(): string {
		return (string) get_permalink( $this->context->page_id );
	}

	/**
	 * Sanitises one detail value.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string $value  Raw value.
	 * @param  string $kind   Field kind.
	 * @return string
	 */
	private static function clean_detail( string $value, string $kind ): string {
		return match ( $kind ) {
			'textarea' => sanitize_textarea_field( $value ),
			'email'    => sanitize_email( $value ),
			'url'      => esc_url_raw( self::with_scheme( trim( $value ) ) ),
			'position' => 'after' === $value ? 'after' : 'before',
			default    => sanitize_text_field( $value ),
		};
	}

	/**
	 * Adds https:// to bare addresses ("instagram.com/x").
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string $url  Address.
	 * @return string
	 */
	private static function with_scheme( string $url ): string {
		return '' === $url || preg_match( '#^https?://#i', $url ) ? $url : 'https://' . ltrim( $url, '/' );
	}
}
