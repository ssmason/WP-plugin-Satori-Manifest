<?php
/**
 * Template part: client page header.
 *
 * Expects $satori_manifest_page_id and $satori_manifest_brand from the
 * parent template. Navigation comes from the client's own menu
 * (Appearance → Menus); the header layout comes from the brand settings or
 * the design default.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$satori_manifest_header  = $satori_manifest_brand['header'];
$satori_manifest_menu_id = \SatoriManifest\Clients\Client_Repository::menu_id(
	\SatoriManifest\Clients\Client_Repository::client_for_page( $satori_manifest_page_id )
);
$satori_manifest_nav     = '';

if ( $satori_manifest_header['show_nav'] && $satori_manifest_menu_id ) {
	$satori_manifest_nav = (string) wp_nav_menu(
		array(
			'menu'        => $satori_manifest_menu_id,
			'container'   => false,
			'menu_class'  => 'sm-nav__list',
			'depth'       => 1,
			'fallback_cb' => '__return_empty_string',
			'echo'        => false,
		)
	);
}

$satori_manifest_has_cta = '' !== $satori_manifest_header['cta_label'] && '' !== $satori_manifest_header['cta_url'];
$satori_manifest_classes = array(
	'sm-header',
	'sm-header--' . $satori_manifest_header['layout'],
	$satori_manifest_header['sticky'] ? 'is-sticky' : '',
	'' !== $satori_manifest_nav ? 'has-nav' : '',
);
?>
<header class="<?php echo esc_attr( implode( ' ', array_filter( $satori_manifest_classes ) ) ); ?>">
	<div class="sm-header__inner">
		<div class="sm-brand">
			<?php if ( $satori_manifest_brand['logo_id'] ) : ?>
				<?php
				echo wp_get_attachment_image(
					$satori_manifest_brand['logo_id'],
					'medium',
					false,
					array(
						'class' => 'sm-brand__logo',
						'alt'   => $satori_manifest_brand['name'],
					)
				);
				?>
			<?php else : ?>
				<span class="sm-brand__name"><?php echo esc_html( $satori_manifest_brand['name'] ); ?></span>
			<?php endif; ?>

			<?php if ( '' !== $satori_manifest_brand['tagline'] ) : ?>
				<span class="sm-brand__tagline"><?php echo esc_html( $satori_manifest_brand['tagline'] ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( '' !== $satori_manifest_nav || $satori_manifest_has_cta ) : ?>
			<div class="sm-header__actions">
				<?php if ( '' !== $satori_manifest_nav ) : ?>
					<button type="button" class="sm-nav-toggle" aria-expanded="false" aria-controls="sm-nav">
						<?php \SatoriManifest\Branding\Icons::render( 'menu', 22, 'sm-nav-toggle__open' ); ?>
						<?php \SatoriManifest\Branding\Icons::render( 'close', 22, 'sm-nav-toggle__close' ); ?>
						<span class="sm-visually-hidden"><?php esc_html_e( 'Menu', 'satori-manifest' ); ?></span>
					</button>
					<nav id="sm-nav" class="sm-nav" aria-label="<?php esc_attr_e( 'Main', 'satori-manifest' ); ?>">
						<?php echo wp_kses_post( $satori_manifest_nav ); ?>
					</nav>
				<?php endif; ?>

				<?php if ( $satori_manifest_has_cta ) : ?>
					<a class="sm-button sm-header__cta" href="<?php echo esc_url( $satori_manifest_header['cta_url'] ); ?>">
						<?php echo esc_html( $satori_manifest_header['cta_label'] ); ?>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</header>
