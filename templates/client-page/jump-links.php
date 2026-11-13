<?php
/**
 * Template part: jump links to the price list's headings ("Main Menu"),
 * in their own bar under the page header, separate from the navigation.
 *
 * Expects $satori_manifest_jump_links (array of { title, anchor }).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.10.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $satori_manifest_jump_links ) ) {
	return;
}
?>
<nav class="sm-jump" aria-label="<?php esc_attr_e( 'Jump to', 'satori-manifest' ); ?>">
	<ul class="sm-jump__list">
		<?php foreach ( $satori_manifest_jump_links as $satori_manifest_jump ) : ?>
			<li><a class="sm-jump__link" href="#<?php echo esc_attr( $satori_manifest_jump['anchor'] ); ?>"><?php echo esc_html( $satori_manifest_jump['title'] ); ?></a></li>
		<?php endforeach; ?>
	</ul>
</nav>
