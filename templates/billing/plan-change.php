<?php
/**
 * View: "change plan" card (upgrade or downgrade), shown on the Billing screen
 * and, for an upgrade to the AI assistant, on the AI assistant screen.
 *
 * Receives:
 *   $satori_manifest_offer  Plan_Change::offer() result.
 *   $satori_manifest_return Optional: 'ai' to come back to the AI assistant screen.
 *
 * Two steps, no script: the first button opens a confirm box (<details>) that
 * repeats exactly what changes and what is paid; only its button changes the plan.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.15.0
 */

declare( strict_types=1 );

use SatoriManifest\Billing\Plan_Change;
use SatoriManifest\Branding\Icons;
use SatoriManifest\Core\Security;
use SatoriManifest\Registration\Plans;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$satori_manifest_up    = (bool) $satori_manifest_offer['upgrade'];
$satori_manifest_to    = Plans::title( $satori_manifest_offer['to'] );
$satori_manifest_from  = Plans::title( $satori_manifest_offer['from'] );
$satori_manifest_price = Plans::format( $satori_manifest_offer['price'] ) . ' ' . Plans::period_label( $satori_manifest_offer['interval'] );
?>
<section class="sm-panel sm-plan-change sm-plan-change--<?php echo $satori_manifest_up ? 'up' : 'down'; ?>">
	<p class="sm-plan-card__label"><?php echo $satori_manifest_up ? esc_html__( 'Upgrade', 'satori-manifest' ) : esc_html__( 'Change plan', 'satori-manifest' ); ?></p>
	<h2 class="sm-plan-change__title">
		<?php echo esc_html( $satori_manifest_to ); ?>
		<span class="sm-plan-change__price"><?php echo esc_html( $satori_manifest_price ); ?></span>
	</h2>

	<?php if ( $satori_manifest_offer['changes'] ) : ?>
		<p class="sm-plan-change__lead"><?php echo $satori_manifest_up ? esc_html__( 'You get:', 'satori-manifest' ) : esc_html__( 'You would lose:', 'satori-manifest' ); ?></p>
		<ul class="sm-plan-change__list">
			<?php foreach ( $satori_manifest_offer['changes'] as $satori_manifest_change ) : ?>
				<li><?php Icons::render( $satori_manifest_up ? 'check' : 'close', 16 ); ?><span><?php echo esc_html( $satori_manifest_change ); ?></span></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<p class="sm-plan-change__note"><?php echo esc_html( Plan_Change::payment_note( $satori_manifest_offer ) ); ?></p>

	<details class="sm-plan-change__confirm">
		<summary class="button <?php echo $satori_manifest_up ? 'button-primary' : ''; ?>">
			<?php
			/* translators: %s: plan title. */
			echo esc_html( sprintf( $satori_manifest_up ? __( 'Upgrade to %s', 'satori-manifest' ) : __( 'Switch to %s', 'satori-manifest' ), $satori_manifest_to ) );
			?>
		</summary>
		<div class="sm-plan-change__box">
			<p>
				<strong>
					<?php
					/* translators: 1: current plan, 2: new plan, 3: new price and period. */
					echo esc_html( sprintf( __( '%1$s → %2$s, %3$s.', 'satori-manifest' ), $satori_manifest_from, $satori_manifest_to, $satori_manifest_price ) );
					?>
				</strong>
				<?php esc_html_e( 'Your renewal date stays the same.', 'satori-manifest' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( Plan_Change::ACTION ); ?>" />
				<input type="hidden" name="plan" value="<?php echo esc_attr( $satori_manifest_offer['to'] ); ?>" />
				<input type="hidden" name="return" value="<?php echo esc_attr( $satori_manifest_return ?? 'billing' ); ?>" />
				<?php Security::output_form_nonce( Plan_Change::ACTION ); ?>
				<button type="submit" class="button button-primary">
					<?php
					/* translators: %s: plan title. */
					echo esc_html( sprintf( $satori_manifest_up ? __( 'Yes, upgrade to %s', 'satori-manifest' ) : __( 'Yes, switch to %s', 'satori-manifest' ), $satori_manifest_to ) );
					?>
				</button>
			</form>
		</div>
	</details>
</section>
