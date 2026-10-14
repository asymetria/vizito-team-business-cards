<?php
/**
 * Pins what an inactive card does: redirects, notice, status box, slug warning.
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
require_once $tbcards_root . '/includes/card/class-card-type.php';
require_once $tbcards_root . '/includes/front/class-icons.php';
require_once $tbcards_root . '/includes/front/class-card-renderer.php';
require_once $tbcards_root . '/includes/front/class-language.php';
require_once $tbcards_root . '/includes/front/class-template-loader.php';
require_once $tbcards_root . '/includes/lifecycle/class-inactive-card.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Card\Card_Type;
use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Front\Card_Renderer;
use Asymetria\TeamCards\Front\Language;
use Asymetria\TeamCards\Front\Template_Loader;
use Asymetria\TeamCards\Lifecycle\Inactive_Card;

if ( ! function_exists( 'tbcards_test_inactive' ) ) {
	/**
	 * The lifecycle service, recording redirects.
	 *
	 * @param array $redirects Receives array( url, status ).
	 * @return Inactive_Card
	 */
	function tbcards_test_inactive( array &$redirects ): Inactive_Card {
		$settings = new Settings();
		$company  = new Company_Profile( $settings );

		return new Inactive_Card(
			new Card_Repository( $settings, $company ),
			$company,
			static function ( string $url, int $status ) use ( &$redirects ) {
				$redirects[] = array( $url, $status );
			}
		);
	}

	/**
	 * Adds a card to the stub state.
	 *
	 * @param int    $id     Post id.
	 * @param string $slug   Slug.
	 * @param array  $meta   Meta.
	 * @param string $status Post status.
	 * @return void
	 */
	function tbcards_test_add_card( int $id, string $slug, array $meta, string $status = 'publish' ): void {
		$GLOBALS['tbcards_stub']['posts_by_id'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => 'tbcards_card',
			'post_name'   => $slug,
			'post_status' => $status,
		);
		$GLOBALS['tbcards_stub']['post_meta'][ $id ]   = $meta;
		$GLOBALS['tbcards_stub']['permalinks'][ $id ]  = 'https://example.test/card/' . $slug . '/';
	}
}

describe(
	'inactive card: redirects',
	function () {

		before_each(
			function () {
				tbcards_test_add_card(
					5,
					'jan',
					array(
						'_tbcards_first_name' => 'Jan',
						'_tbcards_inactive'   => '1',
					)
				);
				tbcards_test_add_card( 6, 'ola', array( '_tbcards_first_name' => 'Ola' ) );
				$GLOBALS['tbcards_stub']['options']['tbcards_company'] = array( 'website' => 'https://firma.example/' );
				$GLOBALS['tbcards_stub']['is_singular']            = array( 'tbcards_card' );
				$GLOBALS['tbcards_stub']['query_vars']             = array( '__queried_id' => 5 );
			}
		);

		it(
			'sends visitors to the successor with a temporary redirect',
			function () {
				$redirects                                                  = array();
				$GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_inactive_mode']   = 'card';
				$GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_inactive_target'] = 6;

				tbcards_test_inactive( $redirects )->maybe_redirect();

				assert_same( array( array( 'https://example.test/card/ola/', 302 ) ), $redirects );
			}
		);

		it(
			'shows the notice instead when the successor has left too, or is not published',
			function () {
				$redirects                                                  = array();
				$GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_inactive_mode']   = 'card';
				$GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_inactive_target'] = 6;

				$GLOBALS['tbcards_stub']['post_meta'][6]['_tbcards_inactive'] = '1';
				tbcards_test_inactive( $redirects )->maybe_redirect();

				unset( $GLOBALS['tbcards_stub']['post_meta'][6]['_tbcards_inactive'] );
				$GLOBALS['tbcards_stub']['posts_by_id'][6]->post_status = 'draft';
				tbcards_test_inactive( $redirects )->maybe_redirect();

				$GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_inactive_target'] = 5;
				tbcards_test_inactive( $redirects )->maybe_redirect();

				assert_same( array(), $redirects );
			}
		);

		it(
			'redirects to an address on the company website or this site, nowhere else',
			function () {
				$redirects                                                = array();
				$GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_inactive_mode'] = 'url';

				$GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_inactive_target'] = 'https://firma.example/kontakt';
				tbcards_test_inactive( $redirects )->maybe_redirect();

				$GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_inactive_target'] = 'https://example.test/team/';
				tbcards_test_inactive( $redirects )->maybe_redirect();

				$GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_inactive_target'] = 'https://evil.example/';
				tbcards_test_inactive( $redirects )->maybe_redirect();

				assert_same(
					array(
						array( 'https://firma.example/kontakt', 302 ),
						array( 'https://example.test/team/', 302 ),
					),
					$redirects
				);
			}
		);

		it(
			'leaves active cards and the notice mode alone',
			function () {
				$redirects = array();

				tbcards_test_inactive( $redirects )->maybe_redirect();

				$GLOBALS['tbcards_stub']['query_vars']['__queried_id'] = 6;
				tbcards_test_inactive( $redirects )->maybe_redirect();

				assert_same( array(), $redirects );
			}
		);
	}
);

describe(
	'inactive card: notice page',
	function () {

		it(
			'shows the company and nothing of the person',
			function () {
				tbcards_test_add_card(
					5,
					'jan',
					array(
						'_tbcards_first_name' => 'Jan',
						'_tbcards_last_name'  => 'Kowalski',
						'_tbcards_mobile'     => '+48 600 111 222',
						'_tbcards_email'      => 'jan@firma.example',
						'_tbcards_inactive'   => '1',
					)
				);
				$GLOBALS['tbcards_stub']['options']['tbcards_company'] = array(
					'company_name' => 'Acme',
					'landline'     => '+48 22 100 20 30',
					'website'      => 'https://firma.example',
				);
				$GLOBALS['tbcards_stub']['is_singular']            = array( 'tbcards_card' );
				$GLOBALS['tbcards_stub']['query_vars']             = array( '__queried_id' => 5 );

				$settings = new Settings();
				$company  = new Company_Profile( $settings );
				$loader   = new Template_Loader( $settings, $company, new Card_Repository( $settings, $company ), new Card_Type( $settings ), new Card_Renderer( $company ), new Language( $settings ) );
				$loader->template( '' );
				$view = Template_Loader::view();
				Template_Loader::reset_view();

				assert_contains( 'Acme', $view['html'] );
				assert_contains( 'tel:+48221002030', $view['html'] );
				assert_same( 'Acme', $view['title'] );
				assert_same( '', $view['config']['hit'] );

				foreach ( array( 'Jan', 'Kowalski', '600', 'jan@' ) as $personal ) {
					assert_false( str_contains( $view['html'] . $view['title'], $personal ), $personal );
				}
			}
		);
	}
);

describe(
	'inactive card: status box',
	function () {

		before_each(
			function () {
				tbcards_test_add_card( 5, 'jan', array( '_tbcards_first_name' => 'Jan' ) );
				$GLOBALS['tbcards_stub']['capabilities']['edit_post'] = array( 5 );
				$_POST = array( 'tbcards_status_nonce' => 'nonce:tbcards_status_5' );
			}
		);

		it(
			'marks a card inactive with a successor',
			function () {
				$_POST['tbcards_status'] = array(
					'inactive' => '1',
					'mode'     => 'card',
					'card'     => '6x',
				);
				$redirects           = array();

				tbcards_test_inactive( $redirects )->save( 5 );

				$meta = $GLOBALS['tbcards_stub']['post_meta'][5];
				assert_same( '1', $meta['_tbcards_inactive'] );
				assert_same( 'card', $meta['_tbcards_inactive_mode'] );
				assert_same( 6, $meta['_tbcards_inactive_target'] );
			}
		);

		it(
			'reactivates, and falls back to the notice for an unknown mode',
			function () {
				$GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_inactive'] = '1';
				$_POST['tbcards_status']                                  = array( 'mode' => 'teleport' );
				$redirects                                            = array();

				tbcards_test_inactive( $redirects )->save( 5 );

				$meta = $GLOBALS['tbcards_stub']['post_meta'][5];
				assert_false( isset( $meta['_tbcards_inactive'] ) );
				assert_same( 'notice', $meta['_tbcards_inactive_mode'] );
				assert_false( isset( $meta['_tbcards_inactive_target'] ) );
			}
		);

		it(
			'does nothing without this card\'s nonce or the capability',
			function () {
				$_POST['tbcards_status'] = array( 'inactive' => '1' );
				$redirects           = array();

				$_POST['tbcards_status_nonce'] = 'nonce:tbcards_status_6';
				tbcards_test_inactive( $redirects )->save( 5 );

				$_POST['tbcards_status_nonce']                        = 'nonce:tbcards_status_5';
				$GLOBALS['tbcards_stub']['capabilities']['edit_post'] = array( 6 );
				tbcards_test_inactive( $redirects )->save( 5 );

				assert_false( isset( $GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_inactive'] ) );
			}
		);
	}
);

describe(
	'inactive card: slug warning',
	function () {

		it(
			'warns once after a published card\'s address changed, not for drafts',
			function () {
				$redirects = array();
				$service   = tbcards_test_inactive( $redirects );
				$before    = (object) array(
					'post_type'   => 'tbcards_card',
					'post_status' => 'publish',
					'post_name'   => 'jan',
				);
				$after     = (object) array(
					'post_type'   => 'tbcards_card',
					'post_status' => 'publish',
					'post_name'   => 'jan-kowalski',
				);
				$draft     = (object) array(
					'post_type'   => 'tbcards_card',
					'post_status' => 'draft',
					'post_name'   => 'x',
				);

				$service->note_slug_change( 5, $after, $draft );
				ob_start();
				$service->slug_notice();
				assert_same( '', (string) ob_get_clean() );

				$service->note_slug_change( 5, $after, $before );
				ob_start();
				$service->slug_notice();
				$first = (string) ob_get_clean();
				ob_start();
				$service->slug_notice();
				$second = (string) ob_get_clean();

				assert_contains( '<code>jan</code>', $first );
				assert_same( '', $second );

				$trashed = (object) array(
					'post_type'   => 'tbcards_card',
					'post_status' => 'trash',
					'post_name'   => 'jan__trashed',
				);
				$service->note_slug_change( 5, $trashed, $before );
				ob_start();
				$service->slug_notice();
				assert_same( '', (string) ob_get_clean(), 'trashing is not an address change' );
			}
		);
	}
);
