<?php
/**
 * Block render template - satori-manifest/contact-form.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo \SatoriManifest\Contact\Contact_Form::render( get_block_wrapper_attributes( array( 'class' => 'sm-register sm-contact' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes all output.
