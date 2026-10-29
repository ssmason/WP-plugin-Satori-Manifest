<?php
/**
 * Email builder.
 *
 * Turns a YAML template (config/emails/{name}.yaml) into a subject plus HTML
 * and plain-text bodies. Template keys:
 *
 *   subject       Subject line.
 *   preheader     Preview text shown by mail apps next to the subject.
 *   heading       Large heading at the top of the card.
 *   body          Paragraphs, separated by blank lines.
 *   details       Optional "Label: value" lines, shown as a neat table
 *                 (rows whose value is empty are left out).
 *   button_label  Optional call-to-action button…
 *   button_url    …and where it goes.
 *   footer        Small print under the card.
 *
 * Any value may contain {{placeholders}}; they are replaced, and escaped in
 * the HTML part. The HTML uses the portal logo and colours.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mail;

use SatoriManifest\Branding\Portal_Brand;
use SatoriManifest\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Email_Builder
 *
 * Renders templated emails.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Email_Builder {

	/**
	 * Builds subject, HTML and plain-text bodies from a template.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string               $template  Template name.
	 * @param  array<string,string> $vars      Placeholder values.
	 * @return array{subject:string,html:string,text:string}
	 */
	public static function build( string $template, array $vars = array() ): array {
		$config = Config::get( 'emails/' . sanitize_file_name( $template ) . '.yaml' );

		if ( empty( $config ) ) {
			return array(
				'subject' => '',
				'html'    => '',
				'text'    => '',
			);
		}

		$vars = array_merge( self::global_vars(), array_map( 'strval', $vars ) );
		$fill = static fn( string $text ): string => self::fill( $text, $vars );

		return self::render(
			array(
				'subject'      => trim( $fill( (string) ( $config['subject'] ?? '' ) ) ),
				'preheader'    => $fill( (string) ( $config['preheader'] ?? '' ) ),
				'heading'      => $fill( (string) ( $config['heading'] ?? '' ) ),
				'paragraphs'   => self::paragraphs( $fill( (string) ( $config['body'] ?? '' ) ) ),
				'details'      => self::details( $fill( (string) ( $config['details'] ?? '' ) ) ),
				'button_label' => $fill( (string) ( $config['button_label'] ?? '' ) ),
				'button_url'   => $fill( (string) ( $config['button_url'] ?? '' ) ),
				'footer'       => $fill( (string) ( $config['footer'] ?? '' ) ),
			)
		);
	}

	/**
	 * Renders a message from already-filled parts.
	 *
	 * Used by build() and by Core_Emails to wrap WordPress's own emails.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,mixed> $parts  subject, preheader, heading, paragraphs[], details[], button_label, button_url, footer.
	 * @return array{subject:string,html:string,text:string}
	 */
	public static function render( array $parts ): array {
		$satori_manifest_email = array_merge(
			array(
				'preheader'    => '',
				'heading'      => '',
				'paragraphs'   => array(),
				'details'      => array(),
				'button_label' => '',
				'button_url'   => '',
				'footer'       => '',
			),
			$parts,
			array(
				'site_name' => Portal_Brand::name(),
				'site_url'  => home_url( '/' ),
				'logo_url'  => Portal_Brand::logo_url(),
				'accent'    => Portal_Brand::accent(),
				'on_accent' => Portal_Brand::text_on( Portal_Brand::accent() ),
				'dark'      => Portal_Brand::dark(),
				'support'   => Portal_Brand::support_email(),
			)
		);

		ob_start();
		require SATORI_MANIFEST_PATH . 'templates/email/layout.php';
		$html = (string) ob_get_clean();

		return array(
			'subject' => (string) ( $parts['subject'] ?? '' ),
			'html'    => $html,
			'text'    => self::plain_text( $satori_manifest_email ),
		);
	}

	/**
	 * Placeholders available to every template.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return array<string,string>
	 */
	private static function global_vars(): array {
		return array(
			'site_name'     => Portal_Brand::name(),
			'site_url'      => home_url( '/' ),
			'login_url'     => wp_login_url(),
			'support_email' => Portal_Brand::support_email(),
			'year'          => gmdate( 'Y' ),
		);
	}

	/**
	 * Replaces {{placeholders}} in a string.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string               $text  Text with placeholders.
	 * @param  array<string,string> $vars  Values.
	 * @return string
	 */
	public static function fill( string $text, array $vars ): string {
		return (string) preg_replace_callback(
			'/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
			static fn( array $m ): string => $vars[ $m[1] ] ?? '',
			$text
		);
	}

	/**
	 * Splits body text into paragraphs (blank-line separated).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $body  Body text.
	 * @return array<int,string>
	 */
	private static function paragraphs( string $body ): array {
		return array_values( array_filter( array_map( 'trim', (array) preg_split( '/\n\s*\n/', $body ) ) ) );
	}

	/**
	 * Parses "Label: value" lines into rows, dropping empty values.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $details  Detail lines.
	 * @return array<int,array{label:string,value:string}>
	 */
	private static function details( string $details ): array {
		$rows = array();

		foreach ( preg_split( '/\R/', $details ) as $line ) {
			$pair = explode( ':', (string) $line, 2 );

			if ( 2 === count( $pair ) && '' !== trim( $pair[1] ) ) {
				$rows[] = array(
					'label' => trim( $pair[0] ),
					'value' => trim( $pair[1] ),
				);
			}
		}

		return $rows;
	}

	/**
	 * Builds the plain-text alternative.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,mixed> $email  Email parts.
	 * @return string
	 */
	private static function plain_text( array $email ): string {
		$details = array_map( static fn( array $row ): string => $row['label'] . ': ' . $row['value'], $email['details'] );

		return implode(
			"\n\n",
			array_filter(
				array_merge(
					array( $email['heading'] ),
					$email['paragraphs'],
					array( implode( "\n", $details ) ),
					array( '' !== $email['button_url'] ? $email['button_label'] . ': ' . $email['button_url'] : '' ),
					array( $email['footer'] )
				)
			)
		);
	}
}
