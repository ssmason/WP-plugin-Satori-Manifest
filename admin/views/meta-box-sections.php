<?php
/**
 * Meta box view — Sections & Items editor for a single manifest.
 *
 * Receives from Meta_Box::render():
 *   $satori_manifest_sections    array  Saved sections.
 *   $satori_manifest_post_field  string Hidden field name for the JSON payload.
 *   $satori_manifest_currency    array  { symbol, position } for the price adornment.
 *
 * Sections and items are rendered by the two partial closures below, which
 * are also used to print the <template> elements cloned by
 * build/js/manifest-admin.js when adding rows — one source of markup.
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
		<span class="sm-item__grip dashicons dashicons-menu" aria-hidden="true"></span>
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
	$items = isset( $section['items'] ) && is_array( $section['items'] ) ? $section['items'] : array();
	?>
	<section class="sm-section<?php echo $expanded ? '' : ' is-collapsed'; ?>">
		<header class="sm-section__head">
			<button type="button" class="sm-section__toggle sm-icon-button" data-sm-action="section-toggle" aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Show or hide items', 'satori-manifest' ); ?>">
				<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
			</button>
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
			<button type="button" class="button button-primary" data-sm-action="section-add">
				<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Add section', 'satori-manifest' ); ?>
			</button>
		</div>
	</div>

	<div class="sm-sections" id="satori-manifest-sections-list">
		<?php
		foreach ( $satori_manifest_sections as $satori_manifest_index => $satori_manifest_section ) {
			// Open the first section; keep long lists tidy.
			$satori_manifest_render_section( (array) $satori_manifest_section, 0 === $satori_manifest_index );
		}
		?>
	</div>

	<div class="sm-manifest-empty" data-sm-empty>
		<span class="sm-manifest-empty__icon dashicons dashicons-list-view" aria-hidden="true"></span>
		<h3><?php esc_html_e( 'Your price list is empty', 'satori-manifest' ); ?></h3>
		<p><?php esc_html_e( 'Start with a section — for example "Cuts", "Colour" or "Drinks" — then add items with prices.', 'satori-manifest' ); ?></p>
		<button type="button" class="button button-primary" data-sm-action="section-add"><?php esc_html_e( 'Add your first section', 'satori-manifest' ); ?></button>
	</div>
</div>

<template id="satori-manifest-section-template">
	<?php $satori_manifest_render_section( array(), true ); ?>
</template>

<template id="satori-manifest-item-template">
	<?php $satori_manifest_render_item( array() ); ?>
</template>
