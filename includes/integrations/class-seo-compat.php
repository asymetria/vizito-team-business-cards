<?php
/**
 * Compatibility with SEO plugins.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Integrations;

use Asymetria\TeamCards\Card\Card_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\Seo_Compat' ) ) {

	/**
	 * Yoast SEO's "Remove unregistered URL parameters" (crawl optimisation)
	 * answers any address with a parameter it does not know by a 301 to the
	 * bare address. On a card that strips ?lang= (the language switcher stops
	 * working) and ?src=qr (every scan of a printed code counts as a plain
	 * visit). Found on a live site; the stub suite could not see it.
	 *
	 * The two parameters are allowed on card pages only, so the rest of the
	 * site keeps Yoast's behaviour.
	 */
	final class Seo_Compat {

		public const PARAMS = array( 'lang', 'src' );

		/**
		 * Hooks.
		 *
		 * @return void
		 */
		public function hooks(): void {
			add_filter( 'Yoast\WP\SEO\allowlist_permalink_vars', array( $this, 'allow_card_params' ) );
		}

		/**
		 * Adds the card parameters to Yoast's allow-list on card pages.
		 *
		 * @param mixed $vars Allowed parameter names.
		 * @return mixed
		 */
		public function allow_card_params( $vars ) {
			if ( ! is_array( $vars ) || ! is_singular( Card_Repository::POST_TYPE ) ) {
				return $vars;
			}

			return array_values( array_unique( array_merge( $vars, self::PARAMS ) ) );
		}
	}
}
