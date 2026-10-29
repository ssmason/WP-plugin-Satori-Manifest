<?php
/**
 * View: Billing screen (clients).
 *
 * Receives from Billing_Screen::render():
 *   $satori_manifest_subscription, $satori_manifest_payments, $satori_manifest_support, $satori_manifest_error.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SatoriManifest\Billing\Billing_Portal;
use SatoriManifest\Billing\Subscriptions;
use SatoriManifest\Core\Security;
use SatoriManifest\Registration\Plans;

$satori_manifest_sub      = $satori_manifest_subscription;
$satori_manifest_has_sub  = '' !== $satori_manifest_sub['customer'];
$satori_manifest_good     = Subscriptions::in_good_standing( $satori_manifest_sub['status'] );
$satori_manifest_date_fmt = (string) get_option( 'date_format' );
$satori_manifest_renewal  = $satori_manifest_sub['current_period_end'] ? wp_date( $satori_manifest_date_fmt, (int) $satori_manifest_sub['current_period_end'] ) : '';
$satori_manifest_mailto   = 'mailto:' . $satori_manifest_support;
?>
<div class="wrap sm-admin sm-billing-screen">
	<header class="sm-page-header">
		<div>
			<p class="sm-eyebrow"><?php esc_html_e( 'Your account', 'satori-manifest' ); ?></p>
			<h1 class="sm-page-header__title"><?php esc_html_e( 'Billing', 'satori-manifest' ); ?></h1>
			<p class="sm-page-header__lead"><?php esc_html_e( 'Your plan, payments and invoices.', 'satori-manifest' ); ?></p>
		</div>
	</header>

	<?php if ( '' !== $satori_manifest_error ) : ?>
		<div class="sm-notice sm-notice--warning">
			<?php
			echo 'no-subscription' === $satori_manifest_error
				? esc_html__( 'There is no subscription on this account yet.', 'satori-manifest' )
				: esc_html__( 'We could not open the billing portal just now. Please try again in a minute.', 'satori-manifest' );
			?>
		</div>
	<?php endif; ?>

	<?php if ( ! $satori_manifest_has_sub ) : ?>
		<section class="sm-panel">
			<h2 class="sm-panel__title"><?php esc_html_e( 'No online subscription', 'satori-manifest' ); ?></h2>
			<p class="sm-panel__intro">
				<?php esc_html_e( 'Your account was set up by our team, so there is nothing to manage here. Questions about your plan?', 'satori-manifest' ); ?>
				<a href="<?php echo esc_url( $satori_manifest_mailto ); ?>"><?php echo esc_html( $satori_manifest_support ); ?></a>
			</p>
		</section>
	<?php else : ?>
		<div class="sm-two-col">
			<section class="sm-panel sm-plan-card">
				<p class="sm-plan-card__label"><?php esc_html_e( 'Your plan', 'satori-manifest' ); ?></p>
				<h2 class="sm-plan-card__title"><?php echo esc_html( Plans::title( $satori_manifest_sub['plan'] ) ); ?></h2>
				<p class="sm-plan-card__price">
					<strong><?php echo esc_html( Plans::format( (float) $satori_manifest_sub['amount'], $satori_manifest_sub['currency'] ) ); ?></strong>
					<?php echo esc_html( Plans::period_label( $satori_manifest_sub['interval'] ) ); ?>
				</p>

				<p>
					<span class="sm-pill <?php echo $satori_manifest_good ? 'sm-pill--on' : 'sm-pill--off'; ?>"><?php echo esc_html( Subscriptions::status_label( $satori_manifest_sub['status'] ) ); ?></span>
				</p>

				<?php if ( '' !== $satori_manifest_renewal ) : ?>
					<p class="sm-plan-card__meta">
						<?php
						echo esc_html(
							$satori_manifest_sub['cancel_at_period_end']
								/* translators: %s: date. */
								? sprintf( __( 'Your plan ends on %s and will not renew.', 'satori-manifest' ), $satori_manifest_renewal )
								/* translators: %s: date. */
								: sprintf( __( 'Next payment on %s.', 'satori-manifest' ), $satori_manifest_renewal )
						);
						?>
					</p>
				<?php endif; ?>

				<?php if ( ! $satori_manifest_good && 'canceled' !== $satori_manifest_sub['status'] ) : ?>
					<div class="sm-notice sm-notice--warning"><?php esc_html_e( 'Your last payment did not go through. Please update your card so your page stays online.', 'satori-manifest' ); ?></div>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( Billing_Portal::ACTION ); ?>" />
					<?php Security::output_form_nonce( Billing_Portal::ACTION ); ?>
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Manage billing', 'satori-manifest' ); ?></button>
				</form>
				<p class="sm-field__hint"><?php esc_html_e( 'Update your card, switch between monthly and yearly, download invoices or cancel — on our secure payment provider, Stripe.', 'satori-manifest' ); ?></p>
			</section>

			<section class="sm-panel sm-panel--flush">
				<h2 class="sm-panel__title sm-panel__title--inset"><?php esc_html_e( 'Payments', 'satori-manifest' ); ?></h2>
				<?php if ( empty( $satori_manifest_payments ) ) : ?>
					<div class="sm-empty sm-empty--inset"><p><?php esc_html_e( 'Your payments will appear here.', 'satori-manifest' ); ?></p></div>
				<?php else : ?>
					<table class="sm-table">
						<tbody>
							<?php foreach ( $satori_manifest_payments as $satori_manifest_payment ) : ?>
								<tr>
									<td><?php echo esc_html( mysql2date( $satori_manifest_date_fmt, $satori_manifest_payment['date'] ) ); ?></td>
									<td><?php echo esc_html( Plans::format( $satori_manifest_payment['amount'], $satori_manifest_payment['currency'] ) ); ?></td>
									<td><span class="sm-pill <?php echo 'paid' === $satori_manifest_payment['status'] ? 'sm-pill--on' : 'sm-pill--off'; ?>"><?php echo 'paid' === $satori_manifest_payment['status'] ? esc_html__( 'Paid', 'satori-manifest' ) : esc_html__( 'Failed', 'satori-manifest' ); ?></span></td>
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
		</div>
	<?php endif; ?>
</div>
