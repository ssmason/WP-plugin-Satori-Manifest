<?php
/**
 * Staff screen: Support tickets menu.
 *
 * Receives from Staff_Tickets_Screen::render(): $satori_manifest_ticket (0 = list),
 * $satori_manifest_tickets, $satori_manifest_messages, $satori_manifest_counts,
 * $satori_manifest_filter, $satori_manifest_notice, $satori_manifest_action.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.12.0
 */

declare( strict_types=1 );

use SatoriManifest\Branding\Icons;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Core\Security;
use SatoriManifest\Support\Admin\Staff_Tickets_Screen;
use SatoriManifest\Support\Tickets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$satori_manifest_notices = array(
	'replied'        => array( 'success', __( 'Reply sent and emailed to the client.', 'satori-manifest' ) ),
	'replied-unsent' => array( 'warning', __( 'Reply saved, but the email to the client could not be sent. They will still see it in their dashboard.', 'satori-manifest' ) ),
	'saved'          => array( 'success', __( 'Status updated.', 'satori-manifest' ) ),
	'invalid'        => array( 'warning', __( 'That ticket no longer exists.', 'satori-manifest' ) ),
);
?>
<div class="wrap sm-admin sm-support">
	<header class="sm-page-header">
		<div>
			<p class="sm-eyebrow"><?php esc_html_e( 'Support', 'satori-manifest' ); ?></p>
			<h1 class="sm-page-header__title"><?php esc_html_e( 'Support tickets', 'satori-manifest' ); ?></h1>
			<p class="sm-page-header__lead"><?php esc_html_e( 'Clients raise tickets from their dashboard. Your reply is emailed to them and shown in their Support screen.', 'satori-manifest' ); ?></p>
		</div>
	</header>

	<?php if ( isset( $satori_manifest_notices[ $satori_manifest_notice ] ) ) : ?>
		<div class="sm-notice sm-notice--<?php echo esc_attr( $satori_manifest_notices[ $satori_manifest_notice ][0] ); ?>" role="status"><?php Icons::render( 'check', 18 ); ?> <?php echo esc_html( $satori_manifest_notices[ $satori_manifest_notice ][1] ); ?></div>
	<?php endif; ?>

	<?php if ( $satori_manifest_ticket ) : ?>
		<?php
		$satori_manifest_status = Tickets::status( $satori_manifest_ticket );
		$satori_manifest_viewer = 'staff';
		$satori_manifest_client = get_userdata( Tickets::owner( $satori_manifest_ticket ) );
		$satori_manifest_page   = $satori_manifest_client ? Client_Repository::page_id( $satori_manifest_client->ID ) : 0;
		?>
		<p><a href="<?php echo esc_url( Staff_Tickets_Screen::url() ); ?>">&larr; <?php esc_html_e( 'All tickets', 'satori-manifest' ); ?></a></p>
		<section class="sm-panel sm-ticket">
			<header class="sm-ticket__head">
				<h2 class="sm-panel__title"><?php echo esc_html( Tickets::subject( $satori_manifest_ticket ) ); ?> <span class="sm-ticket__number">#<?php echo esc_html( (string) $satori_manifest_ticket ); ?></span></h2>
				<?php require __DIR__ . '/status.php'; ?>
			</header>
			<?php if ( $satori_manifest_client ) : ?>
				<p class="sm-ticket__client">
					<?php echo esc_html( $satori_manifest_client->display_name ); ?> ·
					<a href="mailto:<?php echo esc_attr( $satori_manifest_client->user_email ); ?>"><?php echo esc_html( $satori_manifest_client->user_email ); ?></a>
					<?php if ( $satori_manifest_page ) : ?>
						· <a href="<?php echo esc_url( (string) get_permalink( $satori_manifest_page ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View page', 'satori-manifest' ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<?php require __DIR__ . '/thread.php'; ?>

			<form class="sm-ticket-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( $satori_manifest_action ); ?>" />
				<input type="hidden" name="ticket" value="<?php echo esc_attr( (string) $satori_manifest_ticket ); ?>" />
				<?php Security::output_form_nonce( $satori_manifest_action ); ?>
				<label class="sm-ticket-form__field">
					<span><?php esc_html_e( 'Reply (emailed to the client)', 'satori-manifest' ); ?></span>
					<textarea name="message" rows="6" maxlength="<?php echo esc_attr( (string) Tickets::MAX_MESSAGE ); ?>"></textarea>
				</label>
				<div class="sm-ticket-form__actions">
					<label>
						<?php esc_html_e( 'Status', 'satori-manifest' ); ?>
						<select name="status">
							<?php foreach ( Tickets::statuses() as $satori_manifest_key => $satori_manifest_label ) : ?>
								<option value="<?php echo esc_attr( $satori_manifest_key ); ?>" <?php selected( Tickets::OPEN === $satori_manifest_status ? Tickets::IN_PROGRESS : $satori_manifest_status, $satori_manifest_key ); ?>><?php echo esc_html( $satori_manifest_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Send reply', 'satori-manifest' ); ?></button>
				</div>
			</form>
		</section>
	<?php else : ?>
		<nav class="sm-ticket-filters" aria-label="<?php esc_attr_e( 'Filter by status', 'satori-manifest' ); ?>">
			<a href="<?php echo esc_url( Staff_Tickets_Screen::url() ); ?>" class="<?php echo esc_attr( '' === $satori_manifest_filter ? 'is-current' : '' ); ?>"><?php esc_html_e( 'All', 'satori-manifest' ); ?> (<?php echo esc_html( (string) array_sum( $satori_manifest_counts ) ); ?>)</a>
			<?php foreach ( Tickets::statuses() as $satori_manifest_key => $satori_manifest_label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'status', $satori_manifest_key, Staff_Tickets_Screen::url() ) ); ?>" class="<?php echo esc_attr( $satori_manifest_key === $satori_manifest_filter ? 'is-current' : '' ); ?>"><?php echo esc_html( $satori_manifest_label ); ?> (<?php echo esc_html( (string) $satori_manifest_counts[ $satori_manifest_key ] ); ?>)</a>
			<?php endforeach; ?>
		</nav>

		<section class="sm-panel sm-panel--flush">
			<?php if ( ! $satori_manifest_tickets ) : ?>
				<p class="sm-muted sm-ticket-empty"><?php esc_html_e( 'No tickets here.', 'satori-manifest' ); ?></p>
			<?php else : ?>
				<table class="sm-table sm-ticket-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Ticket', 'satori-manifest' ); ?></th>
							<th><?php esc_html_e( 'Client', 'satori-manifest' ); ?></th>
							<th><?php esc_html_e( 'Status', 'satori-manifest' ); ?></th>
							<th><?php esc_html_e( 'Last activity', 'satori-manifest' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $satori_manifest_tickets as $satori_manifest_row ) : ?>
							<?php
							$satori_manifest_status = Tickets::status( $satori_manifest_row->ID );
							$satori_manifest_author = get_userdata( (int) $satori_manifest_row->post_author );
							?>
							<tr>
								<td><a href="<?php echo esc_url( Staff_Tickets_Screen::url( $satori_manifest_row->ID ) ); ?>"><strong><?php echo esc_html( Tickets::subject( $satori_manifest_row ) ); ?></strong></a> <span class="sm-muted">#<?php echo esc_html( (string) $satori_manifest_row->ID ); ?></span></td>
								<td><?php echo esc_html( $satori_manifest_author ? $satori_manifest_author->display_name : '' ); ?></td>
								<td><?php require __DIR__ . '/status.php'; ?></td>
								<td><?php echo esc_html( get_the_modified_date( '', $satori_manifest_row ) . ' ' . get_the_modified_time( '', $satori_manifest_row ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>
