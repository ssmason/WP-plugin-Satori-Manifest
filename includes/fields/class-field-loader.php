<?php
/**
 * ACF field groups from YAML.
 *
 * Reads every file in config/acf/ (or a theme override, see Config) and
 * registers it as an ACF local field group. Field keys are derived from the
 * group and field names, so YAML stays short and human-editable. Works with
 * ACF and ACF Pro; when ACF is not active the plugin falls back to defaults.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Fields;

use SatoriManifest\Branding\Fonts;
use SatoriManifest\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Field_Loader
 *
 * Converts YAML field definitions into ACF local field groups.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Field_Loader {

	/**
	 * Config folder holding the field group files.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	public const FOLDER = 'acf';

	/**
	 * Returns true when ACF (free or Pro) is available.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return bool
	 */
	public static function acf_available(): bool {
		return function_exists( 'acf_add_local_field_group' ) && function_exists( 'acf_form' );
	}

	/**
	 * Registers all YAML field groups with ACF.
	 *
	 * Hooked to 'acf/init'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return void
	 */
	public static function register(): void {
		if ( ! self::acf_available() ) {
			return;
		}

		foreach ( self::groups() as $group ) {
			acf_add_local_field_group( $group );
		}

		add_filter( 'acf/load_field/name=sm_font_heading', array( self::class, 'load_font_choices' ) );
		add_filter( 'acf/load_field/name=sm_font_body', array( self::class, 'load_font_choices' ) );
	}

	/**
	 * Returns all field groups as ACF arrays, keyed by YAML file name.
	 *
	 * Extra plugin metadata (the "screen" block used by the Branding screen)
	 * is kept on the group under the "satori_screen" key.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return array<string,array<string,mixed>>
	 */
	public static function groups(): array {
		$groups = array();

		foreach ( Config::collect( self::FOLDER ) as $name => $data ) {
			if ( empty( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
				continue;
			}

			$slug = sanitize_key( str_replace( '-', '_', $name ) );

			$groups[ $name ] = array(
				'key'                   => (string) ( $data['key'] ?? 'group_sm_' . $slug ),
				'title'                 => (string) ( $data['title'] ?? ucfirst( $name ) ),
				'fields'                => self::normalise_fields( $data['fields'], 'field_sm_' . $slug ),
				'location'              => self::normalise_location( $data['location'] ?? array() ),
				'menu_order'            => (int) ( $data['menu_order'] ?? 0 ),
				'position'              => (string) ( $data['position'] ?? 'normal' ),
				'style'                 => (string) ( $data['style'] ?? 'seamless' ),
				'label_placement'       => (string) ( $data['label_placement'] ?? 'top' ),
				'instruction_placement' => (string) ( $data['instruction_placement'] ?? 'label' ),
				'active'                => true,
				'satori_screen'         => is_array( $data['screen'] ?? null ) ? $data['screen'] : array(),
			);
		}

		return $groups;
	}

	/**
	 * Adds generated keys (recursively) to a list of YAML field definitions.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  array<int,mixed> $fields      Field definitions from YAML.
	 * @param  string           $key_prefix  Prefix for generated keys.
	 * @return array<int,array<string,mixed>>
	 */
	private static function normalise_fields( array $fields, string $key_prefix ): array {
		$out = array();

		foreach ( $fields as $index => $field ) {
			if ( ! is_array( $field ) || empty( $field['type'] ) ) {
				continue;
			}

			$name = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';

			if ( empty( $field['key'] ) ) {
				$field['key'] = $key_prefix . '_' . ( '' !== $name ? $name : $field['type'] . '_' . $index );
			}

			$field['name']  = $name;
			$field['label'] = (string) ( $field['label'] ?? '' );

			foreach ( array( 'sub_fields', 'layouts' ) as $child_key ) {
				if ( isset( $field[ $child_key ] ) && is_array( $field[ $child_key ] ) ) {
					$field[ $child_key ] = self::normalise_fields( $field[ $child_key ], $field['key'] );
				}
			}

			$out[] = $field;
		}

		return $out;
	}

	/**
	 * Normalises a YAML location rule set.
	 *
	 * Accepts ACF's native nested format, or a flat list of rules which is
	 * treated as a single AND group. An empty list means the group is only
	 * rendered explicitly (e.g. via acf_form on the Branding screen).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  mixed $location  Location data from YAML.
	 * @return array<int,array<int,array<string,string>>>
	 */
	private static function normalise_location( $location ): array {
		if ( ! is_array( $location ) || empty( $location ) ) {
			return array();
		}

		$first = reset( $location );

		// Flat list of rules → wrap as one AND group.
		if ( is_array( $first ) && isset( $first['param'] ) ) {
			return array( array_values( $location ) );
		}

		return array_values( $location );
	}

	/**
	 * Populates the font select choices from config/fonts.yaml.
	 *
	 * Hooked to 'acf/load_field/name=sm_font_heading' and '…sm_font_body'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  array<string,mixed> $field  ACF field array.
	 * @return array<string,mixed>
	 */
	public static function load_font_choices( array $field ): array {
		$field['choices'] = array();

		foreach ( Fonts::all() as $slug => $font ) {
			$field['choices'][ $slug ] = $font['label'];
		}

		return $field;
	}
}
