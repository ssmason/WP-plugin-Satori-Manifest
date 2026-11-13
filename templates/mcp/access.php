<?php
/**
 * View: Manifest → AI assistant (administrators).
 *
 * Receives from Access_Screen::render():
 *   $satori_manifest_rows, $satori_manifest_plans, $satori_manifest_modes,
 *   $satori_manifest_action, $satori_manifest_saved.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SatoriManifest\Core\Security;

$satori_manifest_date_fmt = (string) get_option( 'date_format' );
?>
<div class="wrap sm-admin sm-ai-access-screen">
	<header class="sm-page-header">
		<div>
			<p class="sm-eyebrow"><?php esc_html_e( 'Manifest', 'satori-manifest' ); ?></p>
			<h1 class="sm-page-header__title"><?php esc_html_e( 'AI assistant', 'satori-manifest' ); ?></h1>
			<p class="sm-page-header__lead">
				<?php
				echo esc_html(
					array() !== $satori_manifest_plans
						/* translators: %s: plan names. */
						? sprintf( __( 'Included in: %s. Switch it on or off for any client here; that wins over their plan.', 'satori-manifest' ), implode( ', ', $satori_manifest_plans ) )
						: __( 'No plan includes it (ai-assistant.yaml). Switch it on for individual clients here.', 'satori-manifest' )
				);
				?>
			</p>
		</div>
	</header>

	<?php if ( $satori_manifest_saved ) : ?>
		<div class="sm-notice sm-notice--info"><?php esc_html_e( 'Saved. Switching a client off also disconnects their AI app.', 'satori-manifest' ); ?></div>
	<?php endif; ?>

	<section class="sm-panel sm-panel--flush">
		<?php if ( array() === $satori_manifest_rows ) : ?>
			<div class="sm-empty"><p><?php esc_html_e( 'No clients yet.', 'satori-manifest' ); ?></p></div>
		<?php else : ?>
			<table class="sm-table sm-table--compact">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Client', 'satori-manifest' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Plan', 'satori-manifest' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Assistant', 'satori-manifest' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Connected', 'satori-manifest' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Changes', 'satori-manifest' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Setting', 'satori-manifest' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $satori_manifest_rows as $satori_manifest_row ) : ?>
						<tr>
							<td><strong class="sm-table__name"><?php echo esc_html( $satori_manifest_row['name'] ); ?></strong></td>
							<td><?php echo esc_html( $satori_manifest_row['plan'] ); ?></td>
							<td>
								<span class="sm-pill <?php echo $satori_manifest_row['allowed'] ? 'sm-pill--on' : 'sm-pill--off'; ?>">
									<?php echo $satori_manifest_row['allowed'] ? esc_html__( 'Allowed', 'satori-manifest' ) : esc_html__( 'Not included', 'satori-manifest' ); ?>
								</span>
							</td>
							<td>
								<?php
								if ( null === $satori_manifest_row['token'] ) {
									echo '<span class="sm-muted">' . esc_html__( 'No', 'satori-manifest' ) . '</span>';
								} else {
									echo esc_html(
										$satori_manifest_row['token']['last_used']
											/* translators: %s: date. */
											? sprintf( __( 'Yes, last used %s', 'satori-manifest' ), wp_date( $satori_manifest_date_fmt, $satori_manifest_row['token']['last_used'] ) )
											: __( 'Yes, not used yet', 'satori-manifest' )
									);
								}
								?>
							</td>
							<td><?php echo esc_html( (string) $satori_manifest_row['changes'] ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sm-ai-access-form">
									<?php Security::output_form_nonce( $satori_manifest_action ); ?>
									<input type="hidden" name="action" value="<?php echo esc_attr( $satori_manifest_action ); ?>">
									<input type="hidden" name="client" value="<?php echo esc_attr( (string) $satori_manifest_row['id'] ); ?>">
									<label class="screen-reader-text" for="sm-ai-mode-<?php echo esc_attr( (string) $satori_manifest_row['id'] ); ?>"><?php esc_html_e( 'AI assistant setting', 'satori-manifest' ); ?></label>
									<select id="sm-ai-mode-<?php echo esc_attr( (string) $satori_manifest_row['id'] ); ?>" name="mode">
										<?php foreach ( $satori_manifest_modes as $satori_manifest_value => $satori_manifest_label ) : ?>
											<option value="<?php echo esc_attr( $satori_manifest_value ); ?>" <?php selected( $satori_manifest_row['mode'], $satori_manifest_value ); ?>><?php echo esc_html( $satori_manifest_label ); ?></option>
										<?php endforeach; ?>
									</select>
									<button type="submit" class="button button-small"><?php esc_html_e( 'Save', 'satori-manifest' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</section>
</div>
