<?php
/**
 * Pins the company profile: its own fields, per-language resolution and sanitising.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/card/class-fields.php';
require_once dirname( __DIR__ ) . '/includes/card/class-field-sanitizer.php';
require_once dirname( __DIR__ ) . '/includes/company/class-company-profile.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Card\Fields;
use Asymetria\TeamCards\Company\Company_Profile;

describe(
	'company profile',
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
			}
		);

		it(
			'has the company name, the logo and every inheritable card field',
			function () {
				assert_same(
					array_merge( array( 'company_name', 'logo_id' ), Fields::inheritable() ),
					array_keys( Company_Profile::fields() )
				);
			}
		);

		it(
			'resolves a translated value in the asked language, then the default',
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_company'] = array(
					'intro' => array( 'pl' => 'Tworzymy automatyzacje.' ),
				);
				$company = new Company_Profile( new Settings() );

				assert_same( 'Tworzymy automatyzacje.', $company->value( 'intro', 'de' ) );
			}
		);

		it(
			'answers plain values regardless of language, and empty for unknown keys',
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_company'] = array( 'city' => 'Warszawa' );
				$company = new Company_Profile( new Settings() );

				assert_same( 'Warszawa', $company->value( 'city', 'de' ) );
				assert_same( '', $company->value( 'nope', 'pl' ) );
			}
		);

		it(
			'reads the logo as an integer',
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_company'] = array( 'logo_id' => '42' );
				assert_same( 42, ( new Company_Profile( new Settings() ) )->logo_id() );
			}
		);

		it(
			'survives a non-array option',
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_company'] = 'garbage';
				assert_same( '', ( new Company_Profile( new Settings() ) )->value( 'city', 'pl' ) );
			}
		);

		it(
			'sanitises by field type and drops unknown keys',
			function () {
				$clean = ( new Company_Profile( new Settings() ) )->sanitize(
					array(
						'company_name' => '<i>Acme</i> sp. z o.o.',
						'logo_id'      => '7x',
						'website'      => 'javascript:x',
						'intro'        => array(
							'pl' => 'Cześć',
							'fr' => 'Salut',
						),
						'hack'         => 'x',
					)
				);

				assert_same( 'Acme sp. z o.o.', $clean['company_name'] );
				assert_same( 7, $clean['logo_id'] );
				assert_same( '', $clean['website'] );
				assert_same( array( 'pl' => 'Cześć' ), $clean['intro'] );
				assert_false( isset( $clean['hack'] ) );
			}
		);
	}
);
