<?php
/**
 * Subscriptions.
 *
 * Each client's Stripe subscription, mirrored on their user record so admin
 * screens never need to call Stripe. Kept in sync by the webhook.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Billing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Subscriptions
 *
 * Repository for subscription data stored in user meta.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Subscriptions {

	/**
	 * User meta key holding the subscription snapshot.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	public const META = '_sm_subscription';

	/**
	 * User meta key holding the Stripe customer ID (indexed for lookups).
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	public const META_CUSTOMER = '_sm_stripe_customer';

	/**
	 * Stripe statuses in which the client is paid up (or in a trial).
	 *
	 * @since 3.0.0
	 * @var   array<int,string>
	 */
	public const GOOD_STANDING = array( 'active', 'trialing' );

	/**
	 * Returns a client's subscription snapshot.
	 *
	 * Status "none" means the client has never subscribed (e.g. created by
	 * staff without payment).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  int $user_id  Client user ID.
	 * @return array{customer:string,id:string,status:string,plan:string,interval:string,amount:float,currency:string,current_period_end:int,cancel_at_period_end:bool,updated:int}
	 */
	public static function for_user( int $user_id ): array {
		$saved = get_user_meta( $user_id, self::META, true );

		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Merges new values into a client's subscription snapshot.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  int                 $user_id  Client user ID.
	 * @param  array<string,mixed> $values   Values to store.
	 * @return void
	 */
	public static function save( int $user_id, array $values ): void {
		$data            = array_merge( self::for_user( $user_id ), array_intersect_key( $values, self::defaults() ) );
		$data['updated'] = time();

		update_user_meta( $user_id, self::META, $data );

		if ( '' !== $data['customer'] ) {
			update_user_meta( $user_id, self::META_CUSTOMER, $data['customer'] );
		}
	}

	/**
	 * Copies a Stripe subscription object onto the matching client.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,mixed> $subscription  Stripe subscription object.
	 * @param  int                 $user_id       Client user ID (looked up when 0).
	 * @return int  Client user ID, or 0 when no client matches.
	 */
	public static function sync_from_stripe( array $subscription, int $user_id = 0 ): int {
		$customer = (string) ( $subscription['customer'] ?? '' );
		$user_id  = $user_id ? $user_id : self::user_for_customer( $customer );

		if ( ! $user_id ) {
			return 0;
		}

		$item     = $subscription['items']['data'][0] ?? array();
		$price    = is_array( $item ) ? (array) ( $item['price'] ?? array() ) : array();
		$metadata = (array) ( $subscription['metadata'] ?? array() );

		$values = array(
			'customer'             => $customer,
			'id'                   => (string) ( $subscription['id'] ?? '' ),
			'status'               => (string) ( $subscription['status'] ?? '' ),
			'plan'                 => (string) ( $metadata['plan'] ?? '' ),
			'interval'             => (string) ( $price['recurring']['interval'] ?? ( $metadata['interval'] ?? '' ) ),
			'currency'             => (string) ( $price['currency'] ?? '' ),
			'current_period_end'   => (int) ( $subscription['current_period_end'] ?? ( $item['current_period_end'] ?? 0 ) ),
			'cancel_at_period_end' => ! empty( $subscription['cancel_at_period_end'] ),
		);

		if ( isset( $price['unit_amount'] ) ) {
			$values['amount'] = Stripe_Client::from_minor_units( (int) $price['unit_amount'] );
		}

		// Keep what we already know when Stripe leaves a field out.
		self::save(
			$user_id,
			array_filter( $values, static fn( $value ): bool => '' !== $value && 0 !== $value )
		);

		return $user_id;
	}

	/**
	 * Finds the client for a Stripe customer ID.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $customer  Stripe customer ID (cus_…).
	 * @return int  User ID, or 0.
	 */
	public static function user_for_customer( string $customer ): int {
		if ( '' === $customer ) {
			return 0;
		}

		$users = get_users(
			array(
				'meta_key'   => self::META_CUSTOMER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one row per client; webhook-only lookup.
				'meta_value' => $customer, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
				'fields'     => 'ID',
			)
		);

		return $users ? (int) $users[0] : 0;
	}

	/**
	 * Returns every client that has (or had) a subscription, with its snapshot.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return array<int,array<string,mixed>>  Rows with "user" (WP_User) and "subscription".
	 */
	public static function all(): array {
		$users = get_users(
			array(
				'meta_key' => self::META_CUSTOMER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin-only listing.
				'orderby'  => 'registered',
				'order'    => 'DESC',
			)
		);

		return array_map(
			static fn( \WP_User $user ): array => array(
				'user'         => $user,
				'subscription' => self::for_user( $user->ID ),
			),
			$users
		);
	}

	/**
	 * Returns true for statuses in which the client is paid up.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $status  Stripe subscription status.
	 * @return bool
	 */
	public static function in_good_standing( string $status ): bool {
		return in_array( $status, self::GOOD_STANDING, true );
	}

	/**
	 * Returns a human label for a Stripe subscription status.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $status  Stripe status.
	 * @return string
	 */
	public static function status_label( string $status ): string {
		$labels = array(
			'none'               => __( 'No subscription', 'satori-manifest' ),
			'active'             => __( 'Active', 'satori-manifest' ),
			'trialing'           => __( 'Trial', 'satori-manifest' ),
			'past_due'           => __( 'Payment overdue', 'satori-manifest' ),
			'unpaid'             => __( 'Unpaid', 'satori-manifest' ),
			'canceled'           => __( 'Cancelled', 'satori-manifest' ),
			'incomplete'         => __( 'Incomplete', 'satori-manifest' ),
			'incomplete_expired' => __( 'Expired', 'satori-manifest' ),
			'paused'             => __( 'Paused', 'satori-manifest' ),
		);

		return $labels[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
	}

	/**
	 * Default snapshot.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return array<string,mixed>
	 */
	private static function defaults(): array {
		return array(
			'customer'             => '',
			'id'                   => '',
			'status'               => 'none',
			'plan'                 => '',
			'interval'             => '',
			'amount'               => 0.0,
			'currency'             => '',
			'current_period_end'   => 0,
			'cancel_at_period_end' => false,
			'updated'              => 0,
		);
	}
}
