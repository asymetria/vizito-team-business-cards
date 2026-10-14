<?php
/**
 * Pins defaults, reading and sanitising of the plugin settings.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/class-settings.php';

use Asymetria\TeamCards\Settings;

describe(
	'settings: defaults and reading',
	function () {

		it(
			'derives the first language from the site locale',
			function () {
				$GLOBALS['tbcards_stub']['options']['locale'] = 'pl_PL';
				$settings = new Settings();

				assert_same( array( 'pl' ), $settings->languages() );
				assert_same( 'pl', $settings->default_language() );
				assert_same( 'pl_PL', $settings->locale_for( 'pl' ) );
			}
		);

		it(
			'reads saved values over defaults and ignores unknown keys',
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_settings'] = array(
					'languages'        => array(
						array( 'code' => 'pl', 'locale' => 'pl_PL' ),
						array( 'code' => 'de', 'locale' => 'de_DE' ),
					),
					'default_language' => 'pl',
					'url_base'         => 'wizytowka',
					'junk'             => 'x',
				);
				$settings = new Settings();

				assert_same( array( 'pl', 'de' ), $settings->languages() );
				assert_same( 'wizytowka', $settings->get( 'url_base' ) );
				assert_same( null, $settings->get( 'junk' ) );
				assert_same( 'auto', $settings->get( 'scheme' ) );
			}
		);

		it(
			'answers an empty locale for a language it does not have',
			function () {
				assert_same( '', ( new Settings() )->locale_for( 'xx' ) );
			}
		);

		it(
			'survives a non-array option',
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_settings'] = 'garbage';
				assert_same( 'card', ( new Settings() )->get( 'url_base' ) );
			}
		);
	}
);

describe(
	'settings: sanitise',
	function () {

		it(
			'parses language lines from a textarea',
			function () {
				$clean = ( new Settings() )->sanitize(
					array( 'languages' => "pl pl_PL\nDE  de_DE\r\n\nen" )
				);

				assert_same(
					array(
						array( 'code' => 'pl', 'locale' => 'pl_PL' ),
						array( 'code' => 'de', 'locale' => 'de_DE' ),
						array( 'code' => 'en', 'locale' => '' ),
					),
					$clean['languages']
				);
			}
		);

		it(
			'drops invalid codes, invalid locales and duplicates',
			function () {
				$clean = ( new Settings() )->sanitize(
					array( 'languages' => "pl pl_PL\npolski pl_PL\npl de_DE\nde <script>" )
				);

				assert_same(
					array(
						array( 'code' => 'pl', 'locale' => 'pl_PL' ),
						array( 'code' => 'de', 'locale' => '' ),
					),
					$clean['languages']
				);
			}
		);

		it(
			'falls back to the default language list when nothing valid is left',
			function () {
				$GLOBALS['tbcards_stub']['options']['locale'] = 'en_GB';
				$clean = ( new Settings() )->sanitize( array( 'languages' => "!!\n" ) );

				assert_same( array( array( 'code' => 'en', 'locale' => 'en_GB' ) ), $clean['languages'] );
			}
		);

		it(
			'keeps the default language only when it is on the list',
			function () {
				$settings = new Settings();

				$kept = $settings->sanitize(
					array(
						'languages'        => "pl pl_PL\nde de_DE",
						'default_language' => 'de',
					)
				);
				$lost = $settings->sanitize(
					array(
						'languages'        => "pl pl_PL\nde de_DE",
						'default_language' => 'fr',
					)
				);

				assert_same( 'de', $kept['default_language'] );
				assert_same( 'pl', $lost['default_language'] );
			}
		);

		it(
			'normalises the url base and never lets it be empty',
			function () {
				$settings = new Settings();

				assert_same( 'wizytowka', $settings->sanitize( array( 'url_base' => ' Wizytowka/ ' ) )['url_base'] );
				assert_same( 'card', $settings->sanitize( array( 'url_base' => '///' ) )['url_base'] );
			}
		);

		it(
			'accepts only listed schemes, shapes and valid colours',
			function () {
				$clean = ( new Settings() )->sanitize(
					array(
						'scheme'      => 'neon',
						'photo_shape' => 'square',
						'accent'      => 'red',
					)
				);

				assert_same( 'auto', $clean['scheme'] );
				assert_same( 'square', $clean['photo_shape'] );
				assert_same( '#1d4ed8', $clean['accent'] );
			}
		);

		it(
			'casts booleans and bounds retention',
			function () {
				$clean = ( new Settings() )->sanitize(
					array(
						'auto_language'  => '',
						'delete_data'    => '1',
						'retention_days' => '99999',
					)
				);

				assert_false( $clean['auto_language'] );
				assert_true( $clean['delete_data'] );
				assert_same( 3650, $clean['retention_days'] );
			}
		);

		it(
			'asks for a rewrite flush when the url base changes, and only then',
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_settings'] = array( 'url_base' => 'card' );
				$settings = new Settings();

				$settings->sanitize( array( 'url_base' => 'card' ) );
				assert_false( isset( $GLOBALS['tbcards_stub']['options']['tbcards_flush_rewrite'] ) );

				$settings->sanitize( array( 'url_base' => 'wizytowka' ) );
				assert_same( 1, $GLOBALS['tbcards_stub']['options']['tbcards_flush_rewrite'] );
			}
		);

		it(
			'remembers the old url base for the printed-codes warning',
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_settings'] = array( 'url_base' => 'card' );

				( new Settings() )->sanitize( array( 'url_base' => 'wizytowka' ) );

				assert_same( 'card', $GLOBALS['tbcards_stub']['transients']['tbcards_base_changed']['value'] );
			}
		);
	}
);

describe(
	'settings: qr colours',
	function () {

		it(
			'defaults to black on white without a logo',
			function () {
				$settings = new Settings();

				assert_same( '#000000', $settings->get( 'qr_dark' ) );
				assert_same( '#ffffff', $settings->get( 'qr_light' ) );
				assert_false( $settings->get( 'qr_logo' ) );
			}
		);

		it(
			'accepts a readable pair',
			function () {
				$clean = ( new Settings() )->sanitize(
					array(
						'qr_dark'  => '#012169',
						'qr_light' => '#FFFFFF',
						'qr_logo'  => '1',
					)
				);

				assert_same( '#012169', $clean['qr_dark'] );
				assert_same( '#ffffff', $clean['qr_light'] );
				assert_true( $clean['qr_logo'] );
			}
		);

		it(
			'keeps the previous pair and reports why when the new one would not scan',
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_settings'] = array(
					'qr_dark'  => '#012169',
					'qr_light' => '#ffffff',
				);

				$clean = ( new Settings() )->sanitize(
					array(
						'qr_dark'  => '#ffffff',
						'qr_light' => '#000000',
					)
				);

				assert_same( '#012169', $clean['qr_dark'] );
				assert_same( '#ffffff', $clean['qr_light'] );
				assert_same( 'tbcards_qr_colors', $GLOBALS['tbcards_stub']['settings_errors'][0]['code'] );
			}
		);
	}
);

describe(
	'settings: accessibility',
	function () {

		it(
			'saves a light accent but warns that white text on it is hard to read',
			function () {
				$clean = ( new Settings() )->sanitize( array( 'accent' => '#ffd400' ) );

				assert_same( '#ffd400', $clean['accent'] );
				assert_same( 'tbcards_accent_contrast', $GLOBALS['tbcards_stub']['settings_errors'][0]['code'] );
				assert_same( 'warning', $GLOBALS['tbcards_stub']['settings_errors'][0]['type'] );
			}
		);

		it(
			'says nothing about a dark accent',
			function () {
				( new Settings() )->sanitize( array( 'accent' => '#1d4ed8' ) );

				assert_false( isset( $GLOBALS['tbcards_stub']['settings_errors'] ) );
			}
		);
	}
);
