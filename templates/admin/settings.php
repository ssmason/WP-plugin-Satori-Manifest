<?php
/**
 * View: Settings screen (administrators).
 *
 * Receives from Settings_Screen::render():
 *   $satori_manifest_settings, $satori_manifest_logo, $satori_manifest_status, $satori_manifest_saved.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SatoriManifest\Admin\Screens\Settings_Screen;
use SatoriManifest\Branding\Icons;
use SatoriManifest\Core\Security;

$satori_manifest_s = $satori_manifest_settings;
?>
<div class="wrap sm-admin sm-settings-screen">
	<header class="sm-page-header">
		<div>
			<p class="sm-eyebrow"><?php esc_html_e( 'Manifest', 'satori-manifest' ); ?></p>
			<h1 class="sm-page-header__title"><?php esc_html_e( 'Settings', 'satori-manifest' ); ?></h1>
			<p class="sm-page-header__lead"><?php esc_html_e( 'How the client portal and emails look, and who hears about new sign-ups and payments.', 'satori-manifest' ); ?></p>
		</div>
	</header>

	<?php if ( $satori_manifest_saved ) : ?>
		<div class="sm-notice sm-notice--success"><?php Icons::render( 'check', 18 ); ?> <?php esc_html_e( 'Settings saved.', 'satori-manifest' ); ?></div>
	<?php endif; ?>

	<div class="sm-two-col">
		<form class="sm-panel sm-form-panel" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Screen::ACTION ); ?>" />
			<?php Security::output_form_nonce( Settings_Screen::ACTION ); ?>

			<h2 class="sm-panel__title"><?php esc_html_e( 'Portal branding', 'satori-manifest' ); ?></h2>
			<p class="sm-panel__intro"><?php esc_html_e( 'Used on the login screen, the client dashboard and every email.', 'satori-manifest' ); ?></p>

			<div class="sm-field sm-logo-picker" data-sm-logo-picker>
				<span class="sm-field__label"><?php esc_html_e( 'Logo', 'satori-manifest' ); ?></span>
				<input type="hidden" name="settings[portal_logo_id]" value="<?php echo esc_attr( (string) $satori_manifest_s['portal_logo_id'] ); ?>" data-sm-logo-id />
				<span class="sm-logo-picker__preview" data-sm-logo-preview>
					<?php if ( '' !== $satori_manifest_logo ) : ?>
						<img src="<?php echo esc_url( $satori_manifest_logo ); ?>" alt="" />
					<?php else : ?>
						<span class="sm-muted"><?php esc_html_e( 'Using the site logo', 'satori-manifest' ); ?></span>
					<?php endif; ?>
				</span>
				<span class="sm-logo-picker__actions">
					<button type="button" class="button" data-sm-logo-choose><?php esc_html_e( 'Choose logo', 'satori-manifest' ); ?></button>
					<button type="button" class="button-link" data-sm-logo-clear><?php esc_html_e( 'Use site logo', 'satori-manifest' ); ?></button>
				</span>
			</div>

			<div class="sm-field-row">
				<p class="sm-field">
					<label for="sm-accent"><?php esc_html_e( 'Accent colour', 'satori-manifest' ); ?></label>
					<input id="sm-accent" type="color" name="settings[accent_color]" value="<?php echo esc_attr( (string) $satori_manifest_s['accent_color'] ); ?>" />
					<span class="sm-field__hint"><?php esc_html_e( 'Buttons and highlights.', 'satori-manifest' ); ?></span>
				</p>
				<p class="sm-field">
					<label for="sm-dark"><?php esc_html_e( 'Dark colour', 'satori-manifest' ); ?></label>
					<input id="sm-dark" type="color" name="settings[dark_color]" value="<?php echo esc_attr( (string) $satori_manifest_s['dark_color'] ); ?>" />
					<span class="sm-field__hint"><?php esc_html_e( 'Headings and the email header.', 'satori-manifest' ); ?></span>
				</p>
			</div>

			<h2 class="sm-panel__title sm-panel__title--spaced"><?php esc_html_e( 'Email', 'satori-manifest' ); ?></h2>

			<p class="sm-field">
				<label for="sm-support-email"><?php esc_html_e( 'Support email', 'satori-manifest' ); ?></label>
				<input id="sm-support-email" type="email" class="regular-text" name="settings[support_email]" value="<?php echo esc_attr( (string) $satori_manifest_s['support_email'] ); ?>" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>" />
				<span class="sm-field__hint"><?php esc_html_e( 'Shown to clients on the Help screen and in emails.', 'satori-manifest' ); ?></span>
			</p>

			<p class="sm-field">
				<label for="sm-staff-emails"><?php esc_html_e( 'Notify staff at', 'satori-manifest' ); ?></label>
				<input id="sm-staff-emails" type="text" class="regular-text" name="settings[staff_emails]" value="<?php echo esc_attr( (string) $satori_manifest_s['staff_emails'] ); ?>" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>" />
				<span class="sm-field__hint"><?php esc_html_e( 'New applications and payment events. Separate several addresses with commas.', 'satori-manifest' ); ?></span>
			</p>

			<h2 class="sm-panel__title sm-panel__title--spaced"><?php esc_html_e( 'Approvals', 'satori-manifest' ); ?></h2>

			<p class="sm-field sm-field--inline">
				<input id="sm-require-payment" type="checkbox" name="settings[require_payment]" value="1" <?php checked( (bool) $satori_manifest_s['require_payment'] ); ?> />
				<label for="sm-require-payment"><?php esc_html_e( 'Take payment before creating the account (pre-ticked when approving)', 'satori-manifest' ); ?></label>
			</p>

			<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save settings', 'satori-manifest' ); ?></button>
		</form>

		<aside class="sm-panel sm-status-panel">
			<h2 class="sm-panel__title"><?php esc_html_e( 'Connections', 'satori-manifest' ); ?></h2>
			<p class="sm-panel__intro"><?php esc_html_e( 'Keys and passwords are kept in the server environment (.env), never in the database.', 'satori-manifest' ); ?></p>

			<dl class="sm-status-list">
				<div>
					<dt><?php esc_html_e( 'Stripe payments', 'satori-manifest' ); ?></dt>
					<dd>
						<?php if ( '' === $satori_manifest_status['stripe_mode'] ) : ?>
							<span class="sm-pill sm-pill--off"><?php esc_html_e( 'Not connected', 'satori-manifest' ); ?></span>
							<span class="sm-field__hint"><?php esc_html_e( 'Add STRIPE_SECRET_KEY to .env.', 'satori-manifest' ); ?></span>
						<?php else : ?>
							<span class="sm-pill <?php echo 'live' === $satori_manifest_status['stripe_mode'] ? 'sm-pill--on' : 'sm-pill--test'; ?>">
								<?php echo 'live' === $satori_manifest_status['stripe_mode'] ? esc_html__( 'Live', 'satori-manifest' ) : esc_html__( 'Test mode', 'satori-manifest' ); ?>
							</span>
						<?php endif; ?>
					</dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Stripe webhook', 'satori-manifest' ); ?></dt>
					<dd>
						<?php if ( $satori_manifest_status['stripe_webhook'] ) : ?>
							<span class="sm-pill sm-pill--on"><?php esc_html_e( 'Signing secret set', 'satori-manifest' ); ?></span>
						<?php else : ?>
							<span class="sm-pill sm-pill--off"><?php esc_html_e( 'Not set', 'satori-manifest' ); ?></span>
							<span class="sm-field__hint"><?php esc_html_e( 'Add STRIPE_WEBHOOK_SECRET to .env.', 'satori-manifest' ); ?></span>
						<?php endif; ?>
						<code class="sm-copy"><?php echo esc_html( $satori_manifest_status['webhook_url'] ); ?></code>
					</dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Email delivery', 'satori-manifest' ); ?></dt>
					<dd>
						<?php if ( 'brevo' === $satori_manifest_status['mail_transport'] ) : ?>
							<span class="sm-pill sm-pill--on"><?php esc_html_e( 'Brevo API', 'satori-manifest' ); ?></span>
						<?php elseif ( $satori_manifest_status['smtp_plugin'] ) : ?>
							<span class="sm-pill sm-pill--on"><?php esc_html_e( 'WP Mail SMTP', 'satori-manifest' ); ?></span>
							<a class="sm-field__hint" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-mail-smtp' ) ); ?>"><?php esc_html_e( 'Configure the mail server', 'satori-manifest' ); ?></a>
						<?php else : ?>
							<span class="sm-pill sm-pill--test"><?php esc_html_e( 'Server default (wp_mail)', 'satori-manifest' ); ?></span>
						<?php endif; ?>
						<span class="sm-field__hint"><?php echo esc_html( $satori_manifest_status['mail_from'] ); ?></span>
					</dd>
				</div>
			</dl>
		</aside>
	</div>
</div>
