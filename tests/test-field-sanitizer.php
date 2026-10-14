<?php
/**
 * Pins sanitising per field type and the dialable phone form.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/card/class-fields.php';
require_once dirname( __DIR__ ) . '/includes/card/class-field-sanitizer.php';

use Asymetria\TeamCards\Card\Field_Sanitizer;

describe(
	'field sanitizer: values',
	function () {

		it(
			'strips tags from text and keeps line breaks in textareas',
			function () {
				assert_same( 'Jan', Field_Sanitizer::value( 'text', ' <b>Jan</b> ' ) );
				assert_same( "Line one\nLine two", Field_Sanitizer::value( 'textarea', "Line one\nLine two" ) );
			}
		);

		it(
			'empties an invalid email',
			function () {
				assert_same( 'jan@example.com', Field_Sanitizer::value( 'email', ' jan@example.com ' ) );
				assert_same( '', Field_Sanitizer::value( 'email', 'not-an-email' ) );
			}
		);

		it(
			'allows only http and https links',
			function () {
				assert_same( 'https://example.com/a', Field_Sanitizer::value( 'url', 'https://example.com/a' ) );
				assert_same( '', Field_Sanitizer::value( 'url', 'javascript:alert(1)' ) );
				assert_same( '', Field_Sanitizer::value( 'url', 'ftp://example.com' ) );
			}
		);

		it(
			'keeps a phone as typed, minus anything that is not part of a number',
			function () {
				assert_same( '+48 609 909 828', Field_Sanitizer::value( 'tel', '+48 609 909 828' ) );
				assert_same( '(22) 123-45-67', Field_Sanitizer::value( 'tel', '(22) 123-45-67 <x>' ) );
			}
		);

		it(
			'returns an empty string for non-scalars',
			function () {
				assert_same( '', Field_Sanitizer::value( 'text', array( 'a' ) ) );
			}
		);
	}
);

describe(
	'field sanitizer: translated values',
	function () {

		it(
			'keeps only configured languages and drops empty ones',
			function () {
				assert_same(
					array(
						'pl' => 'Prezes',
						'de' => 'Geschäftsführer',
					),
					Field_Sanitizer::translated(
						'text',
						array(
							'pl' => 'Prezes',
							'de' => 'Geschäftsführer',
							'en' => '  ',
							'fr' => 'PDG',
						),
						array( 'pl', 'de', 'en' )
					)
				);
			}
		);

		it(
			'answers an empty array for non-array input',
			function () {
				assert_same( array(), Field_Sanitizer::translated( 'text', 'Prezes', array( 'pl' ) ) );
			}
		);
	}
);

describe(
	'field sanitizer: dial',
	function () {

		it(
			'turns an international number into plus and digits',
			function () {
				assert_same( '+48609909828', Field_Sanitizer::dial( '+48 609 909 828' ) );
				assert_same( '+48609909828', Field_Sanitizer::dial( '0048 609 909 828' ) );
			}
		);

		it(
			'dials a local number as digits and never invents a country',
			function () {
				assert_same( '221234567', Field_Sanitizer::dial( '(22) 123-45-67' ) );
			}
		);

		it(
			'answers empty for something with no digits',
			function () {
				assert_same( '', Field_Sanitizer::dial( '+ ( )' ) );
			}
		);
	}
);
