<?php
/**
 * Printed QR codes.
 *
 * Every new client gets one standard-size printed QR code free (see
 * config/printed-codes.yaml). At sign-up they can choose other sizes and more
 * codes: the extra cost and any postage are added to their first payment, after
 * the free trial, and staff are told to print and post them when that payment
 * arrives. Later on a client can order more from their Home screen and pays
 * straight away.
 *
 * The client picks where codes go (a postage region); Stripe Checkout then only
 * accepts a postal address in that region's countries.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
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
 * Class Printed_Codes
 *
 * Postal address, codes at sign-up and later orders.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */
class Printed_Codes {

	/**
	 * The admin-post action for ordering more codes.
	 *
	 * @since 3.8.0
	 * @var   string
	 */
	public const ACTION = 'satori_manifest_order_codes';

	/**
	 * Checkout metadata value marking a codes order.
	 *
	 * @since 3.8.0
	 * @var   string
	 */
	public const ORDER = 'printed_codes';

	/**
	 * Form field holding quantities per size (codes[m]=2).
	 *
	 * @since 3.8.0
	 * @var   string
	 */
	public const FIELD = 'codes';

	/**
	 * Form field holding the postage region.
	 *
	 * @since 3.8.0
	 * @var   string
	 */
	public const REGION_FIELD = 'ship_region';

	/**
	 * User meta: codes waiting to be printed at the first payment ("m:1").
	 *
	 * @since 3.8.0
	 * @var   string
	 */
	public const META_PENDING = '_satori_manifest_codes_pending';

	/**
	 * User meta: postal address from Stripe Checkout.
	 *
	 * @since 3.8.0
	 * @var   string
	 */
	public const META_ADDRESS = '_satori_manifest_postal_address';

	/**
	 * Registers the order handler.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'start_order' ) );
	}

	/**
	 * Whether clients can order codes online (Stripe connected and sizes configured).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return bool
	 */
	public static function can_order(): bool {
		return Stripe_Settings::is_configured() && array() !== Code_Catalogue::sizes();
	}

	/**
	 * Reads an order (size → quantity) from posted form data.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  mixed $posted  Value of the codes field.
	 * @return array<string,int>
	 */
	public static function order_from_input( $posted ): array {
		return Code_Catalogue::order( is_array( $posted ) ? $posted : array() );
	}

	/**
	 * Checkout parameters that collect a postal address in the region's countries.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  string $region  Region slug.
	 * @return array<string,mixed>
	 */
	public static function shipping_params( string $region ): array {
		return array(
			'shipping_address_collection' => array( 'allowed_countries' => Code_Catalogue::regions()[ Code_Catalogue::region( $region ) ]['countries'] ),
		);
	}

	/**
	 * Handles a completed sign-up: stores the address, and adds extra codes and
	 * postage to the first invoice. The setup service is paid at checkout (see
	 * Checkout::create_session()).
	 *
	 * The plan's included codes (Plans::codes_included()) are standard size and
	 * free; codes chosen on the form come on top at full price.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  int                 $user_id  New client.
	 * @param  array<string,mixed> $app      Application.
	 * @param  array<string,mixed> $session  Stripe Checkout session.
	 * @return string  Line for the staff email.
	 */
	public static function on_signup( int $user_id, array $app, array $session ): string {
		$extras = Code_Catalogue::decode( (string) ( $app['codes'] ?? '' ) );
		$order  = self::signup_order( (string) ( $app['plan'] ?? '' ), $extras );

		$region  = Code_Catalogue::region( (string) ( $app['ship_region'] ?? '' ) );
		$address = self::address_from_session( $session );
		$setup   = self::signup_setup( $app );

		update_user_meta( $user_id, self::META_PENDING, Code_Catalogue::encode( $order ) );
		if ( '' !== $address ) {
			update_user_meta( $user_id, self::META_ADDRESS, $address );
		}

		if ( ! empty( $session['customer'] ) ) {
			// Pending invoice items join the subscription's next invoice: the first payment after the trial.
			self::add_invoice_item( $session, Code_Catalogue::codes_cost( $extras ), __( 'Extra printed QR codes', 'satori-manifest' ) );
			self::add_invoice_item( $session, Code_Catalogue::postage( $region, Code_Catalogue::count( $order ) ), __( 'Postage for printed QR codes', 'satori-manifest' ) );
		}

		$line = self::summary( $order, $address );

		if ( $setup > 0 ) {
			/* translators: %s: setup price. */
			$line .= ' ' . sprintf( __( 'Set up the page for them (%s, paid at sign-up).', 'satori-manifest' ), Plans::format( $setup ) );
		}

		return $line;
	}

	/**
	 * Codes to post after sign-up: the plan's included standard codes plus any extras.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.13.0
	 * @param  string            $plan    Plan slug.
	 * @param  array<string,int> $extras  Extra codes chosen on the form.
	 * @return array<string,int>
	 */
	public static function signup_order( string $plan, array $extras ): array {
		$order    = $extras;
		$standard = Code_Catalogue::standard();

		$order[ $standard ] = ( $order[ $standard ] ?? 0 ) + Plans::codes_included( $plan );

		return array_filter( $order );
	}

	/**
	 * What the setup service costs this applicant (0 when not asked for, included or free).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.13.0
	 * @param  array<string,mixed> $app  Application.
	 * @return float
	 */
	public static function signup_setup( array $app ): float {
		if ( empty( $app['setup'] ) ) {
			return 0.0;
		}

		return Plans::setup_cost( (string) ( $app['plan'] ?? '' ), Plans::normalize_interval( (string) ( $app['interval'] ?? '' ) ) );
	}

	/**
	 * Staff line for the codes waiting to be posted at the first payment, then clears them.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  int $user_id  Client.
	 * @return string
	 */
	public static function take_pending( int $user_id ): string {
		$order = Code_Catalogue::decode( (string) get_user_meta( $user_id, self::META_PENDING, true ) );

		delete_user_meta( $user_id, self::META_PENDING );

		return self::summary( $order ? $order : array( Code_Catalogue::standard() => 1 ), (string) get_user_meta( $user_id, self::META_ADDRESS, true ) );
	}

	/**
	 * Starts Stripe Checkout for more printed codes.
	 *
	 * Hooked to 'admin_post_satori_manifest_order_codes'.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @return void
	 */
	public static function start_order(): void {
		// The nonce is read here and verified by Security::verify_form_nonce(); size keys are checked by Code_Catalogue.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$nonce  = isset( $_POST[ Security::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Security::NONCE_FIELD ] ) ) : '';
		$order  = self::order_from_input( isset( $_POST[ self::FIELD ] ) ? array_map( 'intval', (array) wp_unslash( $_POST[ self::FIELD ] ) ) : array() );
		$region = Code_Catalogue::region( isset( $_POST[ self::REGION_FIELD ] ) ? sanitize_key( wp_unslash( $_POST[ self::REGION_FIELD ] ) ) : '' );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$back = Admin_Menu::url( Admin_Menu::QR_SLUG ); // The order form sits on the QR builder.

		if ( ! Security::verify_form_nonce( $nonce, self::ACTION ) || ! Client_Role::is_client() || ! self::can_order() ) {
			wp_die( esc_html__( 'Sorry, you cannot order printed codes.', 'satori-manifest' ), 403 );
		}

		if ( ! $order ) {
			wp_safe_redirect( add_query_arg( 'sm_notice', 'codes-empty', $back ) );
			exit;
		}

		$user     = wp_get_current_user();
		$customer = Subscriptions::for_user( $user->ID )['customer'];
		$sizes    = Code_Catalogue::sizes();
		$items    = array();

		foreach ( $order as $size => $quantity ) {
			/* translators: %s: size label, e.g. "M – 35 × 46 mm". */
			$items[] = self::line_item( sprintf( __( 'Printed QR code, %s', 'satori-manifest' ), $sizes[ $size ]['label'] ), $sizes[ $size ]['price'], $quantity );
		}

		$postage = Code_Catalogue::postage( $region, Code_Catalogue::count( $order ) );
		if ( $postage > 0 ) {
			/* translators: %s: postage region, e.g. "Canada". */
			$items[] = self::line_item( sprintf( __( 'Postage to %s', 'satori-manifest' ), Code_Catalogue::regions()[ $region ]['label'] ), $postage, 1 );
		}

		$params = array_merge(
			array(
				'mode'        => 'payment',
				'line_items'  => $items,
				'metadata'    => array(
					'order'       => self::ORDER,
					'user_id'     => (string) $user->ID,
					'codes'       => Code_Catalogue::encode( $order ),
					'ship_region' => $region,
				),
				'locale'      => 'auto',
				'success_url' => add_query_arg( 'sm_notice', 'codes-ordered', $back ),
				'cancel_url'  => $back,
			),
			'' !== $customer ? array( 'customer' => $customer ) : array( 'customer_email' => $user->user_email ),
			self::shipping_params( $region )
		);

		$session = Stripe_Client::request( 'POST', 'checkout/sessions', $params );

		if ( ! is_array( $session ) || empty( $session['url'] ) ) {
			wp_safe_redirect( add_query_arg( 'sm_notice', 'codes-failed', $back ) );
			exit;
		}

		wp_safe_redirect( (string) $session['url'], 303 );
		exit;
	}

	/**
	 * Handles a paid codes order: records the payment and tells staff to post the codes.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  array<string,mixed> $session  Stripe Checkout session (mode "payment").
	 * @return void
	 */
	public static function on_order_paid( array $session ): void {
		$user_id = (int) ( $session['metadata']['user_id'] ?? 0 );
		$order   = Code_Catalogue::decode( (string) ( $session['metadata']['codes'] ?? '' ) );
		$user    = $user_id ? get_userdata( $user_id ) : false;

		if ( ! $user || ! $order || 'paid' !== ( $session['payment_status'] ?? '' ) ) {
			return;
		}

		$address = self::address_from_session( $session );
		if ( '' !== $address ) {
			update_user_meta( $user_id, self::META_ADDRESS, $address );
		}

		$amount = Stripe_Client::from_minor_units( (int) ( $session['amount_total'] ?? 0 ) );

		Payments::record(
			array(
				'user_id'     => $user_id,
				'invoice'     => (string) ( $session['id'] ?? '' ),
				'status'      => 'paid',
				'amount'      => $amount,
				'currency'    => (string) ( $session['currency'] ?? '' ),
				/* translators: %s: order summary, e.g. "2 × M – 35 × 46 mm". */
				'description' => sprintf( __( 'Printed QR codes: %s', 'satori-manifest' ), Code_Catalogue::describe( $order ) ),
			)
		);

		Billing_Notifier::staff(
			__( 'Printed QR codes ordered: print and post', 'satori-manifest' ),
			(string) $user->display_name,
			(string) $user->user_email,
			self::summary( $order, $address ) . ' ' . Plans::format( $amount, (string) ( $session['currency'] ?? '' ) ) . '.'
		);
	}

	/**
	 * Formats the postal address Stripe collected, '' when there is none.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  array<string,mixed> $session  Stripe Checkout session.
	 * @return string
	 */
	public static function address_from_session( array $session ): string {
		$details = (array) ( $session['collected_information']['shipping_details'] ?? $session['shipping_details'] ?? array() );
		$address = (array) ( $details['address'] ?? array() );

		$parts = array_filter(
			array_map(
				static fn( $part ): string => sanitize_text_field( (string) $part ),
				array(
					$details['name'] ?? '',
					$address['line1'] ?? '',
					$address['line2'] ?? '',
					trim( ( $address['postal_code'] ?? '' ) . ' ' . ( $address['city'] ?? '' ) ),
					$address['state'] ?? '',
					$address['country'] ?? '',
				)
			)
		);

		return implode( ', ', $parts );
	}

	/**
	 * Adds a one-off amount to the customer's next subscription invoice (skipped when 0).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  array<string,mixed> $session      Stripe Checkout session.
	 * @param  float               $amount       Amount.
	 * @param  string              $description  Invoice line text.
	 * @return void
	 */
	private static function add_invoice_item( array $session, float $amount, string $description ): void {
		if ( $amount <= 0 ) {
			return;
		}

		Stripe_Client::request(
			'POST',
			'invoiceitems',
			array(
				'customer'     => (string) $session['customer'],
				'subscription' => (string) ( $session['subscription'] ?? '' ),
				'currency'     => Plans::currency(),
				'amount'       => (string) Stripe_Client::to_minor_units( $amount ),
				'description'  => $description,
			)
		);
	}

	/**
	 * A Checkout line item with an inline one-off price.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  string $name      Product name.
	 * @param  float  $price     Unit price.
	 * @param  int    $quantity  Quantity.
	 * @return array<string,mixed>
	 */
	public static function line_item( string $name, float $price, int $quantity ): array {
		return array(
			'quantity'   => $quantity,
			'price_data' => array(
				'currency'     => Plans::currency(),
				'unit_amount'  => Stripe_Client::to_minor_units( $price ),
				'product_data' => array( 'name' => $name ),
			),
		);
	}

	/**
	 * One line for staff: what to print and where to post it.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.8.0
	 * @param  array<string,int> $order    Order.
	 * @param  string            $address  Postal address ('' when unknown).
	 * @return string
	 */
	private static function summary( array $order, string $address ): string {
		/* translators: %s: order summary, e.g. "2 × M – 35 × 46 mm". */
		$line = sprintf( __( 'Printed QR codes: %s', 'satori-manifest' ), Code_Catalogue::describe( $order ) );

		return '' !== $address
			/* translators: 1: "Printed QR codes: 2 × M", 2: postal address. */
			? sprintf( __( '%1$s. Post to: %2$s.', 'satori-manifest' ), $line, $address )
			/* translators: %s: "Printed QR codes: 2 × M". */
			: sprintf( __( '%s. No postal address given: ask the client.', 'satori-manifest' ), $line );
	}
}
