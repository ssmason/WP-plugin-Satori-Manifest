<?php
/**
 * View: Branding screen.
 *
 * Receives from Branding_Screen::render():
 *   $satori_manifest_client_id, $satori_manifest_page_id, $satori_manifest_tabs,
 *   $satori_manifest_tab, $satori_manifest_updated, $satori_manifest_clients.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SatoriManifest\Admin_Menu;
use SatoriManifest\Field_Loader;
use SatoriManifest\Icons;

$satori_manifest_base_url = Admin_Menu::url( Admin_Menu::BRANDING_SLUG, $satori_manifest_client_id );
$satori_manifest_page_url = $satori_manifest_page_id ? (string) get_permalink( $satori_manifest_page_id ) : '';
$satori_manifest_current  = $satori_manifest_tabs[ $satori_manifest_tab ] ?? null;
?>
<div class="wrap sm-admin sm-branding-screen">
	<header class="sm-page-header">
		<div>
			<p class="sm-eyebrow"><?php esc_html_e( 'Branding', 'satori-manifest' ); ?></p>
			<h1 class="sm-page-header__title"><?php esc_html_e( 'Make it yours', 'satori-manifest' ); ?></h1>
			<p class="sm-page-header__lead"><?php esc_html_e( 'Your logo, colours, header and footer. Changes appear on your page as soon as you save.', 'satori-manifest' ); ?></p>
		</div>
		<?php if ( $satori_manifest_page_url ) : ?>
			<a class="button" href="<?php echo esc_url( $satori_manifest_page_url ); ?>" target="_blank" rel="noopener"><?php Icons::render( 'eye', 16 ); ?> <?php esc_html_e( 'View page', 'satori-manifest' ); ?></a>
		<?php endif; ?>
	</header>

	<?php if ( ! Field_Loader::acf_available() ) : ?>
		<div class="sm-notice sm-notice--warning"><?php esc_html_e( 'Branding needs the Advanced Custom Fields plugin. Please ask your administrator to activate it.', 'satori-manifest' ); ?></div>
	<?php elseif ( ! $satori_manifest_client_id ) : ?>
		<?php if ( empty( $satori_manifest_clients ) ) : ?>
			<div class="sm-empty"><p><?php esc_html_e( 'No clients yet. Add one from Manifest → Clients.', 'satori-manifest' ); ?></p></div>
		<?php else : ?>
			<form class="sm-client-picker" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( Admin_Menu::BRANDING_SLUG ); ?>" />
				<label for="sm-client-select"><?php esc_html_e( 'Choose a client', 'satori-manifest' ); ?></label>
				<select id="sm-client-select" name="client">
					<?php foreach ( $satori_manifest_clients as $satori_manifest_client ) : ?>
						<option value="<?php echo esc_attr( (string) $satori_manifest_client->ID ); ?>"><?php echo esc_html( $satori_manifest_client->display_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Open', 'satori-manifest' ); ?></button>
			</form>
		<?php endif; ?>
	<?php elseif ( ! $satori_manifest_page_id || ! $satori_manifest_current ) : ?>
		<div class="sm-empty"><p><?php esc_html_e( 'There is nothing to edit yet.', 'satori-manifest' ); ?></p></div>
	<?php else : ?>
		<?php if ( $satori_manifest_updated ) : ?>
			<div class="sm-notice sm-notice--success"><?php Icons::render( 'check', 18 ); ?> <?php esc_html_e( 'Saved. Your page has been updated.', 'satori-manifest' ); ?></div>
		<?php endif; ?>

		<nav class="sm-tabs" aria-label="<?php esc_attr_e( 'Branding sections', 'satori-manifest' ); ?>">
			<?php foreach ( $satori_manifest_tabs as $satori_manifest_slug => $satori_manifest_tab_data ) : ?>
				<a class="sm-tabs__tab<?php echo esc_attr( $satori_manifest_slug === $satori_manifest_tab ? ' is-active' : '' ); ?>"
					href="<?php echo esc_url( add_query_arg( 'tab', $satori_manifest_slug, $satori_manifest_base_url ) ); ?>"
					<?php echo $satori_manifest_slug === $satori_manifest_tab ? 'aria-current="page"' : ''; ?>>
					<span class="dashicons <?php echo esc_attr( $satori_manifest_tab_data['icon'] ); ?>" aria-hidden="true"></span>
					<?php echo esc_html( $satori_manifest_tab_data['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="sm-branding-layout">
			<section class="sm-panel sm-branding-form">
				<?php if ( '' !== $satori_manifest_current['description'] ) : ?>
					<p class="sm-panel__intro"><?php echo esc_html( $satori_manifest_current['description'] ); ?></p>
				<?php endif; ?>

				<?php
				acf_form(
					array(
						'id'                    => 'sm-branding-' . $satori_manifest_tab,
						'post_id'               => $satori_manifest_page_id,
						'field_groups'          => array( $satori_manifest_current['group_key'] ),
						'form'                  => true,
						'return'                => add_query_arg(
							array(
								'tab'     => $satori_manifest_tab,
								'updated' => 1,
							),
							$satori_manifest_base_url
						),
						'submit_value'          => __( 'Save changes', 'satori-manifest' ),
						'html_submit_button'    => '<button type="submit" class="button button-primary button-hero">%s</button>',
						'updated_message'       => false,
						'label_placement'       => 'top',
						'instruction_placement' => 'label',
						'uploader'              => 'wp',
					)
				);
				?>
			</section>

			<aside class="sm-panel sm-preview-panel" aria-label="<?php esc_attr_e( 'Live preview', 'satori-manifest' ); ?>">
				<div class="sm-preview-panel__bar">
					<span class="sm-preview-panel__dots" aria-hidden="true"><i></i><i></i><i></i></span>
					<span class="sm-preview-panel__label"><?php esc_html_e( 'Live preview', 'satori-manifest' ); ?></span>
				</div>
				<div class="sm-preview-panel__frame">
					<iframe src="<?php echo esc_url( add_query_arg( 'sm_design', \SatoriManifest\Client_Repository::design_for_page( $satori_manifest_page_id ), $satori_manifest_page_url ) ); ?>" title="<?php esc_attr_e( 'Page preview', 'satori-manifest' ); ?>" loading="lazy"></iframe>
				</div>
			</aside>
		</div>
	<?php endif; ?>
</div>
