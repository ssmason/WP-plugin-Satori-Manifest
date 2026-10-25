<?php
/**
 * Template: registration form block.
 *
 * Receives from Registration_Form::render():
 *   $satori_manifest_result, $satori_manifest_fields, $satori_manifest_types,
 *   $satori_manifest_designs, $satori_manifest_plans, $satori_manifest_plan, $satori_manifest_wrapper.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SatoriManifest\Registration_Form;

$satori_manifest_messages = Registration_Form::messages();
$satori_manifest_message  = $satori_manifest_messages[ $satori_manifest_result ] ?? null;
$satori_manifest_done     = 'received' === $satori_manifest_result;
$satori_manifest_return   = remove_query_arg( Registration_Form::RESULT_VAR, (string) get_permalink() );
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
			<input type="hidden" name="_sm_nonce" value="<?php echo esc_attr( wp_create_nonce( Registration_Form::ACTION ) ); ?>" />
			<input type="hidden" name="_sm_started" value="<?php echo esc_attr( (string) time() ); ?>" />
			<input type="hidden" name="_sm_return" value="<?php echo esc_url( $satori_manifest_return ); ?>" />

			<?php // Honeypot — hidden from people, irresistible to bots. ?>
			<p class="sm-form__trap" aria-hidden="true">
				<label for="sm-company-url"><?php esc_html_e( 'Leave this empty', 'satori-manifest' ); ?></label>
				<input type="text" id="sm-company-url" name="sm_company_url" tabindex="-1" autocomplete="off" />
			</p>

			<?php if ( ! empty( $satori_manifest_plans ) ) : ?>
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
									<span class="sm-plan__check" aria-hidden="true"><?php \SatoriManifest\Icons::render( 'check', 16 ); ?></span>
								</span>
								<span class="sm-plan__tagline"><?php echo esc_html( $satori_manifest_plan_data['tagline'] ); ?></span>
								<span class="sm-plan__price">
									<strong><?php echo esc_html( $satori_manifest_plan_data['price'] ); ?></strong>
									<?php echo esc_html( $satori_manifest_plan_data['period'] ); ?>
								</span>
								<span class="sm-plan__features">
									<?php foreach ( $satori_manifest_plan_data['features'] as $satori_manifest_feature ) : ?>
										<span class="sm-plan__feature"><?php \SatoriManifest\Icons::render( 'check', 16 ); ?><?php echo esc_html( $satori_manifest_feature ); ?></span>
									<?php endforeach; ?>
								</span>
							</span>
						</label>
					<?php endforeach; ?>
				</fieldset>
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

			<fieldset class="sm-form__designs">
				<legend><?php echo esc_html( $satori_manifest_fields['design'] ); ?> <span class="sm-form__hint"><?php esc_html_e( '— you can change it any time', 'satori-manifest' ); ?></span></legend>
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

			<button type="submit" class="sm-button sm-button--large"><?php esc_html_e( 'Send my application', 'satori-manifest' ); ?></button>
		</form>
	<?php endif; ?>
</div>
