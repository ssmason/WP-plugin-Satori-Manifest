<?php
/**
 * Price parsing and arithmetic for the assistant.
 *
 * Prices are stored as two-decimal strings ("5.50"); an empty string means
 * "no price" (the item shows as a sub-heading or a priceless line).
 * Arithmetic is done in cents to avoid floating-point drift.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp;

use SatoriManifest\Mcp\Server\Tool_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Prices
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Prices {

	/**
	 * Highest price accepted.
	 */
	public const MAX = 100000;

	/**
	 * Normalises a price from the model: 5.5, "5.50", "€5,50", "5,50 €", null or "".
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  mixed $value  Price as given.
	 * @return string  "5.50", or '' for no price.
	 * @throws Tool_Error When it is not a price.
	 */
	public static function parse( $value ): string {
		if ( null === $value || '' === $value ) {
			return '';
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return self::format_cents( self::checked_cents( (float) $value, (string) $value ) );
		}

		$text = trim( preg_replace( '/[^\d.,\-]/u', '', (string) $value ) ?? '' );

		// A comma followed by exactly two digits at the end is a decimal comma ("5,50", "1.250,00").
		if ( preg_match( '/,\d{1,2}$/', $text ) ) {
			$text = str_replace( array( '.', ',' ), array( '', '.' ), $text );
		} else {
			$text = str_replace( ',', '', $text );
		}

		if ( '' === $text || ! is_numeric( $text ) ) {
			throw new Tool_Error( sprintf( '"%s" is not a price. Give a number such as 5.50, or leave the price empty.', (string) $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
		}

		return self::format_cents( self::checked_cents( (float) $text, (string) $value ) );
	}

	/**
	 * Applies a change to a stored price.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  string     $price     Stored price ("5.50").
	 * @param  float|null $percent   Percentage change (10 = +10%, -5 = -5%), or null.
	 * @param  float|null $amount    Fixed change in currency units, or null.
	 * @param  float      $round_to  Round the result to this step (0.05, 0.10, 0.50, 1); 0 for cents.
	 * @return string  New price; '' stays ''.
	 */
	public static function adjust( string $price, ?float $percent, ?float $amount, float $round_to = 0.0 ): string {
		if ( '' === $price ) {
			return '';
		}

		$cents = (int) round( (float) $price * 100 );

		if ( null !== $percent ) {
			$cents = (int) round( $cents * ( 1 + $percent / 100 ) );
		}

		if ( null !== $amount ) {
			$cents += (int) round( $amount * 100 );
		}

		$step = (int) round( $round_to * 100 );

		if ( $step > 1 ) {
			$cents = (int) ( round( $cents / $step ) * $step );
		}

		return self::format_cents( max( 0, min( self::MAX * 100, $cents ) ) );
	}

	/**
	 * Converts and range-checks.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  float  $amount  Amount.
	 * @param  string $given   Original input, for the message.
	 * @return int  Cents.
	 * @throws Tool_Error When out of range.
	 */
	private static function checked_cents( float $amount, string $given ): int {
		if ( $amount < 0 || $amount > self::MAX ) {
			throw new Tool_Error( sprintf( '"%s" is out of range. Prices must be between 0 and %d.', $given, self::MAX ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tool message (JSON), not HTML.
		}

		return (int) round( $amount * 100 );
	}

	/**
	 * Cents to "5.50".
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  int $cents  Cents.
	 * @return string
	 */
	private static function format_cents( int $cents ): string {
		return sprintf( '%d.%02d', intdiv( $cents, 100 ), $cents % 100 );
	}
}
