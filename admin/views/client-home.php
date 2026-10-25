<?php
/**
 * View: client Home screen.
 *
 * Receives $satori_manifest_summary from Client_Home::render().
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
use SatoriManifest\Icons;

$satori_manifest_s    = $satori_manifest_summary;
$satori_manifest_name = $satori_manifest_s['brand']['name'] ?? ( $satori_manifest_s['user'] ? $satori_manifest_s['user']->display_name : '' );
$satori_manifest_live = 'publish' === $satori_manifest_s['page_status'];

$satori_manifest_cards = array(
	array(
		'icon'  => 'page',
		'title' => __( 'Page content', 'satori-manifest' ),
		'text'  => __( 'Edit headings, intro text, images and where your price list sits on the page.', 'satori-manifest' ),
		'meta'  => $satori_manifest_s['modified'] ? sprintf( /* translators: %s: date. */ __( 'Last updated %s', 'satori-manifest' ), $satori_manifest_s['modified'] ) : '',
		'url'   => $satori_manifest_s['page_id'] ? admin_url( 'post.php?post=' . $satori_manifest_s['page_id'] . '&action=edit' ) : '',
		'cta'   => __( 'Edit page', 'satori-manifest' ),
	),
	array(
		'icon'  => 'list',
		'title' => __( 'Price list', 'satori-manifest' ),
		'text'  => __( 'Add sections and items, update prices and reorder everything.', 'satori-manifest' ),
		'meta'  => sprintf(
			/* translators: 1: number of sections, 2: number of items. */
			_n( '%1$d section', '%1$d sections', $satori_manifest_s['sections'], 'satori-manifest' ) . ' · ' . _n( '%2$d item', '%2$d items', $satori_manifest_s['items'], 'satori-manifest' ),
			$satori_manifest_s['sections'],
			$satori_manifest_s['items']
		),
		'url'   => $satori_manifest_s['manifest_id'] ? admin_url( 'post.php?post=' . $satori_manifest_s['manifest_id'] . '&action=edit' ) : '',
		'cta'   => __( 'Edit prices', 'satori-manifest' ),
	),
	array(
		'icon'  => 'palette',
		'title' => __( 'Design', 'satori-manifest' ),
		'text'  => __( 'Choose the look of your page. Switch any time — your content stays.', 'satori-manifest' ),
		'meta'  => $satori_manifest_s['design'] ? sprintf( /* translators: %s: design name. */ __( 'Current design: %s', 'satori-manifest' ), $satori_manifest_s['design']['title'] ) : '',
		'url'   => Admin_Menu::url( Admin_Menu::DESIGN_SLUG, $satori_manifest_s['user'] ? $satori_manifest_s['user']->ID : 0 ),
		'cta'   => __( 'Change design', 'satori-manifest' ),
	),
	array(
		'icon'  => 'brush',
		'title' => __( 'Branding', 'satori-manifest' ),
		'text'  => __( 'Logo, colours, fonts, header button, contact details and social links.', 'satori-manifest' ),
		'meta'  => '',
		'url'   => Admin_Menu::url( Admin_Menu::BRANDING_SLUG, $satori_manifest_s['user'] ? $satori_manifest_s['user']->ID : 0 ),
		'cta'   => __( 'Edit branding', 'satori-manifest' ),
	),
	array(
		'icon'  => 'link',
		'title' => __( 'Navigation', 'satori-manifest' ),
		'text'  => __( 'Links in your page header — for example back to your main website or a booking page.', 'satori-manifest' ),
		'meta'  => $satori_manifest_s['menu_items']
			? sprintf( /* translators: %d: number of links. */ _n( '%d link', '%d links', $satori_manifest_s['menu_items'], 'satori-manifest' ), $satori_manifest_s['menu_items'] )
			: __( 'No links — standalone page', 'satori-manifest' ),
		'url'   => $satori_manifest_s['menu_id'] ? admin_url( 'nav-menus.php?action=edit&menu=' . $satori_manifest_s['menu_id'] ) : '',
		'cta'   => __( 'Edit links', 'satori-manifest' ),
	),
);
?>
<div class="wrap sm-admin sm-home">
	<header class="sm-hero">
		<div class="sm-hero__text">
			<p class="sm-eyebrow"><?php esc_html_e( 'Satori Manifest', 'satori-manifest' ); ?></p>
			<h1 class="sm-hero__title">
				<?php
				/* translators: %s: business name. */
				printf( esc_html__( 'Welcome back, %s', 'satori-manifest' ), esc_html( (string) $satori_manifest_name ) );
				?>
			</h1>
			<p class="sm-hero__lead"><?php esc_html_e( 'Everything for your QR price list in one place.', 'satori-manifest' ); ?></p>
		</div>

		<?php if ( $satori_manifest_s['page_url'] ) : ?>
			<div class="sm-qr-card">
				<div class="sm-qr-card__icon"><?php Icons::render( 'qr', 28 ); ?></div>
				<div class="sm-qr-card__body">
					<span class="sm-status <?php echo esc_attr( $satori_manifest_live ? 'is-live' : 'is-draft' ); ?>">
						<?php echo $satori_manifest_live ? esc_html__( 'Live', 'satori-manifest' ) : esc_html__( 'Not published', 'satori-manifest' ); ?>
					</span>
					<p class="sm-qr-card__label"><?php esc_html_e( 'Your QR code opens', 'satori-manifest' ); ?></p>
					<code class="sm-qr-card__url"><?php echo esc_html( $satori_manifest_s['page_url'] ); ?></code>
				</div>
				<a class="button button-primary sm-qr-card__view" href="<?php echo esc_url( $satori_manifest_s['page_url'] ); ?>" target="_blank" rel="noopener">
					<?php Icons::render( 'eye', 16 ); ?>
					<?php esc_html_e( 'View page', 'satori-manifest' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</header>

	<?php if ( ! $satori_manifest_s['page_id'] ) : ?>
		<div class="sm-empty">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<p><?php esc_html_e( 'Your page has not been set up yet. Please contact Satori and we will sort it out.', 'satori-manifest' ); ?></p>
		</div>
	<?php else : ?>
		<div class="sm-card-grid">
			<?php foreach ( $satori_manifest_cards as $satori_manifest_card ) : ?>
				<?php
				if ( '' === $satori_manifest_card['url'] ) {
					continue;
				}
				?>
				<a class="sm-task-card" href="<?php echo esc_url( $satori_manifest_card['url'] ); ?>">
					<span class="sm-task-card__icon sm-task-card__icon--<?php echo esc_attr( $satori_manifest_card['icon'] ); ?>"><?php Icons::render( $satori_manifest_card['icon'], 24 ); ?></span>
					<span class="sm-task-card__title"><?php echo esc_html( $satori_manifest_card['title'] ); ?></span>
					<span class="sm-task-card__text"><?php echo esc_html( $satori_manifest_card['text'] ); ?></span>
					<?php if ( '' !== $satori_manifest_card['meta'] ) : ?>
						<span class="sm-task-card__meta"><?php echo esc_html( $satori_manifest_card['meta'] ); ?></span>
					<?php endif; ?>
					<span class="sm-task-card__cta"><?php echo esc_html( $satori_manifest_card['cta'] ); ?> <?php Icons::render( 'arrow', 16 ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
