<?php
/**
 * PDF document writer.
 *
 * A small, dependency-free PDF 1.4 writer — just what a price list needs:
 * pages, text in the standard Helvetica fonts, lines, filled rectangles,
 * JPEG images and web links. Coordinates are in points measured from the
 * top-left corner of the page (PDF itself measures from the bottom-left;
 * the conversion happens here). Page content is Flate-compressed.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.2.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Pdf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Pdf_Document
 *
 * Builds a PDF in memory and returns it as a string.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.2.0
 */
class Pdf_Document {

	/**
	 * A4 width in points.
	 *
	 * @since 3.2.0
	 * @var   float
	 */
	public const A4_WIDTH = 595.28;

	/**
	 * A4 height in points.
	 *
	 * @since 3.2.0
	 * @var   float
	 */
	public const A4_HEIGHT = 841.89;

	/**
	 * Page width in points.
	 *
	 * @since 3.2.0
	 * @var   float
	 */
	private float $width;

	/**
	 * Page height in points.
	 *
	 * @since 3.2.0
	 * @var   float
	 */
	private float $height;

	/**
	 * Content stream operators, one array per page.
	 *
	 * @since 3.2.0
	 * @var   array<int,array<int,string>>
	 */
	private array $pages = array();

	/**
	 * Link annotations per page: array( x, y, w, h, url ) in PDF coordinates.
	 *
	 * @since 3.2.0
	 * @var   array<int,array<int,array<int,mixed>>>
	 */
	private array $links = array();

	/**
	 * Embedded JPEG images: name => array( data, pixel width, pixel height ).
	 *
	 * @since 3.2.0
	 * @var   array<string,array<int,mixed>>
	 */
	private array $images = array();

	/**
	 * Index of the page being drawn on.
	 *
	 * @since 3.2.0
	 * @var   int
	 */
	private int $current = -1;

	/**
	 * Document title (shown in PDF viewers).
	 *
	 * @since 3.2.0
	 * @var   string
	 */
	private string $title = '';

	/**
	 * Creates an empty document.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  float $width   Page width in points (A4 by default).
	 * @param  float $height  Page height in points.
	 */
	public function __construct( float $width = self::A4_WIDTH, float $height = self::A4_HEIGHT ) {
		$this->width  = $width;
		$this->height = $height;
	}

	/**
	 * Sets the document title (UTF-8).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  string $title  Title.
	 * @return void
	 */
	public function set_title( string $title ): void {
		$this->title = $title;
	}

	/**
	 * Starts a new page and makes it current.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return int  Page index.
	 */
	public function add_page(): int {
		$this->pages[] = array();
		$this->links[] = array();
		$this->current = count( $this->pages ) - 1;

		return $this->current;
	}

	/**
	 * Switches drawing to an existing page (e.g. to add page footers last).
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  int $index  Page index.
	 * @return void
	 */
	public function set_page( int $index ): void {
		if ( isset( $this->pages[ $index ] ) ) {
			$this->current = $index;
		}
	}

	/**
	 * Returns the number of pages.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return int
	 */
	public function page_count(): int {
		return count( $this->pages );
	}

	/**
	 * Returns the page width in points.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return float
	 */
	public function width(): float {
		return $this->width;
	}

	/**
	 * Returns the page height in points.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return float
	 */
	public function height(): float {
		return $this->height;
	}

	/**
	 * Draws a line of text with its baseline at $y.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  float            $x     Left edge.
	 * @param  float            $y     Baseline, from the top of the page.
	 * @param  string           $text  Windows-1252 text (Font_Metrics::encode()).
	 * @param  float            $size  Font size in points.
	 * @param  bool             $bold  Helvetica-Bold instead of Helvetica.
	 * @param  array<int,float> $rgb   Colour as 0–1 floats.
	 * @return void
	 */
	public function text( float $x, float $y, string $text, float $size, bool $bold, array $rgb ): void {
		if ( '' === $text ) {
			return;
		}

		$this->op(
			sprintf(
				'BT %s rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET',
				self::rgb( $rgb ),
				$bold ? 'F2' : 'F1',
				$size,
				$x,
				$this->height - $y,
				self::escape( $text )
			)
		);
	}

	/**
	 * Draws a straight line.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  float            $x1     Start x.
	 * @param  float            $y1     Start y (from the top).
	 * @param  float            $x2     End x.
	 * @param  float            $y2     End y (from the top).
	 * @param  float            $width  Line width in points.
	 * @param  array<int,float> $rgb    Colour as 0–1 floats.
	 * @param  bool             $dotted Dotted rather than solid.
	 * @return void
	 */
	public function line( float $x1, float $y1, float $x2, float $y2, float $width, array $rgb, bool $dotted = false ): void {
		$this->op(
			sprintf(
				'%s RG %.2F w %s %.2F %.2F m %.2F %.2F l S [] 0 d 0 J',
				self::rgb( $rgb ),
				$width,
				$dotted ? '1 J [0.1 2.4] 0 d' : '[] 0 d',
				$x1,
				$this->height - $y1,
				$x2,
				$this->height - $y2
			)
		);
	}

	/**
	 * Draws a filled rectangle.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  float            $x    Left.
	 * @param  float            $y    Top (from the top of the page).
	 * @param  float            $w    Width.
	 * @param  float            $h    Height.
	 * @param  array<int,float> $rgb  Fill colour as 0–1 floats.
	 * @return void
	 */
	public function rect( float $x, float $y, float $w, float $h, array $rgb ): void {
		$this->op( sprintf( '%s rg %.2F %.2F %.2F %.2F re f', self::rgb( $rgb ), $x, $this->height - $y - $h, $w, $h ) );
	}

	/**
	 * Places a JPEG image.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  string $jpeg      JPEG file contents (RGB).
	 * @param  int    $pixels_w  Image width in pixels.
	 * @param  int    $pixels_h  Image height in pixels.
	 * @param  float  $x         Left.
	 * @param  float  $y         Top (from the top of the page).
	 * @param  float  $w         Drawn width in points.
	 * @param  float  $h         Drawn height in points.
	 * @return void
	 */
	public function jpeg( string $jpeg, int $pixels_w, int $pixels_h, float $x, float $y, float $w, float $h ): void {
		$name                  = 'Im' . ( count( $this->images ) + 1 );
		$this->images[ $name ] = array( $jpeg, $pixels_w, $pixels_h );

		$this->op( sprintf( 'q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q', $w, $h, $x, $this->height - $y - $h, $name ) );
	}

	/**
	 * Makes a rectangle on the current page a clickable web link.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  float  $x    Left.
	 * @param  float  $y    Top (from the top of the page).
	 * @param  float  $w    Width.
	 * @param  float  $h    Height.
	 * @param  string $url  Absolute http(s) URL.
	 * @return void
	 */
	public function link( float $x, float $y, float $w, float $h, string $url ): void {
		if ( $this->current < 0 || ! preg_match( '#^https?://#i', $url ) ) {
			return;
		}

		$this->links[ $this->current ][] = array( $x, $this->height - $y - $h, $w, $h, $url );
	}

	/**
	 * Returns the finished PDF.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return string  PDF bytes.
	 */
	public function output(): string {
		if ( ! $this->pages ) {
			$this->add_page();
		}

		// Object numbers: 1 catalog, 2 page tree, 3–4 fonts, 5 info, then images, then pages.
		$objects    = array();
		$image_refs = array();
		$next       = 6;

		foreach ( $this->images as $name => $image ) {
			$image_refs[ $name ] = $next;
			$objects[ $next ]    = sprintf(
				"<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
				$image[1],
				$image[2],
				strlen( $image[0] ),
				$image[0]
			);
			++$next;
		}

		$xobjects = '';
		foreach ( $image_refs as $name => $ref ) {
			$xobjects .= sprintf( '/%s %d 0 R ', $name, $ref );
		}

		$resources = '<< /Font << /F1 3 0 R /F2 4 0 R >>' . ( '' !== $xobjects ? ' /XObject << ' . $xobjects . '>>' : '' ) . ' >>';
		$page_refs = array();

		foreach ( $this->pages as $index => $ops ) {
			$page_ref    = $next;
			$content_ref = $next + 1;
			$next       += 2;

			$annots = array();
			foreach ( $this->links[ $index ] as $link ) {
				$objects[ $next ] = sprintf(
					'<< /Type /Annot /Subtype /Link /Rect [%.2F %.2F %.2F %.2F] /Border [0 0 0] /A << /S /URI /URI (%s) >> >>',
					$link[0],
					$link[1],
					$link[0] + $link[2],
					$link[1] + $link[3],
					self::escape( $link[4] )
				);
				$annots[]         = $next . ' 0 R';
				++$next;
			}

			$stream                  = (string) gzcompress( implode( "\n", $ops ) );
			$objects[ $page_ref ]    = sprintf(
				'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources %s /Contents %d 0 R%s >>',
				$this->width,
				$this->height,
				$resources,
				$content_ref,
				$annots ? ' /Annots [' . implode( ' ', $annots ) . ']' : ''
			);
			$objects[ $content_ref ] = sprintf( "<< /Length %d /Filter /FlateDecode >>\nstream\n%s\nendstream", strlen( $stream ), $stream );
			$page_refs[]             = $page_ref . ' 0 R';
		}

		$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[2] = sprintf( '<< /Type /Pages /Kids [%s] /Count %d >>', implode( ' ', $page_refs ), count( $page_refs ) );
		$objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		$objects[5] = sprintf( '<< /Title (%s) /Producer (Satori Manifest) /CreationDate (D:%s) >>', self::escape( Font_Metrics::encode( $this->title ) ), gmdate( 'YmdHis' ) );
		ksort( $objects );

		$pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();

		foreach ( $objects as $number => $body ) {
			$offsets[ $number ] = strlen( $pdf );
			$pdf               .= $number . " 0 obj\n" . $body . "\nendobj\n";
		}

		$xref  = strlen( $pdf );
		$count = max( array_keys( $objects ) ) + 1;
		$pdf  .= "xref\n0 " . $count . "\n0000000000 65535 f \n";

		for ( $i = 1; $i < $count; $i++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] ?? 0 );
		}

		return $pdf . sprintf( "trailer\n<< /Size %d /Root 1 0 R /Info 5 0 R >>\nstartxref\n%d\n%%%%EOF\n", $count, $xref );
	}

	/**
	 * Appends an operator to the current page.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  string $operator  PDF content operator(s).
	 * @return void
	 */
	private function op( string $operator ): void {
		if ( $this->current < 0 ) {
			$this->add_page();
		}

		$this->pages[ $this->current ][] = $operator;
	}

	/**
	 * Formats an RGB colour (0–1 floats) for a content stream.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  array<int,float> $rgb  Red, green, blue.
	 * @return string
	 */
	private static function rgb( array $rgb ): string {
		return sprintf( '%.3F %.3F %.3F', $rgb[0] ?? 0, $rgb[1] ?? 0, $rgb[2] ?? 0 );
	}

	/**
	 * Escapes a string for a PDF literal string.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  string $text  Raw bytes.
	 * @return string
	 */
	private static function escape( string $text ): string {
		return strtr(
			$text,
			array(
				'\\' => '\\\\',
				'('  => '\\(',
				')'  => '\\)',
				"\r" => '\\r',
				"\n" => ' ',
			)
		);
	}
}
