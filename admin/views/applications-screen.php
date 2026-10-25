<?php
/**
 * View: Applications screen.
 *
 * Receives from Applications_Screen::render():
 *   $satori_manifest_status, $satori_manifest_notice, $satori_manifest_error,
 *   $satori_manifest_apps, $satori_manifest_counts, $satori_manifest_types, $satori_manifest_designs.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SatoriManifest\Admin_Menu;
use SatoriManifest\Applications_Screen;
use SatoriManifest\Client_Repository;
use SatoriManifest\Icons;
use SatoriManifest\Mailer;
use SatoriManifest\Security;

$satori_manifest_tabs = array(
	'pending'  => __( 'Pending', 'satori-manifest' ),
	'approved' => __( 'Approved', 'satori-manifest' ),
	'declined' => __( 'Declined', 'satori-manifest' ),
	'all'      => __( 'All', 'satori-manifest' ),
);

$satori_manifest_notices = array(
	'approved' => __( 'Approved. The client account, page and price list are ready, and the welcome email has been sent (see the email log on the application).', 'satori-manifest' ),
	'declined' => __( 'Application declined.', 'satori-manifest' ),
	'deleted'  => __( 'Application deleted.', 'satori-manifest' ),
);
?>
<div class="wrap sm-admin sm-applications-screen">
	<header class="sm-page-header">
		<div>
			<p class="sm-eyebrow"><?php esc_html_e( 'Satori Manifest', 'satori-manifest' ); ?></p>
			<h1 class="sm-page-header__title"><?php esc_html_e( 'Applications', 'satori-manifest' ); ?></h1>
			<p class="sm-page-header__lead"><?php esc_html_e( 'People who registered on the website. Approving creates their login, page and price list and emails them how to sign in.', 'satori-manifest' ); ?></p>
		</div>
		<span class="sm-badge sm-badge--neutral">
			<?php
			/* translators: %s: transport name. */
			printf( esc_html__( 'Email via %s', 'satori-manifest' ), esc_html( 'brevo' === Mailer::transport() ? 'Brevo' : 'wp_mail' ) );
			?>
		</span>
	</header>

	<?php if ( isset( $satori_manifest_notices[ $satori_manifest_notice ] ) ) : ?>
		<div class="sm-notice sm-notice--success"><?php Icons::render( 'check', 18 ); ?> <?php echo esc_html( $satori_manifest_notices[ $satori_manifest_notice ] ); ?></div>
	<?php endif; ?>
	<?php if ( '' !== $satori_manifest_error ) : ?>
		<div class="sm-notice sm-notice--warning"><?php echo esc_html( $satori_manifest_error ); ?></div>
	<?php endif; ?>

	<nav class="sm-tabs" aria-label="<?php esc_attr_e( 'Filter applications', 'satori-manifest' ); ?>">
		<?php foreach ( $satori_manifest_tabs as $satori_manifest_key => $satori_manifest_label ) : ?>
			<a class="sm-tabs__tab<?php echo esc_attr( $satori_manifest_key === $satori_manifest_status ? ' is-active' : '' ); ?>" href="<?php echo esc_url( Applications_Screen::url( $satori_manifest_key ) ); ?>">
				<?php echo esc_html( $satori_manifest_label ); ?>
				<?php if ( isset( $satori_manifest_counts[ $satori_manifest_key ] ) ) : ?>
					<span class="sm-tabs__count"><?php echo esc_html( (string) $satori_manifest_counts[ $satori_manifest_key ] ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( empty( $satori_manifest_apps ) ) : ?>
		<div class="sm-empty">
			<span class="dashicons dashicons-id-alt" aria-hidden="true"></span>
			<p><?php esc_html_e( 'Nothing here yet. New registrations from the website appear in Pending.', 'satori-manifest' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="sm-app-list">
		<?php foreach ( $satori_manifest_apps as $satori_manifest_app ) : ?>
			<?php
			$satori_manifest_id     = (int) $satori_manifest_app['id'];
			$satori_manifest_design = $satori_manifest_designs[ $satori_manifest_app['design'] ] ?? null;
			?>
			<article class="sm-app sm-app--<?php echo esc_attr( (string) $satori_manifest_app['status'] ); ?>">
				<header class="sm-app__head">
					<div>
						<h2 class="sm-app__title"><?php echo esc_html( (string) $satori_manifest_app['business_name'] ); ?></h2>
						<p class="sm-app__meta">
							<?php echo esc_html( (string) $satori_manifest_app['contact_name'] ); ?> ·
							<a href="<?php echo esc_url( 'mailto:' . $satori_manifest_app['email'] ); ?>"><?php echo esc_html( (string) $satori_manifest_app['email'] ); ?></a>
							<?php if ( '' !== $satori_manifest_app['phone'] ) : ?>
								· <?php echo esc_html( (string) $satori_manifest_app['phone'] ); ?>
							<?php endif; ?>
						</p>
					</div>
					<div class="sm-app__status">
						<span class="sm-pill sm-pill--<?php echo esc_attr( (string) $satori_manifest_app['status'] ); ?>"><?php echo esc_html( ucfirst( (string) $satori_manifest_app['status'] ) ); ?></span>
						<time datetime="<?php echo esc_attr( (string) $satori_manifest_app['date'] ); ?>"><?php echo esc_html( human_time_diff( (int) strtotime( (string) $satori_manifest_app['date'] ) ) . ' ' . __( 'ago', 'satori-manifest' ) ); ?></time>
					</div>
				</header>

				<dl class="sm-app__details">
					<div><dt><?php esc_html_e( 'Plan', 'satori-manifest' ); ?></dt><dd><strong><?php echo esc_html( '' !== $satori_manifest_app['plan'] ? \SatoriManifest\Applications::plan_title( (string) $satori_manifest_app['plan'] ) : '—' ); ?></strong></dd></div>
					<div><dt><?php esc_html_e( 'Type', 'satori-manifest' ); ?></dt><dd><?php echo esc_html( $satori_manifest_types[ $satori_manifest_app['business_type'] ] ?? '—' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Preferred design', 'satori-manifest' ); ?></dt><dd><?php echo esc_html( $satori_manifest_design ? $satori_manifest_design['title'] : __( 'No preference', 'satori-manifest' ) ); ?></dd></div>
					<div>
						<dt><?php esc_html_e( 'Website', 'satori-manifest' ); ?></dt>
						<dd>
							<?php if ( '' !== $satori_manifest_app['website'] ) : ?>
								<a href="<?php echo esc_url( (string) $satori_manifest_app['website'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) wp_parse_url( (string) $satori_manifest_app['website'], PHP_URL_HOST ) ); ?></a>
							<?php else : ?>
								<?php esc_html_e( 'None — standalone page', 'satori-manifest' ); ?>
							<?php endif; ?>
						</dd>
					</div>
					<?php if ( '' !== $satori_manifest_app['message'] ) : ?>
						<div class="sm-app__message"><dt><?php esc_html_e( 'Message', 'satori-manifest' ); ?></dt><dd><?php echo wp_kses( nl2br( esc_html( (string) $satori_manifest_app['message'] ) ), array( 'br' => array() ) ); ?></dd></div>
					<?php endif; ?>
				</dl>

				<?php if ( 'pending' === $satori_manifest_app['status'] ) : ?>
					<div class="sm-app__actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sm-app__approve">
							<input type="hidden" name="action" value="<?php echo esc_attr( Applications_Screen::ACTION ); ?>" />
							<input type="hidden" name="decision" value="approve" />
							<input type="hidden" name="application_id" value="<?php echo esc_attr( (string) $satori_manifest_id ); ?>" />
							<?php Security::output_form_nonce( Applications_Screen::ACTION, false ); ?>
							<label>
								<span><?php esc_html_e( 'Username', 'satori-manifest' ); ?></span>
								<input type="text" name="username" value="<?php echo esc_attr( Applications_Screen::suggest_username( (string) $satori_manifest_app['business_name'] ) ); ?>" required />
							</label>
							<label>
								<span><?php esc_html_e( 'Design', 'satori-manifest' ); ?></span>
								<select name="design">
									<?php foreach ( $satori_manifest_designs as $satori_manifest_slug => $satori_manifest_d ) : ?>
										<option value="<?php echo esc_attr( $satori_manifest_slug ); ?>" <?php selected( $satori_manifest_slug, $satori_manifest_app['design'] ); ?>><?php echo esc_html( $satori_manifest_d['title'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label class="sm-app__check">
								<input type="checkbox" name="notify" value="1" checked />
								<span><?php esc_html_e( 'Email login details', 'satori-manifest' ); ?></span>
							</label>
							<button type="submit" class="button button-primary"><?php Icons::render( 'check', 16 ); ?> <?php esc_html_e( 'Approve & create account', 'satori-manifest' ); ?></button>
						</form>

						<details class="sm-app__decline">
							<summary><?php esc_html_e( 'Decline…', 'satori-manifest' ); ?></summary>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="<?php echo esc_attr( Applications_Screen::ACTION ); ?>" />
								<input type="hidden" name="decision" value="decline" />
								<input type="hidden" name="application_id" value="<?php echo esc_attr( (string) $satori_manifest_id ); ?>" />
								<?php Security::output_form_nonce( Applications_Screen::ACTION, false ); ?>
								<textarea name="reason" rows="2" placeholder="<?php esc_attr_e( 'Optional note included in the email', 'satori-manifest' ); ?>"></textarea>
								<label class="sm-app__check">
									<input type="checkbox" name="notify" value="1" />
									<span><?php esc_html_e( 'Email the applicant', 'satori-manifest' ); ?></span>
								</label>
								<button type="submit" class="button"><?php esc_html_e( 'Decline application', 'satori-manifest' ); ?></button>
							</form>
						</details>
					</div>
				<?php elseif ( 'approved' === $satori_manifest_app['status'] && $satori_manifest_app['user_id'] ) : ?>
					<?php $satori_manifest_page = Client_Repository::page_id( (int) $satori_manifest_app['user_id'] ); ?>
					<div class="sm-app__actions sm-app__actions--done">
						<?php if ( $satori_manifest_page ) : ?>
							<a class="button" href="<?php echo esc_url( (string) get_permalink( $satori_manifest_page ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View page', 'satori-manifest' ); ?></a>
						<?php endif; ?>
						<a class="button" href="<?php echo esc_url( Admin_Menu::url( Admin_Menu::BRANDING_SLUG, (int) $satori_manifest_app['user_id'] ) ); ?>"><?php esc_html_e( 'Branding', 'satori-manifest' ); ?></a>
						<a class="button" href="<?php echo esc_url( (string) get_edit_user_link( (int) $satori_manifest_app['user_id'] ) ); ?>"><?php esc_html_e( 'User', 'satori-manifest' ); ?></a>
					</div>
				<?php else : ?>
					<div class="sm-app__actions sm-app__actions--done">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( Applications_Screen::ACTION ); ?>" />
							<input type="hidden" name="decision" value="delete" />
							<input type="hidden" name="application_id" value="<?php echo esc_attr( (string) $satori_manifest_id ); ?>" />
							<?php Security::output_form_nonce( Applications_Screen::ACTION, false ); ?>
							<button type="submit" class="button-link sm-link-danger"><?php esc_html_e( 'Delete application', 'satori-manifest' ); ?></button>
						</form>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $satori_manifest_app['emails'] ) ) : ?>
					<details class="sm-app__emails">
						<summary>
							<?php
							/* translators: %d: number of emails. */
							printf( esc_html( _n( '%d email', '%d emails', count( $satori_manifest_app['emails'] ), 'satori-manifest' ) ), count( $satori_manifest_app['emails'] ) );
							?>
						</summary>
						<ul>
							<?php foreach ( $satori_manifest_app['emails'] as $satori_manifest_email ) : ?>
								<li>
									<span class="sm-pill sm-pill--<?php echo esc_attr( ! empty( $satori_manifest_email['sent'] ) ? 'approved' : 'declined' ); ?>"><?php echo ! empty( $satori_manifest_email['sent'] ) ? esc_html__( 'Sent', 'satori-manifest' ) : esc_html__( 'Failed', 'satori-manifest' ); ?></span>
									<strong><?php echo esc_html( (string) ( $satori_manifest_email['subject'] ?? '' ) ); ?></strong>
									→ <?php echo esc_html( (string) ( $satori_manifest_email['to'] ?? '' ) ); ?>
									<span class="sm-muted">(<?php echo esc_html( (string) ( $satori_manifest_email['transport'] ?? '' ) ); ?><?php echo ! empty( $satori_manifest_email['error'] ) ? ' — ' . esc_html( (string) $satori_manifest_email['error'] ) : ''; ?>)</span>
								</li>
							<?php endforeach; ?>
						</ul>
					</details>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
</div>
