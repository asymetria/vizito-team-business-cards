<?php
/**
 * Pins the card edit screen: form, inherited placeholders, save and derived title.
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
require_once $tbcards_root . '/includes/admin/class-settings-page.php';
require_once $tbcards_root . '/includes/admin/class-card-metabox.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Admin\Card_Metabox;

if ( ! function_exists( 'tbcards_test_metabox' ) ) {
	/**
	 * A meta box over fresh services.
	 *
	 * @param bool $skip What the autosave/revision check answers.
	 * @return Card_Metabox
	 */
	function tbcards_test_metabox( bool $skip = false ): Card_Metabox {
		$settings = new Settings();
		$company  = new Company_Profile( $settings );

		return new Card_Metabox(
			$settings,
			$company,
			new Card_Repository( $settings, $company ),
			static function () use ( $skip ) {
				return $skip;
			}
		);
	}

	/**
	 * The meta box HTML for post 5.
	 *
	 * @return string
	 */
	function tbcards_test_metabox_html(): string {
		ob_start();
		tbcards_test_metabox()->render( (object) array( 'ID' => 5 ) );
		return (string) ob_get_clean();
	}
}

/**
 * Two languages, a company profile and one stored card.
 */
$tbcards_metabox_fixture = function () {
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
		'city'  => 'Warszawa',
		'intro' => array( 'pl' => 'Intro firmy' ),
	);
	$GLOBALS['tbcards_stub']['post_meta'][5] = array(
		'_tbcards_first_name' => 'Jan "Szef"',
		'_tbcards_job_title'  => array( 'pl' => 'Prezes' ),
	);
	$GLOBALS['tbcards_stub']['posts_by_id'][5] = (object) array(
		'ID'        => 5,
		'post_type' => 'tbcards_card',
		'post_name' => 'jan',
	);
	unset( $_POST['tbcards'], $_POST['tbcards_card_nonce'] );
};

describe(
	'card metabox: form',
	function () use ( $tbcards_metabox_fixture ) {

		before_each( $tbcards_metabox_fixture );

		it(
			'registers the meta box on the card screen',
			function () {
				tbcards_test_metabox()->add();

				$box = $GLOBALS['tbcards_stub']['meta_boxes'][0];
				assert_same( 'tbcards_card_details', $box['id'] );
				assert_same( 'tbcards_card', $box['screen'] );
				assert_same( 'normal', $box['context'] );
			}
		);

		it(
			'renders stored plain values, escaped',
			function () {
				$html = tbcards_test_metabox_html();

				assert_contains( 'name="tbcards[first_name]"', $html );
				assert_contains( 'value="Jan &quot;Szef&quot;"', $html );
			}
		);

		it(
			'renders one input per language for translated fields',
			function () {
				$html = tbcards_test_metabox_html();

				assert_contains( 'name="tbcards[job_title][pl]" value="Prezes"', $html );
				assert_contains( 'name="tbcards[job_title][de]" value=""', $html );
				assert_contains( 'name="tbcards[intro][de]"', $html );
			}
		);

		it(
			'shows the company value as a placeholder on empty inheritable fields only',
			function () {
				$html = tbcards_test_metabox_html();

				assert_contains( 'name="tbcards[city]" value="" placeholder="Warszawa"', $html );
				assert_contains( 'placeholder="Intro firmy"', $html );
				assert_false( str_contains( $html, 'name="tbcards[email]" value="" placeholder' ) );
			}
		);

		it(
			'renders every language panel visible, so the form works without script',
			function () {
				$html = tbcards_test_metabox_html();

				assert_contains( 'id="tbcards-panel-pl"', $html );
				assert_contains( 'id="tbcards-panel-de"', $html );
				assert_false( (bool) preg_match( '/role="tabpanel"[^>]*hidden/', $html ) );
			}
		);

		it(
			'prints a nonce for this post',
			function () {
				assert_contains( 'value="nonce:tbcards_save_card_5"', tbcards_test_metabox_html() );
			}
		);
	}
);

describe(
	'card metabox: save',
	function () use ( $tbcards_metabox_fixture ) {

		before_each(
			function () use ( $tbcards_metabox_fixture ) {
				$tbcards_metabox_fixture();
				$GLOBALS['tbcards_stub']['capabilities']['edit_post'] = array( 5 );
				$_POST['tbcards_card_nonce']                          = 'nonce:tbcards_save_card_5';
				$_POST['tbcards']                                     = array(
					'first_name' => 'Anna',
					'website'    => 'javascript:alert(1)',
					'job_title'  => array(
						'pl' => 'Dyrektor',
						'de' => ' ',
					),
				);
			}
		);

		it(
			'writes sanitised values and fires the saved action once',
			function () {
				$fired = array();
				add_action(
					'tbcards_card_saved',
					function ( $id ) use ( &$fired ) {
						$fired[] = $id;
					}
				);

				tbcards_test_metabox()->save( 5 );

				$meta = $GLOBALS['tbcards_stub']['post_meta'][5];
				assert_same( 'Anna', $meta['_tbcards_first_name'] );
				assert_same( array( 'pl' => 'Dyrektor' ), $meta['_tbcards_job_title'] );
				assert_false( isset( $meta['_tbcards_website'] ) );
				assert_same( array( 5 ), $fired );
			}
		);

		it(
			'does nothing without a nonce, with a wrong one, or without the capability',
			function () {
				unset( $_POST['tbcards_card_nonce'] );
				tbcards_test_metabox()->save( 5 );
				assert_same( 'Jan "Szef"', $GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_first_name'] );

				$_POST['tbcards_card_nonce'] = 'nonce:tbcards_save_card_6';
				tbcards_test_metabox()->save( 5 );
				assert_same( 'Jan "Szef"', $GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_first_name'] );

				$_POST['tbcards_card_nonce']                          = 'nonce:tbcards_save_card_5';
				$GLOBALS['tbcards_stub']['capabilities']['edit_post'] = array( 6 );
				tbcards_test_metabox()->save( 5 );
				assert_same( 'Jan "Szef"', $GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_first_name'] );
			}
		);

		it(
			'does nothing on autosave or revisions',
			function () {
				tbcards_test_metabox( true )->save( 5 );

				assert_same( 'Jan "Szef"', $GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_first_name'] );
			}
		);

		it(
			'unslashes the request before sanitising',
			function () {
				$_POST['tbcards'] = array( 'last_name' => "O\\'Brien" );

				tbcards_test_metabox()->save( 5 );

				assert_same( "O'Brien", $GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_last_name'] );
			}
		);
	}
);

describe(
	'card metabox: derived title',
	function () use ( $tbcards_metabox_fixture ) {

		before_each(
			function () use ( $tbcards_metabox_fixture ) {
				$tbcards_metabox_fixture();
				$_POST['tbcards_card_nonce'] = 'nonce:tbcards_save_card_5';
				$_POST['tbcards']            = array(
					'first_name' => 'Anna',
					'last_name'  => 'Nowak',
				);
			}
		);

		it(
			'sets the title from the posted names',
			function () {
				$data = tbcards_test_metabox()->derive_title(
					array(
						'post_type'  => 'tbcards_card',
						'post_title' => '',
					),
					array( 'ID' => 5 )
				);

				assert_same( 'Anna Nowak', $data['post_title'] );
			}
		);

		it(
			'leaves other post types, missing nonces and empty names alone',
			function () {
				$metabox = tbcards_test_metabox();
				$other   = $metabox->derive_title(
					array(
						'post_type'  => 'post',
						'post_title' => 'X',
					),
					array( 'ID' => 5 )
				);
				assert_same( 'X', $other['post_title'] );

				$_POST['tbcards']  = array(
					'first_name' => '',
					'last_name'  => ' ',
				);
				$empty         = $metabox->derive_title(
					array(
						'post_type'  => 'tbcards_card',
						'post_title' => 'Old',
					),
					array( 'ID' => 5 )
				);
				assert_same( 'Old', $empty['post_title'] );

				unset( $_POST['tbcards_card_nonce'] );
				$_POST['tbcards'] = array( 'first_name' => 'Anna' );
				$unsigned     = $metabox->derive_title(
					array(
						'post_type'  => 'tbcards_card',
						'post_title' => 'Old',
					),
					array( 'ID' => 5 )
				);
				assert_same( 'Old', $unsigned['post_title'] );
			}
		);
	}
);
