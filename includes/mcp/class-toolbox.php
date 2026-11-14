<?php
/**
 * Builds the MCP server for one client: tools, prompts and instructions.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp;

use SatoriManifest\Mcp\Server\Prompt;
use SatoriManifest\Mcp\Server\Server;
use SatoriManifest\Mcp\Server\Tool;
use SatoriManifest\Mcp\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Toolbox
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Toolbox {

	/**
	 * Tool classes, in the order AI apps list them.
	 */
	private const TOOLS = array(
		Tools\Get_Page::class,
		Tools\Set_Up_Price_List::class,
		Tools\Update_Items::class,
		Tools\Add_Items::class,
		Tools\Remove_Items::class,
		Tools\Adjust_Prices::class,
		Tools\Add_Section::class,
		Tools\Update_Section::class,
		Tools\Move_Section::class,
		Tools\Remove_Section::class,
		Tools\Add_Heading::class,
		Tools\Update_Heading::class,
		Tools\Get_Page_Layout::class,
		Tools\List_Images::class,
		Tools\Add_Block::class,
		Tools\Update_Block::class,
		Tools\Remove_Block::class,
		Tools\Update_Page_Details::class,
		Tools\List_Designs::class,
		Tools\Choose_Design::class,
		Tools\List_Changes::class,
		Tools\Undo_Last_Change::class,
		Tools\Get_Visits::class,
	);

	/**
	 * The server for a client.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  Client_Context $context  Client.
	 * @return Server
	 */
	public static function server( Client_Context $context ): Server {
		$store  = new Page_Store( $context );
		$server = new Server(
			'satori-manifest',
			sprintf( '%s price list', wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) ),
			defined( 'SATORI_MANIFEST_VERSION' ) ? (string) SATORI_MANIFEST_VERSION : '0',
			self::instructions( $store )
		);

		foreach ( self::tools( $store ) as $tool ) {
			$server->add_tool( $tool );
		}

		foreach ( self::prompts() as $prompt ) {
			$server->add_prompt( $prompt );
		}

		return $server;
	}

	/**
	 * The tools for a client.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  Page_Store $store  Client's page.
	 * @return array<int,Tool>
	 */
	public static function tools( Page_Store $store ): array {
		$tools = array_map( static fn( string $class_name ): Tool => new $class_name( $store ), self::TOOLS );

		/**
		 * Filters the assistant's tools for a client.
		 *
		 * @since 3.9.0
		 * @param Tool[]     $tools  Tools.
		 * @param Page_Store $store  Client's page.
		 */
		return array_values( array_filter( (array) apply_filters( 'satori_manifest_ai_tools', $tools, $store ), static fn( $tool ): bool => $tool instanceof Tool ) );
	}

	/**
	 * Guidance sent to the model when it connects.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @param  Page_Store $store  Client's page.
	 * @return string
	 */
	private static function instructions( Page_Store $store ): string {
		$name = $store->details()['name'];

		return implode(
			"\n",
			array(
				sprintf( 'These tools manage one thing: the public price list page of "%s" (%s), which customers open by scanning a QR code or tapping an NFC sign. Every change is live straight away.', $name, $store->url() ),
				'',
				'- Start with get_page to see the current details and price list (with section ids).',
				'- Setting up from a menu: read the photo, PDF or text the user shares and use set_up_price_list. Keep the business\'s own wording, spelling and order, one section per category (Starters, Mains…). Never invent items or prices; ask about anything you cannot read.',
				'- Headings: a menu with big titles over several categories (e.g. "Main Menu" over Mains, Burgers and Sides) gets a heading, which groups every section below it up to the next heading. Use the heading field of set_up_price_list, or add_heading / update_heading. "Turn the breakfast menu off" means update_heading with visible: false.',
				'- On the page each heading is a banner of its own with its menu below it, laid out automatically. For a temporary or extra menu ("Christmas Menu", "Today\'s special: ham, egg & chips"): add_heading, then add_section(s) straight after it, and make sure the next heading follows them, so it does not take over sections that were already there. A heading placed above existing sections with no heading after them would take those sections too; ask the user first. Switch a seasonal menu off with update_heading visible: false rather than deleting it.',
				'- Prices are plain numbers (5.5, not "€5,50"); the currency is a page detail. An item with no price shows as a sub-heading. Put allergens, sizes or notes in the item description.',
				'- Ask before replacing an existing list, removing a section or loading a design\'s layout. For price rises across many items, run adjust_prices with preview first and show the result.',
				'- After a change, say in one line what changed and give the page link. Mention that "undo" puts the last change back.',
				'- Page content: the client can also add their own blocks (text, headings, images, buttons…) inside the page\'s groups. Call get_page_layout first, then add_block / update_block / remove_block. Images must be the client\'s own (list_images); they upload new ones in the dashboard. Price lists and heading banners on the page come from the price list - change those with the price list tools.',
				'- These tools cannot change billing, the account, upload photos or anything outside this page; for those, point the user to their dashboard.',
			)
		);
	}

	/**
	 * Ready-made requests users can pick in their AI app.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return array<int,Prompt>
	 */
	private static function prompts(): array {
		return array(
			new Prompt(
				'set_up_from_menu',
				__( 'Set up my page from my menu', 'satori-manifest' ),
				__( 'Share a photo, PDF or text of your menu or price list and the assistant builds your page from it.', 'satori-manifest' ),
				"I'm sharing my menu / price list. Please set up my page from it: keep my wording and order, one section per heading, prices as numbers. Ask me about anything you can't read before saving. Then suggest a design that suits my business.\n\nBusiness type: {business_type}",
				array(
					array(
						'name'        => 'business_type',
						'description' => __( 'e.g. Irish bar, barber, nail salon', 'satori-manifest' ),
						'required'    => false,
					),
				)
			),
			new Prompt(
				'change_prices',
				__( 'Change my prices', 'satori-manifest' ),
				__( 'Raise or lower prices across the whole list or some sections, with a preview first.', 'satori-manifest' ),
				'Please change my prices by {change} for {which}. Round sensibly for my currency. Show me a preview before saving.',
				array(
					array(
						'name'        => 'change',
						'description' => __( 'e.g. 5%, 50 cents', 'satori-manifest' ),
						'required'    => true,
					),
					array(
						'name'        => 'which',
						'description' => __( 'e.g. everything, cocktails', 'satori-manifest' ),
						'required'    => false,
					),
				)
			),
			new Prompt(
				'add_specials',
				__( 'Add specials', 'satori-manifest' ),
				__( 'Add a section of specials or seasonal items.', 'satori-manifest' ),
				'Add a section called "{title}" near the top of my price list with these items: {items}',
				array(
					array(
						'name'        => 'title',
						'description' => __( 'e.g. Today\'s specials', 'satori-manifest' ),
						'required'    => true,
					),
					array(
						'name'        => 'items',
						'description' => __( 'Items and prices', 'satori-manifest' ),
						'required'    => true,
					),
				)
			),
			new Prompt(
				'check_my_page',
				__( 'Check my price list', 'satori-manifest' ),
				__( 'Look for typos, missing prices and inconsistencies, and suggest fixes.', 'satori-manifest' ),
				'Please check my price list and page details for typos, missing prices, inconsistent names or formatting, and anything a customer might find confusing. List what you would change and wait for my OK before changing anything.'
			),
		);
	}
}
