<?php
/**
 * Block render template - satori-manifest/price-list-heading.
 *
 * Draws one heading of the client's price list as a banner: title, note,
 * own colours and size from the Price list editor. Nothing when the heading
 * is switched off ("Show on page") or no longer exists. Sits in a group of its
 * own above the heading's menu (see Manifest\Page_Sync).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SatoriManifest\Manifest\Headings;
use SatoriManifest\Manifest\Price_List_View;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- render callback scope.

$heading = Headings::find(
	isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID(),
	(string) ( $attributes['headingId'] ?? '' )
);

if ( null === $heading ) {
	return;
}

$heading_title = (string) ( $heading['title'] ?? '' );
$heading_note  = (string) ( $heading['description'] ?? '' );
$heading_style = Headings::style_vars( $heading );
$heading_seen  = array();
$heading_id    = Price_List_View::anchor( $heading_title, $heading_seen );

$wrapper = array(
	'class' => 'satori-manifest-price-list__heading is-size-' . Headings::size( $heading ) . ( '' !== $heading_style ? ' has-own-colours' : '' ),
);

if ( '' !== $heading_style ) {
	$wrapper['style'] = $heading_style;
}

if ( '' !== $heading_id ) {
	$wrapper['id'] = $heading_id;
}
?>
<header <?php echo get_block_wrapper_attributes( $wrapper ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns pre-escaped HTML attributes. ?>>
	<?php if ( '' !== $heading_title ) : ?>
		<h2 class="satori-manifest-price-list__heading-title"><?php echo esc_html( $heading_title ); ?></h2>
	<?php endif; ?>
	<?php if ( '' !== $heading_note ) : ?>
		<p class="satori-manifest-price-list__heading-note"><?php echo esc_html( $heading_note ); ?></p>
	<?php endif; ?>
</header>
