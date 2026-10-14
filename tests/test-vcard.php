<?php
/**
 * Pins the vCard file and its endpoint.
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
require_once $tbcards_root . '/includes/front/class-language.php';
require_once $tbcards_root . '/includes/vcard/class-vcard-builder.php';
require_once $tbcards_root . '/includes/vcard/class-vcard-endpoint.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Card\Card;
use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Front\Language;
use Asymetria\TeamCards\VCard\VCard_Builder;
use Asymetria\TeamCards\VCard\VCard_Endpoint;

if ( ! function_exists( 'tbcards_test_vcard_card' ) ) {
	/**
	 * A card for vCard cases.
	 *
	 * @param array $values Overrides.
	 * @return Card
	 */
	function tbcards_test_vcard_card( array $values = array() ): Card {
		return new Card(
			5,
			'jozef-wezycki',
			'pl',
			array_merge(
				array(
					'first_name' => 'Józef',
					'last_name'  => 'Wężycki',
					'job_title'  => 'Prezes',
					'mobile'     => '+48 609 909 828',
					'email'      => 'jozef@firma.example',
					'street'     => 'Twarda 18',
					'postcode'   => '00-105',
					'city'       => 'Warszawa',
					'country'    => 'Polska',
					'website'    => 'https://firma.example',
					'linkedin'   => 'https://www.linkedin.com/in/bg',
				),
				$values
			),
			0,
			'https://example.test/card/jozef-wezycki/'
		);
	}
}

describe(
	'vcard builder',
	function () {

		it(
			'writes a complete vCard 3.0 with CRLF line ends',
			function () {
				$expected = implode(
					"\r\n",
					array(
						'BEGIN:VCARD',
						'VERSION:3.0',
						'N:Wężycki;Józef;;;',
						'FN:Józef Wężycki',
						'ORG:Acme sp. z o.o.',
						'TITLE:Prezes',
						'TEL;TYPE=CELL,VOICE:+48609909828',
						'EMAIL;TYPE=INTERNET,WORK:jozef@firma.example',
						'ADR;TYPE=WORK:;;Twarda 18;Warszawa;;00-105;Polska',
						'URL;TYPE=WORK:https://firma.example',
						'X-SOCIALPROFILE;TYPE=linkedin:https://www.linkedin.com/in/bg',
						'URL;TYPE=linkedin:https://www.linkedin.com/in/bg',
						'URL:https://example.test/card/jozef-wezycki/',
						'END:VCARD',
					)
				) . "\r\n";

				assert_same( $expected, ( new VCard_Builder() )->build( tbcards_test_vcard_card(), 'Acme sp. z o.o.' ) );
			}
		);

		it(
			'escapes backslashes, commas, semicolons and newlines in text',
			function () {
				$vcard = ( new VCard_Builder() )->build( tbcards_test_vcard_card( array( 'job_title' => "CEO, Sales; A\\B\nNext" ) ), '' );

				assert_contains( 'TITLE:CEO\\, Sales\\; A\\\\B\\nNext', $vcard );
			}
		);

		it(
			'leaves out every empty value, the company included',
			function () {
				$vcard = ( new VCard_Builder() )->build(
					tbcards_test_vcard_card(
						array(
							'job_title' => '',
							'email'     => '',
							'street'    => '',
							'postcode'  => '',
							'city'      => '',
							'country'   => '',
						)
					),
					''
				);

				foreach ( array( 'ORG:', 'TITLE:', 'EMAIL', 'ADR' ) as $property ) {
					assert_false( str_contains( $vcard, $property ), $property );
				}
			}
		);

		it(
			'folds long lines at 75 octets without splitting a character',
			function () {
				$vcard = ( new VCard_Builder() )->build( tbcards_test_vcard_card( array( 'job_title' => str_repeat( 'Zażółć gęślą jaźń ', 8 ) ) ), '' );

				foreach ( explode( "\r\n", $vcard ) as $line ) {
					assert_true( strlen( $line ) <= 75, 'line too long: ' . strlen( $line ) );
					assert_true( 1 === preg_match( '//u', $line ), 'split UTF-8 in: ' . $line );
				}

				$unfolded = str_replace( "\r\n ", '', $vcard );
				assert_contains( 'TITLE:' . rtrim( str_repeat( 'Zażółć gęślą jaźń ', 8 ) ), $unfolded );
			}
		);

		it(
			'adds the landline as a work number and the photo as base64',
			function () {
				$vcard = ( new VCard_Builder() )->build( tbcards_test_vcard_card( array( 'landline' => '(22) 123 45 67' ) ), '', 'QUJD' );

				assert_contains( 'TEL;TYPE=WORK,VOICE:221234567', $vcard );
				assert_contains( 'PHOTO;ENCODING=b;TYPE=JPEG:QUJD', $vcard );
			}
		);

		it(
			'names the file after the person, in plain ASCII',
			function () {
				assert_same( 'jozef-wezycki.vcf', VCard_Builder::filename( tbcards_test_vcard_card() ) );
				assert_same( 'contact.vcf', VCard_Builder::filename( tbcards_test_vcard_card( array( 'first_name' => '', 'last_name' => '' ) ) ) );
			}
		);
	}
);

describe(
	'vcard endpoint',
	function () {

		before_each(
			function () {
				unset( $_GET['lang'] );
				$GLOBALS['tbcards_stub']['options']['tbcards_company'] = array( 'company_name' => 'Acme' );
				$GLOBALS['tbcards_stub']['posts_by_id'][5]          = (object) array(
					'ID'        => 5,
					'post_type' => 'tbcards_card',
					'post_name' => 'jan',
				);
				$GLOBALS['tbcards_stub']['post_meta'][5]            = array(
					'_tbcards_first_name' => 'Jan',
					'_tbcards_last_name'  => 'Kowalski',
					'_tbcards_mobile'     => '+48 600 000 000',
				);
				$GLOBALS['tbcards_stub']['is_singular']             = array( 'tbcards_card' );
				$GLOBALS['tbcards_stub']['query_vars']              = array(
					'__queried_id' => 5,
					'tbcards_vcard'    => '1',
				);
			}
		);

		/**
		 * The endpoint, recording its responses.
		 */
		$make = static function ( array &$sent ): VCard_Endpoint {
			$settings = new Settings();
			$company  = new Company_Profile( $settings );

			return new VCard_Endpoint(
				new Card_Repository( $settings, $company ),
				$settings,
				$company,
				new Language( $settings ),
				static function ( int $status, array $headers, string $body ) use ( &$sent ) {
					$sent[] = compact( 'status', 'headers', 'body' );
				}
			);
		};

		it(
			'sends the vCard as an attachment, uncached, and reports the download',
			function () use ( $make ) {
				$sent  = array();
				$fired = array();
				add_action(
					'tbcards_vcard_downloaded',
					function ( $id ) use ( &$fired ) {
						$fired[] = $id;
					}
				);

				$make( $sent )->serve();

				assert_same( 200, $sent[0]['status'] );
				assert_same( 'Content-Type: text/vcard; charset=utf-8', $sent[0]['headers'][0] );
				assert_same( 'Content-Disposition: attachment; filename="jan-kowalski.vcf"', $sent[0]['headers'][1] );
				assert_contains( 'ORG:Acme', $sent[0]['body'] );
				assert_same( array( 5 ), $fired );
			}
		);

		it(
			'answers 410 with no personal data for a card that is inactive',
			function () use ( $make ) {
				$sent                                                 = array();
				$GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_inactive'] = '1';

				$make( $sent )->serve();

				assert_same( 410, $sent[0]['status'] );
				assert_false( str_contains( $sent[0]['body'], 'Kowalski' ) );
				assert_false( str_contains( $sent[0]['body'], '600' ) );
			}
		);

		it(
			'stays out of every other request',
			function () use ( $make ) {
				$sent = array();
				unset( $GLOBALS['tbcards_stub']['query_vars']['tbcards_vcard'] );

				$make( $sent )->serve();

				assert_same( array(), $sent );
			}
		);

		it(
			'uses the language of the request for the job title',
			function () use ( $make ) {
				$sent                                               = array();
				$_GET['lang']                                       = 'de';
				$GLOBALS['tbcards_stub']['options']['tbcards_settings']     = array(
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
				$GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_job_title'] = array(
					'pl' => 'Prezes',
					'de' => 'Geschäftsführer',
				);

				$make( $sent )->serve();

				assert_contains( 'TITLE:Geschäftsführer', $sent[0]['body'] );
			}
		);
	}
);

if ( ! function_exists( 'wp_get_image_editor' ) ) {
	/**
	 * A stand-in image editor: "saves" by copying the source bytes.
	 *
	 * @param string $file Image path.
	 * @return object
	 */
	function wp_get_image_editor( $file ) {
		return new class( $file ) {
			private $file;
			public function __construct( $file ) {
				$this->file = $file;
			}
			public function resize( $w, $h, $crop = false ) {
				return true;
			}
			public function set_quality( $q ) {
				return true;
			}
			public function save( $dest, $mime ) {
				copy( $this->file, $dest . '.jpg' );
				return array( 'path' => $dest . '.jpg' );
			}
		};
	}
}

describe(
	'vcard endpoint: photo',
	function () {

		it(
			'embeds the photo on the public site, where wp_tempnam() does not exist',
			function () {
				assert_false( function_exists( 'wp_tempnam' ), 'wp_tempnam is admin-only and must not be relied on' );

				$photo = tempnam( sys_get_temp_dir(), 'tbc' );
				file_put_contents( $photo, 'JPEGBYTES' );

				$GLOBALS['tbcards_stub']['posts_by_id'][5]    = (object) array(
					'ID'        => 5,
					'post_type' => 'tbcards_card',
					'post_name' => 'jan',
				);
				$GLOBALS['tbcards_stub']['post_meta'][5]      = array( '_tbcards_first_name' => 'Jan' );
				$GLOBALS['tbcards_stub']['thumbnails'][5]     = 42;
				$GLOBALS['tbcards_stub']['attached_files'][42] = $photo;
				$GLOBALS['tbcards_stub']['is_singular']       = array( 'tbcards_card' );
				$GLOBALS['tbcards_stub']['query_vars']        = array(
					'__queried_id'  => 5,
					'tbcards_vcard' => '1',
				);

				$sent     = array();
				$settings = new Settings();
				$company  = new Company_Profile( $settings );
				$endpoint = new VCard_Endpoint(
					new Card_Repository( $settings, $company ),
					$settings,
					$company,
					new Language( $settings ),
					static function ( int $status, array $headers, string $body ) use ( &$sent ) {
						$sent[] = $body;
					}
				);

				$endpoint->serve();
				unlink( $photo );

				assert_contains( 'PHOTO;ENCODING=b;TYPE=JPEG:' . base64_encode( 'JPEGBYTES' ), $sent[0] );
				assert_same( base64_encode( 'JPEGBYTES' ), $GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_vcard_photo']['data'] );
			}
		);
	}
);
