<?php
/**
 * Template: registration form block.
 *
 * Receives from Registration_Form::render():
 *   $satori_manifest_result, $satori_manifest_fields, $satori_manifest_types,
 *   $satori_manifest_designs, $satori_manifest_plans, $satori_manifest_toggle, $satori_manifest_plan,
 *   $satori_manifest_interval, $satori_manifest_wrapper, $satori_manifest_pay_now.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SatoriManifest\Branding\Icons;
use SatoriManifest\Registration\Plans;
use SatoriManifest\Registration\Registration_Form;

$satori_manifest_messages = Registration_Form::messages();
$satori_manifest_message  = $satori_manifest_messages[ $satori_manifest_result ] ?? null;
$satori_manifest_done     = 'received' === $satori_manifest_result;
$satori_manifest_return   = remove_query_arg( Registration_Form::RESULT_VAR, (string) get_permalink() );

// "2 months free": the smallest yearly saving across plans, in months.
$satori_manifest_months_free = 0;
foreach ( array_keys( $satori_manifest_plans ) as $satori_manifest_plan_slug ) {
	$satori_manifest_monthly     = Plans::price( $satori_manifest_plan_slug, 'month' );
	$satori_manifest_months      = $satori_manifest_monthly > 0 ? (int) floor( Plans::yearly_saving( $satori_manifest_plan_slug ) / $satori_manifest_monthly ) : 0;
	$satori_manifest_months_free = 0 === $satori_manifest_months_free ? $satori_manifest_months : min( $satori_manifest_months_free, $satori_manifest_months );
}
?>
<div <?php echo $satori_manifest_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by get_block_wrapper_attributes(). ?> id="sm-register">
	<?php if ( $satori_manifest_message ) : ?>
		<div class="sm-form-notice sm-form-notice--<?php echo esc_attr( $satori_manifest_message['type'] ); ?>" role="status">
			<?php echo esc_html( $satori_manifest_message['text'] ); ?>
		</div>
	<?php endif; ?>

	<?php if ( ! $satori_manifest_done ) : ?>
		<form class="sm-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
			<input type="hidden" name="action" value="<?php echo esc_attr( Registration_Form::ACTION ); ?>" />
			<?php \SatoriManifest\Core\Security::output_form_nonce( Registration_Form::ACTION, false ); ?>
			<input type="hidden" name="_sm_started" value="<?php echo esc_attr( (string) time() ); ?>" />
			<input type="hidden" name="_sm_return" value="<?php echo esc_url( $satori_manifest_return ); ?>" />

			<?php // Honeypot: hidden from people, irresistible to bots. ?>
			<p class="sm-form__trap" aria-hidden="true">
				<label for="sm-company-url"><?php esc_html_e( 'Leave this empty', 'satori-manifest' ); ?></label>
				<input type="text" id="sm-company-url" name="sm_company_url" tabindex="-1" autocomplete="off" />
			</p>

			<?php if ( ! empty( $satori_manifest_plans ) ) : ?>
				<?php if ( $satori_manifest_toggle ) : ?>
					<fieldset class="sm-billing-toggle">
						<legend class="screen-reader-text"><?php esc_html_e( 'Billing period', 'satori-manifest' ); ?></legend>
						<?php foreach ( Plans::intervals() as $satori_manifest_key => $satori_manifest_label ) : ?>
							<input class="sm-billing-toggle__input" type="radio" id="sm-billing-<?php echo esc_attr( $satori_manifest_key ); ?>" name="billing" value="<?php echo esc_attr( $satori_manifest_key ); ?>" <?php checked( $satori_manifest_key, $satori_manifest_interval ); ?> />
							<label class="sm-billing-toggle__option" for="sm-billing-<?php echo esc_attr( $satori_manifest_key ); ?>">
								<?php echo esc_html( $satori_manifest_label ); ?>
								<?php if ( 'year' === $satori_manifest_key && $satori_manifest_months_free > 0 ) : ?>
									<span class="sm-billing-toggle__save">
										<?php
										/* translators: %d: number of months. */
										echo esc_html( sprintf( _n( '%d month free', '%d months free', $satori_manifest_months_free, 'satori-manifest' ), $satori_manifest_months_free ) );
										?>
									</span>
								<?php endif; ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				<?php else : ?>
					<input type="hidden" name="billing" value="<?php echo esc_attr( $satori_manifest_interval ); ?>" />
				<?php endif; ?>

				<fieldset class="sm-plans">
					<legend class="sm-plans__legend"><?php esc_html_e( 'Choose your plan', 'satori-manifest' ); ?> <span aria-hidden="true">*</span></legend>
					<?php foreach ( $satori_manifest_plans as $satori_manifest_slug => $satori_manifest_plan_data ) : ?>
						<label class="sm-plan">
							<input class="sm-plan__radio" type="radio" name="plan" value="<?php echo esc_attr( $satori_manifest_slug ); ?>" required <?php checked( $satori_manifest_slug, $satori_manifest_plan ); ?> />
							<span class="sm-plan__card">
								<span class="sm-plan__head">
									<span class="sm-plan__title"><?php echo esc_html( $satori_manifest_plan_data['title'] ); ?></span>
									<?php if ( '' !== $satori_manifest_plan_data['badge'] ) : ?>
										<span class="sm-plan__badge"><?php echo esc_html( $satori_manifest_plan_data['badge'] ); ?></span>
									<?php endif; ?>
									<span class="sm-plan__check" aria-hidden="true"><?php Icons::render( 'check', 16 ); ?></span>
								</span>
								<span class="sm-plan__tagline"><?php echo esc_html( $satori_manifest_plan_data['tagline'] ); ?></span>
								<?php foreach ( $satori_manifest_plan_data['prices'] as $satori_manifest_key => $satori_manifest_amount ) : ?>
									<?php
									if ( $satori_manifest_amount <= 0 ) {
										continue;
									}
									?>
									<span class="sm-plan__price sm-plan__price--<?php echo esc_attr( $satori_manifest_key ); ?>">
										<strong><?php echo esc_html( Plans::format( $satori_manifest_amount ) ); ?></strong>
										<?php echo esc_html( Plans::period_label( $satori_manifest_key ) ); ?>
										<?php if ( 'year' === $satori_manifest_key && Plans::yearly_saving( $satori_manifest_slug ) > 0 ) : ?>
											<span class="sm-plan__saving">
												<?php
												/* translators: %s: amount saved per year. */
												echo esc_html( sprintf( __( 'Save %s', 'satori-manifest' ), Plans::format( Plans::yearly_saving( $satori_manifest_slug ) ) ) );
												?>
											</span>
										<?php endif; ?>
									</span>
								<?php endforeach; ?>
								<span class="sm-plan__features">
									<?php foreach ( $satori_manifest_plan_data['features'] as $satori_manifest_feature ) : ?>
										<span class="sm-plan__feature"><?php Icons::render( 'check', 16 ); ?><?php echo esc_html( $satori_manifest_feature ); ?></span>
									<?php endforeach; ?>
								</span>
							</span>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<?php if ( Plans::setup_price() > 0 ) : ?>
					<p class="sm-form__consent sm-form__setup">
						<input type="checkbox" id="sm-setup" name="setup" value="1" />
						<label for="sm-setup">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: setup price. */
									__( 'Set it up for me: we build your page from your menu or price list (%s one-off, paid when you sign up; the free trial still covers your plan).', 'satori-manifest' ),
									Plans::format( Plans::setup_price() )
								)
							);
							?>
						</label>
					</p>
				<?php endif; ?>
				<?php if ( ! $satori_manifest_pay_now ) : ?>
					<p class="sm-form__hint sm-plans__note"><?php esc_html_e( 'Nothing to pay today. We review your application first, then email you a secure payment link.', 'satori-manifest' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>

			<div class="sm-form__grid">
				<p class="sm-form__field">
					<label for="sm-business-name"><?php echo esc_html( $satori_manifest_fields['business_name'] ); ?> <span aria-hidden="true">*</span></label>
					<input type="text" id="sm-business-name" name="business_name" required autocomplete="organization" />
				</p>
				<p class="sm-form__field">
					<label for="sm-contact-name"><?php echo esc_html( $satori_manifest_fields['contact_name'] ); ?> <span aria-hidden="true">*</span></label>
					<input type="text" id="sm-contact-name" name="contact_name" required autocomplete="name" />
				</p>
				<p class="sm-form__field">
					<label for="sm-email"><?php echo esc_html( $satori_manifest_fields['email'] ); ?> <span aria-hidden="true">*</span></label>
					<input type="email" id="sm-email" name="email" required autocomplete="email" />
				</p>
				<p class="sm-form__field">
					<label for="sm-phone"><?php echo esc_html( $satori_manifest_fields['phone'] ); ?></label>
					<input type="tel" id="sm-phone" name="phone" autocomplete="tel" />
				</p>
				<p class="sm-form__field">
					<label for="sm-website"><?php echo esc_html( $satori_manifest_fields['website'] ); ?></label>
					<input type="url" id="sm-website" name="website" placeholder="https://" autocomplete="url" />
				</p>
				<p class="sm-form__field">
					<label for="sm-business-type"><?php echo esc_html( $satori_manifest_fields['business_type'] ); ?></label>
					<select id="sm-business-type" name="business_type">
						<?php foreach ( $satori_manifest_types as $satori_manifest_value => $satori_manifest_label ) : ?>
							<option value="<?php echo esc_attr( $satori_manifest_value ); ?>"><?php echo esc_html( $satori_manifest_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			</div>

			<?php
			$satori_manifest_code_sizes   = \SatoriManifest\Billing\Code_Catalogue::sizes();
			$satori_manifest_code_std     = \SatoriManifest\Billing\Code_Catalogue::standard();
			$satori_manifest_code_regions = \SatoriManifest\Billing\Code_Catalogue::regions();
			?>
			<?php if ( $satori_manifest_code_sizes ) : ?>
				<fieldset class="sm-form__codes">
					<legend><?php echo esc_html( $satori_manifest_fields['codes'] ); ?></legend>
					<p class="sm-form__hint">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: standard size label, e.g. "M – 35 × 46 mm", 2: codes per plan, e.g. "Starter 1, Plus 3". */
								__( 'Your plan includes printed %1$s QR codes (%2$s), posted after your first payment. Want more, or other sizes? Add them below; they are added to that payment.', 'satori-manifest' ),
								$satori_manifest_code_sizes[ $satori_manifest_code_std ]['label'],
								implode(
									', ',
									array_map(
										static fn( string $slug ): string => Plans::title( $slug ) . ' ' . Plans::codes_included( $slug ),
										array_keys( $satori_manifest_plans )
									)
								)
							)
						);
						?>
					</p>
					<details class="sm-form__codes-more">
						<summary><?php esc_html_e( 'Add extra codes', 'satori-manifest' ); ?></summary>
						<div class="sm-codes-grid">
							<?php foreach ( $satori_manifest_code_sizes as $satori_manifest_size => $satori_manifest_size_terms ) : ?>
								<label class="sm-codes-grid__row">
									<span class="sm-codes-grid__label"><?php echo esc_html( $satori_manifest_size_terms['label'] ); ?></span>
									<span class="sm-codes-grid__price"><?php echo esc_html( \SatoriManifest\Registration\Plans::format( $satori_manifest_size_terms['price'] ) ); ?></span>
									<input type="number" name="<?php echo esc_attr( \SatoriManifest\Billing\Printed_Codes::FIELD . '[' . $satori_manifest_size . ']' ); ?>" value="0" min="0" max="<?php echo esc_attr( (string) \SatoriManifest\Billing\Code_Catalogue::max() ); ?>" step="1" inputmode="numeric" />
								</label>
							<?php endforeach; ?>
						</div>
					</details>
					<p class="sm-form__field">
						<label for="sm-ship-region"><?php echo esc_html( $satori_manifest_fields['ship_region'] ); ?></label>
						<select id="sm-ship-region" name="<?php echo esc_attr( \SatoriManifest\Billing\Printed_Codes::REGION_FIELD ); ?>">
							<?php foreach ( $satori_manifest_code_regions as $satori_manifest_region => $satori_manifest_region_terms ) : ?>
								<option value="<?php echo esc_attr( $satori_manifest_region ); ?>">
									<?php
									echo esc_html( \SatoriManifest\Billing\Code_Catalogue::region_label( $satori_manifest_region ) );
									?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>
				</fieldset>
			<?php endif; ?>

			<fieldset class="sm-form__designs">
				<legend><?php echo esc_html( $satori_manifest_fields['design'] ); ?> <span class="sm-form__hint"><?php esc_html_e( '(you can change it any time)', 'satori-manifest' ); ?></span></legend>
				<label class="sm-form__design">
					<input type="radio" name="design" value="" checked />
					<span><?php esc_html_e( 'Not sure yet', 'satori-manifest' ); ?></span>
				</label>
				<?php foreach ( $satori_manifest_designs as $satori_manifest_slug => $satori_manifest_design ) : ?>
					<label class="sm-form__design">
						<input type="radio" name="design" value="<?php echo esc_attr( $satori_manifest_slug ); ?>" />
						<span><?php echo esc_html( $satori_manifest_design['title'] ); ?> <small><?php echo esc_html( $satori_manifest_design['best_for'] ); ?></small></span>
					</label>
				<?php endforeach; ?>
			</fieldset>

			<p class="sm-form__field sm-form__field--full">
				<label for="sm-message"><?php echo esc_html( $satori_manifest_fields['message'] ); ?></label>
				<textarea id="sm-message" name="message" rows="4"></textarea>
			</p>

			<p class="sm-form__consent">
				<input type="checkbox" id="sm-consent" name="consent" value="1" required />
				<label for="sm-consent"><?php esc_html_e( 'I agree to be contacted about my application and for my details to be stored for that purpose.', 'satori-manifest' ); ?></label>
			</p>

			<?php \SatoriManifest\Hardening\Turnstile::render(); ?>
			<?php if ( $satori_manifest_pay_now && \SatoriManifest\Registration\Plans::trial_days() > 0 ) : ?>
				<p class="sm-form__trial">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: number of free days, 2: date of the first payment. */
							__( 'Free for %1$d days. We take your card now but charge nothing until %2$s. Cancel before then and you pay nothing.', 'satori-manifest' ),
							\SatoriManifest\Registration\Plans::trial_days(),
							wp_date( get_option( 'date_format' ), time() + \SatoriManifest\Registration\Plans::trial_days() * DAY_IN_SECONDS )
						)
					);
					?>
				</p>
			<?php endif; ?>
			<button type="submit" class="sm-button sm-button--large">
				<?php
				if ( $satori_manifest_pay_now ) {
					esc_html_e( 'Continue to payment', 'satori-manifest' );
				} else {
					esc_html_e( 'Send my application', 'satori-manifest' );
				}
				?>
			</button>
		</form>
	<?php endif; ?>
</div>
