<?php
/**
 * View: client Home screen.
 *
 * Receives from Client_Home::render():
 *   $satori_manifest_summary  Client_Summary::for_user() data.
 *   $satori_manifest_embed    Website embed snippet (Embed_Code), or ''.
 *   $satori_manifest_qr_svg   The page's printable QR sign as SVG markup (built by Qr_Sign), or ''.
 *   $satori_manifest_stats    Views and scans in the last 30 days.
 *   $satori_manifest_wants    Whether the client gets the monthly summary email.
 *   $satori_manifest_notice   Notice code from a redirect.
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
use SatoriManifest\Branding\Icons;
use SatoriManifest\Branding\Portal_Brand;
use SatoriManifest\Core\Security;
use SatoriManifest\Pdf\Pdf_Download;
use SatoriManifest\Qr\Qr_Download;
use SatoriManifest\Stats\Monthly_Summary;

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
		'text'  => __( 'Choose the look of your page. Switch any time and your content stays.', 'satori-manifest' ),
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
		'text'  => __( 'Links in your page header, for example back to your main website or a booking page.', 'satori-manifest' ),
		'meta'  => $satori_manifest_s['menu_items']
			? sprintf( /* translators: %d: number of links. */ _n( '%d link', '%d links', $satori_manifest_s['menu_items'], 'satori-manifest' ), $satori_manifest_s['menu_items'] )
			: __( 'No links (standalone page)', 'satori-manifest' ),
		'url'   => $satori_manifest_s['menu_id'] ? admin_url( 'nav-menus.php?action=edit&menu=' . $satori_manifest_s['menu_id'] ) : '',
		'cta'   => __( 'Edit links', 'satori-manifest' ),
	),
);
?>
<div class="wrap sm-admin sm-home">
	<header class="sm-hero">
		<div class="sm-hero__text">
			<p class="sm-eyebrow"><?php echo esc_html( Portal_Brand::name() ); ?></p>
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
				<a class="sm-qr-card__code sm-qr-card__code--sign" href="<?php echo esc_url( Admin_Menu::url( Admin_Menu::QR_SLUG ) ); ?>" title="<?php esc_attr_e( 'Design your sign in the QR builder', 'satori-manifest' ); ?>">
					<?php echo $satori_manifest_qr_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG generated by Qr_Sign; values escaped there. ?>
				</a>
				<div class="sm-qr-card__body">
					<span class="sm-status <?php echo esc_attr( $satori_manifest_live ? 'is-live' : 'is-draft' ); ?>">
						<?php echo $satori_manifest_live ? esc_html__( 'Live', 'satori-manifest' ) : esc_html__( 'Not published', 'satori-manifest' ); ?>
					</span>
					<p class="sm-qr-card__label"><?php esc_html_e( 'Your QR code opens', 'satori-manifest' ); ?></p>
					<code class="sm-qr-card__url"><?php echo esc_html( $satori_manifest_s['page_url'] ); ?></code>
					<p class="sm-qr-card__stats">
						<?php
						printf(
							/* translators: 1: visits, 2: QR scans. */
							esc_html__( 'Last 30 days: %1$s visits · %2$s QR scans', 'satori-manifest' ),
							'<strong>' . esc_html( number_format_i18n( $satori_manifest_stats['views'] ) ) . '</strong>',
							'<strong>' . esc_html( number_format_i18n( $satori_manifest_stats['scans'] ) ) . '</strong>'
						);
						?>
					</p>
				</div>
				<div class="sm-qr-card__actions">
					<a class="button button-primary sm-qr-card__view" href="<?php echo esc_url( $satori_manifest_s['page_url'] ); ?>" target="_blank" rel="noopener">
						<?php Icons::render( 'eye', 16 ); ?>
						<?php esc_html_e( 'View page', 'satori-manifest' ); ?>
					</a>
				</div>
				<?php $satori_manifest_pdf_headings = \SatoriManifest\Pdf\Price_List_Pdf::printable_headings( (int) $satori_manifest_s['page_id'] ); ?>
				<form class="sm-qr-card__pdf-form" method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( Pdf_Download::ACTION ); ?>" />
					<input type="hidden" name="page_id" value="<?php echo esc_attr( (string) $satori_manifest_s['page_id'] ); ?>" />
					<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( Pdf_Download::ACTION . '_' . $satori_manifest_s['page_id'] ) ); ?>" />
					<span class="sm-qr-card__pdf-label"><?php esc_html_e( 'Print-ready PDF', 'satori-manifest' ); ?></span>
					<?php if ( $satori_manifest_pdf_headings ) : ?>
						<label>
							<span class="screen-reader-text"><?php esc_html_e( 'What to print', 'satori-manifest' ); ?></span>
							<select name="heading">
								<option value=""><?php esc_html_e( 'Whole price list', 'satori-manifest' ); ?></option>
								<?php foreach ( $satori_manifest_pdf_headings as $satori_manifest_heading_id => $satori_manifest_heading_title ) : ?>
									<option value="<?php echo esc_attr( $satori_manifest_heading_id ); ?>"><?php echo esc_html( $satori_manifest_heading_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					<?php endif; ?>
					<label>
						<span class="screen-reader-text"><?php esc_html_e( 'Paper size', 'satori-manifest' ); ?></span>
						<select name="paper">
							<option value="a4">A4</option>
							<option value="a3">A3</option>
						</select>
					</label>
					<button type="submit" class="button sm-qr-card__pdf"><?php esc_html_e( 'Download PDF', 'satori-manifest' ); ?></button>
				</form>
				<p class="sm-qr-card__downloads">
					<?php esc_html_e( 'Your QR sign:', 'satori-manifest' ); ?>
					<a href="<?php echo esc_url( Qr_Download::url( (int) $satori_manifest_s['page_id'], 'sign' ) ); ?>"><?php esc_html_e( 'Download PDF', 'satori-manifest' ); ?></a>
					·
					<a href="<?php echo esc_url( Admin_Menu::url( Admin_Menu::QR_SLUG ) ); ?>"><?php esc_html_e( 'Edit in QR builder', 'satori-manifest' ); ?></a>
					<br />
					<?php esc_html_e( 'Just the code:', 'satori-manifest' ); ?>
					<a href="<?php echo esc_url( Qr_Download::url( (int) $satori_manifest_s['page_id'], 'png' ) ); ?>"><?php esc_html_e( 'PNG for printing', 'satori-manifest' ); ?></a>
					·
					<a href="<?php echo esc_url( Qr_Download::url( (int) $satori_manifest_s['page_id'], 'svg' ) ); ?>"><?php esc_html_e( 'SVG for designers', 'satori-manifest' ); ?></a>
				</p>
			</div>
		<?php endif; ?>
	</header>

	<?php if ( ! $satori_manifest_s['page_id'] ) : ?>
		<div class="sm-empty">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<p><?php esc_html_e( 'Your page has not been set up yet. Please contact Satori and we will sort it out.', 'satori-manifest' ); ?></p>
		</div>
	<?php else : ?>
		<a class="sm-guide-banner" href="<?php echo esc_url( Admin_Menu::url( Admin_Menu::HELP_SLUG ) ); ?>">
			<span class="sm-guide-banner__label"><?php esc_html_e( 'New here?', 'satori-manifest' ); ?></span>
			<span class="sm-guide-banner__text"><?php esc_html_e( 'Follow the five-step getting started guide. Most people are done in twenty minutes.', 'satori-manifest' ); ?></span>
			<span class="sm-guide-banner__cta"><?php esc_html_e( 'Open guide', 'satori-manifest' ); ?> <?php Icons::render( 'arrow', 16 ); ?></span>
		</a>

		<?php if ( 'welcome' === $satori_manifest_notice ) : ?>
			<div class="sm-notice sm-notice--success"><?php Icons::render( 'check', 18 ); ?> <?php esc_html_e( 'Welcome! Your account is ready and your free trial has started.', 'satori-manifest' ); ?></div>
		<?php endif; ?>

		<?php if ( \SatoriManifest\Clients\First_Password::needed( get_current_user_id() ) ) : ?>
			<div class="sm-notice sm-notice--warning sm-password-prompt">
				<?php Icons::render( 'lock', 18 ); ?>
				<span><?php esc_html_e( 'Choose a password now so you can log in again next time.', 'satori-manifest' ); ?></span>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'profile.php#password' ) ); ?>"><?php esc_html_e( 'Choose my password', 'satori-manifest' ); ?></a>
			</div>
		<?php endif; ?>

		<?php if ( 'summary' === $satori_manifest_notice ) : ?>
			<div class="sm-notice sm-notice--success"><?php Icons::render( 'check', 18 ); ?> <?php esc_html_e( 'Your email preference is saved.', 'satori-manifest' ); ?></div>
		<?php endif; ?>

		<?php if ( 'codes-ordered' === $satori_manifest_notice ) : ?>
			<div class="sm-notice sm-notice--success"><?php Icons::render( 'check', 18 ); ?> <?php esc_html_e( 'Thank you! Your printed QR codes are paid for. We will print and post them to you shortly.', 'satori-manifest' ); ?></div>
		<?php elseif ( 'codes-empty' === $satori_manifest_notice ) : ?>
			<div class="sm-notice sm-notice--warning"><?php esc_html_e( 'Please enter how many codes you would like in at least one size.', 'satori-manifest' ); ?></div>
		<?php elseif ( 'codes-failed' === $satori_manifest_notice ) : ?>
			<div class="sm-notice sm-notice--warning"><?php esc_html_e( 'We could not start the payment just now. Please try again in a few minutes.', 'satori-manifest' ); ?></div>
		<?php endif; ?>

		<?php if ( \SatoriManifest\Billing\Printed_Codes::can_order() ) : ?>
			<section class="sm-panel sm-codes-panel">
				<h2 class="sm-panel__title"><?php esc_html_e( 'Order printed QR codes', 'satori-manifest' ); ?></h2>
				<p class="sm-panel__intro"><?php esc_html_e( 'Durable 3D-printed, self-adhesive QR codes for tables, the counter or the window, posted to you. Until they arrive you can download and print your code above.', 'satori-manifest' ); ?></p>
				<form class="sm-codes-panel__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( \SatoriManifest\Billing\Printed_Codes::ACTION ); ?>" />
					<?php \SatoriManifest\Core\Security::output_form_nonce( \SatoriManifest\Billing\Printed_Codes::ACTION ); ?>
					<div class="sm-codes-panel__sizes">
						<?php foreach ( \SatoriManifest\Billing\Code_Catalogue::sizes() as $satori_manifest_size => $satori_manifest_size_terms ) : ?>
							<label class="sm-codes-panel__size">
								<span><?php echo esc_html( $satori_manifest_size_terms['label'] ); ?></span>
								<span class="sm-muted"><?php echo esc_html( \SatoriManifest\Registration\Plans::format( $satori_manifest_size_terms['price'] ) ); ?></span>
								<input type="number" name="<?php echo esc_attr( \SatoriManifest\Billing\Printed_Codes::FIELD . '[' . $satori_manifest_size . ']' ); ?>" value="0" min="0" max="<?php echo esc_attr( (string) \SatoriManifest\Billing\Code_Catalogue::max() ); ?>" step="1" inputmode="numeric" />
							</label>
						<?php endforeach; ?>
					</div>
					<p class="sm-codes-panel__region">
						<label for="sm-codes-region"><?php esc_html_e( 'Post to', 'satori-manifest' ); ?></label>
						<select id="sm-codes-region" name="<?php echo esc_attr( \SatoriManifest\Billing\Printed_Codes::REGION_FIELD ); ?>">
							<?php foreach ( \SatoriManifest\Billing\Code_Catalogue::regions() as $satori_manifest_region => $satori_manifest_region_terms ) : ?>
								<option value="<?php echo esc_attr( $satori_manifest_region ); ?>">
									<?php
									echo esc_html( \SatoriManifest\Billing\Code_Catalogue::region_label( $satori_manifest_region ) );
									?>
								</option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Order and pay', 'satori-manifest' ); ?></button>
					</p>
				</form>
			</section>
		<?php endif; ?>

		<?php if ( '' !== $satori_manifest_embed ) : ?>
			<section class="sm-panel sm-embed-panel">
				<div class="sm-embed-panel__text">
					<h2 class="sm-panel__title"><?php esc_html_e( 'Add your price list to your website', 'satori-manifest' ); ?></h2>
					<p class="sm-panel__intro">
						<?php esc_html_e( 'Copy this code and paste it into an HTML or embed block on your own website, for example a Custom HTML block in WordPress, Embed code in Wix or a Code block in Squarespace. It always shows your latest prices.', 'satori-manifest' ); ?>
					</p>
				</div>
				<div class="sm-embed-panel__code">
					<label class="screen-reader-text" for="sm-embed-code"><?php esc_html_e( 'Embed code', 'satori-manifest' ); ?></label>
					<textarea id="sm-embed-code" class="sm-embed-panel__textarea" rows="4" readonly data-sm-copy-source><?php echo esc_textarea( $satori_manifest_embed ); ?></textarea>
					<div class="sm-embed-panel__actions">
						<button type="button" class="button button-primary" data-sm-copy="#sm-embed-code">
							<?php esc_html_e( 'Copy code', 'satori-manifest' ); ?>
						</button>
						<span class="sm-embed-panel__status" data-sm-copy-status aria-live="polite"></span>
					</div>
				</div>
			</section>
		<?php endif; ?>

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

		<form class="sm-panel sm-summary-pref" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( Monthly_Summary::ACTION ); ?>" />
			<?php Security::output_form_nonce( Monthly_Summary::ACTION ); ?>
			<label class="sm-summary-pref__label">
				<input type="checkbox" name="monthly_summary" value="1" <?php checked( $satori_manifest_wants ); ?> />
				<span><?php esc_html_e( 'Email me a monthly summary of visits and QR scans', 'satori-manifest' ); ?></span>
			</label>
			<button type="submit" class="button"><?php esc_html_e( 'Save', 'satori-manifest' ); ?></button>
		</form>

		<?php if ( \SatoriManifest\Clients\Client_Role::is_client() ) : ?>
			<section class="sm-panel sm-contact-panel" id="sm-contact">
				<h2 class="sm-panel__title"><?php esc_html_e( 'Need a hand?', 'satori-manifest' ); ?></h2>
				<p class="sm-panel__intro">
					<?php esc_html_e( 'Raise a support ticket. We reply in your dashboard and email you when we do.', 'satori-manifest' ); ?>
					<a href="<?php echo esc_url( \SatoriManifest\Support\Admin\Client_Tickets_Screen::url() ); ?>"><?php esc_html_e( 'Your tickets', 'satori-manifest' ); ?></a>
				</p>
				<?php
				$satori_manifest_form_action = \SatoriManifest\Support\Admin\Client_Tickets_Screen::ACTION;
				require SATORI_MANIFEST_PATH . 'templates/support/new-ticket.php';
				?>
			</section>
		<?php endif; ?>
	<?php endif; ?>
</div>
