<?php
/**
 * Partial: a ticket status pill. Expects $satori_manifest_status (string).
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
?>
<span class="sm-ticket-status is-<?php echo esc_attr( str_replace( '_', '-', $satori_manifest_status ) ); ?>"><?php echo esc_html( Tickets::statuses()[ $satori_manifest_status ] ?? '' ); ?></span>
