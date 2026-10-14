<?php
/**
 * Which language a card is shown in.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Front;

use Asymetria\TeamCards\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Language' ) ) {

	/**
	 * Resolves the language of a request and switches the locale for labels.
	 *
	 * The full card page ignores Polylang and WPML on purpose: a card address
	 * carries no language prefix and the card is not a translated post. Only
	 * an embed, which sits on an ordinary translated page, follows them.
	 */
	final class Language {

		public const PARAM  = 'lang';
		public const DOMAIN = 'vizito-team-business-cards';

		/**
		 * Plugin settings.
		 *
		 * @var Settings
		 */
		private Settings $settings;

		/**
		 * Constructor.
		 *
		 * @param Settings $settings Plugin settings.
		 */
		public function __construct( Settings $settings ) {
			$this->settings = $settings;
		}

		/**
		 * The language of the current request.
		 *
		 * @param string $context "page" for the card page, "embed" elsewhere.
		 * @return string
		 */
		public function current( string $context = 'page' ): string {
			$codes = $this->settings->languages();

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only language choice.
			$asked = isset( $_GET[ self::PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::PARAM ] ) ) : '';

			if ( in_array( $asked, $codes, true ) ) {
				return $asked;
			}

			if ( 'embed' === $context ) {
				$site = $this->site_language();

				if ( in_array( $site, $codes, true ) ) {
					return $site;
				}
			}

			return $this->settings->default_language();
		}

		/**
		 * Runs a callback with the locale mapped to a language code.
		 *
		 * @param string   $code Language code.
		 * @param callable $fn   Callback.
		 * @return mixed The callback's return value.
		 */
		public function with_locale( string $code, callable $fn ) {
			$locale = $this->settings->locale_for( $code );

			if ( '' === $locale || $locale === determine_locale() ) {
				return $fn();
			}

			$switched = (bool) switch_to_locale( $locale );
			$own      = false;

			/*
			 * Core switches only to locales whose language pack is installed. A
			 * Polish company rarely installs German WordPress just so its cards
			 * have a German version, so the labels would stay Polish on a German
			 * card. The plugin ships its own translations; load the one for this
			 * locale for the time of the callback, or none at all, which shows
			 * English rather than the site's language.
			 */
			if ( ! $switched ) {
				$own = true;
				unload_textdomain( self::DOMAIN, true );

				$mo = TBCARDS_DIR . 'languages/' . self::DOMAIN . '-' . $locale . '.mo';

				if ( is_readable( $mo ) ) {
					load_textdomain( self::DOMAIN, $mo, $locale );
				}
			}

			try {
				return $fn();
			} finally {
				if ( $switched ) {
					restore_previous_locale();
				}

				if ( $own ) {
					unload_textdomain( self::DOMAIN, true );
					// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- reloads the plugin's own bundled translations after a per-card switch.
					load_plugin_textdomain( self::DOMAIN, false, dirname( plugin_basename( TBCARDS_FILE ) ) . '/languages' );
				}
			}
		}

		/**
		 * A switcher link. The language is always explicit, the default one
		 * included, so a click is never overridden by the browser-language
		 * auto switch, which only acts on addresses without the parameter.
		 *
		 * @param string $url  Card address.
		 * @param string $code Language code.
		 * @return string
		 */
		public function switch_url( string $url, string $code ): string {
			return add_query_arg( self::PARAM, $code, $url );
		}

		/**
		 * Polylang's or WPML's current language, "" when neither runs.
		 *
		 * @return string
		 */
		private function site_language(): string {
			if ( function_exists( 'pll_current_language' ) ) {
				$pll = pll_current_language( 'slug' );

				if ( is_string( $pll ) && '' !== $pll ) {
					return $pll;
				}
			}

			$wpml = apply_filters( 'wpml_current_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own public filter, read, not defined, here.

			return is_string( $wpml ) ? $wpml : '';
		}
	}
}
