<?php
/**
 * Partial: the new support ticket form (Support screen and the Home "Need a hand?" panel).
 *
 * Expects $satori_manifest_form_action (the form action / nonce action).
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.12.0
 */

declare( strict_types=1 );

use SatoriManifest\Core\Security;
use SatoriManifest\Support\Tickets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form class="sm-ticket-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( $satori_manifest_form_action ); ?>" />
	<input type="hidden" name="task" value="new" />
	<?php Security::output_form_nonce( $satori_manifest_form_action ); ?>
	<label class="sm-ticket-form__field">
		<span><?php esc_html_e( 'Subject', 'satori-manifest' ); ?></span>
		<input type="text" name="subject" maxlength="<?php echo esc_attr( (string) Tickets::MAX_SUBJECT ); ?>" required />
	</label>
	<label class="sm-ticket-form__field">
		<span><?php esc_html_e( 'Message', 'satori-manifest' ); ?></span>
		<textarea name="message" rows="5" maxlength="<?php echo esc_attr( (string) Tickets::MAX_MESSAGE ); ?>" required></textarea>
	</label>
	<div class="sm-ticket-form__actions">
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Send ticket', 'satori-manifest' ); ?></button>
	</div>
</form>
