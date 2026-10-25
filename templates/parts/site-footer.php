<?php
/**
 * Template part: public site footer.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$satori_manifest_register = \SatoriManifest\Site_Installer::register_url();
$satori_manifest_privacy  = get_privacy_policy_url();
?>
<footer class="sm-footer">
	<div class="sm-footer__inner">
		<div class="sm-footer__brand">
			<span class="sm-footer__name"><?php bloginfo( 'name' ); ?></span>
			<span class="sm-footer__tagline"><?php esc_html_e( 'QR code price lists and menus that stay up to date.', 'satori-manifest' ); ?></span>
		</div>
		<div class="sm-footer__col">
			<h2 class="sm-footer__heading"><?php esc_html_e( 'Get started', 'satori-manifest' ); ?></h2>
			<ul class="sm-contact">
				<?php if ( '' !== $satori_manifest_register ) : ?>
					<li><a href="<?php echo esc_url( $satori_manifest_register ); ?>"><?php esc_html_e( 'Register your business', 'satori-manifest' ); ?></a></li>
				<?php endif; ?>
				<li><a href="<?php echo esc_url( wp_login_url() ); ?>"><?php esc_html_e( 'Client log in', 'satori-manifest' ); ?></a></li>
			</ul>
		</div>
	</div>
	<div class="sm-footer__bottom">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
		<?php if ( $satori_manifest_privacy ) : ?>
			<p><a href="<?php echo esc_url( $satori_manifest_privacy ); ?>"><?php esc_html_e( 'Privacy policy', 'satori-manifest' ); ?></a></p>
		<?php endif; ?>
	</div>
</footer>
