<?php
/**
 * Support ticket emails.
 *
 * New tickets and client replies go to the support address (config/support.yaml,
 * else the staff addresses) as a prompt; replies are written in wp-admin.
 * Staff replies go to the client, with a link to the ticket in their dashboard.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.12.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Support;

use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Core\Config;
use SatoriManifest\Mail\Mailer;
use SatoriManifest\Support\Admin\Client_Tickets_Screen;
use SatoriManifest\Support\Admin\Staff_Tickets_Screen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Ticket_Notifier
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.12.0
 */
final class Ticket_Notifier {

	/**
	 * Tells support about a new ticket.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  int    $ticket   Ticket.
	 * @param  string $message  First message.
	 * @return bool  Whether at least one email went out.
	 */
	public static function new_ticket( int $ticket, string $message ): bool {
		return self::to_support( 'ticket-new', $ticket, $message );
	}

	/**
	 * Tells support the client added to a ticket.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  int    $ticket   Ticket.
	 * @param  string $message  The client's message.
	 * @return bool
	 */
	public static function client_reply( int $ticket, string $message ): bool {
		return self::to_support( 'ticket-client-reply', $ticket, $message );
	}

	/**
	 * Sends the client a staff reply.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  int    $ticket   Ticket.
	 * @param  string $message  The reply.
	 * @return bool
	 */
	public static function staff_reply( int $ticket, string $message ): bool {
		$client = get_userdata( Tickets::owner( $ticket ) );

		if ( ! $client || ! is_email( $client->user_email ) ) {
			return false;
		}

		$vars = array(
			'ticket'  => (string) $ticket,
			'subject' => (string) Tickets::subject( $ticket ),
			'message' => $message,
			'status'  => Tickets::statuses()[ Tickets::status( $ticket ) ],
			'link'    => Client_Tickets_Screen::url( $ticket ),
		);

		return (bool) Mailer::send( 'ticket-reply', $client->user_email, $vars )['sent'];
	}

	/**
	 * Where support emails go.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @return array<int,string>
	 */
	public static function recipients(): array {
		$configured = array_values( array_filter( array_map( 'sanitize_email', (array) ( Config::get( 'support.yaml' )['notify'] ?? array() ) ), 'is_email' ) );

		return array() !== $configured ? $configured : Mailer::staff_recipients();
	}

	/**
	 * Sends a support email to every recipient (Reply-To: the client).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  string $template  Email template.
	 * @param  int    $ticket    Ticket.
	 * @param  string $message   Message.
	 * @return bool
	 */
	private static function to_support( string $template, int $ticket, string $message ): bool {
		$client  = get_userdata( Tickets::owner( $ticket ) );
		$page_id = $client ? Client_Repository::page_id( $client->ID ) : 0;
		$vars    = array(
			'ticket'       => (string) $ticket,
			'subject'      => (string) Tickets::subject( $ticket ),
			'message'      => $message,
			'client_name'  => $client ? (string) $client->display_name : '',
			'client_email' => $client ? (string) $client->user_email : '',
			'page_url'     => $page_id ? (string) get_permalink( $page_id ) : '',
			'link'         => Staff_Tickets_Screen::url( $ticket ),
		);
		$sent    = false;

		foreach ( self::recipients() as $recipient ) {
			$sent = Mailer::send( $template, $recipient, $vars, $vars['client_email'] )['sent'] || $sent;
		}

		return $sent;
	}
}
