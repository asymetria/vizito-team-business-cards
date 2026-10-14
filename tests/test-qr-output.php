<?php
/**
 * Pins how settings and the company logo shape a card's QR files.
 *
 * @package Vizito_Team_Business_Cards
 */

$tbcards_root = dirname( __DIR__ );
require_once $tbcards_root . '/includes/class-settings.php';
require_once $tbcards_root . '/includes/card/class-fields.php';
require_once $tbcards_root . '/includes/card/class-card.php';
require_once $tbcards_root . '/includes/company/class-company-profile.php';
require_once $tbcards_root . '/includes/qr/class-qr-colors.php';
require_once $tbcards_root . '/includes/qr/class-qr-service.php';
require_once $tbcards_root . '/includes/qr/class-svg-writer.php';
require_once $tbcards_root . '/includes/qr/class-png-writer.php';
require_once $tbcards_root . '/includes/qr/class-qr-output.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Card\Card;
use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Qr\Qr_Output;
use Asymetria\TeamCards\Qr\Qr_Service;

if ( ! function_exists( 'tbcards_test_qr_output' ) ) {
	/**
	 * Output over fresh services.
	 *
	 * @return Qr_Output
	 */
	function tbcards_test_qr_output(): Qr_Output {
		$settings = new Settings();
		return new Qr_Output( $settings, new Company_Profile( $settings ), new Qr_Service() );
	}
}

describe(
	'qr output',
	function () {

		before_each(
			function () {
				$png = tempnam( sys_get_temp_dir(), 'tbc' ) . '.png';
				file_put_contents( $png, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=' ) );

				$GLOBALS['tbcards_stub']['attached_files'][3]      = $png;
				$GLOBALS['tbcards_stub']['options']['tbcards_company'] = array( 'logo_id' => 3 );
				$GLOBALS['tbcards_stub']['options']['tbcards_settings'] = array(
					'qr_dark'  => '#012169',
					'qr_light' => '#ffffff',
					'qr_logo'  => true,
				);
			}
		);

		it(
			'encodes the card address with src=qr, in the configured colours',
			function () {
				$card = new Card( 5, 'jan', 'pl', array(), 0, 'https://example.test/card/jan/' );
				$svg  = tbcards_test_qr_output()->svg( $card, false );

				assert_contains( 'fill="#012169"', $svg );
				assert_false( str_contains( $svg, '<image' ) );
			}
		);

		it(
			'embeds the logo as a data URI when asked and switched on',
			function () {
				$card = new Card( 5, 'jan', 'pl', array(), 0, 'https://example.test/card/jan/' );

				assert_contains( '<image href="data:image/png;base64,', tbcards_test_qr_output()->svg( $card, true ) );
			}
		);

		it(
			'uses level H with the logo, so the symbol grows',
			function () {
				$card  = new Card( 5, 'jan', 'pl', array(), 0, 'https://example.test/card/a-rather-long-name-here/' );
				$plain = tbcards_test_qr_output()->svg( $card, false );
				$logo  = tbcards_test_qr_output()->svg( $card, true );

				preg_match( '/viewBox="0 0 (\d+)/', $plain, $a );
				preg_match( '/viewBox="0 0 (\d+)/', $logo, $b );

				assert_true( (int) $b[1] > (int) $a[1] );
			}
		);

		it(
			'leaves the logo out when it is switched off or the file is missing',
			function () {
				$card = new Card( 5, 'jan', 'pl', array(), 0, 'https://example.test/card/jan/' );

				$GLOBALS['tbcards_stub']['options']['tbcards_settings']['qr_logo'] = false;
				assert_false( str_contains( tbcards_test_qr_output()->svg( $card, true ), '<image' ) );

				$GLOBALS['tbcards_stub']['options']['tbcards_settings']['qr_logo'] = true;
				$GLOBALS['tbcards_stub']['attached_files'][3]                  = '/nope/logo.png';
				assert_false( str_contains( tbcards_test_qr_output()->svg( $card, true ), '<image' ) );
			}
		);

		it(
			'answers null for PNG without GD, bytes with it',
			function () {
				$card = new Card( 5, 'jan', 'pl', array(), 0, 'https://example.test/card/jan/' );
				$png  = tbcards_test_qr_output()->png( $card, true );

				if ( function_exists( 'imagepng' ) ) {
					assert_true( str_starts_with( (string) $png, "\x89PNG" ) );
				} else {
					assert_same( null, $png );
				}
			}
		);
	}
);
