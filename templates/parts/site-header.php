<?php
/**
 * Template part: public site header.
 *
 * Navigation comes from the "Satori site header" menu location when one is
 * assigned; otherwise sensible default links are shown. The Register and
 * Log in buttons always appear (Log in becomes "My account" when logged in).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SatoriManifest\Site_Installer;
use SatoriManifest\Site_Page;

$satori_manifest_register = Site_Installer::register_url();

if ( has_nav_menu( Site_Page::MENU_LOCATION ) ) {
	$satori_manifest_nav = (string) wp_nav_menu(
		array(
			'theme_location' => Site_Page::MENU_LOCATION,
			'container'      => false,
			'menu_class'     => 'sm-nav__list',
			'depth'          => 1,
			'echo'           => false,
		)
	);
} else {
	$satori_manifest_links = array(
		home_url( '/#how-it-works' ) => __( 'How it works', 'satori-manifest' ),
		home_url( '/#designs' )      => __( 'Designs', 'satori-manifest' ),
		home_url( '/#faq' )          => __( 'FAQ', 'satori-manifest' ),
	);
	$satori_manifest_nav   = '<ul class="sm-nav__list">';
	foreach ( $satori_manifest_links as $satori_manifest_url => $satori_manifest_label ) {
		$satori_manifest_nav .= '<li><a href="' . esc_url( $satori_manifest_url ) . '">' . esc_html( $satori_manifest_label ) . '</a></li>';
	}
	$satori_manifest_nav .= '</ul>';
}
?>
<header class="sm-header sm-header--split has-nav is-sticky">
	<div class="sm-header__inner">
		<a class="sm-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<span class="sm-brand__mark" aria-hidden="true"><?php \SatoriManifest\Icons::render( 'qr', 22 ); ?></span>
			<span class="sm-brand__name"><?php bloginfo( 'name' ); ?></span>
		</a>

		<div class="sm-header__actions">
			<button type="button" class="sm-nav-toggle" aria-expanded="false" aria-controls="sm-nav">
				<?php \SatoriManifest\Icons::render( 'menu', 22, 'sm-nav-toggle__open' ); ?>
				<?php \SatoriManifest\Icons::render( 'close', 22, 'sm-nav-toggle__close' ); ?>
				<span class="sm-visually-hidden"><?php esc_html_e( 'Menu', 'satori-manifest' ); ?></span>
			</button>
			<nav id="sm-nav" class="sm-nav" aria-label="<?php esc_attr_e( 'Main', 'satori-manifest' ); ?>">
				<?php echo wp_kses_post( $satori_manifest_nav ); ?>
				<ul class="sm-nav__list sm-nav__list--account">
					<li>
						<?php if ( is_user_logged_in() ) : ?>
							<a href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'My account', 'satori-manifest' ); ?></a>
						<?php else : ?>
							<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php esc_html_e( 'Log in', 'satori-manifest' ); ?></a>
						<?php endif; ?>
					</li>
				</ul>
			</nav>
			<?php if ( '' !== $satori_manifest_register && ! is_user_logged_in() ) : ?>
				<a class="sm-button sm-header__cta" href="<?php echo esc_url( $satori_manifest_register ); ?>"><?php esc_html_e( 'Register', 'satori-manifest' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</header>
