<?php
/**
 * Validates tool arguments against their JSON Schema.
 *
 * Covers the subset tools use: type (single or list), properties, required,
 * additionalProperties: false, minProperties, items, enum,
 * minLength/maxLength, pattern, minimum/maximum, minItems/maxItems. Unknown keywords are ignored.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp\Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Schema_Validator
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Schema_Validator {

	/**
	 * Most problems reported in one go.
	 */
	private const MAX_ERRORS = 10;

	/**
	 * Validates a value and returns the problems found (empty when valid).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  mixed               $value   Value to check.
	 * @param  array<string,mixed> $schema  JSON Schema.
	 * @param  string              $path    Path for messages.
	 * @return array<int,string>
	 */
	public static function errors( $value, array $schema, string $path = 'arguments' ): array {
		$errors = array();
		self::check( $value, $schema, $path, $errors );

		return array_slice( $errors, 0, self::MAX_ERRORS );
	}

	/**
	 * Recursive check.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  mixed               $value   Value.
	 * @param  array<string,mixed> $schema  Schema.
	 * @param  string              $path    Path.
	 * @param  array<int,string>   $errors  Collected problems.
	 * @return void
	 */
	private static function check( $value, array $schema, string $path, array &$errors ): void {
		if ( count( $errors ) >= self::MAX_ERRORS ) {
			return;
		}

		if ( isset( $schema['type'] ) ) {
			$types = (array) $schema['type'];

			if ( ! self::matches_any( $value, $types ) ) {
				$errors[] = sprintf( '%s must be %s.', $path, implode( ' or ', $types ) );
				return;
			}
		}

		if ( isset( $schema['enum'] ) && ! self::in_enum( $value, (array) $schema['enum'] ) ) {
			$errors[] = sprintf( '%s must be one of: %s.', $path, implode( ', ', array_map( 'strval', (array) $schema['enum'] ) ) );
		}

		if ( is_string( $value ) ) {
			$length = mb_strlen( $value );

			if ( isset( $schema['minLength'] ) && $length < (int) $schema['minLength'] ) {
				$errors[] = sprintf( '%s must be at least %d characters.', $path, (int) $schema['minLength'] );
			}
			if ( isset( $schema['maxLength'] ) && $length > (int) $schema['maxLength'] ) {
				$errors[] = sprintf( '%s must be at most %d characters.', $path, (int) $schema['maxLength'] );
			}
			if ( isset( $schema['pattern'] ) && 1 !== preg_match( '~' . str_replace( '~', '\\~', (string) $schema['pattern'] ) . '~u', $value ) ) {
				$errors[] = sprintf( '%s is not in the expected format.', $path );
			}
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			if ( isset( $schema['minimum'] ) && $value < $schema['minimum'] ) {
				$errors[] = sprintf( '%s must be at least %s.', $path, (string) $schema['minimum'] );
			}
			if ( isset( $schema['maximum'] ) && $value > $schema['maximum'] ) {
				$errors[] = sprintf( '%s must be at most %s.', $path, (string) $schema['maximum'] );
			}
		}

		if ( is_array( $value ) && array_is_list( $value ) && self::allows( $schema, 'array' ) ) {
			self::check_list( $value, $schema, $path, $errors );
			return;
		}

		if ( is_array( $value ) && self::allows( $schema, 'object' ) ) {
			self::check_object( $value, $schema, $path, $errors );
		}
	}

	/**
	 * Checks an array's length and items.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<int,mixed>    $value   List.
	 * @param  array<string,mixed> $schema  Schema.
	 * @param  string              $path    Path.
	 * @param  array<int,string>   $errors  Collected problems.
	 * @return void
	 */
	private static function check_list( array $value, array $schema, string $path, array &$errors ): void {
		if ( isset( $schema['minItems'] ) && count( $value ) < (int) $schema['minItems'] ) {
			$errors[] = sprintf( '%s needs at least %d entries.', $path, (int) $schema['minItems'] );
		}
		if ( isset( $schema['maxItems'] ) && count( $value ) > (int) $schema['maxItems'] ) {
			$errors[] = sprintf( '%s can have at most %d entries.', $path, (int) $schema['maxItems'] );
		}

		if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			foreach ( $value as $index => $item ) {
				self::check( $item, $schema['items'], $path . '[' . $index . ']', $errors );
			}
		}
	}

	/**
	 * Checks an object's required, known and extra properties.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<string,mixed> $value   Object as an associative array.
	 * @param  array<string,mixed> $schema  Schema.
	 * @param  string              $path    Path.
	 * @param  array<int,string>   $errors  Collected problems.
	 * @return void
	 */
	private static function check_object( array $value, array $schema, string $path, array &$errors ): void {
		$properties = (array) ( $schema['properties'] ?? array() );

		if ( isset( $schema['minProperties'] ) && count( $value ) < (int) $schema['minProperties'] ) {
			$errors[] = sprintf( '%s needs at least %d field(s).', $path, (int) $schema['minProperties'] );
		}

		foreach ( (array) ( $schema['required'] ?? array() ) as $required ) {
			if ( ! array_key_exists( (string) $required, $value ) ) {
				$errors[] = sprintf( '%s.%s is required.', $path, (string) $required );
			}
		}

		foreach ( $value as $key => $item ) {
			if ( isset( $properties[ $key ] ) && is_array( $properties[ $key ] ) ) {
				self::check( $item, $properties[ $key ], $path . '.' . $key, $errors );
			} elseif ( false === ( $schema['additionalProperties'] ?? true ) ) {
				$errors[] = sprintf( '%s.%s is not a known field.', $path, (string) $key );
			}
		}
	}

	/**
	 * Whether a value is one of the allowed values (1 and 1.0 count as equal).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  mixed            $value    Value.
	 * @param  array<int,mixed> $allowed  Allowed values.
	 * @return bool
	 */
	private static function in_enum( $value, array $allowed ): bool {
		foreach ( $allowed as $option ) {
			$numbers = ( is_int( $value ) || is_float( $value ) ) && ( is_int( $option ) || is_float( $option ) );

			if ( $numbers ? abs( (float) $value - (float) $option ) < 1e-9 : $value === $option ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a schema allows a type (no type means anything).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  array<string,mixed> $schema  Schema.
	 * @param  string              $type    Type name.
	 * @return bool
	 */
	private static function allows( array $schema, string $type ): bool {
		return ! isset( $schema['type'] ) || in_array( $type, (array) $schema['type'], true );
	}

	/**
	 * Whether a value matches one of the JSON types.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  mixed             $value  Value.
	 * @param  array<int,string> $types  JSON type names.
	 * @return bool
	 */
	private static function matches_any( $value, array $types ): bool {
		foreach ( $types as $type ) {
			$match = match ( $type ) {
				'string'  => is_string( $value ),
				'integer' => is_int( $value ),
				'number'  => is_int( $value ) || is_float( $value ),
				'boolean' => is_bool( $value ),
				'null'    => null === $value,
				// An empty JSON object decodes to an empty array.
				'array'   => is_array( $value ) && array_is_list( $value ),
				'object'  => is_array( $value ) && ( array() === $value || ! array_is_list( $value ) ),
				default   => true,
			};

			if ( $match ) {
				return true;
			}
		}

		return false;
	}
}
