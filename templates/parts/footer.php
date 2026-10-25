<?php
/**
 * Template part: client page footer.
 *
 * Expects $satori_manifest_brand from the parent template. Every block is
 * optional — empty fields are simply not rendered.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$satori_manifest_footer      = $satori_manifest_brand['footer'];
$satori_manifest_has_contact = '' !== $satori_manifest_footer['address'] || '' !== $satori_manifest_footer['phone'] || '' !== $satori_manifest_footer['email'];
$satori_manifest_social      = array(
	'website'   => __( 'Website', 'satori-manifest' ),
	'instagram' => __( 'Instagram', 'satori-manifest' ),
	'facebook'  => __( 'Facebook', 'satori-manifest' ),
	'tiktok'    => __( 'TikTok', 'satori-manifest' ),
);
?>
<footer class="sm-footer">
	<div class="sm-footer__inner">
		<div class="sm-footer__brand">
			<span class="sm-footer__name"><?php echo esc_html( $satori_manifest_brand['name'] ); ?></span>
			<?php if ( '' !== $satori_manifest_brand['tagline'] ) : ?>
				<span class="sm-footer__tagline"><?php echo esc_html( $satori_manifest_brand['tagline'] ); ?></span>
			<?php endif; ?>

			<?php if ( ! empty( $satori_manifest_footer['social'] ) ) : ?>
				<ul class="sm-social">
					<?php foreach ( $satori_manifest_footer['social'] as $satori_manifest_network => $satori_manifest_url ) : ?>
						<li>
							<a class="sm-social__link" href="<?php echo esc_url( $satori_manifest_url ); ?>" rel="noopener" target="_blank">
								<?php \SatoriManifest\Icons::render( $satori_manifest_network, 20 ); ?>
								<span class="sm-visually-hidden"><?php echo esc_html( $satori_manifest_social[ $satori_manifest_network ] ?? $satori_manifest_network ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<?php if ( $satori_manifest_has_contact ) : ?>
			<div class="sm-footer__col">
				<h2 class="sm-footer__heading"><?php esc_html_e( 'Find us', 'satori-manifest' ); ?></h2>
				<ul class="sm-contact">
					<?php if ( '' !== $satori_manifest_footer['address'] ) : ?>
						<li><?php \SatoriManifest\Icons::render( 'location', 18 ); ?><span><?php echo wp_kses( nl2br( esc_html( $satori_manifest_footer['address'] ) ), array( 'br' => array() ) ); ?></span></li>
					<?php endif; ?>
					<?php if ( '' !== $satori_manifest_footer['phone'] ) : ?>
						<li><?php \SatoriManifest\Icons::render( 'phone', 18 ); ?><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $satori_manifest_footer['phone'] ) ); ?>"><?php echo esc_html( $satori_manifest_footer['phone'] ); ?></a></li>
					<?php endif; ?>
					<?php if ( '' !== $satori_manifest_footer['email'] ) : ?>
						<li><?php \SatoriManifest\Icons::render( 'email', 18 ); ?><a href="<?php echo esc_url( 'mailto:' . antispambot( $satori_manifest_footer['email'] ) ); ?>"><?php echo esc_html( antispambot( $satori_manifest_footer['email'] ) ); ?></a></li>
					<?php endif; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $satori_manifest_footer['hours'] ) : ?>
			<div class="sm-footer__col">
				<h2 class="sm-footer__heading"><?php esc_html_e( 'Opening hours', 'satori-manifest' ); ?></h2>
				<p class="sm-footer__hours"><?php echo wp_kses( nl2br( esc_html( $satori_manifest_footer['hours'] ) ), array( 'br' => array() ) ); ?></p>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( '' !== $satori_manifest_footer['note'] || $satori_manifest_footer['show_credit'] ) : ?>
		<div class="sm-footer__bottom">
			<?php if ( '' !== $satori_manifest_footer['note'] ) : ?>
				<p class="sm-footer__note"><?php echo esc_html( $satori_manifest_footer['note'] ); ?></p>
			<?php endif; ?>
			<?php if ( $satori_manifest_footer['show_credit'] ) : ?>
				<p class="sm-footer__credit"><?php esc_html_e( 'Powered by Satori', 'satori-manifest' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</footer>
