<?php
/**
 * Pins how raw meta and the company profile become a Card.
 *
 * @package Vizito_Team_Business_Cards
 */

$tbcards_root = dirname( __DIR__ );
require_once $tbcards_root . '/includes/class-settings.php';
require_once $tbcards_root . '/includes/card/class-fields.php';
require_once $tbcards_root . '/includes/card/class-field-sanitizer.php';
require_once $tbcards_root . '/includes/card/class-card.php';
require_once $tbcards_root . '/includes/company/class-company-profile.php';
require_once $tbcards_root . '/includes/card/class-card-repository.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Company\Company_Profile;

if ( ! function_exists( 'tbcards_test_repository' ) ) {
	/**
	 * A repository over fresh settings and company profile.
	 *
	 * @return Card_Repository
	 */
	function tbcards_test_repository(): Card_Repository {
		$settings = new Settings();
		return new Card_Repository( $settings, new Company_Profile( $settings ) );
	}
}

describe(
	'card repository: find',
	function () {

		before_each(
			function () {
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
					),
					'default_language' => 'pl',
				);
				$GLOBALS['tbcards_stub']['options']['tbcards_company'] = array(
					'intro'   => array(
						'pl' => 'Intro firmy',
						'de' => 'Firmen-Intro',
					),
					'city'    => 'Warszawa',
					'website' => 'https://firma.example',
				);
				$GLOBALS['tbcards_stub']['posts_by_id'][5] = (object) array(
					'ID'          => 5,
					'post_type'   => 'tbcards_card',
					'post_name'   => 'jan-kowalski',
					'post_status' => 'publish',
				);
				$GLOBALS['tbcards_stub']['post_meta'][5] = array(
					'_tbcards_first_name' => 'Jan',
					'_tbcards_last_name'  => 'Kowalski',
					'_tbcards_job_title'  => array(
						'pl' => 'Prezes',
						'de' => 'Geschäftsführer',
					),
					'_tbcards_city'       => 'Kraków',
				);
				$GLOBALS['tbcards_stub']['thumbnails'][5] = 99;
				$GLOBALS['tbcards_stub']['permalinks'][5] = 'https://example.test/card/jan-kowalski/';
			}
		);

		it(
			'answers null for a missing post or another post type',
			function () {
				$GLOBALS['tbcards_stub']['posts_by_id'][6] = (object) array(
					'ID'        => 6,
					'post_type' => 'post',
					'post_name' => 'x',
				);

				assert_same( null, tbcards_test_repository()->find( 404 ) );
				assert_same( null, tbcards_test_repository()->find( 6 ) );
			}
		);

		it(
			'builds the card in the asked language',
			function () {
				$card = tbcards_test_repository()->find( 5, 'de' );

				assert_same( 'de', $card->lang() );
				assert_same( 'Geschäftsführer', $card->get( 'job_title' ) );
				assert_same( 'jan-kowalski', $card->slug() );
				assert_same( 99, $card->photo_id() );
				assert_same( 'https://example.test/card/jan-kowalski/', $card->url() );
			}
		);

		it(
			'falls back to the default language for an unknown or missing language',
			function () {
				assert_same( 'pl', tbcards_test_repository()->find( 5, 'fr' )->lang() );
				assert_same( 'pl', tbcards_test_repository()->find( 5 )->lang() );
			}
		);

		it(
			'prefers the card value over the company value',
			function () {
				assert_same( 'Kraków', tbcards_test_repository()->find( 5 )->get( 'city' ) );
			}
		);

		it(
			'inherits empty inheritable fields from the company, per language',
			function () {
				assert_same( 'Firmen-Intro', tbcards_test_repository()->find( 5, 'de' )->get( 'intro' ) );
				assert_same( 'https://firma.example', tbcards_test_repository()->find( 5 )->get( 'website' ) );
			}
		);

		it(
			'uses the card default-language value before the company value',
			function () {
				$GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_intro'] = array( 'pl' => 'Moje intro' );

				assert_same( 'Moje intro', tbcards_test_repository()->find( 5, 'de' )->get( 'intro' ) );
			}
		);

		it(
			'never inherits personal fields',
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_company']['email'] = 'biuro@firma.example';

				assert_same( '', tbcards_test_repository()->find( 5 )->get( 'email' ) );
			}
		);

		it(
			'treats a whitespace-only value as empty',
			function () {
				$GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_city'] = '   ';

				assert_same( 'Warszawa', tbcards_test_repository()->find( 5 )->get( 'city' ) );
			}
		);

		it(
			'reads the inactive flag',
			function () {
				assert_true( tbcards_test_repository()->find( 5 )->is_active() );

				$GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_inactive'] = '1';
				assert_false( tbcards_test_repository()->find( 5 )->is_active() );
			}
		);
	}
);

describe(
	'card repository: save',
	function () {

		it(
			'writes known fields and deletes emptied ones',
			function () {
				$GLOBALS['tbcards_stub']['post_meta'][5] = array( '_tbcards_city' => 'Kraków' );

				tbcards_test_repository()->save(
					5,
					array(
						'first_name' => 'Jan',
						'job_title'  => array( 'pl' => 'Prezes' ),
						'city'       => '',
						'hack'       => 'x',
					)
				);

				$meta = $GLOBALS['tbcards_stub']['post_meta'][5];
				assert_same( 'Jan', $meta['_tbcards_first_name'] );
				assert_same( array( 'pl' => 'Prezes' ), $meta['_tbcards_job_title'] );
				assert_false( isset( $meta['_tbcards_city'] ) );
				assert_false( isset( $meta['_tbcards_hack'] ) );
			}
		);

		it(
			'keeps backslashes, which update_post_meta() would otherwise strip',
			function () {
				tbcards_test_repository()->save(
					5,
					array(
						'intro'     => array( 'pl' => 'A\\B' ),
						'last_name' => 'O\\Brien',
					)
				);

				assert_same( 'O\\Brien', $GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_last_name'] );
				assert_same( array( 'pl' => 'A\\B' ), $GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_intro'] );
			}
		);

		it(
			'leaves fields that were not passed untouched',
			function () {
				$GLOBALS['tbcards_stub']['post_meta'][5] = array( '_tbcards_city' => 'Kraków' );

				tbcards_test_repository()->save( 5, array( 'first_name' => 'Jan' ) );

				assert_same( 'Kraków', $GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_city'] );
			}
		);
	}
);
