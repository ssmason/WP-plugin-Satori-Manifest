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

?>
<div <?php echo get_block_wrapper_attributes( $view['wrapper'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns pre-escaped HTML attributes. ?>>
	<?php $section_anchors = array(); ?>
	<?php foreach ( $manifests as $sections ) : ?>
		<div class="satori-manifest-price-list__manifest">
			<?php foreach ( $sections as $section ) : ?>
				<?php
				$section_title = isset( $section['title'] ) ? (string) $section['title'] : '';
				$section_desc  = isset( $section['description'] ) ? (string) $section['description'] : '';
				$section_icon  = isset( $section['icon'] ) ? absint( $section['icon'] ) : 0;
				$items         = isset( $section['items'] ) && is_array( $section['items'] ) ? $section['items'] : array();

				if ( '' === $section_title && empty( $items ) ) {
					continue;
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
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>
</div>
