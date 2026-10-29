<?php
/**
 * View: Design screen.
 *
 * Receives from Design_Screen::render():
 *   $satori_manifest_client_id, $satori_manifest_page_id, $satori_manifest_current,
 *   $satori_manifest_designs, $satori_manifest_clients, $satori_manifest_notice.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Admin\Screens\Design_Screen;
use SatoriManifest\Branding\Fonts;
use SatoriManifest\Branding\Icons;
use SatoriManifest\Core\Security;
use SatoriManifest\Frontend\Client_Page;

$satori_manifest_page_url = $satori_manifest_page_id ? (string) get_permalink( $satori_manifest_page_id ) : '';
?>
<div class="wrap sm-admin sm-design-screen">
	<header class="sm-page-header">
		<div>
			<p class="sm-eyebrow"><?php esc_html_e( 'Design', 'satori-manifest' ); ?></p>
			<h1 class="sm-page-header__title"><?php esc_html_e( 'Choose your look', 'satori-manifest' ); ?></h1>
			<p class="sm-page-header__lead"><?php esc_html_e( 'Each design brings its own layout, colours and fonts. The previews show your own price list. You can fine-tune colours and fonts under Branding.', 'satori-manifest' ); ?></p>
		</div>
		<?php if ( $satori_manifest_page_url ) : ?>
			<a class="button" href="<?php echo esc_url( $satori_manifest_page_url ); ?>" target="_blank" rel="noopener"><?php Icons::render( 'eye', 16 ); ?> <?php esc_html_e( 'View page', 'satori-manifest' ); ?></a>
		<?php endif; ?>
	</header>

	<?php if ( 'applied' === $satori_manifest_notice ) : ?>
		<div class="sm-notice sm-notice--success"><?php Icons::render( 'check', 18 ); ?> <?php esc_html_e( 'Design applied. Your content was kept.', 'satori-manifest' ); ?></div>
	<?php elseif ( 'replaced' === $satori_manifest_notice ) : ?>
		<div class="sm-notice sm-notice--success"><?php Icons::render( 'check', 18 ); ?> <?php esc_html_e( 'Design applied with its starter layout. Your previous content is saved as a revision of your page.', 'satori-manifest' ); ?></div>
	<?php endif; ?>

	<?php if ( ! $satori_manifest_client_id ) : ?>
		<?php if ( empty( $satori_manifest_clients ) ) : ?>
			<div class="sm-empty"><p><?php esc_html_e( 'No clients yet. Add one from Manifest → Clients.', 'satori-manifest' ); ?></p></div>
		<?php else : ?>
			<form class="sm-client-picker" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( Admin_Menu::DESIGN_SLUG ); ?>" />
				<label for="sm-client-select"><?php esc_html_e( 'Choose a client', 'satori-manifest' ); ?></label>
				<select id="sm-client-select" name="client">
					<?php foreach ( $satori_manifest_clients as $satori_manifest_client ) : ?>
						<option value="<?php echo esc_attr( (string) $satori_manifest_client->ID ); ?>"><?php echo esc_html( $satori_manifest_client->display_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Open', 'satori-manifest' ); ?></button>
			</form>
		<?php endif; ?>
	<?php elseif ( ! $satori_manifest_page_id ) : ?>
		<div class="sm-empty"><p><?php esc_html_e( 'This client does not have a page yet.', 'satori-manifest' ); ?></p></div>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sm-design-form">
			<input type="hidden" name="action" value="<?php echo esc_attr( Design_Screen::ACTION ); ?>" />
			<input type="hidden" name="page_id" value="<?php echo esc_attr( (string) $satori_manifest_page_id ); ?>" />
			<?php Security::output_form_nonce( Design_Screen::ACTION ); ?>

			<div class="sm-design-grid" role="radiogroup" aria-label="<?php esc_attr_e( 'Designs', 'satori-manifest' ); ?>">
				<?php foreach ( $satori_manifest_designs as $satori_manifest_slug => $satori_manifest_design ) : ?>
					<?php
					$satori_manifest_is_current = $satori_manifest_slug === $satori_manifest_current;
					$satori_manifest_preview    = add_query_arg( Client_Page::PREVIEW_VAR, $satori_manifest_slug, $satori_manifest_page_url );
					$satori_manifest_heading    = Fonts::get( $satori_manifest_design['fonts']['heading'] );
					$satori_manifest_body       = Fonts::get( $satori_manifest_design['fonts']['body'] );
					?>
					<label class="sm-design-card<?php echo esc_attr( $satori_manifest_is_current ? ' is-current' : '' ); ?>">
						<input class="sm-design-card__radio" type="radio" name="design" value="<?php echo esc_attr( $satori_manifest_slug ); ?>" <?php checked( $satori_manifest_is_current ); ?> />

						<span class="sm-design-card__preview">
							<iframe src="<?php echo esc_url( $satori_manifest_preview ); ?>" title="<?php echo esc_attr( $satori_manifest_design['title'] ); ?>" loading="lazy" tabindex="-1" aria-hidden="true"></iframe>
						</span>

						<span class="sm-design-card__body">
							<span class="sm-design-card__heading">
								<span class="sm-design-card__title"><?php echo esc_html( $satori_manifest_design['title'] ); ?></span>
								<?php if ( $satori_manifest_is_current ) : ?>
									<span class="sm-badge sm-badge--current"><?php esc_html_e( 'Current', 'satori-manifest' ); ?></span>
								<?php endif; ?>
							</span>
							<span class="sm-design-card__desc"><?php echo esc_html( $satori_manifest_design['description'] ); ?></span>

							<?php $satori_manifest_colours = array_values( array_filter( $satori_manifest_design['palette'] ) ); ?>
							<svg class="sm-swatches" width="<?php echo esc_attr( (string) ( 18 * count( $satori_manifest_colours ) + 8 ) ); ?>" height="28" aria-hidden="true" focusable="false">
								<?php foreach ( $satori_manifest_colours as $satori_manifest_i => $satori_manifest_colour ) : ?>
									<circle cx="<?php echo esc_attr( (string) ( 14 + 18 * $satori_manifest_i ) ); ?>" cy="14" r="12" fill="<?php echo esc_attr( $satori_manifest_colour ); ?>" />
								<?php endforeach; ?>
							</svg>

							<span class="sm-design-card__meta">
								<?php
								echo esc_html(
									implode(
										' · ',
										array_filter(
											array(
												$satori_manifest_heading['label'] ?? '',
												$satori_manifest_body['label'] ?? '',
												$satori_manifest_design['best_for'],
											)
										)
									)
								);
								?>
							</span>

							<a class="sm-design-card__open" href="<?php echo esc_url( $satori_manifest_preview ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Full preview', 'satori-manifest' ); ?> <?php Icons::render( 'arrow', 14 ); ?></a>
						</span>
					</label>
				<?php endforeach; ?>
			</div>

			<div class="sm-apply-bar">
				<fieldset class="sm-apply-bar__modes">
					<legend class="screen-reader-text"><?php esc_html_e( 'What should happen to your page content?', 'satori-manifest' ); ?></legend>
					<label class="sm-choice">
						<input type="radio" name="mode" value="keep" checked />
						<span>
							<strong><?php esc_html_e( 'Keep my content', 'satori-manifest' ); ?></strong>
							<small><?php esc_html_e( 'Only the look changes.', 'satori-manifest' ); ?></small>
						</span>
					</label>
					<label class="sm-choice">
						<input type="radio" name="mode" value="replace" />
						<span>
							<strong><?php esc_html_e( 'Use the starter layout', 'satori-manifest' ); ?></strong>
							<small><?php esc_html_e( 'Replaces your page content. A revision is saved.', 'satori-manifest' ); ?></small>
						</span>
					</label>
				</fieldset>
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Apply design', 'satori-manifest' ); ?></button>
			</div>
		</form>
	<?php endif; ?>
</div>
