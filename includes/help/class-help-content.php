<?php
/**
 * Help content.
 *
 * Reads config/help.yaml and resolves each step's "screen" key to the URL of
 * that screen for the client being viewed.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace SatoriManifest\Help;

use SatoriManifest\Admin\Admin_Menu;
use SatoriManifest\Clients\Client_Repository;
use SatoriManifest\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Help_Content
 *
 * Structured access to the help YAML.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */
class Help_Content {

	/**
	 * Returns the whole help document with step URLs resolved.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  int $client_id  Client whose screens the steps link to (0 = none).
	 * @return array{title:string,intro:string,steps:array<int,array<string,string>>,faq:array<int,array<string,string>>,resources:array<int,array<string,string>>}
	 */
	public static function document( int $client_id ): array {
		$config = Config::get( 'help.yaml' );
		$steps  = array();

		foreach ( (array) ( $config['steps'] ?? array() ) as $step ) {
			$steps[] = array(
				'title' => (string) ( $step['title'] ?? '' ),
				'text'  => (string) ( $step['text'] ?? '' ),
				'url'   => self::screen_url( (string) ( $step['screen'] ?? '' ), $client_id ),
			);
		}

		return array(
			'title'     => (string) ( $config['title'] ?? '' ),
			'intro'     => (string) ( $config['intro'] ?? '' ),
			'steps'     => $steps,
			'faq'       => self::pairs( (array) ( $config['faq'] ?? array() ), 'q', 'a' ),
			'resources' => self::pairs( (array) ( $config['resources'] ?? array() ), 'label', 'url' ),
		);
	}

	/**
	 * Returns the contextual help tab for a screen key, or null.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $key  Screen key (home, page, prices, design, branding, menus, billing).
	 * @return array{title:string,text:string}|null
	 */
	public static function tab( string $key ): ?array {
		$tab = Config::get( 'help.yaml' )['tabs'][ $key ] ?? null;

		if ( ! is_array( $tab ) || empty( $tab['text'] ) ) {
			return null;
		}

		return array(
			'title' => (string) ( $tab['title'] ?? __( 'Help', 'satori-manifest' ) ),
			'text'  => (string) $tab['text'],
		);
	}

	/**
	 * Returns the admin URL for a screen key, for a given client.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  string $key        Screen key.
	 * @param  int    $client_id  Client user ID.
	 * @return string  URL, or '' when the screen does not apply.
	 */
	public static function screen_url( string $key, int $client_id ): string {
		$page_id     = $client_id ? Client_Repository::page_id( $client_id ) : 0;
		$manifest_id = $client_id ? Client_Repository::manifest_id( $client_id ) : 0;
		$menu_id     = $client_id ? Client_Repository::menu_id( $client_id ) : 0;

		switch ( $key ) {
			case 'home':
				return Admin_Menu::url( Admin_Menu::HOME_SLUG );
			case 'page':
				return $page_id ? admin_url( 'post.php?post=' . $page_id . '&action=edit' ) : '';
			case 'prices':
				return $manifest_id ? admin_url( 'post.php?post=' . $manifest_id . '&action=edit' ) : '';
			case 'design':
				return Admin_Menu::url( Admin_Menu::DESIGN_SLUG, $client_id );
			case 'branding':
				return Admin_Menu::url( Admin_Menu::BRANDING_SLUG, $client_id );
			case 'menus':
				return $menu_id ? admin_url( 'nav-menus.php?action=edit&menu=' . $menu_id ) : '';
			case 'billing':
				return Admin_Menu::url( Admin_Menu::BILLING_SLUG );
			case 'profile':
				return admin_url( 'profile.php' );
		}

		return '';
	}

	/**
	 * Normalises a list of two-key maps.
	 *
	 * @author Stephen Mason <steve@satori-digital.com>
	 * @since  3.0.0
	 * @param  array<int,mixed> $rows    Rows from YAML.
	 * @param  string           $first   First key.
	 * @param  string           $second  Second key.
	 * @return array<int,array<string,string>>
	 */
	private static function pairs( array $rows, string $first, string $second ): array {
		$out = array();

		foreach ( $rows as $row ) {
			if ( is_array( $row ) && ! empty( $row[ $first ] ) && ! empty( $row[ $second ] ) ) {
				$out[] = array(
					$first  => (string) $row[ $first ],
					$second => (string) $row[ $second ],
				);
			}
		}

		return $out;
	}
}
