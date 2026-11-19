<?php
/**
 * Template: contact form block.
 *
 * Receives from Contact_Form::render():
 *   $satori_manifest_result, $satori_manifest_wrapper, $satori_manifest_user,
 *   $satori_manifest_draft (what the visitor typed before a failed send).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SatoriManifest\Contact\Contact_Form;

$satori_manifest_messages = Contact_Form::messages();
$satori_manifest_message  = $satori_manifest_messages[ $satori_manifest_result ] ?? null;
$satori_manifest_return   = remove_query_arg( Contact_Form::RESULT_VAR, (string) get_permalink() );
?>
<div <?php echo $satori_manifest_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by get_block_wrapper_attributes(). ?> id="sm-contact">
	<?php if ( $satori_manifest_message ) : ?>
		<div class="sm-form-notice sm-form-notice--<?php echo esc_attr( $satori_manifest_message['type'] ); ?>" role="status">
			<?php echo esc_html( $satori_manifest_message['text'] ); ?>
		</div>
	<?php endif; ?>

	<?php if ( 'sent' !== $satori_manifest_result ) : ?>
		<form class="sm-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( Contact_Form::ACTION ); ?>" />
			<?php \SatoriManifest\Core\Security::output_form_nonce( Contact_Form::ACTION, false ); ?>
			<input type="hidden" name="_sm_started" value="<?php echo esc_attr( (string) time() ); ?>" />
			<input type="hidden" name="_sm_return" value="<?php echo esc_url( $satori_manifest_return ); ?>" />

			<?php // Honeypot: hidden from people, irresistible to bots. ?>
			<p class="sm-form__trap" aria-hidden="true">
				<label for="sm-contact-company-url"><?php esc_html_e( 'Leave this empty', 'satori-manifest' ); ?></label>
				<input type="text" id="sm-contact-company-url" name="sm_company_url" tabindex="-1" autocomplete="off" />
			</p>

			<div class="sm-form__grid">
				<p class="sm-form__field">
					<label for="sm-contact-name"><?php esc_html_e( 'Your name', 'satori-manifest' ); ?> <span aria-hidden="true">*</span></label>
					<input type="text" id="sm-contact-name" name="name" required autocomplete="name" value="<?php echo esc_attr( $satori_manifest_user->exists() ? $satori_manifest_user->display_name : $satori_manifest_draft['name'] ); ?>" />
				</p>
				<p class="sm-form__field">
					<label for="sm-contact-email"><?php esc_html_e( 'Email', 'satori-manifest' ); ?> <span aria-hidden="true">*</span></label>
					<input type="email" id="sm-contact-email" name="email" required autocomplete="email" value="<?php echo esc_attr( $satori_manifest_user->exists() ? $satori_manifest_user->user_email : $satori_manifest_draft['email'] ); ?>" />
				</p>
			</div>

			<p class="sm-form__field sm-form__field--full">
				<label for="sm-contact-message"><?php esc_html_e( 'How can we help?', 'satori-manifest' ); ?> <span aria-hidden="true">*</span></label>
				<textarea id="sm-contact-message" name="message" rows="6" required maxlength="5000"><?php echo esc_textarea( $satori_manifest_draft['message'] ); ?></textarea>
			</p>

			<p class="sm-form__note"><?php esc_html_e( 'We only use your details to reply to you.', 'satori-manifest' ); ?></p>

			<?php \SatoriManifest\Hardening\Turnstile::render(); ?>
			<button type="submit" class="sm-button sm-button--large"><?php esc_html_e( 'Send message', 'satori-manifest' ); ?></button>
		</form>
	<?php endif; ?>
</div>
