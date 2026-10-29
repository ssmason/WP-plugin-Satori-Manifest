<?php
/**
 * Payments log.
 *
 * Every invoice Stripe reports (paid or failed) is recorded as a private
 * "sm_payment" post, visible to administrators only. Stripe stays the
 * source of truth; this log lets the Payments screen work without API calls.
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
 * Class Payments
 *
 * Post type registration and data access for payment records.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Payments {

	/**
	 * Post type slug.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	public const CPT = 'sm_payment';

	/**
	 * Meta key prefix.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	private const META = '_sm_pay_';

	/**
	 * Stored fields.
	 *
	 * @since 3.0.0
	 * @var   array<int,string>
	 */
	private const FIELDS = array( 'user_id', 'invoice', 'number', 'status', 'amount', 'currency', 'description', 'invoice_url', 'reason' );

	/**
	 * Registers the post type. Hooked to 'init'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return void
	 */
	public static function register(): void {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array(
					'name'          => __( 'Payments', 'satori-manifest' ),
					'singular_name' => __( 'Payment', 'satori-manifest' ),
				),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title' ),
				// Administrators only.
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
	 * Records a payment, once per Stripe invoice.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<string,mixed> $data  Fields: user_id, invoice, number, status (paid|failed),
	 *                                    amount (whole units), currency, description, invoice_url, reason.
	 * @return int  Payment ID (the existing one when the invoice is already recorded).
	 */
	public static function record( array $data ): int {
		$invoice  = (string) ( $data['invoice'] ?? '' );
		$existing = '' !== $invoice ? self::find_by_invoice( $invoice, (string) ( $data['status'] ?? '' ) ) : 0;

		if ( $existing ) {
			return $existing;
		}

		$user = get_userdata( (int) ( $data['user_id'] ?? 0 ) );
		$id   = wp_insert_post(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'private',
				'post_title'  => $user ? $user->display_name : (string) ( $data['description'] ?? '' ),
			),
			true
		);

		if ( is_wp_error( $id ) ) {
			return 0;
		}

		foreach ( self::FIELDS as $field ) {
			update_post_meta( $id, self::META . $field, (string) ( $data[ $field ] ?? '' ) );
		}

		return (int) $id;
	}

	/**
	 * Returns recent payments, newest first.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  int $limit    Maximum rows.
	 * @param  int $user_id  Only this client's payments (0 for all).
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit = 50, int $user_id = 0 ): array {
		$args = array(
			'post_type'      => self::CPT,
			'post_status'    => 'private',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		if ( $user_id ) {
			$args['meta_key']   = self::META . 'user_id'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small, admin-side table.
			$args['meta_value'] = (string) $user_id; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}

		return array_map( array( self::class, 'get' ), get_posts( $args ) );
	}

	/**
	 * Returns one payment as an array.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  int $id  Payment ID.
	 * @return array<string,mixed>
	 */
	public static function get( int $id ): array {
		$payment = array(
			'id'   => $id,
			'date' => (string) get_post_field( 'post_date', $id ),
		);

		foreach ( self::FIELDS as $field ) {
			$payment[ $field ] = (string) get_post_meta( $id, self::META . $field, true );
		}

		$payment['user_id'] = (int) $payment['user_id'];
		$payment['amount']  = (float) $payment['amount'];

		return $payment;
	}

	/**
	 * Returns the total paid in the last 30 days, per currency.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @return array<string,float>
	 */
	public static function totals_last_30_days(): array {
		$totals = array();
		$since  = time() - 30 * DAY_IN_SECONDS;

		foreach ( self::recent( 500 ) as $payment ) {
			if ( 'paid' !== $payment['status'] || strtotime( $payment['date'] ) < $since ) {
				continue;
			}

			$currency            = '' !== $payment['currency'] ? $payment['currency'] : 'eur';
			$totals[ $currency ] = ( $totals[ $currency ] ?? 0.0 ) + $payment['amount'];
		}

		return $totals;
	}

	/**
	 * Finds an existing record for an invoice and status.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $invoice  Stripe invoice ID.
	 * @param  string $status   Payment status.
	 * @return int
	 */
	private static function find_by_invoice( string $invoice, string $status ): int {
		$ids = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- webhook de-duplication on a small table.
					array(
						'key'   => self::META . 'invoice',
						'value' => $invoice,
					),
					array(
						'key'   => self::META . 'status',
						'value' => $status,
					),
				),
			)
		);

		return $ids ? (int) $ids[0] : 0;
	}
}
