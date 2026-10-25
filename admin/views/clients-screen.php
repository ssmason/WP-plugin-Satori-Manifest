<?php
/**
 * View: Clients screen (staff).
 *
 * Receives from Clients_Screen::render():
 *   $satori_manifest_rows, $satori_manifest_designs, $satori_manifest_notice, $satori_manifest_error.
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
use SatoriManifest\Clients_Screen;
use SatoriManifest\Icons;
use SatoriManifest\Security;
?>
<div class="wrap sm-admin sm-clients-screen">
	<header class="sm-page-header">
		<div>
			<p class="sm-eyebrow"><?php esc_html_e( 'Satori Manifest', 'satori-manifest' ); ?></p>
			<h1 class="sm-page-header__title"><?php esc_html_e( 'Clients', 'satori-manifest' ); ?></h1>
			<p class="sm-page-header__lead"><?php esc_html_e( 'Each client has one login, one page and one price list.', 'satori-manifest' ); ?></p>
		</div>
	</header>

	<?php if ( 'added' === $satori_manifest_notice ) : ?>
		<div class="sm-notice sm-notice--success"><?php Icons::render( 'check', 18 ); ?> <?php esc_html_e( 'Client added. Their page, price list and menu are ready.', 'satori-manifest' ); ?></div>
	<?php endif; ?>
	<?php if ( '' !== $satori_manifest_error ) : ?>
		<div class="sm-notice sm-notice--warning"><?php echo esc_html( $satori_manifest_error ); ?></div>
	<?php endif; ?>

	<div class="sm-clients-layout">
		<section class="sm-panel sm-panel--flush">
			<?php if ( empty( $satori_manifest_rows ) ) : ?>
				<div class="sm-empty"><p><?php esc_html_e( 'No clients yet — add your first one.', 'satori-manifest' ); ?></p></div>
			<?php else : ?>
				<table class="sm-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Client', 'satori-manifest' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Design', 'satori-manifest' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Price list', 'satori-manifest' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Navigation', 'satori-manifest' ); ?></th>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'satori-manifest' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $satori_manifest_rows as $satori_manifest_row ) : ?>
							<?php $satori_manifest_uid = $satori_manifest_row['user'] ? (int) $satori_manifest_row['user']->ID : 0; ?>
							<tr>
								<td>
									<strong class="sm-table__name"><?php echo esc_html( $satori_manifest_row['brand']['name'] ?? $satori_manifest_row['user']->display_name ); ?></strong>
									<span class="sm-table__sub">
										<?php echo esc_html( $satori_manifest_row['user'] ? $satori_manifest_row['user']->user_login . ' · ' . $satori_manifest_row['user']->user_email : '' ); ?>
									</span>
									<?php if ( $satori_manifest_row['page_url'] ) : ?>
										<a class="sm-table__url" href="<?php echo esc_url( $satori_manifest_row['page_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $satori_manifest_row['page_url'], PHP_URL_PATH ) ); ?></a>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $satori_manifest_row['design']['title'] ?? '—' ); ?></td>
								<td>
									<?php
									/* translators: 1: sections, 2: items. */
									printf( esc_html__( '%1$d sections · %2$d items', 'satori-manifest' ), (int) $satori_manifest_row['sections'], (int) $satori_manifest_row['items'] );
									?>
								</td>
								<td>
									<?php
									echo $satori_manifest_row['menu_items']
										/* translators: %d: number of links. */
										? esc_html( sprintf( _n( '%d link', '%d links', (int) $satori_manifest_row['menu_items'], 'satori-manifest' ), (int) $satori_manifest_row['menu_items'] ) )
										: '<span class="sm-muted">' . esc_html__( 'Standalone', 'satori-manifest' ) . '</span>';
									?>
								</td>
								<td class="sm-table__actions">
									<?php if ( $satori_manifest_row['page_id'] ) : ?>
										<a class="button button-small" href="<?php echo esc_url( admin_url( 'post.php?post=' . $satori_manifest_row['page_id'] . '&action=edit' ) ); ?>"><?php esc_html_e( 'Page', 'satori-manifest' ); ?></a>
									<?php endif; ?>
									<?php if ( $satori_manifest_row['manifest_id'] ) : ?>
										<a class="button button-small" href="<?php echo esc_url( admin_url( 'post.php?post=' . $satori_manifest_row['manifest_id'] . '&action=edit' ) ); ?>"><?php esc_html_e( 'Prices', 'satori-manifest' ); ?></a>
									<?php endif; ?>
									<a class="button button-small" href="<?php echo esc_url( Admin_Menu::url( Admin_Menu::DESIGN_SLUG, $satori_manifest_uid ) ); ?>"><?php esc_html_e( 'Design', 'satori-manifest' ); ?></a>
									<a class="button button-small" href="<?php echo esc_url( Admin_Menu::url( Admin_Menu::BRANDING_SLUG, $satori_manifest_uid ) ); ?>"><?php esc_html_e( 'Branding', 'satori-manifest' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>

		<?php if ( current_user_can( 'create_users' ) ) : ?>
			<section class="sm-panel sm-add-client">
				<h2 class="sm-panel__title"><?php esc_html_e( 'Add a client', 'satori-manifest' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( Clients_Screen::ACTION ); ?>" />
					<?php Security::output_form_nonce( Clients_Screen::ACTION ); ?>

					<p class="sm-field">
						<label for="sm-business-name"><?php esc_html_e( 'Business name', 'satori-manifest' ); ?></label>
						<input id="sm-business-name" name="business_name" type="text" class="regular-text" required />
					</p>
					<p class="sm-field">
						<label for="sm-email"><?php esc_html_e( 'Email', 'satori-manifest' ); ?></label>
						<input id="sm-email" name="email" type="email" class="regular-text" required />
					</p>
					<p class="sm-field">
						<label for="sm-username"><?php esc_html_e( 'Username', 'satori-manifest' ); ?> <span class="sm-muted"><?php esc_html_e( '(optional)', 'satori-manifest' ); ?></span></label>
						<input id="sm-username" name="username" type="text" class="regular-text" autocomplete="off" />
					</p>
					<p class="sm-field">
						<label for="sm-design"><?php esc_html_e( 'Starting design', 'satori-manifest' ); ?></label>
						<select id="sm-design" name="design">
							<?php foreach ( $satori_manifest_designs as $satori_manifest_slug => $satori_manifest_design ) : ?>
								<option value="<?php echo esc_attr( $satori_manifest_slug ); ?>"><?php echo esc_html( $satori_manifest_design['title'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p class="sm-field sm-field--inline">
						<input id="sm-notify" name="notify" type="checkbox" value="1" checked />
						<label for="sm-notify"><?php esc_html_e( 'Email the client a link to set their password', 'satori-manifest' ); ?></label>
					</p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Add client', 'satori-manifest' ); ?></button>
				</form>
			</section>
		<?php endif; ?>
	</div>
</div>
