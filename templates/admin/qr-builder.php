<?php
/**
 * Client screen: QR builder.
 *
 * Receives from Qr_Builder_Screen::render():
 *   $satori_manifest_page_id   int                  Client page ID.
 *   $satori_manifest_settings  array                Saved sign settings (Qr_Sign::settings()).
 *   $satori_manifest_previews  array<string,string> SVG per size (built by Qr_Sign).
 *   $satori_manifest_notice    string               '' or 'saved'.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */

declare( strict_types=1 );

use SatoriManifest\Admin\Screens\Qr_Builder_Screen;
use SatoriManifest\Branding\Icons;
use SatoriManifest\Core\Security;
use SatoriManifest\Qr\Qr_Download;
use SatoriManifest\Qr\Qr_Sign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$satori_manifest_sizes = array(
	'large' => array( __( 'Large sign', 'satori-manifest' ), __( 'Your text, a subtitle and your colour. One per A4 page, for counters and windows.', 'satori-manifest' ) ),
	'small' => array( __( 'Small sign', 'satori-manifest' ), __( 'Short text, our red. Four per A4 page, for tables.', 'satori-manifest' ) ),
);
?>
<div class="wrap sm-admin sm-qr-builder" data-sm-qr-builder>
	<header class="sm-page-header">
		<div>
			<p class="sm-eyebrow"><?php esc_html_e( 'QR builder', 'satori-manifest' ); ?></p>
			<h1 class="sm-page-header__title"><?php esc_html_e( 'Your printable QR sign', 'satori-manifest' ); ?></h1>
			<p class="sm-page-header__lead"><?php esc_html_e( 'Choose a size, add your wording and download a print-ready PDF. The code always opens your latest prices.', 'satori-manifest' ); ?></p>
		</div>
	</header>

	<?php if ( 'saved' === $satori_manifest_notice ) : ?>
		<div class="sm-notice sm-notice--success"><?php Icons::render( 'check', 18 ); ?> <?php esc_html_e( 'Saved. Your sign is ready to download, and it now shows on your Home screen.', 'satori-manifest' ); ?></div>
	<?php endif; ?>

	<div class="sm-qr-builder__layout">
		<form class="sm-panel sm-qr-builder__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( Qr_Builder_Screen::ACTION ); ?>" />
			<input type="hidden" name="page_id" value="<?php echo esc_attr( (string) $satori_manifest_page_id ); ?>" />
			<?php Security::output_form_nonce( Qr_Builder_Screen::ACTION ); ?>

			<fieldset class="sm-qr-builder__sizes">
				<legend class="sm-qr-builder__label"><?php esc_html_e( 'Size', 'satori-manifest' ); ?></legend>
				<?php foreach ( $satori_manifest_sizes as $satori_manifest_key => $satori_manifest_size ) : ?>
					<label class="sm-qr-builder__size">
						<input type="radio" name="size" value="<?php echo esc_attr( $satori_manifest_key ); ?>" <?php checked( $satori_manifest_settings['size'], $satori_manifest_key ); ?> />
						<span>
							<strong><?php echo esc_html( $satori_manifest_size[0] ); ?></strong>
							<small><?php echo esc_html( $satori_manifest_size[1] ); ?></small>
						</span>
					</label>
				<?php endforeach; ?>
			</fieldset>

			<label class="sm-qr-builder__field">
				<span class="sm-qr-builder__label"><?php esc_html_e( 'Top text', 'satori-manifest' ); ?></span>
				<input type="text" name="title" maxlength="<?php echo esc_attr( (string) Qr_Sign::MAX_TITLE ); ?>" value="<?php echo esc_attr( $satori_manifest_settings['title'] ); ?>" data-sm-sign-input="title" />
				<small data-sm-only="small"><?php esc_html_e( 'The last word shows in red, e.g. "Our Menu" or "Drinks List".', 'satori-manifest' ); ?></small>
			</label>

			<label class="sm-qr-builder__field" data-sm-only="large">
				<span class="sm-qr-builder__label"><?php esc_html_e( 'Subtitle', 'satori-manifest' ); ?></span>
				<input type="text" name="subtitle" maxlength="<?php echo esc_attr( (string) Qr_Sign::MAX_SUBTITLE ); ?>" value="<?php echo esc_attr( $satori_manifest_settings['subtitle'] ); ?>" data-sm-sign-input="subtitle" />
			</label>

			<label class="sm-qr-builder__field sm-qr-builder__field--colour" data-sm-only="large">
				<span class="sm-qr-builder__label"><?php esc_html_e( 'Colour', 'satori-manifest' ); ?></span>
				<input type="color" name="colour" value="<?php echo esc_attr( $satori_manifest_settings['colour'] ); ?>" data-sm-sign-input="colour" />
			</label>

			<div class="sm-qr-builder__actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save sign', 'satori-manifest' ); ?></button>
				<a class="button" href="<?php echo esc_url( Qr_Download::url( $satori_manifest_page_id, 'sign' ) ); ?>" data-sm-sign-download><?php esc_html_e( 'Download PDF', 'satori-manifest' ); ?></a>
			</div>
			<p class="sm-hint" data-sm-sign-unsaved hidden><?php esc_html_e( 'You have unsaved changes: save first, then download.', 'satori-manifest' ); ?></p>
		</form>

		<div class="sm-panel sm-qr-builder__preview">
			<p class="sm-qr-builder__label"><?php esc_html_e( 'Preview', 'satori-manifest' ); ?></p>
			<?php foreach ( $satori_manifest_previews as $satori_manifest_key => $satori_manifest_svg ) : ?>
				<div class="sm-qr-builder__sign sm-qr-builder__sign--<?php echo esc_attr( $satori_manifest_key ); ?>" data-sm-preview="<?php echo esc_attr( $satori_manifest_key ); ?>"<?php echo $satori_manifest_key !== $satori_manifest_settings['size'] ? ' hidden' : ''; ?>>
					<?php echo $satori_manifest_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG built by Qr_Sign; every value escaped there. ?>
				</div>
			<?php endforeach; ?>
			<p class="sm-hint"><?php esc_html_e( 'Printed in black and your colour on white. Print at actual size (100%) and cut along the outline.', 'satori-manifest' ); ?></p>
		</div>
	</div>
</div>
