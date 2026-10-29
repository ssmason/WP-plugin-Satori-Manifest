<?php
/**
 * Registration applications.
 *
 * Each public registration is stored as a private sm_application post
 * (title = business name, details in meta). Only administrators
 * (manage_options) can see or act on applications. Nothing is created for
 * the applicant until staff approve — see Applications_Screen.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Registration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Applications
 *
 * Application post type and data access.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */
class Applications {

	/**
	 * Post type slug.
	 *
	 * @since 2.1.0
	 * @var   string
	 */
	public const CPT = 'sm_application';

	/**
	 * Meta key prefix for application fields.
	 *
	 * @since 2.1.0
	 * @var   string
	 */
	private const META = '_sm_app_';

	/**
	 * Application statuses.
	 *
	 * Statuses:
	 * pending           Waiting for a decision.
	 * awaiting_payment  Approved; the applicant has been sent a payment link.
	 * approved          The client account exists (paid, or approved without payment).
	 * declined          Turned down.
	 *
	 * @since 2.1.0
	 * @var   array<int,string>
	 */
	public const STATUSES = array( 'pending', 'awaiting_payment', 'approved', 'declined' );

	/**
	 * Fields captured by the registration form (meta suffix => label).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return array<string,string>
	 */
	public static function fields(): array {
		return array(
			'business_name' => __( 'Business name', 'satori-manifest' ),
			'contact_name'  => __( 'Your name', 'satori-manifest' ),
			'email'         => __( 'Email', 'satori-manifest' ),
			'phone'         => __( 'Phone', 'satori-manifest' ),
			'website'       => __( 'Website', 'satori-manifest' ),
			'business_type' => __( 'Type of business', 'satori-manifest' ),
			'plan'          => __( 'Plan', 'satori-manifest' ),
			'interval'      => __( 'Billing', 'satori-manifest' ),
			'design'        => __( 'Preferred design', 'satori-manifest' ),
			'message'       => __( 'Anything else?', 'satori-manifest' ),
		);
	}

	/**
	 * Business types offered on the form.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return array<string,string>
	 */
	public static function business_types(): array {
		return array(
			'hair-beauty' => __( 'Hair & beauty salon', 'satori-manifest' ),
			'barber'      => __( 'Barber', 'satori-manifest' ),
			'nails'       => __( 'Nails & lashes', 'satori-manifest' ),
			'spa'         => __( 'Spa & wellbeing', 'satori-manifest' ),
			'bar'         => __( 'Bar or pub', 'satori-manifest' ),
			'restaurant'  => __( 'Restaurant', 'satori-manifest' ),
			'cafe'        => __( 'Café', 'satori-manifest' ),
			'other'       => __( 'Something else', 'satori-manifest' ),
		);
	}

	/**
	 * Registers the post type. Hooked to 'init'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return void
	 */
	public static function register(): void {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array(
					'name'          => __( 'Applications', 'satori-manifest' ),
					'singular_name' => __( 'Application', 'satori-manifest' ),
				),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title' ),
				// Every capability maps to manage_options: administrators only.
				'capabilities'    => array_fill_keys(
					array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'create_posts' ),
					'manage_options'
				),
				'map_meta_cap'    => false,
				'capability_type' => 'post',
			)
		);
	}

	/**
	 * Stores a new application.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  array<string,string> $data  Sanitized field values keyed by fields().
	 * @return int  Application ID, 0 on failure.
	 */
	public static function create( array $data ): int {
		$id = wp_insert_post(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'private',
				'post_title'  => (string) ( $data['business_name'] ?? '' ),
			),
			true
		);

		if ( is_wp_error( $id ) ) {
			return 0;
		}

		foreach ( array_keys( self::fields() ) as $key ) {
			update_post_meta( $id, self::META . $key, (string) ( $data[ $key ] ?? '' ) );
		}

		update_post_meta( $id, self::META . 'status', 'pending' );

		return (int) $id;
	}

	/**
	 * Returns one application as an array, or null.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  int $id  Application ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( int $id ): ?array {
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post || self::CPT !== $post->post_type ) {
			return null;
		}

		$app = array(
			'id'      => $post->ID,
			'date'    => $post->post_date,
			'status'  => self::meta( $id, 'status', 'pending' ),
			'user_id' => (int) self::meta( $id, 'user_id', '0' ),
			'emails'  => array_values( array_filter( (array) get_post_meta( $id, self::META . 'emails', true ) ) ),
		);

		foreach ( array_keys( self::fields() ) as $key ) {
			$app[ $key ] = self::meta( $id, $key );
		}

		return $app;
	}

	/**
	 * Returns applications, newest first, optionally filtered by status.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  string $status  Status filter, or '' for all.
	 * @return array<int,array<string,mixed>>
	 */
	public static function all( string $status = '' ): array {
		$args = array(
			'post_type'      => self::CPT,
			'post_status'    => 'private',
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		);

		if ( in_array( $status, self::STATUSES, true ) ) {
			$args['meta_key']   = self::META . 'status'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small admin-only table.
			$args['meta_value'] = $status; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}

		return array_values( array_filter( array_map( array( self::class, 'get' ), get_posts( $args ) ) ) );
	}

	/**
	 * Counts pending applications (for the admin menu badge).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @return int
	 */
	public static function pending_count(): int {
		return count( self::all( 'pending' ) );
	}

	/**
	 * Returns true when a pending application already exists for an email.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  string $email  Email address.
	 * @return bool
	 */
	public static function has_pending_for( string $email ): bool {
		foreach ( self::all( 'pending' ) as $app ) {
			if ( 0 === strcasecmp( (string) $app['email'], $email ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sets an application's status (and linked user, when approved).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  int    $id       Application ID.
	 * @param  string $status   New status.
	 * @param  int    $user_id  Created client user ID.
	 * @return void
	 */
	public static function set_status( int $id, string $status, int $user_id = 0 ): void {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return;
		}

		update_post_meta( $id, self::META . 'status', $status );
		update_post_meta( $id, self::META . 'decided', time() );

		if ( $user_id ) {
			update_post_meta( $id, self::META . 'user_id', $user_id );
		}

		/**
		 * Fires after an application changes status.
		 *
		 * @since 3.0.0
		 * @param int    $id       Application ID.
		 * @param string $status   New status.
		 * @param int    $user_id  Client user ID (0 until the account exists).
		 */
		do_action( 'satori_manifest_application_status', $id, $status, $user_id );
	}

	/**
	 * Appends an email result to the application's history.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  int                 $id      Application ID.
	 * @param  array<string,mixed> $result  Mailer::send() result.
	 * @return void
	 */
	public static function log_email( int $id, array $result ): void {
		$history   = (array) get_post_meta( $id, self::META . 'emails', true );
		$history[] = array_intersect_key( $result, array_flip( array( 'template', 'to', 'subject', 'transport', 'sent', 'error', 'time' ) ) );

		update_post_meta( $id, self::META . 'emails', array_values( array_filter( $history ) ) );
	}

	/**
	 * Stores a workflow value on an application (e.g. the approved username).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  int    $id     Application ID.
	 * @param  string $key    Key (without prefix).
	 * @param  string $value  Value.
	 * @return void
	 */
	public static function set_meta( int $id, string $key, string $value ): void {
		update_post_meta( $id, self::META . $key, $value );
	}

	/**
	 * Reads a workflow value stored with set_meta().
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  int    $id   Application ID.
	 * @param  string $key  Key (without prefix).
	 * @return string
	 */
	public static function get_meta( int $id, string $key ): string {
		return self::meta( $id, $key );
	}

	/**
	 * Reads one application meta value as a string.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.1.0
	 * @param  int    $id        Application ID.
	 * @param  string $key       Field key (without prefix).
	 * @param  string $fallback  Default.
	 * @return string
	 */
	private static function meta( int $id, string $key, string $fallback = '' ): string {
		$value = get_post_meta( $id, self::META . $key, true );
		return is_scalar( $value ) && '' !== (string) $value ? (string) $value : $fallback;
	}
}
