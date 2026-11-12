<?php
/**
 * Template: examples bar on demo pages.
 *
 * Variables from Example_Bar::render():
 *   $satori_manifest_groups   Link groups (label + links).
 *   $satori_manifest_current  Current page ID.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<nav class="sm-examples" aria-label="<?php esc_attr_e( 'Other examples', 'satori-manifest' ); ?>">
	<div class="sm-examples__inner">
		<a class="sm-examples__home" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<span aria-hidden="true">←</span> <?php echo esc_html( get_bloginfo( 'name' ) ); ?>
		</a>
		<div class="sm-examples__groups">
			<?php foreach ( $satori_manifest_groups as $satori_manifest_group ) : ?>
				<div class="sm-examples__group">
					<span class="sm-examples__label"><?php echo esc_html( $satori_manifest_group['label'] ); ?></span>
					<?php foreach ( $satori_manifest_group['links'] as $satori_manifest_link ) : ?>
						<?php if ( $satori_manifest_link['id'] === $satori_manifest_current ) : ?>
							<span class="sm-examples__link is-current" aria-current="page"><?php echo esc_html( $satori_manifest_link['label'] ); ?></span>
						<?php else : ?>
							<a class="sm-examples__link" href="<?php echo esc_url( $satori_manifest_link['url'] ); ?>"><?php echo esc_html( $satori_manifest_link['label'] ); ?></a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</nav>
<?php
// Phones: bring the current example into view in the scrolling list.
wp_print_inline_script_tag( 'document.querySelector(".sm-examples .is-current")?.scrollIntoView({inline:"center",block:"nearest"});' );
