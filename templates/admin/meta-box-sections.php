<?php
/**
 * Meta box view - Sections & Items editor for a single manifest.
 *
 * Receives from Meta_Box::render():
 *   $satori_manifest_sections    array  Saved sections.
 *   $satori_manifest_post_field  string Hidden field name for the JSON payload.
 *   $satori_manifest_currency    array  { symbol, position } for the price adornment.
 *
 * Sections and items are rendered by the two partial closures below, which
 * are also used to print the <template> elements cloned by
 * build/js/manifest-admin.js when adding rows - one source of markup.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$satori_manifest_badges = array(
	__( 'Popular', 'satori-manifest' ),
	__( 'New', 'satori-manifest' ),
	__( 'Offer', 'satori-manifest' ),
	__( 'Signature', 'satori-manifest' ),
	__( 'Vegan', 'satori-manifest' ),
);

/**
 * Renders one item row.
 *
 * @param array<string,mixed> $item  Item data (empty for the template).
 * @return void
 */
$satori_manifest_render_item = static function ( array $item ) use ( $satori_manifest_currency ): void {
	$price  = isset( $item['price'] ) && '' !== (string) $item['price'] ? (string) (float) $item['price'] : '';
	$symbol = (string) $satori_manifest_currency['symbol'];
	?>
	<div class="sm-item<?php echo '' === $price && ! empty( $item['label'] ) ? ' is-subheading' : ''; ?>">
		<span class="sm-item__grip dashicons dashicons-menu" data-sm-grip title="<?php esc_attr_e( 'Drag to move', 'satori-manifest' ); ?>" aria-hidden="true"></span>
		<label class="sm-item__field sm-item__field--label">
			<span class="screen-reader-text"><?php esc_html_e( 'Item name', 'satori-manifest' ); ?></span>
			<input type="text" class="sm-item__label" value="<?php echo esc_attr( (string) ( $item['label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Item name', 'satori-manifest' ); ?>" />
		</label>
		<label class="sm-item__field sm-item__field--description">
			<span class="screen-reader-text"><?php esc_html_e( 'Description', 'satori-manifest' ); ?></span>
			<input type="text" class="sm-item__description" value="<?php echo esc_attr( (string) ( $item['description'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Short description (optional)', 'satori-manifest' ); ?>" />
		</label>
		<label class="sm-item__field sm-item__field--badge">
			<span class="screen-reader-text"><?php esc_html_e( 'Badge', 'satori-manifest' ); ?></span>
			<input type="text" class="sm-item__badge" list="sm-badge-options" maxlength="24" value="<?php echo esc_attr( (string) ( $item['badge'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Badge', 'satori-manifest' ); ?>" />
		</label>
		<label class="sm-item__field sm-item__field--prefix">
			<span class="screen-reader-text"><?php esc_html_e( 'Price prefix', 'satori-manifest' ); ?></span>
			<input type="text" class="sm-item__prefix" value="<?php echo esc_attr( (string) ( $item['price_prefix'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'from', 'satori-manifest' ); ?>" />
		</label>
		<label class="sm-item__field sm-item__field--price sm-money sm-money--<?php echo esc_attr( (string) $satori_manifest_currency['position'] ); ?>">
			<span class="screen-reader-text"><?php esc_html_e( 'Price', 'satori-manifest' ); ?></span>
			<span class="sm-money__symbol" aria-hidden="true"><?php echo esc_html( $symbol ); ?></span>
			<input type="number" step="0.01" min="0" inputmode="decimal" class="sm-item__price" value="<?php echo esc_attr( $price ); ?>" placeholder="0.00" />
		</label>
		<span class="sm-item__actions">
			<button type="button" class="sm-icon-button" data-sm-action="item-up" aria-label="<?php esc_attr_e( 'Move item up', 'satori-manifest' ); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
			<button type="button" class="sm-icon-button" data-sm-action="item-down" aria-label="<?php esc_attr_e( 'Move item down', 'satori-manifest' ); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
			<button type="button" class="sm-icon-button sm-icon-button--danger" data-sm-action="item-remove" aria-label="<?php esc_attr_e( 'Remove item', 'satori-manifest' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
		</span>
	</div>
	<?php
};

/**
 * Renders one section card.
 *
 * @param array<string,mixed> $section   Section data (empty for the template).
 * @param bool                $expanded  Whether the body starts open.
 * @return void
 */
$satori_manifest_render_section = static function ( array $section, bool $expanded ) use ( $satori_manifest_render_item ): void {
	$items    = isset( $section['items'] ) && is_array( $section['items'] ) ? $section['items'] : array();
	$icon_id  = isset( $section['icon'] ) ? absint( $section['icon'] ) : 0;
	$icon_url = $icon_id ? (string) wp_get_attachment_image_url( $icon_id, 'thumbnail' ) : '';
	?>
	<section class="sm-section<?php echo $expanded ? '' : ' is-collapsed'; ?>" data-sm-id="<?php echo esc_attr( (string) ( $section['id'] ?? '' ) ); ?>">
		<header class="sm-section__head">
			<span class="sm-grip dashicons dashicons-menu" data-sm-grip title="<?php esc_attr_e( 'Drag to move', 'satori-manifest' ); ?>" aria-hidden="true"></span>
			<button type="button" class="sm-section__toggle sm-icon-button" data-sm-action="section-toggle" aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Show or hide items', 'satori-manifest' ); ?>">
				<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
			</button>
			<span class="sm-section__icon<?php echo '' !== $icon_url ? ' has-icon' : ''; ?>">
				<input type="hidden" class="sm-section__icon-input" value="<?php echo esc_attr( '' !== $icon_url ? (string) $icon_id : '' ); ?>" />
				<button type="button" class="sm-section__icon-button" data-sm-action="section-icon" title="<?php esc_attr_e( 'Icon or image shown left of the section title', 'satori-manifest' ); ?>" aria-label="<?php esc_attr_e( 'Choose a section icon', 'satori-manifest' ); ?>">
					<?php if ( '' !== $icon_url ) : ?>
						<img src="<?php echo esc_url( $icon_url ); ?>" alt="" />
					<?php else : ?>
						<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
					<?php endif; ?>
				</button>
				<button type="button" class="sm-section__icon-remove" data-sm-action="section-icon-remove" aria-label="<?php esc_attr_e( 'Remove section icon', 'satori-manifest' ); ?>">&times;</button>
			</span>
			<label class="sm-section__title">
				<span class="screen-reader-text"><?php esc_html_e( 'Section title', 'satori-manifest' ); ?></span>
				<input type="text" class="sm-section__title-input" value="<?php echo esc_attr( (string) ( $section['title'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Section title, e.g. Colour', 'satori-manifest' ); ?>" />
			</label>
			<span class="sm-section__count" data-sm-count></span>
			<span class="sm-section__actions">
				<button type="button" class="sm-icon-button" data-sm-action="section-up" aria-label="<?php esc_attr_e( 'Move section up', 'satori-manifest' ); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
				<button type="button" class="sm-icon-button" data-sm-action="section-down" aria-label="<?php esc_attr_e( 'Move section down', 'satori-manifest' ); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
				<button type="button" class="sm-icon-button sm-icon-button--danger" data-sm-action="section-remove" aria-label="<?php esc_attr_e( 'Remove section', 'satori-manifest' ); ?>">
					<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					<span class="sm-confirm-label"><?php esc_html_e( 'Click again to remove', 'satori-manifest' ); ?></span>
				</button>
			</span>
		</header>

		<div class="sm-section__body">
			<label class="sm-section__description">
				<span class="screen-reader-text"><?php esc_html_e( 'Section description', 'satori-manifest' ); ?></span>
				<textarea class="sm-section__description-input" rows="1" placeholder="<?php esc_attr_e( 'Optional intro shown under the section title', 'satori-manifest' ); ?>"><?php echo esc_textarea( (string) ( $section['description'] ?? '' ) ); ?></textarea>
			</label>

			<div class="sm-items-head" aria-hidden="true">
				<span></span>
				<span><?php esc_html_e( 'Item', 'satori-manifest' ); ?></span>
				<span><?php esc_html_e( 'Description', 'satori-manifest' ); ?></span>
				<span><?php esc_html_e( 'Badge', 'satori-manifest' ); ?></span>
				<span><?php esc_html_e( 'Prefix', 'satori-manifest' ); ?></span>
				<span><?php esc_html_e( 'Price', 'satori-manifest' ); ?></span>
				<span></span>
			</div>

			<div class="sm-items">
				<?php
				foreach ( $items as $item ) {
					$satori_manifest_render_item( (array) $item );
				}
				?>
			</div>

			<div class="sm-section__footer">
				<button type="button" class="button" data-sm-action="item-add">
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'Add item', 'satori-manifest' ); ?>
				</button>
				<span class="sm-hint"><?php esc_html_e( 'Tip: leave the price empty to use an item as a sub-heading.', 'satori-manifest' ); ?></span>
			</div>
		</div>
	</section>
	<?php
};

/**
 * Renders one menu heading row ("Main Menu"): groups the sections below it.
 *
 * @param array<string,mixed> $heading  Heading data (empty for the template).
 * @return void
 */
$satori_manifest_render_heading = static function ( array $heading ): void {
	$style      = isset( $heading['style'] ) && is_array( $heading['style'] ) ? $heading['style'] : array();
	$background = (string) sanitize_hex_color( (string) ( $style['background'] ?? '' ) );
	$text       = (string) sanitize_hex_color( (string) ( $style['text'] ?? '' ) );
	$own        = '' !== $background || '' !== $text;
	$size       = (string) ( $style['size'] ?? 'm' );
	$visible    = ! isset( $heading['visible'] ) || false !== $heading['visible'];
	$sizes      = array(
		's' => __( 'Small', 'satori-manifest' ),
		'm' => __( 'Medium', 'satori-manifest' ),
		'l' => __( 'Large', 'satori-manifest' ),
	);
	?>
	<div class="sm-heading<?php echo $visible ? '' : ' is-off'; ?><?php echo $own ? ' has-own-colours' : ''; ?>" data-sm-id="<?php echo esc_attr( (string) ( $heading['id'] ?? '' ) ); ?>" data-sm-heading>
		<span class="sm-grip dashicons dashicons-menu" data-sm-grip title="<?php esc_attr_e( 'Drag to move', 'satori-manifest' ); ?>" aria-hidden="true"></span>
		<div class="sm-heading__fields">
			<span class="sm-heading__tag"><?php esc_html_e( 'Heading', 'satori-manifest' ); ?></span>
			<label class="sm-heading__title">
				<span class="screen-reader-text"><?php esc_html_e( 'Heading', 'satori-manifest' ); ?></span>
				<input type="text" class="sm-heading__title-input" value="<?php echo esc_attr( (string) ( $heading['title'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Heading, e.g. Main Menu', 'satori-manifest' ); ?>" />
			</label>
			<label class="sm-heading__note">
				<span class="screen-reader-text"><?php esc_html_e( 'Note under the heading', 'satori-manifest' ); ?></span>
				<input type="text" class="sm-heading__note-input" value="<?php echo esc_attr( (string) ( $heading['description'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Optional note, e.g. Served until 2 p.m. daily', 'satori-manifest' ); ?>" />
			</label>
			<div class="sm-heading__style">
				<label class="sm-heading__option">
					<input type="checkbox" class="sm-heading__visible" <?php checked( $visible ); ?> />
					<?php esc_html_e( 'Show on page', 'satori-manifest' ); ?>
				</label>
				<label class="sm-heading__option">
					<?php esc_html_e( 'Size', 'satori-manifest' ); ?>
					<select class="sm-heading__size">
						<?php foreach ( $sizes as $satori_manifest_key => $satori_manifest_label ) : ?>
							<option value="<?php echo esc_attr( $satori_manifest_key ); ?>" <?php selected( $size, $satori_manifest_key ); ?>><?php echo esc_html( $satori_manifest_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="sm-heading__option">
					<input type="checkbox" class="sm-heading__own" <?php checked( $own ); ?> />
					<?php esc_html_e( 'Own colours', 'satori-manifest' ); ?>
				</label>
				<label class="sm-heading__option sm-heading__colour">
					<?php esc_html_e( 'Background', 'satori-manifest' ); ?>
					<input type="color" class="sm-heading__background" value="<?php echo esc_attr( '' !== $background ? $background : '#4b5f57' ); ?>" />
				</label>
				<label class="sm-heading__option sm-heading__colour">
					<?php esc_html_e( 'Text', 'satori-manifest' ); ?>
					<input type="color" class="sm-heading__text" value="<?php echo esc_attr( '' !== $text ? $text : '#ffffff' ); ?>" />
				</label>
			</div>
		</div>
		<span class="sm-heading__actions">
			<button type="button" class="sm-icon-button" data-sm-action="entry-up" aria-label="<?php esc_attr_e( 'Move heading up', 'satori-manifest' ); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
			<button type="button" class="sm-icon-button" data-sm-action="entry-down" aria-label="<?php esc_attr_e( 'Move heading down', 'satori-manifest' ); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
			<button type="button" class="sm-icon-button sm-icon-button--danger" data-sm-action="heading-remove" aria-label="<?php esc_attr_e( 'Remove heading', 'satori-manifest' ); ?>">
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
				<span class="sm-confirm-label"><?php esc_html_e( 'Click again to remove', 'satori-manifest' ); ?></span>
			</button>
		</span>
	</div>
	<?php
};
?>
<div class="sm-manifest-editor" data-sm-manifest-editor>

	<input
		type="hidden"
		id="satori-manifest-sections-data"
		name="<?php echo esc_attr( $satori_manifest_post_field ); ?>"
		value="<?php echo esc_attr( (string) wp_json_encode( $satori_manifest_sections ) ); ?>"
	/>

	<datalist id="sm-badge-options">
		<?php foreach ( $satori_manifest_badges as $satori_manifest_badge ) : ?>
			<option value="<?php echo esc_attr( $satori_manifest_badge ); ?>"></option>
		<?php endforeach; ?>
	</datalist>

	<div class="sm-manifest-toolbar">
		<p class="sm-manifest-toolbar__stats" data-sm-stats aria-live="polite"></p>
		<div class="sm-manifest-toolbar__actions">
			<button type="button" class="button button-link" data-sm-action="expand-all"><?php esc_html_e( 'Expand all', 'satori-manifest' ); ?></button>
			<button type="button" class="button button-link" data-sm-action="collapse-all"><?php esc_html_e( 'Collapse all', 'satori-manifest' ); ?></button>
			<button type="button" class="button" data-sm-action="heading-add" data-sm-where="top">
				<span class="dashicons dashicons-heading" aria-hidden="true"></span>
				<?php esc_html_e( 'Add heading', 'satori-manifest' ); ?>
			</button>
			<button type="button" class="button button-primary" data-sm-action="section-add">
				<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Add section', 'satori-manifest' ); ?>
			</button>
		</div>
	</div>

	<div class="sm-sections" id="satori-manifest-sections-list">
		<?php
		$satori_manifest_first = true;
		foreach ( $satori_manifest_sections as $satori_manifest_section ) {
			if ( \SatoriManifest\Manifest\Headings::is_heading( $satori_manifest_section ) ) {
				$satori_manifest_render_heading( (array) $satori_manifest_section );
				continue;
			}

			// Open the first section; keep long lists tidy.
			$satori_manifest_render_section( (array) $satori_manifest_section, $satori_manifest_first );
			$satori_manifest_first = false;
		}
		?>
	</div>

	<div class="sm-manifest-toolbar sm-manifest-toolbar--bottom">
		<p class="sm-hint"><?php esc_html_e( 'Drag sections and headings by their handle to reorder. A heading groups every section below it, up to the next heading.', 'satori-manifest' ); ?></p>
		<div class="sm-manifest-toolbar__actions">
			<button type="button" class="button" data-sm-action="heading-add" data-sm-where="bottom">
				<span class="dashicons dashicons-heading" aria-hidden="true"></span>
				<?php esc_html_e( 'Add heading', 'satori-manifest' ); ?>
			</button>
			<button type="button" class="button" data-sm-action="section-add">
				<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Add section', 'satori-manifest' ); ?>
			</button>
		</div>
	</div>

	<div class="sm-manifest-empty" data-sm-empty>
		<span class="sm-manifest-empty__icon dashicons dashicons-list-view" aria-hidden="true"></span>
		<h3><?php esc_html_e( 'Your price list is empty', 'satori-manifest' ); ?></h3>
		<p><?php esc_html_e( 'Start with a section, for example "Cuts", "Colour" or "Drinks", then add items with prices.', 'satori-manifest' ); ?></p>
		<button type="button" class="button button-primary" data-sm-action="section-add"><?php esc_html_e( 'Add your first section', 'satori-manifest' ); ?></button>
	</div>
</div>

<template id="satori-manifest-section-template">
	<?php $satori_manifest_render_section( array(), true ); ?>
</template>

<template id="satori-manifest-heading-template">
	<?php $satori_manifest_render_heading( array() ); ?>
</template>

<template id="satori-manifest-item-template">
	<?php $satori_manifest_render_item( array() ); ?>
</template>
