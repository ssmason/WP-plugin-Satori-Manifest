<?php
/**
 * Block render template — satori-manifest/registration-form.
 *
 * Delegates to Registration_Form so the markup and the submit handler live
 * together.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo \SatoriManifest\Registration_Form::render( get_block_wrapper_attributes( array( 'class' => 'sm-register' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes all output.
