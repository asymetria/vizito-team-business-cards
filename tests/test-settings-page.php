<?php
/**
 * Pins the admin menu, the two settings registrations and both forms.
 *
 * @package Vizito_Team_Business_Cards
 */

$tbcards_root = dirname( __DIR__ );
require_once $tbcards_root . '/includes/class-settings.php';
require_once $tbcards_root . '/includes/card/class-fields.php';
require_once $tbcards_root . '/includes/card/class-field-sanitizer.php';
require_once $tbcards_root . '/includes/card/class-card-repository.php';
require_once $tbcards_root . '/includes/company/class-company-profile.php';
require_once $tbcards_root . '/includes/admin/class-settings-page.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Admin\Settings_Page;

if ( ! function_exists( 'tbcards_test_settings_page' ) ) {
	/**
	 * A page over fresh settings and company profile.
	 *
	 * @return Settings_Page
	 */
	function tbcards_test_settings_page(): Settings_Page {
		$settings = new Settings();
		return new Settings_Page( $settings, new Company_Profile( $settings ) );
	}

	/**
	 * Captures what a callable prints.
	 *
	 * @param callable $fn Callable.
	 * @return string
	 */
	function tbcards_test_capture( callable $fn ): string {
		ob_start();
		try {
			$fn();
		} finally {
			$out = ob_get_clean();
		}
		return (string) $out;
	}
}

describe(
	'settings page: registration',
	function () {

		it(
			'hooks the menu, the settings, the notice and the assets',
			function () {
				tbcards_test_settings_page()->hooks();

				foreach ( array( 'admin_menu', 'admin_init', 'admin_notices', 'admin_enqueue_scripts' ) as $hook ) {
					assert_true( isset( $GLOBALS['tbcards_stub']['actions'][ $hook ] ), $hook );
				}
			}
		);

		it(
			'adds both pages under the card menu, for administrators only',
			function () {
				tbcards_test_settings_page()->register_menu();

				$pages = array_column( $GLOBALS['tbcards_stub']['admin_pages'], null, 'menu_slug' );

				assert_same( 'edit.php?post_type=tbcards_card', $pages['tbcards-company']['parent_slug'] );
				assert_same( 'manage_options', $pages['tbcards-company']['capability'] );
				assert_same( 'edit.php?post_type=tbcards_card', $pages['tbcards-settings']['parent_slug'] );
				assert_same( 'manage_options', $pages['tbcards-settings']['capability'] );
			}
		);

		it(
			'registers both options with their own sanitize callbacks',
			function () {
				$page = tbcards_test_settings_page();
				$page->register_settings();

				$by_option = array_column( $GLOBALS['tbcards_stub']['settings'], null, 'option' );

				assert_same( 'tbcards_company', $by_option['tbcards_company']['group'] );
				assert_same( 'sanitize', $by_option['tbcards_company']['args']['sanitize_callback'][1] );
				assert_true( $by_option['tbcards_company']['args']['sanitize_callback'][0] instanceof Company_Profile );
				assert_same( 'tbcards_settings', $by_option['tbcards_settings']['group'] );
				assert_true( $by_option['tbcards_settings']['args']['sanitize_callback'][0] instanceof Settings );
			}
		);
	}
);

describe(
	'settings page: company form',
	function () {

		before_each(
			function () {
				$GLOBALS['tbcards_stub']['capabilities']['manage_options'] = true;
				$GLOBALS['tbcards_stub']['options']['tbcards_settings']        = array(
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
			}
		);

		it(
			'escapes stored values',
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_company'] = array( 'company_name' => '"><script>x</script>' );

				$html = tbcards_test_capture( array( tbcards_test_settings_page(), 'render_company' ) );

				assert_contains( '&quot;&gt;&lt;script&gt;', $html );
				assert_false( str_contains( $html, '<script>x' ) );
			}
		);

		it(
			'renders one input per language for translated fields',
			function () {
				$html = tbcards_test_capture( array( tbcards_test_settings_page(), 'render_company' ) );

				assert_contains( 'name="tbcards_company[intro][pl]"', $html );
				assert_contains( 'name="tbcards_company[intro][de]"', $html );
				assert_contains( 'name="tbcards_company[city]"', $html );
				assert_contains( 'name="tbcards_company[logo_id]"', $html );
			}
		);

		it(
			'posts to options.php with the company option group',
			function () {
				$html = tbcards_test_capture( array( tbcards_test_settings_page(), 'render_company' ) );

				assert_contains( 'action="options.php"', $html );
				assert_contains( "value='tbcards_company'", $html );
			}
		);

		it(
			'refuses users who cannot manage options',
			function () {
				$GLOBALS['tbcards_stub']['capabilities'] = array();
				$died                                 = false;

				try {
					tbcards_test_capture( array( tbcards_test_settings_page(), 'render_company' ) );
				} catch ( Tbcards_Died $e ) {
					$died = true;
				}

				assert_true( $died );
			}
		);
	}
);

describe(
	'settings page: settings form',
	function () {

		before_each(
			function () {
				$GLOBALS['tbcards_stub']['capabilities']['manage_options'] = true;
				$GLOBALS['tbcards_stub']['options']['tbcards_settings']        = array(
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
					'default_language' => 'de',
				);
			}
		);

		it(
			'prints the language rows one per line',
			function () {
				$html = tbcards_test_capture( array( tbcards_test_settings_page(), 'render_settings' ) );

				assert_contains( "pl pl_PL\nde de_DE</textarea>", $html );
			}
		);

		it(
			'selects the current default language',
			function () {
				$html = tbcards_test_capture( array( tbcards_test_settings_page(), 'render_settings' ) );

				assert_contains( '<option value="de" selected=\'selected\'>', $html );
			}
		);

		it(
			'sends a zero before every checkbox so unticking is saved',
			function () {
				$html = tbcards_test_capture( array( tbcards_test_settings_page(), 'render_settings' ) );

				foreach ( array( 'auto_language', 'vcard_photo', 'delete_data' ) as $flag ) {
					assert_contains( '<input type="hidden" name="tbcards_settings[' . $flag . ']" value="0">', $html, $flag );
				}
			}
		);

		it(
			'refuses users who cannot manage options',
			function () {
				$GLOBALS['tbcards_stub']['capabilities'] = array();
				$died                                 = false;

				try {
					tbcards_test_capture( array( tbcards_test_settings_page(), 'render_settings' ) );
				} catch ( Tbcards_Died $e ) {
					$died = true;
				}

				assert_true( $died );
			}
		);
	}
);

describe(
	'settings page: url base notice',
	function () {

		it(
			'warns once about printed codes after the base changed',
			function () {
				$GLOBALS['tbcards_stub']['capabilities']['manage_options'] = true;
				$GLOBALS['tbcards_stub']['transients']['tbcards_base_changed'] = array(
					'value'   => 'card',
					'expires' => 0,
				);
				$page = tbcards_test_settings_page();

				$first  = tbcards_test_capture( array( $page, 'base_changed_notice' ) );
				$second = tbcards_test_capture( array( $page, 'base_changed_notice' ) );

				assert_contains( '/card/', $first );
				assert_contains( 'notice-warning', $first );
				assert_same( '', $second );
			}
		);
	}
);

describe(
	'settings page: messages',
	function () {

		it(
			'prints saved and refused messages on both pages, as the Settings menu would',
			function () {
				$GLOBALS['tbcards_stub']['capabilities']['manage_options'] = true;
				add_settings_error( 'tbcards_settings', 'tbcards_qr_colors', 'Colours refused.' );

				assert_contains( 'Colours refused.', tbcards_test_capture( array( tbcards_test_settings_page(), 'render_settings' ) ) );
				assert_contains( 'Colours refused.', tbcards_test_capture( array( tbcards_test_settings_page(), 'render_company' ) ) );
			}
		);
	}
);
