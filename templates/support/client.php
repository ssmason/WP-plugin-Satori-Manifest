<?php
/**
 * Client screen: Support tickets.
 *
 * Receives from Client_Tickets_Screen::render(): $satori_manifest_ticket (0 = list),
 * $satori_manifest_tickets, $satori_manifest_messages, $satori_manifest_notice,
 * $satori_manifest_action.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.12.0
 */

declare( strict_types=1 );

use SatoriManifest\Branding\Icons;
use SatoriManifest\Core\Security;
use SatoriManifest\Support\Admin\Client_Tickets_Screen;
use SatoriManifest\Support\Tickets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$satori_manifest_notices = array(
	'created' => array( 'success', __( 'Thanks, your ticket is raised. We\'ll reply here and email you when we do.', 'satori-manifest' ) ),
	'replied' => array( 'success', __( 'Sent. We\'ll reply here and email you.', 'satori-manifest' ) ),
	'closed'  => array( 'success', __( 'Ticket closed. Reply to it any time to open it again.', 'satori-manifest' ) ),
	'invalid' => array( 'warning', __( 'Please add a subject and a message.', 'satori-manifest' ) ),
);
?>
<div class="wrap sm-admin sm-support">
	<header class="sm-page-header">
		<div>
			<p class="sm-eyebrow"><?php esc_html_e( 'Support', 'satori-manifest' ); ?></p>
			<h1 class="sm-page-header__title"><?php esc_html_e( 'Support tickets', 'satori-manifest' ); ?></h1>
			<p class="sm-page-header__lead"><?php esc_html_e( 'Ask us anything about your page, price list or account. We reply here and email you when we do.', 'satori-manifest' ); ?></p>
		</div>
	</header>

	<?php if ( isset( $satori_manifest_notices[ $satori_manifest_notice ] ) ) : ?>
		<div class="sm-notice sm-notice--<?php echo esc_attr( $satori_manifest_notices[ $satori_manifest_notice ][0] ); ?>" role="status"><?php Icons::render( 'check', 18 ); ?> <?php echo esc_html( $satori_manifest_notices[ $satori_manifest_notice ][1] ); ?></div>
	<?php endif; ?>

	<?php if ( $satori_manifest_ticket ) : ?>
		<?php
		$satori_manifest_status = Tickets::status( $satori_manifest_ticket );
		$satori_manifest_viewer = 'client';
		?>
		<p><a href="<?php echo esc_url( Client_Tickets_Screen::url() ); ?>">&larr; <?php esc_html_e( 'All tickets', 'satori-manifest' ); ?></a></p>
		<section class="sm-panel sm-ticket">
			<header class="sm-ticket__head">
				<h2 class="sm-panel__title"><?php echo esc_html( Tickets::subject( $satori_manifest_ticket ) ); ?> <span class="sm-ticket__number">#<?php echo esc_html( (string) $satori_manifest_ticket ); ?></span></h2>
				<?php require __DIR__ . '/status.php'; ?>
			</header>

			<?php require __DIR__ . '/thread.php'; ?>

			<form class="sm-ticket-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( $satori_manifest_action ); ?>" />
				<input type="hidden" name="ticket" value="<?php echo esc_attr( (string) $satori_manifest_ticket ); ?>" />
				<?php Security::output_form_nonce( $satori_manifest_action ); ?>
				<label class="sm-ticket-form__field">
					<span><?php esc_html_e( 'Reply', 'satori-manifest' ); ?></span>
					<textarea name="message" rows="5" maxlength="<?php echo esc_attr( (string) Tickets::MAX_MESSAGE ); ?>" required></textarea>
				</label>
				<div class="sm-ticket-form__actions">
					<button type="submit" name="task" value="reply" class="button button-primary"><?php esc_html_e( 'Send reply', 'satori-manifest' ); ?></button>
					<?php if ( Tickets::CLOSED !== $satori_manifest_status ) : ?>
						<button type="submit" name="task" value="close" class="button" formnovalidate><?php esc_html_e( 'Close ticket', 'satori-manifest' ); ?></button>
					<?php endif; ?>
				</div>
			</form>
		</section>
	<?php else : ?>
		<div class="sm-support__layout">
			<section class="sm-panel">
				<h2 class="sm-panel__title"><?php esc_html_e( 'Raise a ticket', 'satori-manifest' ); ?></h2>
				<?php
				$satori_manifest_form_action = $satori_manifest_action;
				require __DIR__ . '/new-ticket.php';
				?>
			</section>

			<section class="sm-panel">
				<h2 class="sm-panel__title"><?php esc_html_e( 'Your tickets', 'satori-manifest' ); ?></h2>
				<?php if ( ! $satori_manifest_tickets ) : ?>
					<p class="sm-muted"><?php esc_html_e( 'No tickets yet.', 'satori-manifest' ); ?></p>
				<?php else : ?>
					<ul class="sm-ticket-list">
						<?php foreach ( $satori_manifest_tickets as $satori_manifest_row ) : ?>
							<?php $satori_manifest_status = Tickets::status( $satori_manifest_row->ID ); ?>
							<li>
								<a href="<?php echo esc_url( Client_Tickets_Screen::url( $satori_manifest_row->ID ) ); ?>">
									<span class="sm-ticket-list__subject"><?php echo esc_html( Tickets::subject( $satori_manifest_row ) ); ?></span>
									<span class="sm-ticket-list__date"><?php echo esc_html( get_the_modified_date( '', $satori_manifest_row ) ); ?></span>
								</a>
								<?php require __DIR__ . '/status.php'; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		</div>
	<?php endif; ?>
</div>
