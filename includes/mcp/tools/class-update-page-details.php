<?php
/**
 * Tool: update_page_details - name, tagline, contact details, hours, currency.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp\Tools;

use SatoriManifest\Mcp\Change_Log;
use SatoriManifest\Mcp\Page_Store;
use SatoriManifest\Mcp\Server\Tool_Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Update_Page_Details
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Update_Page_Details extends Client_Tool {

	/**
	 * What each field is, for the model.
	 */
	private const HELP = array(
		'name'              => 'Business name in the page header.',
		'tagline'           => 'Short line under the name, e.g. "Irish bar · La Zenia".',
		'currency_symbol'   => 'e.g. "€", "£", "$".',
		'currency_position' => '"before" (£5) or "after" (5 €).',
		'address'           => 'Address in the footer; new lines allowed.',
		'opening_hours'     => 'Opening hours in the footer; new lines allowed.',
		'phone'             => 'Phone number.',
		'email'             => 'Contact email address.',
		'footer_note'       => 'Small print, e.g. "Prices include VAT".',
		'website'           => 'Website address.',
		'instagram'         => 'Instagram profile address.',
		'facebook'          => 'Facebook page address.',
		'tiktok'            => 'TikTok profile address.',
		'button_label'      => 'Optional header button text, e.g. "Book now".',
		'button_link'       => 'Where the header button goes.',
		'header_background' => 'Header background as hex, e.g. #044a14; "" for the design\'s own.',
		'header_text'       => 'Header text colour as hex (name, menu, jump links); "" for the design\'s own. Keep it readable.',
	);

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'update_page_details';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Change page details', 'satori-manifest' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Changes the business name, tagline, currency, address, opening hours, phone, email, footer note, social links, header button or header colours. Only the fields given change; an empty string clears a field.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		$properties = array();

		foreach ( array_keys( Page_Store::DETAILS ) as $name ) {
			$properties[ $name ] = array(
				'type'        => 'string',
				'maxLength'   => in_array( $name, array( 'address', 'opening_hours' ), true ) ? 500 : 200,
				'description' => self::HELP[ $name ] ?? '',
			);
		}

		$properties['currency_position']['enum'] = array( 'before', 'after' );

		foreach ( array( 'header_background', 'header_text' ) as $colour ) {
			$properties[ $colour ]['pattern'] = '^(#[0-9a-fA-F]{6})?$';
		}

		$schema                  = self::object( $properties );
		$schema['minProperties'] = 1;

		return $schema;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,bool>
	 */
	public function annotations(): array {
		return array_merge( parent::annotations(), array( 'idempotentHint' => true ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param  array<string,mixed> $args  Arguments.
	 * @return Tool_Result
	 */
	public function call( array $args ): Tool_Result {
		$names = array_keys( array_intersect_key( $args, Page_Store::DETAILS ) );
		$saved = (array) Change_Log::record(
			$this->store,
			$this->name(),
			'Changed ' . str_replace( '_', ' ', implode( ', ', $names ) ),
			array( Change_Log::PART_DETAILS ),
			fn(): array => $this->store->save_details( $args )
		);

		return Tool_Result::ok( 'Saved. Live now.', array( 'details' => $saved ) );
	}
}
