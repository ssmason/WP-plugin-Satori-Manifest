<?php
/**
 * Block render template - satori-manifest/price-list.
 *
 * Called by WordPress when rendering the block. WordPress makes these
 * variables available in this file's scope:
 *
 *   $attributes (array)    - Block attribute values from the editor.
 *   $content    (string)   - Inner block content (unused - dynamic block).
 *   $block      (WP_Block) - Block instance (context includes postId).
 *
 * Which sections show, the display options, wrapper classes and CSS
 * variables are worked out by Manifest\Price_List_View; this file only
 * outputs the markup.
 *
 * All output is escaped at the point of echo. Section data is sanitized on
 * save by Sanitizer.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SatoriManifest\Branding\Brand;
use SatoriManifest\Manifest\Headings;
use SatoriManifest\Manifest\Price_List_View;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// render.php is a block render callback - variables here are scoped to this
// include and are not true globals.

$view = Price_List_View::prepare(
	(array) $attributes,
	isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID()
);

if ( null === $view ) {
	return;
}

$options   = $view['options'];
$currency  = $view['currency'];
$manifests = $view['manifests'];


/*
 * Output order: a heading is a banner of its own, outside the menu boxes, and
 * the sections between headings form one menu box each (the block's layout,
 * colours and padding). So a banner is never part of the menu below it:
 *
 *   [banner] [menu: sections…] [banner] [menu: sections…]
 *
 * The root carries the block's alignment and font variables; each menu box
 * carries the layout and colour scheme classes.
 */
$segments = array();

foreach ( $manifests as $sections ) {
	$menu_sections = array();

	foreach ( $sections as $section ) {
		if ( Headings::is_heading( $section ) ) {
			if ( $menu_sections ) {
				$segments[]    = array( 'menu', $menu_sections );
				$menu_sections = array();
			}

			$segments[] = array( 'heading', $section );
			continue;
		}

		$menu_sections[] = $section;
	}

	if ( $menu_sections ) {
		$segments[] = array( 'menu', $menu_sections );
	}
}

$root_attributes = array( 'class' => 'satori-manifest-price-list-stack' );

if ( ! empty( $view['wrapper']['style'] ) ) {
	$root_attributes['style'] = $view['wrapper']['style'];
}

$section_anchors = array();

/**
 * Prints one heading as a banner.
 *
 * @param array<string,mixed> $section  Heading entry.
 * @return void
 */
$render_heading = static function ( array $section ) use ( &$section_anchors ): void {
	$heading_title = (string) ( $section['title'] ?? '' );
	$heading_note  = (string) ( $section['description'] ?? '' );

	if ( '' === $heading_title && '' === $heading_note ) {
		return;
	}

	$heading_anchor = Price_List_View::anchor( $heading_title, $section_anchors );
	$heading_style  = Headings::style_vars( $section );
	?>
	<header class="satori-manifest-price-list__heading is-size-<?php echo esc_attr( Headings::size( $section ) ); ?><?php echo '' !== $heading_style ? ' has-own-colours' : ''; ?>"<?php echo '' !== $heading_anchor ? ' id="' . esc_attr( $heading_anchor ) . '"' : ''; ?><?php echo '' !== $heading_style ? ' style="' . esc_attr( $heading_style ) . '"' : ''; ?>>
		<?php if ( '' !== $heading_title ) : ?>
			<h2 class="satori-manifest-price-list__heading-title"><?php echo esc_html( $heading_title ); ?></h2>
		<?php endif; ?>
		<?php if ( '' !== $heading_note ) : ?>
			<p class="satori-manifest-price-list__heading-note"><?php echo esc_html( $heading_note ); ?></p>
		<?php endif; ?>
	</header>
	<?php
};

/**
 * Prints one section with its items.
 *
 * @param array<string,mixed> $section  Section entry.
 * @return void
 */
$render_section = static function ( array $section ) use ( &$section_anchors, $options, $currency ): void {
		$section_title = isset( $section['title'] ) ? (string) $section['title'] : '';
		$section_desc  = isset( $section['description'] ) ? (string) $section['description'] : '';
		$section_icon  = isset( $section['icon'] ) ? absint( $section['icon'] ) : 0;
		$items         = isset( $section['items'] ) && is_array( $section['items'] ) ? $section['items'] : array();

	if ( '' === $section_title && empty( $items ) ) {
		return;
	}

		// Readable anchor from the title ("Hot drinks" → #hot-drinks) for menu links.
		$section_anchor = Price_List_View::anchor( $section_title, $section_anchors );
	?>
		<section class="satori-manifest-price-list__section"<?php echo '' !== $section_anchor ? ' id="' . esc_attr( $section_anchor ) . '"' : ''; ?>>
			<?php if ( '' !== $section_title ) : ?>
				<h3 class="satori-manifest-price-list__section-title<?php echo $section_icon ? ' has-icon' : ''; ?>">
					<?php
					if ( $section_icon ) {
						echo wp_get_attachment_image(
							$section_icon,
							'thumbnail',
							false,
							array(
								'class'   => 'satori-manifest-price-list__section-icon',
								'alt'     => '',
								'loading' => 'lazy',
							)
						);
					}
					?>
					<span><?php echo esc_html( $section_title ); ?></span>
				</h3>
			<?php endif; ?>

			<?php if ( $options['section_intros'] && '' !== $section_desc ) : ?>
				<p class="satori-manifest-price-list__section-desc"><?php echo esc_html( $section_desc ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $items ) ) : ?>
				<ul class="satori-manifest-price-list__items">
					<?php foreach ( $items as $item ) : ?>
						<?php
						$label        = isset( $item['label'] ) ? (string) $item['label'] : '';
						$description  = isset( $item['description'] ) ? (string) $item['description'] : '';
						$badge        = isset( $item['badge'] ) ? (string) $item['badge'] : '';
						$price_raw    = isset( $item['price'] ) ? trim( (string) $item['price'] ) : '';
						$is_subheader = '' === $price_raw;
						$prefix       = '' !== $options['prefix']
							? $options['prefix']
							: ( isset( $item['price_prefix'] ) ? (string) $item['price_prefix'] : '' );

						if ( '' === $label ) {
							continue;
						}
						?>
						<li class="satori-manifest-price-list__item<?php echo esc_attr( $is_subheader ? ' is-subsection-header' : '' ); ?>">
							<div class="satori-manifest-price-list__item-label">
								<span class="satori-manifest-price-list__item-name">
									<?php echo esc_html( $label ); ?>
									<?php if ( $options['badges'] && '' !== $badge ) : ?>
										<span class="satori-manifest-price-list__item-badge"><?php echo esc_html( $badge ); ?></span>
									<?php endif; ?>
								</span>
								<?php if ( $options['descriptions'] && '' !== $description ) : ?>
									<span class="satori-manifest-price-list__item-desc"><?php echo esc_html( $description ); ?></span>
								<?php endif; ?>
							</div>

							<?php if ( $options['prices'] && ! $is_subheader ) : ?>
								<span class="satori-manifest-price-list__item-leader" aria-hidden="true"></span>
								<span class="satori-manifest-price-list__item-price">
									<?php if ( '' !== $prefix ) : ?>
										<span class="satori-manifest-price-list__item-prefix"><?php echo esc_html( $prefix ); ?></span>
									<?php endif; ?>
									<?php echo esc_html( Brand::format_price( (float) $price_raw, $currency ) ); ?>
								</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	<?php
};
?>
<div <?php echo get_block_wrapper_attributes( $root_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns pre-escaped HTML attributes. ?>>
	<?php foreach ( $segments as $segment ) : ?>
		<?php if ( 'heading' === $segment[0] ) : ?>
			<?php $render_heading( $segment[1] ); ?>
		<?php else : ?>
			<div class="<?php echo esc_attr( $view['wrapper']['class'] ); ?>">
				<div class="satori-manifest-price-list__manifest">
					<?php
					foreach ( $segment[1] as $section ) {
						$render_section( $section );
					}
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php endforeach; ?>
</div>
