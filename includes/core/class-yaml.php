<?php
/**
 * Dependency-free YAML reader.
 *
 * Parses the YAML subset used by the plugin's configuration files (ACF field
 * groups, designs, fonts and demo data) so the plugin works on any WordPress
 * stack without Composer packages or the PECL yaml extension.
 *
 * Supported syntax:
 *   - Block mappings and sequences (indentation with spaces).
 *   - Sequences of mappings (`- key: value`).
 *   - Plain, single-quoted and double-quoted scalars.
 *   - Literal (`|`) and folded (`>`) block scalars, with `-` / `+` chomping.
 *   - Single-level flow collections (`[a, b]`, `{ a: 1, b: 2 }`).
 *   - Comments (`# …`) and the `---` document marker.
 *
 * Not supported: anchors/aliases, tags, multi-document streams, nested flow
 * collections. These are rejected or treated as plain strings.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Yaml
 *
 * Minimal recursive-descent YAML parser.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */
class Yaml {

	/**
	 * Regex matching a mapping key at the start of a line's content.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	private const KEY_PATTERN = '/^("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\']|\'\')*\'|[^\s#\'"\[\]{},][^#]*?)\s*:(?=\s|$)/';

	/**
	 * Source lines.
	 *
	 * @since 2.0.0
	 * @var   array<int,string>
	 */
	private array $lines = array();

	/**
	 * Current line pointer.
	 *
	 * @since 2.0.0
	 * @var   int
	 */
	private int $pos = 0;

	/**
	 * Parses a YAML string.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $input  YAML source.
	 * @return mixed          Parsed value (usually an array), null for an empty document.
	 * @throws \UnexpectedValueException When the input uses unsupported or malformed syntax.
	 */
	public static function parse( string $input ) {
		$parser = new self( $input );
		return $parser->parse_document();
	}

	/**
	 * Parses a YAML file and returns its top-level array.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $path  Absolute path to the file.
	 * @return array<mixed>  Parsed data, or an empty array when the file is missing or not a collection.
	 * @throws \UnexpectedValueException When the file contains malformed YAML.
	 */
	public static function parse_file( string $path ): array {
		if ( ! is_readable( $path ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin/theme config file, not a remote URL.
		$contents = file_get_contents( $path );

		try {
			$data = self::parse( (string) $contents );
		} catch ( \UnexpectedValueException $e ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is escaped; $e is the previous exception object.
			throw new \UnexpectedValueException( esc_html( basename( $path ) . ': ' . $e->getMessage() ), 0, $e );
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Splits the input into normalised lines.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $input  YAML source.
	 */
	private function __construct( string $input ) {
		$input       = str_replace( array( "\r\n", "\r" ), "\n", $input );
		$this->lines = explode( "\n", $input );
	}

	/**
	 * Parses the whole document.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return mixed
	 * @throws \UnexpectedValueException On trailing content that could not be parsed.
	 */
	private function parse_document() {
		$line = $this->peek();

		if ( null === $line ) {
			return null;
		}

		$value = $this->parse_node( $this->indent_of( $line ) );

		if ( null !== $this->peek() ) {
			throw new \UnexpectedValueException( esc_html( $this->error( 'Unexpected content (check indentation)' ) ) );
		}

		return $value;
	}

	/**
	 * Parses a block node (mapping or sequence) at the given indent.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int $indent  Indentation of the node's first line.
	 * @return array<mixed>|string|int|float|bool|null
	 */
	private function parse_node( int $indent ) {
		$line = (string) $this->peek();
		$text = ltrim( $line, ' ' );

		if ( $this->is_sequence_item( $text ) ) {
			return $this->parse_sequence( $indent );
		}

		if ( preg_match( self::KEY_PATTERN, $text ) ) {
			return $this->parse_mapping( $indent );
		}

		// A bare scalar on its own line (e.g. a document that is just a value).
		++$this->pos;
		return $this->parse_inline( $text );
	}

	/**
	 * Parses a block sequence.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int $indent  Indentation of the sequence dashes.
	 * @return array<int,mixed>
	 * @throws \UnexpectedValueException On inconsistent indentation.
	 */
	private function parse_sequence( int $indent ): array {
		$result = array();

		while ( null !== ( $line = $this->peek() ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- idiomatic line reader.
			$line_indent = $this->indent_of( $line );

			if ( $line_indent < $indent ) {
				break;
			}

			if ( $line_indent > $indent ) {
				throw new \UnexpectedValueException( esc_html( $this->error( 'Unexpected indentation in list' ) ) );
			}

			$text = ltrim( $line, ' ' );

			if ( ! $this->is_sequence_item( $text ) ) {
				break;
			}

			$rest = ltrim( substr( $text, 1 ), ' ' );

			if ( '' === $this->strip_comment( $rest ) ) {
				++$this->pos;
				$next     = $this->peek();
				$result[] = ( null !== $next && $this->indent_of( $next ) > $indent )
					? $this->parse_node( $this->indent_of( $next ) )
					: null;
				continue;
			}

			if ( preg_match( self::KEY_PATTERN, $rest ) || $this->is_sequence_item( $rest ) ) {
				// "- key: value" — rewrite the line so the mapping (or nested
				// list) starts at the column after the dash, then parse it as a
				// regular block node at that indent.
				$child_indent              = $indent + ( strlen( $text ) - strlen( $rest ) );
				$this->lines[ $this->pos ] = str_repeat( ' ', $child_indent ) . $rest;
				$result[]                  = $this->parse_node( $child_indent );
				continue;
			}

			++$this->pos;
			$result[] = $this->parse_value( $rest, $indent );
		}

		return $result;
	}

	/**
	 * Parses a block mapping.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  int $indent  Indentation of the keys.
	 * @return array<string,mixed>
	 * @throws \UnexpectedValueException On malformed keys or inconsistent indentation.
	 */
	private function parse_mapping( int $indent ): array {
		$result = array();

		while ( null !== ( $line = $this->peek() ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- idiomatic line reader.
			$line_indent = $this->indent_of( $line );

			if ( $line_indent < $indent ) {
				break;
			}

			if ( $line_indent > $indent ) {
				throw new \UnexpectedValueException( esc_html( $this->error( 'Unexpected indentation in mapping' ) ) );
			}

			$text = ltrim( $line, ' ' );

			if ( $this->is_sequence_item( $text ) ) {
				break;
			}

			if ( ! preg_match( self::KEY_PATTERN, $text, $match ) ) {
				throw new \UnexpectedValueException( esc_html( $this->error( 'Expected "key: value"' ) ) );
			}

			$key  = $this->unquote_key( $match[1] );
			$rest = ltrim( substr( $text, strlen( $match[0] ) ), ' ' );
			++$this->pos;

			if ( '' === $this->strip_comment( $rest ) ) {
				$next = $this->peek();

				if ( null !== $next && $this->indent_of( $next ) > $indent ) {
					$result[ $key ] = $this->parse_node( $this->indent_of( $next ) );
				} elseif ( null !== $next && $this->indent_of( $next ) === $indent && $this->is_sequence_item( ltrim( $next, ' ' ) ) ) {
					// Compact form: list at the same indent as its key.
					$result[ $key ] = $this->parse_sequence( $indent );
				} else {
					$result[ $key ] = null;
				}
				continue;
			}

			$result[ $key ] = $this->parse_value( $rest, $indent );
		}

		return $result;
	}

	/**
	 * Parses the value portion of a "key: value" or "- value" line.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $text           Text after the key/dash.
	 * @param  int    $parent_indent  Indentation of the owning key/dash.
	 * @return mixed
	 */
	private function parse_value( string $text, int $parent_indent ) {
		if ( preg_match( '/^([|>])([+-]?)\s*(?:#.*)?$/', $text, $match ) ) {
			return $this->parse_block_scalar( $match[1], $match[2], $parent_indent );
		}

		return $this->parse_inline( $text );
	}

	/**
	 * Parses a literal or folded block scalar.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $style          '|' (literal) or '>' (folded).
	 * @param  string $chomp          '' (clip), '-' (strip) or '+' (keep).
	 * @param  int    $parent_indent  Indentation of the owning key.
	 * @return string
	 */
	private function parse_block_scalar( string $style, string $chomp, int $parent_indent ): string {
		$collected    = array();
		$block_indent = null;
		$total        = count( $this->lines );

		while ( $this->pos < $total ) {
			$raw = $this->lines[ $this->pos ];

			if ( '' === trim( $raw ) ) {
				$collected[] = '';
				++$this->pos;
				continue;
			}

			$line_indent = $this->indent_of( $raw );

			if ( $line_indent <= $parent_indent ) {
				break;
			}

			if ( null === $block_indent ) {
				$block_indent = $line_indent;
			}

			$collected[] = substr( $raw, min( $block_indent, $line_indent ) );
			++$this->pos;
		}

		// Trailing blank lines belong to chomping, not content.
		$trailing = 0;
		while ( ! empty( $collected ) && '' === end( $collected ) ) {
			array_pop( $collected );
			++$trailing;
		}

		if ( '|' === $style ) {
			$value = implode( "\n", $collected );
		} else {
			$value = '';
			foreach ( $collected as $index => $part ) {
				if ( 0 === $index ) {
					$value = $part;
				} elseif ( '' === $part ) {
					$value .= "\n";
				} else {
					$value .= ( str_ends_with( $value, "\n" ) ? '' : ' ' ) . $part;
				}
			}
		}

		if ( '-' === $chomp ) {
			return $value;
		}

		if ( '+' === $chomp ) {
			return $value . str_repeat( "\n", $trailing + 1 );
		}

		return '' === $value ? '' : $value . "\n";
	}

	/**
	 * Parses an inline value: flow collection, quoted or plain scalar.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $text  Raw inline text (may include a trailing comment).
	 * @return mixed
	 * @throws \UnexpectedValueException On unsupported syntax.
	 */
	private function parse_inline( string $text ) {
		$text  = trim( $text );
		$first = $text[0] ?? '';

		if ( '&' === $first || '*' === $first || '!' === $first ) {
			throw new \UnexpectedValueException( esc_html( $this->error( 'Anchors, aliases and tags are not supported' ) ) );
		}

		if ( '"' === $first || "'" === $first ) {
			return $this->parse_quoted( $text );
		}

		$text = $this->strip_comment( $text );

		if ( '[' === $first ) {
			return $this->parse_flow( $text, '[', ']' );
		}

		if ( '{' === $first ) {
			return $this->parse_flow( $text, '{', '}' );
		}

		return $this->cast_plain( $text );
	}

	/**
	 * Parses a single- or double-quoted scalar, ignoring trailing comments.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $text  Text beginning with a quote character.
	 * @return string
	 * @throws \UnexpectedValueException When the closing quote is missing.
	 */
	private function parse_quoted( string $text ): string {
		$quote  = $text[0];
		$length = strlen( $text );
		$out    = '';

		for ( $i = 1; $i < $length; $i++ ) {
			$char = $text[ $i ];

			if ( "'" === $quote ) {
				if ( "'" === $char ) {
					if ( "'" === ( $text[ $i + 1 ] ?? '' ) ) {
						$out .= "'";
						++$i;
						continue;
					}
					return $out;
				}
				$out .= $char;
				continue;
			}

			if ( '\\' === $char ) {
				$next = $text[ $i + 1 ] ?? '';
				$map  = array(
					'n'  => "\n",
					't'  => "\t",
					'"'  => '"',
					'\\' => '\\',
					'/'  => '/',
				);
				$out .= $map[ $next ] ?? $next;
				++$i;
				continue;
			}

			if ( '"' === $char ) {
				return $out;
			}

			$out .= $char;
		}

		throw new \UnexpectedValueException( esc_html( $this->error( 'Unterminated quoted string' ) ) );
	}

	/**
	 * Parses a single-level flow sequence or mapping.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $text   Full flow text including brackets.
	 * @param  string $open   Opening bracket.
	 * @param  string $close  Closing bracket.
	 * @return array<mixed>
	 * @throws \UnexpectedValueException On unbalanced or nested collections.
	 */
	private function parse_flow( string $text, string $open, string $close ): array {
		if ( ! str_ends_with( $text, $close ) ) {
			throw new \UnexpectedValueException( esc_html( $this->error( 'Unclosed flow collection' ) ) );
		}

		$inner = trim( substr( $text, 1, -1 ) );

		if ( '' === $inner ) {
			return array();
		}

		$parts  = $this->split_flow( $inner );
		$result = array();

		foreach ( $parts as $part ) {
			if ( '[' === $part[0] || '{' === $part[0] ) {
				throw new \UnexpectedValueException( esc_html( $this->error( 'Nested flow collections are not supported' ) ) );
			}

			if ( '[' === $open ) {
				$result[] = $this->parse_inline( $part );
				continue;
			}

			if ( ! preg_match( self::KEY_PATTERN, $part, $match ) ) {
				throw new \UnexpectedValueException( esc_html( $this->error( 'Expected "key: value" in flow mapping' ) ) );
			}

			$value                                     = trim( substr( $part, strlen( $match[0] ) ) );
			$result[ $this->unquote_key( $match[1] ) ] = '' === $value ? null : $this->parse_inline( $value );
		}

		return $result;
	}

	/**
	 * Splits flow collection content on top-level commas, respecting quotes.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $inner  Content between the brackets.
	 * @return array<int,string>
	 */
	private function split_flow( string $inner ): array {
		$parts  = array();
		$buffer = '';
		$quote  = '';
		$length = strlen( $inner );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $inner[ $i ];

			if ( '' !== $quote ) {
				$buffer .= $char;
				if ( '\\' === $char && '"' === $quote ) {
					$buffer .= $inner[ $i + 1 ] ?? '';
					++$i;
				} elseif ( $char === $quote ) {
					$quote = '';
				}
				continue;
			}

			// A quote only opens a quoted scalar at the start of an item or value
			// (so apostrophes inside plain words like "today's" stay literal).
			if ( ( '"' === $char || "'" === $char ) && preg_match( '/(^|:)\s*$/', $buffer ) ) {
				$quote   = $char;
				$buffer .= $char;
				continue;
			}

			if ( ',' === $char ) {
				$parts[] = trim( $buffer );
				$buffer  = '';
				continue;
			}

			$buffer .= $char;
		}

		if ( '' !== trim( $buffer ) ) {
			$parts[] = trim( $buffer );
		}

		return $parts;
	}

	/**
	 * Converts a plain scalar to its PHP type.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $text  Plain scalar text (comment already stripped).
	 * @return string|int|float|bool|null
	 */
	private function cast_plain( string $text ) {
		$lower = strtolower( $text );

		if ( '' === $text || '~' === $text || 'null' === $lower ) {
			return null;
		}

		if ( 'true' === $lower ) {
			return true;
		}

		if ( 'false' === $lower ) {
			return false;
		}

		if ( preg_match( '/^-?(?:0|[1-9]\d*)$/', $text ) ) {
			return (int) $text;
		}

		if ( preg_match( '/^-?(?:0|[1-9]\d*)\.\d+$/', $text ) ) {
			return (float) $text;
		}

		return $text;
	}

	/**
	 * Removes a trailing " # comment" from unquoted text.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $text  Text that may contain a comment.
	 * @return string        Trimmed text without the comment.
	 */
	private function strip_comment( string $text ): string {
		if ( str_starts_with( $text, '#' ) ) {
			return '';
		}

		$quote  = '';
		$length = strlen( $text );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $text[ $i ];

			if ( '' !== $quote ) {
				if ( $char === $quote ) {
					$quote = '';
				}
				continue;
			}

			if ( ( '"' === $char || "'" === $char ) && ( 0 === $i || in_array( $text[ $i - 1 ], array( ' ', '[', '{', ',' ), true ) ) ) {
				$quote = $char;
				continue;
			}

			if ( '#' === $char && $i > 0 && ' ' === $text[ $i - 1 ] ) {
				return rtrim( substr( $text, 0, $i ) );
			}
		}

		return trim( $text );
	}

	/**
	 * Removes quotes from a mapping key.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $key  Raw key.
	 * @return string
	 */
	private function unquote_key( string $key ): string {
		$key = trim( $key );

		if ( '' !== $key && ( '"' === $key[0] || "'" === $key[0] ) ) {
			return $this->parse_quoted( $key );
		}

		return $key;
	}

	/**
	 * Returns the next significant line without consuming it.
	 *
	 * Skips (and consumes) blank lines, full-line comments and document markers.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @return string|null  The line, or null at end of input.
	 * @throws \UnexpectedValueException When a line is indented with tabs.
	 */
	private function peek(): ?string {
		$total = count( $this->lines );

		while ( $this->pos < $total ) {
			$line    = $this->lines[ $this->pos ];
			$trimmed = trim( $line );

			if ( '' === $trimmed || str_starts_with( $trimmed, '#' ) || '---' === $trimmed || '...' === $trimmed ) {
				++$this->pos;
				continue;
			}

			if ( preg_match( '/^ *\t/', $line ) ) {
				throw new \UnexpectedValueException( esc_html( $this->error( 'Tabs are not allowed for indentation' ) ) );
			}

			return $line;
		}

		return null;
	}

	/**
	 * Returns true when the text starts a sequence item.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $text  Left-trimmed line content.
	 * @return bool
	 */
	private function is_sequence_item( string $text ): bool {
		return '-' === $text || str_starts_with( $text, '- ' );
	}

	/**
	 * Returns the number of leading spaces on a line.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $line  Raw line.
	 * @return int
	 */
	private function indent_of( string $line ): int {
		return strlen( $line ) - strlen( ltrim( $line, ' ' ) );
	}

	/**
	 * Formats an error message with the current line number.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  2.0.0
	 * @param  string $message  Error description.
	 * @return string
	 */
	private function error( string $message ): string {
		return sprintf( '%s on line %d', $message, $this->pos + 1 );
	}
}
