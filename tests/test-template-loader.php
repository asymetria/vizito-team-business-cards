<?php
/**
 * Pins the full-page template: when it is used, the view model, and the document.
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

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Card\Card_Type;
use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Front\Card_Renderer;
use Asymetria\TeamCards\Front\Language;
use Asymetria\TeamCards\Front\Template_Loader;

if ( ! function_exists( 'tbcards_test_loader' ) ) {
	/**
	 * A loader over fresh services.
	 *
	 * @return Template_Loader
	 */
	function tbcards_test_loader(): Template_Loader {
		$settings = new Settings();
		$company  = new Company_Profile( $settings );

		return new Template_Loader(
			$settings,
			$company,
			new Card_Repository( $settings, $company ),
			new Card_Type( $settings ),
			new Card_Renderer( $company ),
			new Language( $settings )
		);
	}
}

describe(
	'template loader',
	function () {

		before_each(
			function () {
				unset( $_GET['lang'] );
				Template_Loader::reset_view();
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
					),
					'default_language' => 'pl',
					'accent'           => '#aa0000',
					'photo_shape'      => 'square',
					'auto_language'    => true,
				);
				$GLOBALS['tbcards_stub']['options']['tbcards_company']  = array( 'company_name' => 'Acme' );
				$GLOBALS['tbcards_stub']['posts_by_id'][5]          = (object) array(
					'ID'        => 5,
					'post_type' => 'tbcards_card',
					'post_name' => 'jan',
				);
				$GLOBALS['tbcards_stub']['post_meta'][5]            = array(
					'_tbcards_first_name' => 'Jan',
					'_tbcards_last_name'  => 'Kowalski',
					'_tbcards_job_title'  => array(
						'pl' => 'Prezes',
						'de' => 'Geschäftsführer',
					),
				);
				$GLOBALS['tbcards_stub']['permalinks'][5]           = 'https://example.test/card/jan/';
				$GLOBALS['tbcards_stub']['is_singular']             = array( 'tbcards_card' );
				$GLOBALS['tbcards_stub']['query_vars']              = array( '__queried_id' => 5 );
			}
		);

		it(
			'leaves every other request alone',
			function () {
				$GLOBALS['tbcards_stub']['is_singular'] = array( 'post' );

				assert_same( '/theme/single.php', tbcards_test_loader()->template( '/theme/single.php' ) );
				assert_same( null, Template_Loader::view() );
			}
		);

		it(
			'leaves vCard requests to the vCard endpoint',
			function () {
				$GLOBALS['tbcards_stub']['query_vars']['tbcards_vcard'] = '1';

				assert_same( '/theme/single.php', tbcards_test_loader()->template( '/theme/single.php' ) );
			}
		);

		it(
			'uses the plugin template, or a theme override when there is one',
			function () {
				assert_same( TBCARDS_DIR . 'templates/card.php', tbcards_test_loader()->template( '/theme/single.php' ) );

				$GLOBALS['tbcards_stub']['located_templates']['vizito-team-business-cards/card.php'] = '/theme/vizito-team-business-cards/card.php';
				assert_same( '/theme/vizito-team-business-cards/card.php', tbcards_test_loader()->template( '/theme/single.php' ) );
			}
		);

		it(
			'builds the view in the asked language',
			function () {
				$_GET['lang'] = 'de';
				tbcards_test_loader()->template( '' );
				$view = Template_Loader::view();

				assert_same( 'de', $view['lang'] );
				assert_same( 'Jan Kowalski — Acme', $view['title'] );
				assert_contains( 'Geschäftsführer', $view['html'] );
				assert_contains( 'href="https://example.test/card/jan/vcard/?lang=de"', $view['html'] );
				assert_same( array( 'de_DE' ), $GLOBALS['tbcards_stub']['locale_switches'] );
			}
		);

		it(
			'links the vCard without a language parameter in the default language',
			function () {
				tbcards_test_loader()->template( '' );

				assert_contains( 'href="https://example.test/card/jan/vcard/"', Template_Loader::view()['html'] );
			}
		);

		it(
			'turns appearance settings into custom properties',
			function () {
				tbcards_test_loader()->template( '' );
				$vars = Template_Loader::view()['vars'];

				assert_contains( '--tbcards-accent:#aa0000', $vars );
				assert_contains( '--tbcards-photo-radius:14px', $vars );
			}
		);

		it(
			'hands the script its languages, default and auto switch',
			function () {
				tbcards_test_loader()->template( '' );
				$config = Template_Loader::view()['config'];

				assert_same( array( 'pl', 'de' ), $config['languages'] );
				assert_same( 'pl', $config['default'] );
				assert_true( $config['auto'] );
				assert_same( 'pl', $config['lang'] );
				assert_same( 5, $config['card'] );
				assert_contains( 'tbcards/v1/hit', $config['hit'] );
			}
		);

		it(
			'renders a complete, theme-free document',
			function () {
				$_GET['lang'] = 'de';
				$file         = tbcards_test_loader()->template( '' );

				ob_start();
				include $file;
				$html = (string) ob_get_clean();

				assert_contains( '<!doctype html>', $html );
				assert_contains( '<html lang="de"', $html );
				assert_contains( '<meta name="robots" content="noindex, noarchive">', $html );
				assert_contains( '<title>Jan Kowalski — Acme</title>', $html );
				assert_contains( '--tbcards-accent:#aa0000', $html );
				assert_contains( 'class="tbcards-card tbcards-card--page"', $html );
				assert_contains( 'assets/card.js?ver=', $html );
				assert_contains( 'assets/card.css?ver=', $html );
				assert_false( str_contains( $html, '<style>' ) ); // Only through wp_print_styles(), never a bare tag.
				assert_false( str_contains( $html, 'wp_head' ) );
			}
		);

		it(
			'keeps a script breakout out of the inline config',
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_company'] = array( 'company_name' => '</script><script>alert(1)</script>' );

				$file = tbcards_test_loader()->template( '' );
				ob_start();
				include $file;
				$html = (string) ob_get_clean();

				assert_false( str_contains( $html, '</script><script>alert(1)' ) );
			}
		);
	}
);
