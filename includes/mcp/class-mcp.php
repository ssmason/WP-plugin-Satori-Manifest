<?php
/**
 * AI assistant (MCP) module - the one entry point.
 *
 * Clients connect an AI app (Claude, ChatGPT…) over the Model Context
 * Protocol and manage their own price list, page details and design in
 * plain language. Everything for it lives in includes/mcp/:
 *
 *   server/   Generic MCP over JSON-RPC (Server, Tool, Prompt, Schema_Validator) -
 *             no price-list knowledge, reusable for any tool set.
 *   tools/    One class per tool, built on Client_Tool.
 *   admin/    Client screen (AI assistant) and staff screen (Manifest → AI assistant).
 *   *.php     Access (who may use it), Tokens (connection keys), Endpoint (HTTP),
 *             Page_Store (the only place it writes), Price_List / Prices
 *             (pure helpers), Change_Log (history and undo), Toolbox (wiring).
 *
 * Settings: config/ai-assistant.yaml. Remove the register() call in Plugin
 * and the module is gone.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Mcp;

use SatoriManifest\Mcp\Admin\Access_Screen;
use SatoriManifest\Mcp\Admin\Connect_Screen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mcp
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */
final class Mcp {

	/**
	 * Registers the module's hooks.
	 *
	 * Settings are read lazily inside the callbacks, so theme overrides of
	 * ai-assistant.yaml are honoured.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.9.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( Change_Log::class, 'register_post_type' ) );
		add_action( 'rest_api_init', array( Endpoint::class, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( Endpoint::class, 'serve' ), 10, 3 );
		add_action( 'delete_user', array( Change_Log::class, 'forget' ) );

		Connect_Screen::register();
		Access_Screen::register();
	}
}
