<?php
/**
 * Block render template — satori-manifest/price-list.
 *
 * Called by WordPress when rendering the block. WordPress makes these
 * variables available in this file's scope:
 *
 *   $attributes (array)    — Block attribute values from the editor.
 *   $content    (string)   — Inner block content (unused — dynamic block).
 *   $block      (WP_Block) — Block instance (context includes postId).
 *
 * Manifest resolution:
 *   - manifestIds set   → those manifests.
 *   - excludedSectionIds set → those sections (by permanent id) are hidden, so
 *                              several blocks can show different slices with
 *                              different layouts; new sections show by default.
 *   - manifestIds empty → on a client page, that client's own manifest.
 *   - On a client page only manifests owned by that client are rendered,
 *     so a block can never display another client's prices.
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

use SatoriManifest\Brand;
use SatoriManifest\Client_Repository;
use SatoriManifest\Manifest_Repository;
use SatoriManifest\Post_Types;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// render.php is a block render callback — variables here are scoped to this
// include and are not true globals.

// ── Read attributes ───────────────────────────────────────────────────────────

$manifest_ids = isset( $attributes['manifestIds'] ) && is_array( $attributes['manifestIds'] )
	? array_values( array_filter( array_map( 'absint', $attributes['manifestIds'] ) ) )
	: array();

$layout                = isset( $attributes['layout'] ) ? sanitize_key( $attributes['layout'] ) : 'single-column';
$color_scheme          = isset( $attributes['colorScheme'] ) ? sanitize_key( $attributes['colorScheme'] ) : 'default';
$show_prices           = isset( $attributes['showPrices'] ) ? (bool) $attributes['showPrices'] : true;
$show_descs            = isset( $attributes['showDescriptions'] ) ? (bool) $attributes['showDescriptions'] : true;
$show_section_descs    = isset( $attributes['showSectionDescriptions'] ) ? (bool) $attributes['showSectionDescriptions'] : true;
$show_badges           = isset( $attributes['showBadges'] ) ? (bool) $attributes['showBadges'] : true;
$price_prefix_override = isset( $attributes['pricePrefix'] ) ? sanitize_text_field( (string) $attributes['pricePrefix'] ) : '';
$show_background       = isset( $attributes['showBackground'] ) ? (bool) $attributes['showBackground'] : true;
$title_bg_color        = isset( $attributes['titleBgColor'] ) ? sanitize_hex_color( (string) $attributes['titleBgColor'] ) : '';
$title_font_size       = isset( $attributes['titleFontSize'] ) ? absint( $attributes['titleFontSize'] ) : 0;
// Font weight: allow only numeric weights (100–900) and CSS keywords.
// Font family: allow only characters safe in a CSS value (letters, digits,
// spaces, commas, hyphens, apostrophes) so values cannot break out of the
// style attribute.
$allowed_weights   = array( '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold', 'lighter', 'bolder' );
$title_font_weight = isset( $attributes['titleFontWeight'] ) && in_array( (string) $attributes['titleFontWeight'], $allowed_weights, true ) ? (string) $attributes['titleFontWeight'] : '';
$title_font_family = isset( $attributes['titleFontFamily'] ) ? (string) preg_replace( '/[^a-zA-Z0-9\s,\'\-]/', '', (string) $attributes['titleFontFamily'] ) : '';
$item_font_size    = isset( $attributes['itemFontSize'] ) ? absint( $attributes['itemFontSize'] ) : 0;
$item_font_weight  = isset( $attributes['itemFontWeight'] ) && in_array( (string) $attributes['itemFontWeight'], $allowed_weights, true ) ? (string) $attributes['itemFontWeight'] : '';
$item_font_family  = isset( $attributes['itemFontFamily'] ) ? (string) preg_replace( '/[^a-zA-Z0-9\s,\'\-]/', '', (string) $attributes['itemFontFamily'] ) : '';
$card_padding      = isset( $attributes['cardPadding'] ) ? (bool) $attributes['cardPadding'] : true;
$title_padding     = isset( $attributes['titlePadding'] ) ? (bool) $attributes['titlePadding'] : true;
$show_item_border  = isset( $attributes['showItemBorder'] ) ? (bool) $attributes['showItemBorder'] : true;

// ── Page context ──────────────────────────────────────────────────────────────

$context_post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
$page_client_id  = Client_Repository::client_for_page( $context_post_id );
$currency        = $page_client_id
	? Brand::for_page( $context_post_id )['currency']
	: array(
		'symbol'   => '',
		'position' => 'before',
	);

if ( empty( $manifest_ids ) && $page_client_id ) {
	$own_manifest = Client_Repository::manifest_id( $page_client_id );
	$manifest_ids = $own_manifest ? array( $own_manifest ) : array();
}

// ── Wrapper attributes ────────────────────────────────────────────────────────

$wrapper_classes = implode(
	' ',
	array_filter(
		array(
			'satori-manifest-price-list',
			'is-layout-' . $layout,
			'is-scheme-' . $color_scheme,
			! $show_background ? 'has-no-background' : '',
			$title_bg_color ? 'has-title-bg' : '',
			! $card_padding ? 'has-no-card-padding' : '',
			! $title_padding ? 'has-no-title-padding' : '',
			! $show_item_border ? 'has-no-item-border' : '',
		)
	)
);

// CSS custom properties cascade to all descendants. Values are sanitized
// above; get_block_wrapper_attributes() escapes the attribute.
$css_vars = array();

if ( 'custom' === $color_scheme ) {
	foreach ( array(
		'customBgColor'     => '--sm-custom-bg',
		'customAccentColor' => '--sm-custom-accent',
		'customTitleColor'  => '--sm-custom-title',
		'customCardColor'   => '--sm-custom-card',
	) as $attribute => $property ) {
		$value = isset( $attributes[ $attribute ] ) ? sanitize_hex_color( (string) $attributes[ $attribute ] ) : '';
		if ( $value ) {
			$css_vars[] = $property . ':' . $value;
		}
	}
}

if ( $title_bg_color ) {
	$css_vars[] = '--sm-title-bg:' . $title_bg_color;
}
if ( '' !== $title_font_family ) {
	$css_vars[] = '--sm-title-font:' . $title_font_family;
}
if ( $title_font_size > 0 ) {
	$css_vars[] = '--sm-title-size:' . $title_font_size . 'px';
}
if ( '' !== $title_font_weight ) {
	$css_vars[] = '--sm-title-weight:' . $title_font_weight;
}
if ( '' !== $item_font_family ) {
	$css_vars[] = '--sm-item-font:' . $item_font_family;
}
if ( $item_font_size > 0 ) {
	$css_vars[] = '--sm-item-size:' . $item_font_size . 'px';
}
if ( '' !== $item_font_weight ) {
	$css_vars[] = '--sm-item-weight:' . $item_font_weight;
}

$wrapper_attr_args = array( 'class' => $wrapper_classes );
if ( $css_vars ) {
	$wrapper_attr_args['style'] = implode( ';', $css_vars ) . ';';
}

// ── Collect sections ──────────────────────────────────────────────────────────

$manifests = array();

foreach ( $manifest_ids as $manifest_post_id ) {
	$manifest = get_post( $manifest_post_id );

	if (
		! $manifest instanceof \WP_Post
		|| Post_Types::CPT_MANIFEST !== $manifest->post_type
		|| 'publish' !== $manifest->post_status
		|| ( $page_client_id && (int) get_post_meta( $manifest_post_id, Client_Repository::OWNER, true ) !== $page_client_id )
	) {
		continue;
	}

	$manifests[] = Manifest_Repository::get_sections( $manifest_post_id );
}

// Optional "sections to hide": drop the excluded sections from this block.
$excluded_ids = isset( $attributes['excludedSectionIds'] ) && is_array( $attributes['excludedSectionIds'] )
	? array_values( array_filter( array_map( static fn( $id ): string => \SatoriManifest\Sanitizer::section_id( (string) $id ), $attributes['excludedSectionIds'] ) ) )
	: array();

if ( $excluded_ids ) {
	$manifests = array_values(
		array_filter(
			array_map(
				static fn( array $sections ): array => array_values(
					array_filter( $sections, static fn( $section ): bool => ! in_array( (string) ( $section['id'] ?? '' ), $excluded_ids, true ) )
				),
				$manifests
			)
		)
	);
}

// No manifest to show: a styled demo in editor/REST previews, nothing on the front end.
if ( empty( $manifests ) ) {
	if ( ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	$manifests = array( Manifest_Repository::demo_sections() );
}

// ── Render ────────────────────────────────────────────────────────────────────
?>
<div <?php echo get_block_wrapper_attributes( $wrapper_attr_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns pre-escaped HTML attributes. ?>>
	<?php foreach ( $manifests as $sections ) : ?>
		<div class="satori-manifest-price-list__manifest">
			<?php foreach ( $sections as $section ) : ?>
				<?php
				$section_title = isset( $section['title'] ) ? (string) $section['title'] : '';
				$section_desc  = isset( $section['description'] ) ? (string) $section['description'] : '';
				$items         = isset( $section['items'] ) && is_array( $section['items'] ) ? $section['items'] : array();

				if ( '' === $section_title && empty( $items ) ) {
					continue;
				}
				?>
				<section class="satori-manifest-price-list__section">
					<?php if ( '' !== $section_title ) : ?>
						<h3 class="satori-manifest-price-list__section-title"><?php echo esc_html( $section_title ); ?></h3>
					<?php endif; ?>

					<?php if ( $show_section_descs && '' !== $section_desc ) : ?>
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
								$prefix       = '' !== $price_prefix_override
									? $price_prefix_override
									: ( isset( $item['price_prefix'] ) ? (string) $item['price_prefix'] : '' );

								if ( '' === $label ) {
									continue;
								}
								?>
								<li class="satori-manifest-price-list__item<?php echo esc_attr( $is_subheader ? ' is-subsection-header' : '' ); ?>">
									<div class="satori-manifest-price-list__item-label">
										<span class="satori-manifest-price-list__item-name">
											<?php echo esc_html( $label ); ?>
											<?php if ( $show_badges && '' !== $badge ) : ?>
												<span class="satori-manifest-price-list__item-badge"><?php echo esc_html( $badge ); ?></span>
											<?php endif; ?>
										</span>
										<?php if ( $show_descs && '' !== $description ) : ?>
											<span class="satori-manifest-price-list__item-desc"><?php echo esc_html( $description ); ?></span>
										<?php endif; ?>
									</div>

									<?php if ( $show_prices && ! $is_subheader ) : ?>
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
