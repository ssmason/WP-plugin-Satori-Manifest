<?php
/**
 * Plan changes: a client moves between plans (e.g. Starter ↔ Plus) on the same
 * billing period, keeping their renewal date.
 *
 * Money (Stripe works out the exact amounts):
 *   - During the free trial nothing is charged; the first payment is the new price.
 *   - Monthly upgrade: the difference for the rest of the month joins the next payment.
 *   - Yearly upgrade: the difference for the rest of the year is charged now (otherwise
 *     the upgrade would be free until the renewal).
 *   - Downgrade: the unused part becomes a credit on the next payment.
 *
 * The plan is saved locally straight away (so the AI assistant switches on or off at
 * once) and again by the customer.subscription.updated webhook. The client and
 * staff are emailed.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.15.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Billing;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Clients\Client_Role;
use SatoriManifest\Core\Security;
use SatoriManifest\Registration\Plans;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plan_Change
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.15.0
 */
final class Plan_Change {

	/**
	 * Form action (admin-post).
	 */
	public const ACTION = 'satori_manifest_change_plan';

	/**
	 * Option: Stripe product id per plan, per mode ("test" / "live").
	 */
	public const PRODUCTS_OPTION = 'satori_manifest_stripe_products';

	/**
	 * Hooks the form handler.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.15.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * The plan a client can move to, with what changes and when they pay.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.15.0
	 * @param  int $user_id  Client.
	 * @return array<string,mixed>|null  Null when the client cannot change plan online.
	 */
	public static function offer( int $user_id ): ?array {
		$sub = Subscriptions::for_user( $user_id );

		if ( '' === $sub['id'] || ! in_array( $sub['status'], array( 'trialing', 'active' ), true ) || ! Plans::get( $sub['plan'] ) ) {
			return null;
		}

		$interval = Plans::normalize_interval( $sub['interval'] );
		$current  = Plans::price( $sub['plan'], $interval );

		foreach ( array_keys( Plans::all() ) as $slug ) {
			$price = Plans::price( $slug, $interval );

			if ( $slug === $sub['plan'] || $price <= 0 ) {
				continue;
			}

			$upgrade = $price > $current;

			return array(
				'from'     => $sub['plan'],
				'to'       => $slug,
				'upgrade'  => $upgrade,
				'interval' => $interval,
				'price'    => $price,
				'current'  => $current,
				'trial'    => 'trialing' === $sub['status'],
				'renewal'  => (int) $sub['current_period_end'],
				'estimate' => self::estimate( $price - $current, $interval, (int) $sub['current_period_end'] ),
				// Upgrading gains the dearer plan's extras; downgrading loses the current plan's.
				'changes'  => $upgrade ? Plans::extras( $slug ) : Plans::extras( $sub['plan'] ),
			);
		}

		return null;
	}

	/**
	 * Rough share of a price difference for the rest of the period (Stripe charges the exact amount).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.15.0
	 * @param  float  $difference  New price minus current price (negative for a downgrade).
	 * @param  string $interval    "month" or "year".
	 * @param  int    $period_end  Renewal timestamp.
	 * @return float  Absolute amount, 0 when unknown.
	 */
	public static function estimate( float $difference, string $interval, int $period_end ): float {
		$length = 'year' === $interval ? YEAR_IN_SECONDS : 30 * DAY_IN_SECONDS;
		$left   = max( 0, $period_end - time() );

		return $period_end ? round( abs( $difference ) * min( 1, $left / $length ), 2 ) : 0.0;
	}

	/**
	 * Moves a client to another plan in Stripe and locally, then emails them and staff.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.15.0
	 * @param  int    $user_id  Client.
	 * @param  string $to       Target plan slug.
	 * @return true|\WP_Error
	 */
	public static function change( int $user_id, string $to ) {
		$offer = self::offer( $user_id );

		if ( ! $offer || $offer['to'] !== $to ) {
			return new \WP_Error( 'satori_manifest_plan', __( 'That plan change is not available.', 'satori-manifest' ) );
		}

		$sub    = Subscriptions::for_user( $user_id );
		$stripe = Stripe_Client::request( 'GET', 'subscriptions/' . rawurlencode( $sub['id'] ) );

		if ( ! is_array( $stripe ) || empty( $stripe['items']['data'][0]['id'] ) ) {
			return new \WP_Error( 'satori_manifest_plan', __( 'We could not reach our payment provider. Please try again in a minute.', 'satori-manifest' ) );
		}

		$item = self::item( $to, $offer['interval'] );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$item['id'] = (string) $stripe['items']['data'][0]['id'];

		$updated = Stripe_Client::request(
			'POST',
			'subscriptions/' . rawurlencode( $sub['id'] ),
			array(
				'items'              => array( $item ),
				'proration_behavior' => self::proration( $offer ),
				'metadata'           => array( 'plan' => $to ),
			)
		);

		if ( ! is_array( $updated ) ) {
			return new \WP_Error( 'satori_manifest_plan', __( 'We could not change your plan just now. Nothing has been charged. Please try again in a minute.', 'satori-manifest' ) );
		}

		Subscriptions::save(
			$user_id,
			array(
				'plan'   => $to,
				'amount' => $offer['price'],
			)
		);
		Subscriptions::sync_from_stripe( $updated, $user_id );

		self::notify( $user_id, $offer );

		return true;
	}

	/**
	 * How Stripe should handle the price difference.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.15.0
	 * @param  array<string,mixed> $offer  From offer().
	 * @return string
	 */
	public static function proration( array $offer ): string {
		if ( $offer['trial'] ) {
			return 'none';
		}

		return $offer['upgrade'] && 'year' === $offer['interval'] ? 'always_invoice' : 'create_prorations';
	}

	/**
	 * The subscription item for a plan: its Stripe Price ID, or an inline price on the plan's product.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.15.0
	 * @param  string $plan      Plan slug.
	 * @param  string $interval  "month" or "year".
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function item( string $plan, string $interval ) {
		$price_id = Plans::stripe_price( $plan, $interval );

		if ( '' !== $price_id ) {
			return array( 'price' => $price_id );
		}

		$product = self::product( $plan );

		if ( '' === $product ) {
			return new \WP_Error( 'satori_manifest_plan', __( 'We could not reach our payment provider. Please try again in a minute.', 'satori-manifest' ) );
		}

		return array(
			'price_data' => array(
				'currency'    => Plans::currency(),
				'product'     => $product,
				'unit_amount' => Stripe_Client::to_minor_units( Plans::price( $plan, $interval ) ),
				'recurring'   => array( 'interval' => $interval ),
			),
		);
	}

	/**
	 * The Stripe product for a plan, created once per mode and remembered.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.15.0
	 * @param  string $plan  Plan slug.
	 * @return string  Product id, '' on failure.
	 */
	private static function product( string $plan ): string {
		$mode     = Stripe_Settings::mode();
		$products = (array) get_option( self::PRODUCTS_OPTION, array() );

		if ( ! empty( $products[ $mode ][ $plan ] ) ) {
			return (string) $products[ $mode ][ $plan ];
		}

		$product = Stripe_Client::request(
			'POST',
			'products',
			array(
				/* translators: 1: site name, 2: plan title. */
				'name'     => sprintf( __( '%1$s: %2$s', 'satori-manifest' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), Plans::title( $plan ) ),
				'metadata' => array( 'plan' => $plan ),
			)
		);

		if ( ! is_array( $product ) || empty( $product['id'] ) ) {
			return '';
		}

		$products[ $mode ][ $plan ] = (string) $product['id'];
		update_option( self::PRODUCTS_OPTION, $products, false );

		return (string) $product['id'];
	}

	/**
	 * Emails the client and staff about the change.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.15.0
	 * @param  int                 $user_id  Client.
	 * @param  array<string,mixed> $offer    From offer().
	 * @return void
	 */
	private static function notify( int $user_id, array $offer ): void {
		$price = Plans::format( $offer['price'] ) . ' ' . Plans::period_label( $offer['interval'] );
		$note  = self::payment_note( $offer );

		Billing_Notifier::client(
			'plan-changed',
			$user_id,
			array(
				'plan'         => Plans::title( $offer['to'] ),
				'old_plan'     => Plans::title( $offer['from'] ),
				'price'        => $price,
				'change_note'  => ( $offer['upgrade']
					? __( 'You now have:', 'satori-manifest' )
					: __( 'No longer included:', 'satori-manifest' ) ) . ' ' . implode( '; ', $offer['changes'] ) . '.',
				'payment_note' => $note,
			)
		);

		$user = get_userdata( $user_id );

		Billing_Notifier::staff(
			$offer['upgrade'] ? __( 'Plan upgraded', 'satori-manifest' ) : __( 'Plan downgraded', 'satori-manifest' ),
			$user ? (string) $user->display_name : '',
			$user ? (string) $user->user_email : '',
			Plans::title( $offer['from'] ) . ' → ' . Plans::title( $offer['to'] ) . ', ' . $price . '. ' . $note
		);
	}

	/**
	 * One sentence on what the client pays, and when.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.15.0
	 * @param  array<string,mixed> $offer  From offer().
	 * @return string
	 */
	public static function payment_note( array $offer ): string {
		$date  = $offer['renewal'] ? wp_date( (string) get_option( 'date_format' ), $offer['renewal'] ) : '';
		$price = Plans::format( $offer['price'] ) . ' ' . Plans::period_label( $offer['interval'] );
		$about = Plans::format( $offer['estimate'] );

		if ( $offer['trial'] ) {
			/* translators: 1: date, 2: price and period, e.g. "€15 per month". */
			return sprintf( __( 'Nothing to pay now. Your first payment on %1$s will be %2$s.', 'satori-manifest' ), $date, $price );
		}

		if ( ! $offer['upgrade'] ) {
			/* translators: 1: estimated credit, 2: date, 3: price and period. */
			return sprintf( __( 'The unused part of your current plan (about %1$s) comes off your next payment on %2$s. After that it is %3$s.', 'satori-manifest' ), $about, $date, $price );
		}

		if ( 'year' === $offer['interval'] ) {
			/* translators: 1: estimated amount, 2: price and period, 3: date. */
			return sprintf( __( 'We charge the difference for the rest of your year now (about %1$s). Then %2$s from %3$s.', 'satori-manifest' ), $about, $price, $date );
		}

		/* translators: 1: estimated amount, 2: date, 3: price and period. */
		return sprintf( __( 'The difference for the rest of this month (about %1$s) is added to your next payment on %2$s. After that it is %3$s.', 'satori-manifest' ), $about, $date, $price );
	}

	/**
	 * Handles the confirm button. Hooked to admin_post_satori_manifest_change_plan.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.15.0
	 * @return void
	 */
	public static function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified with Security::verify_form_nonce() first.
		$nonce = isset( $_POST[ Security::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ Security::NONCE_FIELD ] ) ) : '';
		$to    = isset( $_POST['plan'] ) ? sanitize_key( wp_unslash( $_POST['plan'] ) ) : '';
		$from  = isset( $_POST['return'] ) ? sanitize_key( wp_unslash( $_POST['return'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! Security::verify_form_nonce( $nonce, self::ACTION ) || ! Client_Role::is_client() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'satori-manifest' ), 403 );
		}

		$result = self::change( get_current_user_id(), $to );
		$back   = Admin_Menu::url( Admin_Menu::BILLING_SLUG );

		if ( ! is_wp_error( $result ) && 'ai' === $from ) {
			// Upgraded from the AI assistant screen: straight back to connect it.
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => \SatoriManifest\Mcp\Admin\Connect_Screen::SLUG,
						'sm_ai' => 'upgraded',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		wp_safe_redirect( is_wp_error( $result ) ? add_query_arg( 'sm_error', 'plan-change', $back ) : add_query_arg( 'sm_notice', 'plan-changed', $back ) );
		exit;
	}
}
