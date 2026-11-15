<?php
/**
 * Partial: a support ticket's messages, oldest first.
 *
 * Expects $satori_manifest_ticket (int), $satori_manifest_messages (WP_Post[])
 * and $satori_manifest_viewer ('client' or 'staff').
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.12.0
 */

declare( strict_types=1 );

use SatoriManifest\Support\Tickets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$satori_manifest_owner = Tickets::owner( $satori_manifest_ticket );
?>
<ol class="sm-ticket-thread">
	<?php foreach ( $satori_manifest_messages as $satori_manifest_message ) : ?>
		<?php
		$satori_manifest_mine   = (int) $satori_manifest_message->post_author === $satori_manifest_owner;
		$satori_manifest_author = get_userdata( (int) $satori_manifest_message->post_author );

		if ( 'client' === $satori_manifest_viewer ) {
			$satori_manifest_who = $satori_manifest_mine ? __( 'You', 'satori-manifest' ) : __( 'Support', 'satori-manifest' );
		} else {
			$satori_manifest_who = $satori_manifest_author ? $satori_manifest_author->display_name : __( 'Unknown', 'satori-manifest' );
		}
		?>
		<li class="sm-ticket-message <?php echo esc_attr( $satori_manifest_mine ? 'is-client' : 'is-staff' ); ?>">
			<p class="sm-ticket-message__meta">
				<strong><?php echo esc_html( $satori_manifest_who ); ?></strong>
				<time datetime="<?php echo esc_attr( get_post_time( 'c', true, $satori_manifest_message ) ); ?>"><?php echo esc_html( get_the_date( '', $satori_manifest_message ) . ' ' . get_the_time( '', $satori_manifest_message ) ); ?></time>
			</p>
			<div class="sm-ticket-message__body"><?php echo nl2br( esc_html( $satori_manifest_message->post_content ) ); ?></div>
		</li>
	<?php endforeach; ?>
</ol>
