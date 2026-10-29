<?php
/**
 * Price list PDF.
 *
 * Lays out a client's price list as a print-ready A4 PDF: logo, business
 * name and tagline, every section and item with right-aligned prices,
 * contact details, and a footer on each page with the live page address.
 * Uses the client's brand colours, adjusted when needed so text stays
 * readable on white paper.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.2.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Pdf;

use SatoriManifest\Branding\Brand;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Manifest\Manifest_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Price_List_Pdf
 *
 * Builds the PDF for one client page.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.2.0
 */
class Price_List_Pdf {

	/**
	 * Page margin in points.
	 *
	 * @since 3.2.0
	 * @var   float
	 */
	private const MARGIN = 50.0;

	/**
	 * Space kept clear at the bottom of each page for the footer.
	 *
	 * @since 3.2.0
	 * @var   float
	 */
	private const FOOTER_SPACE = 64.0;

	/**
	 * Document being drawn.
	 *
	 * @since 3.2.0
	 * @var   Pdf_Document
	 */
	private Pdf_Document $pdf;

	/**
	 * Current vertical position (from the top of the page).
	 *
	 * @since 3.2.0
	 * @var   float
	 */
	private float $y = self::MARGIN;

	/**
	 * Resolved brand for the page.
	 *
	 * @since 3.2.0
	 * @var   array<string,mixed>
	 */
	private array $brand;

	/**
	 * Print colours: text, muted, accent, rule (0–1 RGB).
	 *
	 * @since 3.2.0
	 * @var   array<string,array<int,float>>
	 */
	private array $colours;

	/**
	 * Builds the PDF for a client page.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  int $page_id  Client page ID.
	 * @return string  PDF bytes, or '' when the page is not a client page.
	 */
	public static function render( int $page_id ): string {
		if ( ! Client_Repository::is_client_page( $page_id ) ) {
			return '';
		}

		return ( new self( $page_id ) )->build( $page_id );
	}

	/**
	 * Returns a download file name for a page, e.g. "blue-door-barbers-prices.pdf".
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  int $page_id  Client page ID.
	 * @return string
	 */
	public static function filename( int $page_id ): string {
		$slug = sanitize_title( (string) Brand::for_page( $page_id )['name'] );

		return ( '' !== $slug ? $slug : 'price-list' ) . '-prices.pdf';
	}

	/**
	 * Prepares brand and colours.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  int $page_id  Client page ID.
	 */
	private function __construct( int $page_id ) {
		$this->pdf     = new Pdf_Document();
		$this->brand   = Brand::for_page( $page_id );
		$palette       = (array) $this->brand['colours'];
		$this->colours = array(
			'text'   => Print_Colour::readable( (string) ( $palette['text'] ?? '' ), '#1c1c1c', 0.55 ),
			'muted'  => Print_Colour::readable( (string) ( $palette['muted'] ?? '' ), '#6b6b6b', 0.7 ),
			'accent' => Print_Colour::readable( (string) ( $palette['accent'] ?? '' ), '#1c1c1c', 0.75 ),
			'rule'   => Print_Colour::from_hex( '#dcdcdc' ),
		);
	}

	/**
	 * Lays out every part of the document.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  int $page_id  Client page ID.
	 * @return string
	 */
	private function build( int $page_id ): string {
		$this->pdf->set_title( (string) $this->brand['name'] );
		$this->pdf->add_page();

		$this->header();

		$manifest_id = Client_Repository::manifest_for_page( $page_id );
		$sections    = $manifest_id ? Manifest_Repository::get_sections( $manifest_id ) : array();

		foreach ( $sections as $section ) {
			$this->section( (array) $section );
		}

		$this->contact();
		$this->footers( (string) get_permalink( $page_id ) );

		return $this->pdf->output();
	}

	/**
	 * Logo, business name, tagline and a rule in the accent colour.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return void
	 */
	private function header(): void {
		$logo = Pdf_Image::from_attachment( (int) $this->brand['logo_id'] );

		if ( $logo ) {
			$scale = min( 170 / $logo['width'], 60 / $logo['height'], 1.0 );
			$w     = $logo['width'] * $scale;
			$h     = $logo['height'] * $scale;

			$this->pdf->jpeg( $logo['jpeg'], $logo['pixels_w'], $logo['pixels_h'], self::MARGIN, $this->y, $w, $h );
			$this->y += $h + 18;
		}

		foreach ( Font_Metrics::wrap( $this->clean( (string) $this->brand['name'] ), 24, $this->content_width(), true ) as $line ) {
			$this->y += 24;
			$this->pdf->text( self::MARGIN, $this->y, $line, 24, true, $this->colours['text'] );
		}

		$tagline = $this->clean( (string) $this->brand['tagline'] );

		if ( '' !== $tagline ) {
			$this->y += 17;
			$this->pdf->text( self::MARGIN, $this->y, $tagline, 11, false, $this->colours['muted'] );
		}

		$this->y += 16;
		$this->pdf->line( self::MARGIN, $this->y, self::MARGIN + $this->content_width(), $this->y, 1.5, $this->colours['accent'] );
		$this->y += 26;
	}

	/**
	 * One section: title, optional intro and its items.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  array<string,mixed> $section  Section from the manifest.
	 * @return void
	 */
	private function section( array $section ): void {
		$items = (array) ( $section['items'] ?? array() );
		$title = $this->clean( (string) ( $section['title'] ?? '' ) );

		// Keep the title with at least its first item.
		$this->ensure( 60 );

		if ( '' !== $title ) {
			$this->y += 14;
			$this->pdf->text( self::MARGIN, $this->y, $title, 14, true, $this->colours['accent'] );
			$this->y += 8;
		}

		$description = $this->clean( (string) ( $section['description'] ?? '' ) );

		if ( '' !== $description ) {
			$this->paragraph( $description, 9.5, 13, $this->colours['muted'] );
		}

		$this->y += 6;

		foreach ( $items as $item ) {
			$this->item( (array) $item );
		}

		$this->y += 18;
	}

	/**
	 * One item: name (and badge), dotted leader, price, optional description.
	 *
	 * An item without a price is a sub-heading.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  array<string,mixed> $item  Item from the manifest.
	 * @return void
	 */
	private function item( array $item ): void {
		$label     = $this->clean( (string) ( $item['label'] ?? '' ) );
		$raw_price = (string) ( $item['price'] ?? '' );
		$left      = self::MARGIN;
		$right     = self::MARGIN + $this->content_width();

		if ( '' === $raw_price ) {
			if ( '' === $label ) {
				return;
			}

			$this->ensure( 26 );
			$this->y += 16;
			$this->pdf->text( $left, $this->y, Font_Metrics::encode( strtoupper( (string) ( $item['label'] ?? '' ) ) ), 8.5, true, $this->colours['muted'] );
			$this->y += 6;
			return;
		}

		$price = $this->clean( trim( (string) ( $item['price_prefix'] ?? '' ) . ' ' . Brand::format_price( (float) $raw_price, (array) $this->brand['currency'] ) ) );
		$badge = $this->clean( strtoupper( (string) ( $item['badge'] ?? '' ) ) );

		$price_w = Font_Metrics::width( $price, 10.5, true );
		$badge_w = '' !== $badge ? Font_Metrics::width( $badge, 7, true ) + 8 : 0;
		$lines   = Font_Metrics::wrap( $label, 10.5, $this->content_width() - $price_w - $badge_w - 30, true );
		$details = $this->clean( (string) ( $item['description'] ?? '' ) );
		$desc    = '' !== $details ? Font_Metrics::wrap( $details, 9, $this->content_width() - 90 ) : array();

		$this->ensure( 15 * count( $lines ) + 12 * count( $desc ) + 8 );

		foreach ( $lines as $index => $line ) {
			$this->y += 15;
			$this->pdf->text( $left, $this->y, $line, 10.5, true, $this->colours['text'] );

			if ( count( $lines ) - 1 !== $index ) {
				continue;
			}

			$end = $left + Font_Metrics::width( $line, 10.5, true );

			if ( '' !== $badge ) {
				$this->pdf->text( $end + 8, $this->y - 1, $badge, 7, true, $this->colours['accent'] );
				$end += $badge_w;
			}

			$price_x = $right - $price_w;

			if ( $price_x - $end > 16 ) {
				$this->pdf->line( $end + 6, $this->y - 1, $price_x - 6, $this->y - 1, 1.1, $this->colours['muted'], true );
			}

			$this->pdf->text( $price_x, $this->y, $price, 10.5, true, $this->colours['text'] );
		}

		foreach ( $desc as $line ) {
			$this->y += 12;
			$this->pdf->text( $left, $this->y, $line, 9, false, $this->colours['muted'] );
		}

		$this->y += 7;
	}

	/**
	 * Contact details from the client's footer settings.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return void
	 */
	private function contact(): void {
		$footer  = (array) $this->brand['footer'];
		$website = (string) ( $footer['social']['website'] ?? '' );
		$rows    = array_filter(
			array(
				__( 'Address', 'satori-manifest' )       => (string) ( $footer['address'] ?? '' ),
				__( 'Opening hours', 'satori-manifest' ) => (string) ( $footer['hours'] ?? '' ),
				__( 'Phone', 'satori-manifest' )         => (string) ( $footer['phone'] ?? '' ),
				__( 'Email', 'satori-manifest' )         => (string) ( $footer['email'] ?? '' ),
				__( 'Website', 'satori-manifest' )       => $website,
			),
			static fn( string $value ): bool => '' !== trim( $value )
		);
		$note    = $this->clean( (string) ( $footer['note'] ?? '' ) );

		if ( ! $rows && '' === $note ) {
			return;
		}

		$this->ensure( 40 + 26 * count( $rows ) );
		$this->pdf->line( self::MARGIN, $this->y, self::MARGIN + $this->content_width(), $this->y, 0.75, $this->colours['rule'] );
		$this->y += 8;

		foreach ( $rows as $label => $value ) {
			$this->ensure( 30 );
			$this->y += 14;
			$this->pdf->text( self::MARGIN, $this->y, $this->clean( strtoupper( (string) $label ) ), 7.5, true, $this->colours['muted'] );

			foreach ( preg_split( '/\R/', $value ) as $part ) {
				$top = $this->y;
				$this->paragraph( $this->clean( (string) $part ), 10, 14, $this->colours['text'] );

				if ( $website === $value ) {
					$this->pdf->link( self::MARGIN, $top + 2, $this->content_width(), $this->y - $top, esc_url_raw( $website ) );
				}
			}
		}

		if ( '' !== $note ) {
			$this->y += 10;
			$this->paragraph( $note, 9, 12, $this->colours['muted'] );
		}
	}

	/**
	 * Adds "name · page x of y" and the live page address to every page.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  string $page_url  Live page URL.
	 * @return void
	 */
	private function footers( string $page_url ): void {
		$count = $this->pdf->page_count();
		$base  = $this->pdf->height() - self::MARGIN + 8;
		$right = self::MARGIN + $this->content_width();
		$live  = Font_Metrics::encode(
			/* translators: 1: date, 2: page address. */
			sprintf( __( 'Prices as of %1$s · always up to date at %2$s', 'satori-manifest' ), wp_date( (string) get_option( 'date_format' ) ), preg_replace( '#^https?://#', '', untrailingslashit( $page_url ) ) )
		);

		for ( $page = 0; $page < $count; $page++ ) {
			$this->pdf->set_page( $page );
			$this->pdf->line( self::MARGIN, $base - 14, $right, $base - 14, 0.5, $this->colours['rule'] );
			$this->pdf->text( self::MARGIN, $base, $live, 7.5, false, $this->colours['muted'] );
			$this->pdf->link( self::MARGIN, $base - 9, Font_Metrics::width( $live, 7.5 ), 12, $page_url );

			/* translators: 1: page number, 2: page count. */
			$number = Font_Metrics::encode( sprintf( __( 'Page %1$d of %2$d', 'satori-manifest' ), $page + 1, $count ) );
			$this->pdf->text( $right - Font_Metrics::width( $number, 7.5 ), $base, $number, 7.5, false, $this->colours['muted'] );
		}
	}

	/**
	 * Writes wrapped text, moving the cursor down.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  string           $text         Windows-1252 text.
	 * @param  float            $size         Font size.
	 * @param  float            $line_height  Line height.
	 * @param  array<int,float> $rgb          Colour.
	 * @return void
	 */
	private function paragraph( string $text, float $size, float $line_height, array $rgb ): void {
		foreach ( Font_Metrics::wrap( $text, $size, $this->content_width() ) as $line ) {
			$this->ensure( $line_height + 4 );
			$this->y += $line_height;
			$this->pdf->text( self::MARGIN, $this->y, $line, $size, false, $rgb );
		}
	}

	/**
	 * Starts a new page when the next block would not fit.
	 *
	 * Continuation pages repeat the business name in small type.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  float $height  Height the next block needs.
	 * @return void
	 */
	private function ensure( float $height ): void {
		if ( $this->y + $height <= $this->pdf->height() - self::FOOTER_SPACE ) {
			return;
		}

		$this->pdf->add_page();
		$this->y = self::MARGIN;

		/* translators: %s: business name. */
		$this->pdf->text( self::MARGIN, $this->y, Font_Metrics::encode( sprintf( __( '%s — prices (continued)', 'satori-manifest' ), (string) $this->brand['name'] ) ), 8, true, $this->colours['muted'] );
		$this->y += 18;
	}

	/**
	 * Returns the printable width between the margins.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @return float
	 */
	private function content_width(): float {
		return $this->pdf->width() - 2 * self::MARGIN;
	}

	/**
	 * Strips markup and entities, collapses whitespace, and encodes for the PDF fonts.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.2.0
	 * @param  string $text  UTF-8 text.
	 * @return string  Windows-1252 text.
	 */
	private function clean( string $text ): string {
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );

		return Font_Metrics::encode( trim( (string) preg_replace( '/[ \t]+/', ' ', $text ) ) );
	}
}
