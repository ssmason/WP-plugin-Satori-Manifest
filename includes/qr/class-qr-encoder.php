<?php
/**
 * QR code encoder.
 *
 * A dependency-free QR Code (ISO/IEC 18004) encoder for text such as page
 * addresses: byte mode, versions 1–40, error correction L/M/Q/H, automatic
 * smallest version and best mask. Returns the module matrix; Qr_Renderer
 * turns it into SVG, PNG or PDF drawing.
 *
 * Follows the structure of Project Nayuki's reference implementation
 * (MIT licence): function patterns, Reed–Solomon error correction over
 * GF(256), block interleaving, zig-zag placement and penalty-based masking.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Qr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Qr_Encoder
 *
 * Text → QR module matrix.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.3.0
 */
class Qr_Encoder {

	/**
	 * Error-correction codewords per block, by level then version (index 0 unused).
	 *
	 * @since 3.3.0
	 * @var   array<string,array<int,int>>
	 */
	private const ECC_PER_BLOCK = array(
		'L' => array( -1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30 ),
		'M' => array( -1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28 ),
		'Q' => array( -1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30 ),
		'H' => array( -1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30 ),
	);

	/**
	 * Number of error-correction blocks, by level then version (index 0 unused).
	 *
	 * @since 3.3.0
	 * @var   array<string,array<int,int>>
	 */
	private const BLOCKS = array(
		'L' => array( -1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25 ),
		'M' => array( -1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49 ),
		'Q' => array( -1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68 ),
		'H' => array( -1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81 ),
	);

	/**
	 * Two-bit format code for each error-correction level.
	 *
	 * @since 3.3.0
	 * @var   array<string,int>
	 */
	private const FORMAT_BITS = array(
		'L' => 1,
		'M' => 0,
		'Q' => 3,
		'H' => 2,
	);

	/**
	 * Side length in modules.
	 *
	 * @since 3.3.0
	 * @var   int
	 */
	private int $size = 0;

	/**
	 * Module colours (true = dark), [row][column].
	 *
	 * @since 3.3.0
	 * @var   array<int,array<int,bool>>
	 */
	private array $modules = array();

	/**
	 * Which modules belong to function patterns (never masked), [row][column].
	 *
	 * @since 3.3.0
	 * @var   array<int,array<int,bool>>
	 */
	private array $function = array();

	/**
	 * Encodes text as a QR code.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  string $text  UTF-8 text, e.g. a URL.
	 * @param  string $ecc   Error correction: L (7%), M (15%), Q (25%) or H (30%).
	 * @return array<int,array<int,bool>>  Matrix [row][column], true = dark.
	 * @throws \InvalidArgumentException  When the text is too long for any version.
	 */
	public static function encode( string $text, string $ecc = 'M' ): array {
		$ecc  = isset( self::FORMAT_BITS[ $ecc ] ) ? $ecc : 'M';
		$data = array_values( (array) unpack( 'C*', $text ) );

		for ( $version = 1; $version <= 40; $version++ ) {
			$count_bits = $version <= 9 ? 8 : 16;
			$needed     = 4 + $count_bits + 8 * count( $data );

			if ( $needed <= self::data_codewords( $version, $ecc ) * 8 && count( $data ) < ( 1 << $count_bits ) ) {
				return ( new self() )->build( $version, $ecc, $data, $count_bits );
			}
		}

		throw new \InvalidArgumentException( 'Text is too long for a QR code.' );
	}

	/**
	 * Builds the symbol for a fixed version.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int            $version     Version 1–40.
	 * @param  string         $ecc         Error-correction level.
	 * @param  array<int,int> $data        Bytes to encode.
	 * @param  int            $count_bits  Width of the character-count field.
	 * @return array<int,array<int,bool>>
	 */
	private function build( int $version, string $ecc, array $data, int $count_bits ): array {
		$this->size     = $version * 4 + 17;
		$this->modules  = array_fill( 0, $this->size, array_fill( 0, $this->size, false ) );
		$this->function = $this->modules;

		$codewords = $this->add_ecc_and_interleave( $this->data_bytes( $version, $ecc, $data, $count_bits ), $version, $ecc );

		$this->draw_function_patterns( $version, $ecc );
		$this->draw_codewords( $codewords );

		$best_mask    = 0;
		$best_penalty = PHP_INT_MAX;

		for ( $mask = 0; $mask < 8; $mask++ ) {
			$this->apply_mask( $mask );
			$this->draw_format_bits( $ecc, $mask );
			$penalty = $this->penalty();

			if ( $penalty < $best_penalty ) {
				$best_mask    = $mask;
				$best_penalty = $penalty;
			}

			$this->apply_mask( $mask ); // XOR again to undo.
		}

		$this->apply_mask( $best_mask );
		$this->draw_format_bits( $ecc, $best_mask );

		return $this->modules;
	}

	/**
	 * Builds the data codewords: mode, count, bytes, terminator and padding.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int            $version     Version.
	 * @param  string         $ecc         Level.
	 * @param  array<int,int> $data        Bytes.
	 * @param  int            $count_bits  Count field width.
	 * @return array<int,int>
	 */
	private function data_bytes( int $version, string $ecc, array $data, int $count_bits ): array {
		$capacity = self::data_codewords( $version, $ecc ) * 8;
		$bits     = array();

		self::append_bits( $bits, 0x4, 4 ); // Byte mode.
		self::append_bits( $bits, count( $data ), $count_bits );

		foreach ( $data as $byte ) {
			self::append_bits( $bits, $byte, 8 );
		}

		self::append_bits( $bits, 0, min( 4, $capacity - count( $bits ) ) );
		self::append_bits( $bits, 0, ( 8 - count( $bits ) % 8 ) % 8 );

		$pad_bytes = intdiv( $capacity - count( $bits ), 8 );

		for ( $i = 0; $i < $pad_bytes; $i++ ) {
			self::append_bits( $bits, 0 === $i % 2 ? 0xEC : 0x11, 8 );
		}

		$bytes = array();
		foreach ( array_chunk( $bits, 8 ) as $chunk ) {
			$bytes[] = (int) bindec( implode( '', $chunk ) );
		}

		return $bytes;
	}

	/**
	 * Splits data into blocks, adds Reed–Solomon codewords and interleaves them.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  array<int,int> $data     Data codewords.
	 * @param  int            $version  Version.
	 * @param  string         $ecc      Level.
	 * @return array<int,int>
	 */
	private function add_ecc_and_interleave( array $data, int $version, string $ecc ): array {
		$num_blocks   = self::BLOCKS[ $ecc ][ $version ];
		$block_ecc    = self::ECC_PER_BLOCK[ $ecc ][ $version ];
		$raw          = intdiv( self::raw_data_modules( $version ), 8 );
		$short_blocks = $num_blocks - $raw % $num_blocks;
		$short_len    = intdiv( $raw, $num_blocks );
		$divisor      = self::rs_divisor( $block_ecc );
		$blocks       = array();
		$offset       = 0;

		for ( $i = 0; $i < $num_blocks; $i++ ) {
			$length  = $short_len - $block_ecc + ( $i < $short_blocks ? 0 : 1 );
			$chunk   = array_slice( $data, $offset, $length );
			$offset += $length;
			$check   = self::rs_remainder( $chunk, $divisor );

			if ( $i < $short_blocks ) {
				$chunk[] = 0; // Placeholder, skipped when interleaving.
			}

			$blocks[] = array_merge( $chunk, $check );
		}

		$result = array();
		$width  = count( $blocks[0] );

		for ( $i = 0; $i < $width; $i++ ) {
			foreach ( $blocks as $j => $block ) {
				if ( $i !== $short_len - $block_ecc || $j >= $short_blocks ) {
					$result[] = $block[ $i ];
				}
			}
		}

		return $result;
	}

	/**
	 * Draws finder, timing and alignment patterns, and reserves format/version areas.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int    $version  Version.
	 * @param  string $ecc      Level.
	 * @return void
	 */
	private function draw_function_patterns( int $version, string $ecc ): void {
		for ( $i = 0; $i < $this->size; $i++ ) {
			$this->set_function( 6, $i, 0 === $i % 2 );
			$this->set_function( $i, 6, 0 === $i % 2 );
		}

		$this->draw_finder( 3, 3 );
		$this->draw_finder( $this->size - 4, 3 );
		$this->draw_finder( 3, $this->size - 4 );

		$positions = self::alignment_positions( $version );
		$count     = count( $positions );

		foreach ( $positions as $i => $x ) {
			foreach ( $positions as $j => $y ) {
				$corner = ( 0 === $i && 0 === $j ) || ( 0 === $i && $count - 1 === $j ) || ( $count - 1 === $i && 0 === $j );

				if ( ! $corner ) {
					$this->draw_alignment( $x, $y );
				}
			}
		}

		$this->draw_format_bits( $ecc, 0 );
		$this->draw_version( $version );
	}

	/**
	 * Draws a 9×9 finder pattern (with separator) centred on (x, y).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int $x  Centre column.
	 * @param  int $y  Centre row.
	 * @return void
	 */
	private function draw_finder( int $x, int $y ): void {
		for ( $dy = -4; $dy <= 4; $dy++ ) {
			for ( $dx = -4; $dx <= 4; $dx++ ) {
				$distance = max( abs( $dx ), abs( $dy ) );
				$col      = $x + $dx;
				$row      = $y + $dy;

				if ( $col >= 0 && $col < $this->size && $row >= 0 && $row < $this->size ) {
					$this->set_function( $col, $row, 2 !== $distance && 4 !== $distance );
				}
			}
		}
	}

	/**
	 * Draws a 5×5 alignment pattern centred on (x, y).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int $x  Centre column.
	 * @param  int $y  Centre row.
	 * @return void
	 */
	private function draw_alignment( int $x, int $y ): void {
		for ( $dy = -2; $dy <= 2; $dy++ ) {
			for ( $dx = -2; $dx <= 2; $dx++ ) {
				$distance = max( abs( $dx ), abs( $dy ) );
				$this->set_function( $x + $dx, $y + $dy, 1 !== $distance );
			}
		}
	}

	/**
	 * Draws both copies of the 15-bit format information.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  string $ecc   Level.
	 * @param  int    $mask  Mask 0–7.
	 * @return void
	 */
	private function draw_format_bits( string $ecc, int $mask ): void {
		$data = self::FORMAT_BITS[ $ecc ] << 3 | $mask;
		$rem  = $data;

		for ( $i = 0; $i < 10; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 9 ) * 0x537 );
		}

		$bits = ( $data << 10 | $rem ) ^ 0x5412;

		for ( $i = 0; $i <= 5; $i++ ) {
			$this->set_function( 8, $i, self::bit( $bits, $i ) );
		}

		$this->set_function( 8, 7, self::bit( $bits, 6 ) );
		$this->set_function( 8, 8, self::bit( $bits, 7 ) );
		$this->set_function( 7, 8, self::bit( $bits, 8 ) );

		for ( $i = 9; $i < 15; $i++ ) {
			$this->set_function( 14 - $i, 8, self::bit( $bits, $i ) );
		}

		for ( $i = 0; $i < 8; $i++ ) {
			$this->set_function( $this->size - 1 - $i, 8, self::bit( $bits, $i ) );
		}

		for ( $i = 8; $i < 15; $i++ ) {
			$this->set_function( 8, $this->size - 15 + $i, self::bit( $bits, $i ) );
		}

		$this->set_function( 8, $this->size - 8, true ); // Always-dark module.
	}

	/**
	 * Draws the two version blocks (versions 7 and up).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int $version  Version.
	 * @return void
	 */
	private function draw_version( int $version ): void {
		if ( $version < 7 ) {
			return;
		}

		$rem = $version;

		for ( $i = 0; $i < 12; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 11 ) * 0x1F25 );
		}

		$bits = $version << 12 | $rem;

		for ( $i = 0; $i < 18; $i++ ) {
			$dark = self::bit( $bits, $i );
			$a    = $this->size - 11 + $i % 3;
			$b    = intdiv( $i, 3 );

			$this->set_function( $a, $b, $dark );
			$this->set_function( $b, $a, $dark );
		}
	}

	/**
	 * Places the codeword bits in the zig-zag order, skipping function modules.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  array<int,int> $codewords  Interleaved codewords.
	 * @return void
	 */
	private function draw_codewords( array $codewords ): void {
		$total = count( $codewords ) * 8;
		$index = 0;

		for ( $right = $this->size - 1; $right >= 1; $right -= 2 ) {
			if ( 6 === $right ) {
				$right = 5; // Skip the vertical timing column.
			}

			for ( $vert = 0; $vert < $this->size; $vert++ ) {
				for ( $j = 0; $j < 2; $j++ ) {
					$x      = $right - $j;
					$upward = 0 === ( ( $right + 1 ) & 2 );
					$y      = $upward ? $this->size - 1 - $vert : $vert;

					if ( ! $this->function[ $y ][ $x ] && $index < $total ) {
						$this->modules[ $y ][ $x ] = self::bit( $codewords[ $index >> 3 ], 7 - ( $index & 7 ) );
						++$index;
					}
				}
			}
		}
	}

	/**
	 * XORs a mask pattern over every non-function module.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int $mask  Mask 0–7.
	 * @return void
	 */
	private function apply_mask( int $mask ): void {
		for ( $y = 0; $y < $this->size; $y++ ) {
			for ( $x = 0; $x < $this->size; $x++ ) {
				if ( $this->function[ $y ][ $x ] ) {
					continue;
				}

				switch ( $mask ) {
					case 0:
						$invert = 0 === ( $x + $y ) % 2;
						break;
					case 1:
						$invert = 0 === $y % 2;
						break;
					case 2:
						$invert = 0 === $x % 3;
						break;
					case 3:
						$invert = 0 === ( $x + $y ) % 3;
						break;
					case 4:
						$invert = 0 === ( intdiv( $x, 3 ) + intdiv( $y, 2 ) ) % 2;
						break;
					case 5:
						$invert = 0 === $x * $y % 2 + $x * $y % 3;
						break;
					case 6:
						$invert = 0 === ( $x * $y % 2 + $x * $y % 3 ) % 2;
						break;
					default:
						$invert = 0 === ( ( $x + $y ) % 2 + $x * $y % 3 ) % 2;
				}

				if ( $invert ) {
					$this->modules[ $y ][ $x ] = ! $this->modules[ $y ][ $x ];
				}
			}
		}
	}

	/**
	 * Scores the symbol with the four standard penalty rules (lower is better).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @return int
	 */
	private function penalty(): int {
		$score = 0;
		$dark  = 0;
		$lines = array();

		for ( $y = 0; $y < $this->size; $y++ ) {
			$row    = '';
			$column = '';

			for ( $x = 0; $x < $this->size; $x++ ) {
				$row    .= $this->modules[ $y ][ $x ] ? '1' : '0';
				$column .= $this->modules[ $x ][ $y ] ? '1' : '0';
				$dark   += $this->modules[ $y ][ $x ] ? 1 : 0;

				// Rule 2: 2×2 blocks of one colour.
				if ( $x > 0 && $y > 0 ) {
					$c = $this->modules[ $y ][ $x ];
					if ( $c === $this->modules[ $y - 1 ][ $x ] && $c === $this->modules[ $y ][ $x - 1 ] && $c === $this->modules[ $y - 1 ][ $x - 1 ] ) {
						$score += 3;
					}
				}
			}

			$lines[] = $row;
			$lines[] = $column;
		}

		foreach ( $lines as $line ) {
			// Rule 1: runs of five or more modules of one colour.
			if ( preg_match_all( '/0{5,}|1{5,}/', $line, $runs ) ) {
				foreach ( $runs[0] as $run ) {
					$score += strlen( $run ) - 2;
				}
			}

			// Rule 3: finder-like patterns (1:1:3:1:1 with four light modules on a side).
			$score += 40 * ( substr_count( '0000' . $line, '00001011101' ) + substr_count( $line . '0000', '10111010000' ) );
		}

		// Rule 4: balance of dark and light.
		$total  = $this->size * $this->size;
		$score += 10 * (int) floor( abs( $dark * 20 - $total * 10 ) / $total );

		return $score;
	}

	/**
	 * Sets a module and marks it as part of a function pattern.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int  $x     Column.
	 * @param  int  $y     Row.
	 * @param  bool $dark  Colour.
	 * @return void
	 */
	private function set_function( int $x, int $y, bool $dark ): void {
		$this->modules[ $y ][ $x ]  = $dark;
		$this->function[ $y ][ $x ] = true;
	}

	/**
	 * Returns the centre positions of alignment patterns for a version.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int $version  Version.
	 * @return array<int,int>
	 */
	private static function alignment_positions( int $version ): array {
		if ( 1 === $version ) {
			return array();
		}

		$count     = intdiv( $version, 7 ) + 2;
		$step      = intdiv( $version * 8 + $count * 3 + 5, $count * 4 - 4 ) * 2;
		$positions = array( 6 );

		$pos = $version * 4 + 17 - 7;

		for ( $i = 1; $i < $count; $i++ ) {
			array_splice( $positions, 1, 0, array( $pos ) );
			$pos -= $step;
		}

		return $positions;
	}

	/**
	 * Returns the number of modules available for data and ECC in a version.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int $version  Version.
	 * @return int
	 */
	private static function raw_data_modules( int $version ): int {
		$result = ( 16 * $version + 128 ) * $version + 64;

		if ( $version >= 2 ) {
			$align   = intdiv( $version, 7 ) + 2;
			$result -= ( 25 * $align - 10 ) * $align - 55;

			if ( $version >= 7 ) {
				$result -= 36;
			}
		}

		return $result;
	}

	/**
	 * Returns how many data codewords a version and level can hold.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int    $version  Version.
	 * @param  string $ecc      Level.
	 * @return int
	 */
	private static function data_codewords( int $version, string $ecc ): int {
		return intdiv( self::raw_data_modules( $version ), 8 ) - self::ECC_PER_BLOCK[ $ecc ][ $version ] * self::BLOCKS[ $ecc ][ $version ];
	}

	/**
	 * Returns the Reed–Solomon generator polynomial of a degree.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int $degree  Degree (number of ECC codewords).
	 * @return array<int,int>
	 */
	private static function rs_divisor( int $degree ): array {
		$result                = array_fill( 0, $degree, 0 );
		$result[ $degree - 1 ] = 1;
		$root                  = 1;

		for ( $i = 0; $i < $degree; $i++ ) {
			for ( $j = 0; $j < $degree; $j++ ) {
				$result[ $j ] = self::gf_multiply( $result[ $j ], $root );

				if ( $j + 1 < $degree ) {
					$result[ $j ] ^= $result[ $j + 1 ];
				}
			}

			$root = self::gf_multiply( $root, 0x02 );
		}

		return $result;
	}

	/**
	 * Returns the Reed–Solomon remainder (the ECC codewords) of data.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  array<int,int> $data     Data codewords.
	 * @param  array<int,int> $divisor  Generator polynomial.
	 * @return array<int,int>
	 */
	private static function rs_remainder( array $data, array $divisor ): array {
		$result = array_fill( 0, count( $divisor ), 0 );

		foreach ( $data as $byte ) {
			$factor   = $byte ^ array_shift( $result );
			$result[] = 0;

			foreach ( $divisor as $i => $coefficient ) {
				$result[ $i ] ^= self::gf_multiply( $coefficient, $factor );
			}
		}

		return $result;
	}

	/**
	 * Multiplies two elements of GF(2^8) modulo x^8 + x^4 + x^3 + x^2 + 1.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int $x  Element.
	 * @param  int $y  Element.
	 * @return int
	 */
	private static function gf_multiply( int $x, int $y ): int {
		$z = 0;

		for ( $i = 7; $i >= 0; $i-- ) {
			$z  = ( $z << 1 ) ^ ( ( $z >> 7 ) * 0x11D );
			$z ^= ( ( $y >> $i ) & 1 ) * $x;
		}

		return $z;
	}

	/**
	 * Appends the low bits of a value to a bit list, most significant first.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  array<int,int> $bits    Bit list (modified).
	 * @param  int            $value   Value.
	 * @param  int            $length  Number of bits.
	 * @return void
	 */
	private static function append_bits( array &$bits, int $value, int $length ): void {
		for ( $i = $length - 1; $i >= 0; $i-- ) {
			$bits[] = ( $value >> $i ) & 1;
		}
	}

	/**
	 * Returns bit $i of $value.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.3.0
	 * @param  int $value  Value.
	 * @param  int $i      Bit index (0 = least significant).
	 * @return bool
	 */
	private static function bit( int $value, int $i ): bool {
		return 0 !== ( ( $value >> $i ) & 1 );
	}
}
