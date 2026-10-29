<?php
/**
 * Brand settings for a client page.
 *
 * Reads the ACF brand/header/footer fields stored on the page and merges
 * them with the selected design's defaults. Values are read with
 * get_post_meta() so rendering does not depend on ACF being active.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Branding;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Designs\Design_Registry;
use SatoriManifest\Fields\Field_Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Brand
 *
 * Resolves the effective brand, header and footer settings for a page.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Brand {

	/**
	 * Request cache keyed by "{page_id}:{design}".
	 *
	 * @since 2.0.0
	 * @var   array<string,array<string,mixed>>
	 */
	private static array $cache = array();

	/**
	 * Clears the request cache (after branding is saved).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function flush(): void {
		self::$cache = array();
	}

	/**
	 * Returns the fully resolved settings for a client page.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int    $page_id      Client page ID.
	 * @param  string $design_slug  Optional design override (e.g. for previews).
	 * @return array<string,mixed>
	 */
	public static function for_page( int $page_id, string $design_slug = '' ): array {
		$design_slug = Design_Registry::exists( $design_slug ) ? $design_slug : Client_Repository::design_for_page( $page_id );
		$cache_key   = $page_id . ':' . $design_slug;

		if ( ! isset( self::$cache[ $cache_key ] ) ) {
			self::$cache[ $cache_key ] = self::resolve( $page_id, $design_slug );
		}

		return self::$cache[ $cache_key ];
	}

	/**
	 * Resolves settings for a page and design (uncached).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int    $page_id      Client page ID.
	 * @param  string $design_slug  Valid design slug.
	 * @return array<string,mixed>
	 */
	private static function resolve( int $page_id, string $design_slug ): array {
		$design = (array) Design_Registry::get( $design_slug );

		$colours = array();
		foreach ( Design_Registry::PALETTE_KEYS as $key ) {
			$override        = sanitize_hex_color( self::meta( $page_id, 'sm_colour_' . $key ) );
			$colours[ $key ] = $override ? $override : (string) ( $design['palette'][ $key ] ?? '' );
		}

		$heading_font = sanitize_key( self::meta( $page_id, 'sm_font_heading' ) );
		$body_font    = sanitize_key( self::meta( $page_id, 'sm_font_body' ) );

		$layout = self::meta( $page_id, 'sm_header_layout' );
		$layout = in_array( $layout, Design_Registry::HEADER_LAYOUTS, true ) ? $layout : (string) ( $design['header'] ?? 'split' );

		$logo_width = absint( self::meta( $page_id, 'sm_brand_logo_width' ) );

		return array(
			'design'   => $design_slug,
			'name'     => self::meta( $page_id, 'sm_brand_name', get_the_title( $page_id ) ),
			'tagline'  => self::meta( $page_id, 'sm_brand_tagline' ),
			'logo_id'  => absint( self::meta( $page_id, 'sm_brand_logo' ) ),
			'logo_w'   => $logo_width ? min( 400, max( 40, $logo_width ) ) : 160,
			'currency' => array(
				'symbol'   => self::meta( $page_id, 'sm_currency_symbol', '£' ),
				'position' => 'after' === self::meta( $page_id, 'sm_currency_position' ) ? 'after' : 'before',
			),
			'colours'  => $colours,
			'fonts'    => array(
				'heading' => Fonts::get( $heading_font ) ? $heading_font : (string) ( $design['fonts']['heading'] ?? '' ),
				'body'    => Fonts::get( $body_font ) ? $body_font : (string) ( $design['fonts']['body'] ?? '' ),
			),
			'header'   => array(
				'layout'    => $layout,
				'show_nav'  => '0' !== self::meta( $page_id, 'sm_header_show_nav', '1' ),
				'sticky'    => '1' === self::meta( $page_id, 'sm_header_sticky' ),
				'cta_label' => self::meta( $page_id, 'sm_header_cta_label' ),
				'cta_url'   => self::meta( $page_id, 'sm_header_cta_url' ),
			),
			'footer'   => array(
				'address'     => self::meta( $page_id, 'sm_footer_address' ),
				'hours'       => self::meta( $page_id, 'sm_footer_hours' ),
				'phone'       => self::meta( $page_id, 'sm_footer_phone' ),
				'email'       => self::meta( $page_id, 'sm_footer_email' ),
				'note'        => self::meta( $page_id, 'sm_footer_note' ),
				'show_credit' => '0' !== self::meta( $page_id, 'sm_footer_show_credit', '1' ),
				'social'      => array_filter(
					array(
						'website'   => self::meta( $page_id, 'sm_social_website' ),
						'instagram' => self::meta( $page_id, 'sm_social_instagram' ),
						'facebook'  => self::meta( $page_id, 'sm_social_facebook' ),
						'tiktok'    => self::meta( $page_id, 'sm_social_tiktok' ),
					)
				),
			),
		);
	}

	/**
	 * Builds the CSS custom properties for a resolved brand.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  array<string,mixed> $brand     Output of for_page().
	 * @param  string              $selector  CSS selector to scope the variables to.
	 * @return string  A CSS rule.
	 */
	public static function css_variables( array $brand, string $selector ): string {
		$vars = array();

		foreach ( (array) $brand['colours'] as $key => $value ) {
			if ( '' !== $value ) {
				$vars[] = '--sm-' . str_replace( '_', '-', (string) $key ) . ':' . $value;
			}
		}

		foreach ( (array) $brand['fonts'] as $role => $slug ) {
			$font = Fonts::get( (string) $slug );
			if ( $font ) {
				$vars[] = '--sm-font-' . $role . ':' . $font['family'];
			}
		}

		$vars[] = '--sm-logo-width:' . (int) $brand['logo_w'] . 'px';

		return $selector . '{' . implode( ';', $vars ) . '}';
	}

	/**
	 * Formats a price with the brand's currency symbol.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  float                $amount    Price.
	 * @param  array<string,string> $currency  { symbol, position }.
	 * @return string  Unescaped display string.
	 */
	public static function format_price( float $amount, array $currency ): string {
		$decimals = ( floor( $amount ) === $amount ) ? 0 : 2;
		$number   = number_format_i18n( $amount, $decimals );
		$symbol   = (string) ( $currency['symbol'] ?? '' );

		return 'after' === ( $currency['position'] ?? 'before' ) ? $number . $symbol : $symbol . $number;
	}

	/**
	 * Writes a brand field, including ACF's field-key reference meta.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int    $page_id  Page ID.
	 * @param  string $name     Field name (e.g. sm_brand_name).
	 * @param  mixed  $value    Value to store.
	 * @return void
	 */
	public static function set( int $page_id, string $name, $value ): void {
		update_post_meta( $page_id, $name, $value );
		self::flush();

		foreach ( Field_Loader::groups() as $group ) {
			foreach ( $group['fields'] as $field ) {
				if ( $field['name'] === $name ) {
					update_post_meta( $page_id, '_' . $name, $field['key'] );
					return;
				}
			}
		}
	}

	/**
	 * Reads a single meta value as a trimmed string.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int    $page_id   Page ID.
	 * @param  string $key       Meta key.
	 * @param  string $fallback  Value used when the meta is missing or empty.
	 * @return string
	 */
	private static function meta( int $page_id, string $key, string $fallback = '' ): string {
		$value = get_post_meta( $page_id, $key, true );

		if ( is_array( $value ) || is_object( $value ) ) {
			return $fallback;
		}

		// ACF stores toggles as "0"/"1", so only a missing or empty value falls back.
		$value = trim( (string) $value );

		return '' === $value ? $fallback : $value;
	}
}
