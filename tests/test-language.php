<?php
/**
 * Pins language resolution, locale switching and switcher links.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/front/class-language.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Front\Language;

if ( ! function_exists( 'pll_current_language' ) ) {
	/**
	 * Polylang, as far as Language reads it: the stub key decides.
	 *
	 * @param string $field Ignored.
	 * @return string|false
	 */
	function pll_current_language( $field = 'slug' ) {
		return $GLOBALS['tbcards_stub']['pll_language'] ?? false;
	}
}

describe(
	'language',
	function () {

		before_each(
			function () {
				unset( $_GET['lang'] );
				$GLOBALS['tbcards_stub']['options']['locale']       = 'pl_PL';
				$GLOBALS['tbcards_stub']['options']['tbcards_settings'] = array(
					'languages'        => array(
						array(
							'code'   => 'pl',
							'locale' => 'pl_PL',
						),
						array(
							'code'   => 'de',
							'locale' => 'de_DE',
						),
						array(
							'code'   => 'en',
							'locale' => '',
						),
					),
					'default_language' => 'pl',
				);
			}
		);

		it(
			'takes a configured ?lang=',
			function () {
				$_GET['lang'] = 'de';
				assert_same( 'de', ( new Language( new Settings() ) )->current() );
			}
		);

		it(
			'ignores an unconfigured or hostile ?lang=',
			function () {
				$_GET['lang'] = 'fr';
				assert_same( 'pl', ( new Language( new Settings() ) )->current() );

				$_GET['lang'] = '<script>';
				assert_same( 'pl', ( new Language( new Settings() ) )->current() );
			}
		);

		it(
			'follows Polylang in embeds, never on the card page',
			function () {
				$GLOBALS['tbcards_stub']['pll_language'] = 'de';
				$language                            = new Language( new Settings() );

				assert_same( 'de', $language->current( 'embed' ) );
				assert_same( 'pl', $language->current( 'page' ) );
			}
		);

		it(
			'lets ?lang= win over Polylang in embeds',
			function () {
				$GLOBALS['tbcards_stub']['pll_language'] = 'de';
				$_GET['lang']                        = 'en';

				assert_same( 'en', ( new Language( new Settings() ) )->current( 'embed' ) );
			}
		);

		it(
			'switches locale around a callback and returns its value',
			function () {
				$result = ( new Language( new Settings() ) )->with_locale(
					'de',
					function () {
						return 'inside';
					}
				);

				assert_same( 'inside', $result );
				assert_same( array( 'de_DE' ), $GLOBALS['tbcards_stub']['locale_switches'] );
				assert_same( 1, $GLOBALS['tbcards_stub']['locale_restores'] );
			}
		);

		it(
			'restores the locale even when the callback throws',
			function () {
				$caught = false;

				try {
					( new Language( new Settings() ) )->with_locale(
						'de',
						function () {
							throw new RuntimeException( 'boom' );
						}
					);
				} catch ( RuntimeException $e ) {
					$caught = true;
				}

				assert_true( $caught );
				assert_same( 1, $GLOBALS['tbcards_stub']['locale_restores'] );
			}
		);

		it(
			'does not switch for the current locale or an unmapped language',
			function () {
				$language = new Language( new Settings() );

				$language->with_locale( 'pl', '__return_true' );
				$language->with_locale( 'en', '__return_true' );

				assert_same( array(), $GLOBALS['tbcards_stub']['locale_switches'] );
				assert_same( 0, $GLOBALS['tbcards_stub']['locale_restores'] );
			}
		);

		it(
			'uses its own translation when the site lacks the language pack, then puts things back',
			function () {
				$GLOBALS['tbcards_stub']['locales_missing'] = array( 'de_DE' );

				$result = ( new Language( new Settings() ) )->with_locale(
					'de',
					function () {
						return 'inside';
					}
				);

				assert_same( 'inside', $result );
				assert_same( 0, $GLOBALS['tbcards_stub']['locale_restores'], 'core never switched, so nothing to restore' );
				assert_same(
					array(
						'unload:vizito-team-business-cards',
						'load:vizito-team-business-cards:vizito-team-business-cards-de_DE.mo',
						'unload:vizito-team-business-cards',
					),
					$GLOBALS['tbcards_stub']['textdomain_log']
				);
				assert_same( 'vizito-team-business-cards', $GLOBALS['tbcards_stub']['textdomains'][0]['domain'], 'site language reloaded afterwards' );
			}
		);

		it(
			'falls back to English labels, not the site language, when there is no translation at all',
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_settings']['languages'][] = array(
					'code'   => 'fr',
					'locale' => 'fr_FR',
				);
				$GLOBALS['tbcards_stub']['locales_missing'] = array( 'fr_FR' );

				( new Language( new Settings() ) )->with_locale( 'fr', '__return_true' );

				assert_same( 'unload:vizito-team-business-cards', $GLOBALS['tbcards_stub']['textdomain_log'][0] );
				assert_false( in_array( 'load:vizito-team-business-cards:vizito-team-business-cards-fr_FR.mo', $GLOBALS['tbcards_stub']['textdomain_log'], true ) );
			}
		);

		it(
			'always puts the language in switcher links, the default included',
			function () {
				$language = new Language( new Settings() );

				assert_same( 'https://example.test/card/jan/?lang=pl', $language->switch_url( 'https://example.test/card/jan/', 'pl' ) );
				assert_same( 'https://example.test/card/jan/?lang=de', $language->switch_url( 'https://example.test/card/jan/?lang=pl', 'de' ) );
			}
		);
	}
);
