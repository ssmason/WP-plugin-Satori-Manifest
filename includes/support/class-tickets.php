<?php
/**
 * Support tickets: storage and the rules for status changes.
 *
 * A ticket is a private post (author = the client, title = subject) with a
 * status in post meta; its messages are child posts, oldest first. Nothing is
 * public, in the REST API or in the normal admin screens.
 *
 * Statuses: open (just raised, or the client added to it), in_progress (staff
 * replied or picked it up) and closed. A client reply reopens a closed ticket.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.12.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tickets
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.12.0
 */
final class Tickets {

	/**
	 * Ticket post type.
	 */
	public const POST_TYPE = 'sm_ticket';

	/**
	 * Message post type (children of a ticket).
	 */
	public const MESSAGE_TYPE = 'sm_ticket_msg';

	/**
	 * Status meta key.
	 */
	public const META_STATUS = '_sm_ticket_status';

	/**
	 * Set while the client has a staff reply they have not opened yet.
	 */
	public const META_UNREAD = '_sm_ticket_unread';

	/**
	 * Statuses.
	 */
	public const OPEN        = 'open';
	public const IN_PROGRESS = 'in_progress';
	public const CLOSED      = 'closed';

	/**
	 * Longest subject and message.
	 */
	public const MAX_SUBJECT = 150;
	public const MAX_MESSAGE = 5000;

	/**
	 * Registers the two private post types.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @return void
	 */
	public static function register_post_types(): void {
		foreach ( array(
			self::POST_TYPE    => __( 'Support tickets', 'satori-manifest' ),
			self::MESSAGE_TYPE => __( 'Support messages', 'satori-manifest' ),
		) as $type => $label ) {
			register_post_type(
				$type,
				array(
					'label'               => $label,
					'public'              => false,
					'show_ui'             => false,
					'show_in_rest'        => false,
					'exclude_from_search' => true,
					'rewrite'             => false,
					'query_var'           => false,
					'can_export'          => false,
					'supports'            => array( 'title', 'editor' ),
					'capability_type'     => 'post',
					'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
					'map_meta_cap'        => true,
				)
			);
		}
	}

	/**
	 * Status labels, in display order.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @return array<string,string>
	 */
	public static function statuses(): array {
		return array(
			self::OPEN        => __( 'Opened', 'satori-manifest' ),
			self::IN_PROGRESS => __( 'In progress', 'satori-manifest' ),
			self::CLOSED      => __( 'Closed', 'satori-manifest' ),
		);
	}

	/**
	 * Raises a ticket.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  int    $user_id  Client.
	 * @param  string $subject  Subject.
	 * @param  string $message  First message.
	 * @return int  Ticket id, 0 when the input is empty.
	 */
	public static function create( int $user_id, string $subject, string $message ): int {
		$subject = mb_substr( trim( sanitize_text_field( $subject ) ), 0, self::MAX_SUBJECT );
		$message = self::clean( $message );

		if ( '' === $subject || '' === $message || ! $user_id ) {
			return 0;
		}

		$ticket = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_author' => $user_id,
				'post_title'  => wp_slash( $subject ),
			),
			true
		);

		if ( is_wp_error( $ticket ) ) {
			return 0;
		}

		update_post_meta( $ticket, self::META_STATUS, self::OPEN );
		self::add_message( $ticket, $user_id, $message );

		return $ticket;
	}

	/**
	 * Adds a message; staff replies move an open ticket to in progress, client
	 * replies reopen a closed one.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  int    $ticket   Ticket.
	 * @param  int    $user_id  Who wrote it.
	 * @param  string $message  Message.
	 * @return int  Message id, 0 when empty or not a ticket.
	 */
	public static function reply( int $ticket, int $user_id, string $message ): int {
		$message = self::clean( $message );

		if ( '' === $message || ! self::exists( $ticket ) ) {
			return 0;
		}

		$id = self::add_message( $ticket, $user_id, $message );

		if ( self::owner( $ticket ) === $user_id ) {
			self::mark_read( $ticket );

			if ( self::CLOSED === self::status( $ticket ) ) {
				self::set_status( $ticket, self::OPEN );
			}
		} else {
			update_post_meta( $ticket, self::META_UNREAD, 1 );

			if ( self::OPEN === self::status( $ticket ) ) {
				self::set_status( $ticket, self::IN_PROGRESS );
			}
		}

		return $id;
	}

	/**
	 * Sets the status.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  int    $ticket  Ticket.
	 * @param  string $status  One of the status constants.
	 * @return void
	 */
	public static function set_status( int $ticket, string $status ): void {
		if ( self::exists( $ticket ) && isset( self::statuses()[ $status ] ) ) {
			update_post_meta( $ticket, self::META_STATUS, $status );
			self::touch( $ticket );
		}
	}

	/**
	 * A ticket's status.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  int $ticket  Ticket.
	 * @return string
	 */
	public static function status( int $ticket ): string {
		$status = (string) get_post_meta( $ticket, self::META_STATUS, true );

		return isset( self::statuses()[ $status ] ) ? $status : self::OPEN;
	}

	/**
	 * A ticket's subject (as typed; no "Private:" prefix).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  int|\WP_Post $ticket  Ticket.
	 * @return string
	 */
	public static function subject( $ticket ): string {
		return (string) get_post_field( 'post_title', $ticket, 'raw' );
	}

	/**
	 * Whether an id is a ticket.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  int $ticket  Id.
	 * @return bool
	 */
	public static function exists( int $ticket ): bool {
		return $ticket > 0 && self::POST_TYPE === get_post_type( $ticket );
	}

	/**
	 * The client who raised a ticket.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  int $ticket  Ticket.
	 * @return int
	 */
	public static function owner( int $ticket ): int {
		return self::exists( $ticket ) ? (int) get_post_field( 'post_author', $ticket ) : 0;
	}

	/**
	 * A ticket's messages, oldest first.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  int $ticket  Ticket.
	 * @return array<int,\WP_Post>
	 */
	public static function messages( int $ticket ): array {
		return get_posts(
			array(
				'post_type'      => self::MESSAGE_TYPE,
				'post_status'    => 'private',
				'post_parent'    => $ticket,
				'posts_per_page' => 100,
				'orderby'        => 'date ID',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Tickets, newest activity first: one client's, or everyone's (optionally by status).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  int    $user_id  Client, or 0 for all.
	 * @param  string $status   Status filter, '' for all.
	 * @return array<int,\WP_Post>
	 */
	public static function list( int $user_id = 0, string $status = '' ): array {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => 100,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		if ( $user_id ) {
			$args['author'] = $user_id;
		}

		if ( isset( self::statuses()[ $status ] ) ) {
			$args['meta_key']   = self::META_STATUS; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small, private post type.
			$args['meta_value'] = $status; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- small, private post type.
		}

		return get_posts( $args );
	}

	/**
	 * A client's tickets with a staff reply they have not opened yet.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.2
	 * @param  int $user_id  Client.
	 * @return array<int,\WP_Post>
	 */
	public static function unread( int $user_id ): array {
		return array_values(
			array_filter(
				$user_id ? self::list( $user_id ) : array(),
				static fn( \WP_Post $ticket ): bool => (bool) get_post_meta( $ticket->ID, self::META_UNREAD, true )
			)
		);
	}

	/**
	 * Marks a ticket's replies as seen by the client.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.2
	 * @param  int $ticket  Ticket.
	 * @return void
	 */
	public static function mark_read( int $ticket ): void {
		delete_post_meta( $ticket, self::META_UNREAD );
	}

	/**
	 * Number of tickets per status.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @return array<string,int>
	 */
	public static function counts(): array {
		$counts = array_fill_keys( array_keys( self::statuses() ), 0 );

		foreach ( self::list() as $ticket ) {
			++$counts[ self::status( $ticket->ID ) ];
		}

		return $counts;
	}

	/**
	 * Removes a deleted client's tickets and messages.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  int $user_id  Deleted user.
	 * @return void
	 */
	public static function forget( int $user_id ): void {
		foreach ( self::list( $user_id ) as $ticket ) {
			foreach ( self::messages( $ticket->ID ) as $message ) {
				wp_delete_post( $message->ID, true );
			}

			wp_delete_post( $ticket->ID, true );
		}
	}

	/**
	 * Stores one message.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  int    $ticket   Ticket.
	 * @param  int    $user_id  Author.
	 * @param  string $message  Clean message.
	 * @return int
	 */
	private static function add_message( int $ticket, int $user_id, string $message ): int {
		$id = wp_insert_post(
			array(
				'post_type'    => self::MESSAGE_TYPE,
				'post_status'  => 'private',
				'post_author'  => $user_id,
				'post_parent'  => $ticket,
				'post_content' => wp_slash( $message ),
			),
			true
		);

		self::touch( $ticket );

		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * Marks a ticket as just active (newest first in lists).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  int $ticket  Ticket.
	 * @return void
	 */
	private static function touch( int $ticket ): void {
		global $wpdb;

		// Direct update: wp_update_post() would also refilter the title; only the date changes.
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->posts,
			array(
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', true ),
			),
			array( 'ID' => $ticket )
		);
		clean_post_cache( $ticket );
	}

	/**
	 * Plain text, trimmed and capped (shown escaped, line breaks kept).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.12.0
	 * @param  string $message  Raw.
	 * @return string
	 */
	private static function clean( string $message ): string {
		return mb_substr( trim( sanitize_textarea_field( $message ) ), 0, self::MAX_MESSAGE );
	}
}
