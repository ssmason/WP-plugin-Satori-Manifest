<?php
/**
 * View: order printed QR codes (3D-printed, self-adhesive, posted).
 *
 * Shown on the QR builder under the preview, for clients who may order
 * (Printed_Codes::can_order()). Prices and sizes come from
 * config/printed-codes.yaml.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.12.5
 */

declare( strict_types=1 );

use SatoriManifest\Billing\Code_Catalogue;
use SatoriManifest\Billing\Printed_Codes;
use SatoriManifest\Core\Security;
use SatoriManifest\Registration\Plans;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="sm-panel sm-codes-panel">
	<h2 class="sm-panel__title"><?php esc_html_e( 'Order printed QR codes', 'satori-manifest' ); ?></h2>
	<p class="sm-hint"><?php esc_html_e( '3D-printed and self-adhesive, for tables, the counter or the window. Posted to you. Not the paper sign above: we make these for you.', 'satori-manifest' ); ?></p>
	<form class="sm-codes-panel__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( Printed_Codes::ACTION ); ?>" />
		<?php Security::output_form_nonce( Printed_Codes::ACTION ); ?>
		<div class="sm-codes-panel__sizes">
			<?php foreach ( Code_Catalogue::sizes() as $satori_manifest_size => $satori_manifest_size_terms ) : ?>
				<label class="sm-codes-panel__size">
					<span><?php echo esc_html( $satori_manifest_size_terms['label'] ); ?></span>
					<span class="sm-muted"><?php echo esc_html( Plans::format( $satori_manifest_size_terms['price'] ) ); ?></span>
					<input type="number" name="<?php echo esc_attr( Printed_Codes::FIELD . '[' . $satori_manifest_size . ']' ); ?>" value="0" min="0" max="<?php echo esc_attr( (string) Code_Catalogue::max() ); ?>" step="1" inputmode="numeric" />
				</label>
			<?php endforeach; ?>
		</div>
		<p class="sm-codes-panel__region">
			<label for="sm-codes-region"><?php esc_html_e( 'Post to', 'satori-manifest' ); ?></label>
			<select id="sm-codes-region" name="<?php echo esc_attr( Printed_Codes::REGION_FIELD ); ?>">
				<?php foreach ( array_keys( Code_Catalogue::regions() ) as $satori_manifest_region ) : ?>
					<option value="<?php echo esc_attr( $satori_manifest_region ); ?>"><?php echo esc_html( Code_Catalogue::region_label( $satori_manifest_region ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Order and pay', 'satori-manifest' ); ?></button>
	</form>
</section>
