<?php
/**
 * View: Payments screen (administrators).
 *
 * Receives from Payments_Screen::render():
 *   $satori_manifest_rows, $satori_manifest_payments, $satori_manifest_stats, $satori_manifest_awaiting,
 *   $satori_manifest_stripe, $satori_manifest_plans, $satori_manifest_notice.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SatoriManifest\Admin\Screens\Applications_Screen;
use SatoriManifest\Admin\Screens\Payments_Screen;
use SatoriManifest\Billing\Subscriptions;
use SatoriManifest\Branding\Icons;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Core\Security;
use SatoriManifest\Registration\Plans;

$satori_manifest_date_format = (string) get_option( 'date_format' );
?>
<div class="wrap sm-admin sm-payments-screen">
	<header class="sm-page-header">
		<div>
			<p class="sm-eyebrow"><?php esc_html_e( 'Manifest', 'satori-manifest' ); ?></p>
			<h1 class="sm-page-header__title"><?php esc_html_e( 'Payments', 'satori-manifest' ); ?></h1>
			<p class="sm-page-header__lead"><?php esc_html_e( 'Subscriptions and payments from Stripe. Only administrators can see this screen.', 'satori-manifest' ); ?></p>
		</div>
		<?php if ( '' !== $satori_manifest_stripe['mode'] ) : ?>
			<a class="button" href="<?php echo esc_url( $satori_manifest_stripe['dashboard'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Stripe dashboard', 'satori-manifest' ); ?> ↗</a>
		<?php endif; ?>
	</header>

	<?php if ( in_array( $satori_manifest_notice, array( 'online', 'offline' ), true ) ) : ?>
		<div class="sm-notice sm-notice--success"><?php Icons::render( 'check', 18 ); ?> <?php echo 'online' === $satori_manifest_notice ? esc_html__( 'The page is back online.', 'satori-manifest' ) : esc_html__( 'The page is offline (saved as a draft).', 'satori-manifest' ); ?></div>
	<?php endif; ?>

	<?php if ( '' === $satori_manifest_stripe['mode'] || ! $satori_manifest_stripe['webhook'] ) : ?>
		<section class="sm-panel sm-setup">
			<h2 class="sm-panel__title"><?php esc_html_e( 'Finish connecting Stripe', 'satori-manifest' ); ?></h2>
			<p class="sm-panel__intro"><?php esc_html_e( 'Until Stripe is connected, approving an application creates the account straight away with no payment.', 'satori-manifest' ); ?></p>
			<ol class="sm-steps">
				<li class="<?php echo '' !== $satori_manifest_stripe['mode'] ? 'is-done' : ''; ?>">
					<?php esc_html_e( 'In Stripe, open Developers → API keys and copy the secret key.', 'satori-manifest' ); ?>
					<?php esc_html_e( 'Add it to .env as STRIPE_SECRET_KEY (use the sk_test_ key first).', 'satori-manifest' ); ?>
				</li>
				<li class="<?php echo $satori_manifest_stripe['webhook'] ? 'is-done' : ''; ?>">
					<?php esc_html_e( 'In Developers → Webhooks, add an endpoint for this address:', 'satori-manifest' ); ?>
					<code class="sm-copy"><?php echo esc_html( $satori_manifest_stripe['webhook_url'] ); ?></code>
					<?php esc_html_e( 'Choose the events checkout.session.completed, invoice.paid, invoice.payment_failed, customer.subscription.updated and customer.subscription.deleted, then add its signing secret to .env as STRIPE_WEBHOOK_SECRET.', 'satori-manifest' ); ?>
				</li>
				<li><?php esc_html_e( 'In Settings → Billing → Customer portal, save the portal settings once so clients can manage their card and invoices.', 'satori-manifest' ); ?></li>
			</ol>
		</section>
	<?php endif; ?>

	<div class="sm-stats">
		<div class="sm-stat">
			<span class="sm-stat__label"><?php esc_html_e( 'Active subscriptions', 'satori-manifest' ); ?></span>
			<strong class="sm-stat__value"><?php echo esc_html( number_format_i18n( $satori_manifest_stats['active'] ) ); ?></strong>
		</div>
		<div class="sm-stat">
			<span class="sm-stat__label"><?php esc_html_e( 'Monthly recurring revenue', 'satori-manifest' ); ?></span>
			<strong class="sm-stat__value"><?php echo esc_html( Plans::format( $satori_manifest_stats['mrr'] ) ); ?></strong>
		</div>
		<div class="sm-stat">
			<span class="sm-stat__label"><?php esc_html_e( 'Paid in the last 30 days', 'satori-manifest' ); ?></span>
			<strong class="sm-stat__value">
				<?php
				$satori_manifest_totals = array();
				foreach ( $satori_manifest_stats['last_30'] as $satori_manifest_currency => $satori_manifest_total ) {
					$satori_manifest_totals[] = Plans::format( $satori_manifest_total, $satori_manifest_currency );
				}
				echo esc_html( $satori_manifest_totals ? implode( ' · ', $satori_manifest_totals ) : Plans::format( 0 ) );
				?>
			</strong>
		</div>
		<div class="sm-stat <?php echo $satori_manifest_stats['attention'] ? 'sm-stat--alert' : ''; ?>">
			<span class="sm-stat__label"><?php esc_html_e( 'Need attention', 'satori-manifest' ); ?></span>
			<strong class="sm-stat__value"><?php echo esc_html( number_format_i18n( $satori_manifest_stats['attention'] ) ); ?></strong>
		</div>
	</div>

	<?php if ( $satori_manifest_awaiting ) : ?>
		<section class="sm-panel sm-panel--flush sm-section-gap">
			<h2 class="sm-panel__title sm-panel__title--inset"><?php esc_html_e( 'Approved, waiting for payment', 'satori-manifest' ); ?></h2>
			<table class="sm-table">
				<tbody>
					<?php foreach ( $satori_manifest_awaiting as $satori_manifest_app ) : ?>
						<tr>
							<td>
								<strong class="sm-table__name"><?php echo esc_html( (string) $satori_manifest_app['business_name'] ); ?></strong>
								<span class="sm-table__sub"><?php echo esc_html( (string) $satori_manifest_app['email'] ); ?></span>
							</td>
							<td><?php echo esc_html( Plans::title( (string) $satori_manifest_app['plan'] ) . ' · ' . ( Plans::intervals()[ Plans::normalize_interval( (string) $satori_manifest_app['interval'] ) ] ) ); ?></td>
							<td class="sm-table__actions"><a class="button button-small" href="<?php echo esc_url( Applications_Screen::url( 'awaiting_payment' ) ); ?>"><?php esc_html_e( 'View application', 'satori-manifest' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>

	<section class="sm-panel sm-panel--flush sm-section-gap">
		<h2 class="sm-panel__title sm-panel__title--inset"><?php esc_html_e( 'Subscriptions', 'satori-manifest' ); ?></h2>
		<?php if ( empty( $satori_manifest_rows ) ) : ?>
			<div class="sm-empty sm-empty--inset"><p><?php esc_html_e( 'No subscriptions yet. They appear here as soon as a client pays.', 'satori-manifest' ); ?></p></div>
		<?php else : ?>
			<table class="sm-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Client', 'satori-manifest' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Plan', 'satori-manifest' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'satori-manifest' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Renews', 'satori-manifest' ); ?></th>
						<th scope="col" class="sm-table__actions"><?php esc_html_e( 'Page', 'satori-manifest' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $satori_manifest_rows as $satori_manifest_row ) : ?>
						<?php
						$satori_manifest_sub     = $satori_manifest_row['subscription'];
						$satori_manifest_user    = $satori_manifest_row['user'];
						$satori_manifest_page_id = Client_Repository::page_id( $satori_manifest_user->ID );
						$satori_manifest_live    = $satori_manifest_page_id && 'publish' === get_post_status( $satori_manifest_page_id );
						?>
						<tr>
							<td>
								<strong class="sm-table__name"><?php echo esc_html( $satori_manifest_user->display_name ); ?></strong>
								<span class="sm-table__sub"><?php echo esc_html( $satori_manifest_user->user_email ); ?></span>
							</td>
							<td>
								<?php echo esc_html( Plans::title( $satori_manifest_sub['plan'] ) ); ?>
								<span class="sm-table__sub"><?php echo esc_html( Plans::format( (float) $satori_manifest_sub['amount'], $satori_manifest_sub['currency'] ) . ' ' . Plans::period_label( $satori_manifest_sub['interval'] ) ); ?></span>
							</td>
							<td>
								<span class="sm-pill <?php echo Subscriptions::in_good_standing( $satori_manifest_sub['status'] ) ? 'sm-pill--on' : 'sm-pill--off'; ?>"><?php echo esc_html( Subscriptions::status_label( $satori_manifest_sub['status'] ) ); ?></span>
								<?php if ( $satori_manifest_sub['cancel_at_period_end'] ) : ?>
									<span class="sm-table__sub"><?php esc_html_e( 'Cancels at period end', 'satori-manifest' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo $satori_manifest_sub['current_period_end'] ? esc_html( wp_date( $satori_manifest_date_format, (int) $satori_manifest_sub['current_period_end'] ) ) : '—'; ?></td>
							<td class="sm-table__actions">
								<?php if ( $satori_manifest_page_id ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="<?php echo esc_attr( Payments_Screen::ACTION ); ?>" />
										<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $satori_manifest_user->ID ); ?>" />
										<input type="hidden" name="online" value="<?php echo $satori_manifest_live ? '' : '1'; ?>" />
										<?php Security::output_form_nonce( Payments_Screen::ACTION ); ?>
										<span class="sm-status <?php echo $satori_manifest_live ? 'is-live' : 'is-draft'; ?>"><?php echo $satori_manifest_live ? esc_html__( 'Online', 'satori-manifest' ) : esc_html__( 'Offline', 'satori-manifest' ); ?></span>
										<button type="submit" class="button button-small"><?php echo $satori_manifest_live ? esc_html__( 'Take offline', 'satori-manifest' ) : esc_html__( 'Put online', 'satori-manifest' ); ?></button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</section>

	<section class="sm-panel sm-panel--flush sm-section-gap">
		<h2 class="sm-panel__title sm-panel__title--inset"><?php esc_html_e( 'Recent payments', 'satori-manifest' ); ?></h2>
		<?php if ( empty( $satori_manifest_payments ) ) : ?>
			<div class="sm-empty sm-empty--inset"><p><?php esc_html_e( 'No payments recorded yet.', 'satori-manifest' ); ?></p></div>
		<?php else : ?>
			<table class="sm-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Date', 'satori-manifest' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Client', 'satori-manifest' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Amount', 'satori-manifest' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'satori-manifest' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Invoice', 'satori-manifest' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $satori_manifest_payments as $satori_manifest_payment ) : ?>
						<?php $satori_manifest_payer = get_userdata( $satori_manifest_payment['user_id'] ); ?>
						<tr>
							<td><?php echo esc_html( mysql2date( $satori_manifest_date_format, $satori_manifest_payment['date'] ) ); ?></td>
							<td>
								<?php echo esc_html( $satori_manifest_payer ? $satori_manifest_payer->display_name : '—' ); ?>
								<?php if ( '' !== $satori_manifest_payment['description'] ) : ?>
									<span class="sm-table__sub"><?php echo esc_html( $satori_manifest_payment['description'] ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( Plans::format( $satori_manifest_payment['amount'], $satori_manifest_payment['currency'] ) ); ?></td>
							<td>
								<span class="sm-pill <?php echo 'paid' === $satori_manifest_payment['status'] ? 'sm-pill--on' : 'sm-pill--off'; ?>"><?php echo 'paid' === $satori_manifest_payment['status'] ? esc_html__( 'Paid', 'satori-manifest' ) : esc_html__( 'Failed', 'satori-manifest' ); ?></span>
							</td>
							<td class="sm-table__actions">
								<?php if ( '' !== $satori_manifest_payment['invoice_url'] ) : ?>
									<a href="<?php echo esc_url( $satori_manifest_payment['invoice_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Invoice', 'satori-manifest' ); ?> ↗</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</section>

	<section class="sm-panel sm-section-gap">
		<h2 class="sm-panel__title"><?php esc_html_e( 'Plans', 'satori-manifest' ); ?></h2>
		<p class="sm-panel__intro">
			<?php
			/* translators: %s: file path. */
			printf( esc_html__( 'Prices come from %s. Change them there; existing subscribers keep their price until they change plan.', 'satori-manifest' ), '<code>config/plans.yaml</code>' );
			?>
		</p>
		<table class="sm-table sm-table--compact">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Plan', 'satori-manifest' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Monthly', 'satori-manifest' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Yearly', 'satori-manifest' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $satori_manifest_plans as $satori_manifest_slug => $satori_manifest_plan ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $satori_manifest_plan['title'] ); ?></strong></td>
						<td><?php echo $satori_manifest_plan['prices']['month'] ? esc_html( Plans::format( $satori_manifest_plan['prices']['month'] ) ) : '—'; ?></td>
						<td>
							<?php echo $satori_manifest_plan['prices']['year'] ? esc_html( Plans::format( $satori_manifest_plan['prices']['year'] ) ) : '—'; ?>
							<?php if ( Plans::yearly_saving( $satori_manifest_slug ) > 0 ) : ?>
								<span class="sm-table__sub">
									<?php
									/* translators: %s: amount saved. */
									printf( esc_html__( 'saves %s', 'satori-manifest' ), esc_html( Plans::format( Plans::yearly_saving( $satori_manifest_slug ) ) ) );
									?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</section>
</div>
