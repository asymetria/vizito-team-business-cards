<?php
/**
 * Pins the import plan and its commit, and the export/import round trip.
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
require_once $tbcards_root . '/includes/csv/class-csv-reader.php';
require_once $tbcards_root . '/includes/csv/class-csv-writer.php';
require_once $tbcards_root . '/includes/csv/class-importer.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Csv\Csv_Reader;
use Asymetria\TeamCards\Csv\Csv_Writer;
use Asymetria\TeamCards\Csv\Importer;

if ( ! function_exists( 'tbcards_test_importer' ) ) {
	/**
	 * An importer whose lookups read two small tables.
	 *
	 * @param array $emails Email => card id.
	 * @param array $photos Reference => attachment id.
	 * @return Importer
	 */
	function tbcards_test_importer( array $emails = array(), array $photos = array() ): Importer {
		$settings = new Settings();

		return new Importer(
			$settings,
			new Card_Repository( $settings, new Company_Profile( $settings ) ),
			static function ( string $email, string $slug ) use ( $emails ) {
				return $emails[ $email ] ?? ( $emails[ 'slug:' . $slug ] ?? 0 );
			},
			static function ( string $reference ) use ( $photos ) {
				return $photos[ $reference ] ?? 0;
			}
		);
	}

	/**
	 * A plan from CSV text.
	 *
	 * @param string   $csv      CSV text.
	 * @param Importer $importer Importer.
	 * @return array
	 */
	function tbcards_test_plan( string $csv, Importer $importer ): array {
		return $importer->plan( ( new Csv_Reader() )->parse( $csv ) );
	}
}

describe(
	'importer: plan',
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
			'creates new people, updates known ones by email, then by slug',
			function () {
				$GLOBALS['tbcards_stub']['post_meta'][7] = array(
					'_tbcards_first_name' => 'Piotr',
					'_tbcards_last_name'  => 'Lis',
				);
				$plan = tbcards_test_plan(
					"first_name;last_name;email;slug\nJan;Kowalski;JAN@firma.example;\nOla;Nowak;ola@firma.example;\n;;;piotr\n",
					tbcards_test_importer(
						array(
							'jan@firma.example' => 5,
							'slug:piotr'        => 7,
						)
					)
				);

				assert_same( array( 'update', 'create', 'update' ), array_column( $plan['rows'], 'action' ) );
				assert_same( 'Piotr Lis', $plan['rows'][2]['name'], 'an update without names shows the stored name' );
				assert_same( 5, $plan['rows'][0]['id'] );
				assert_same( 7, $plan['rows'][2]['id'] );
				assert_same(
					array(
						'create' => 1,
						'update' => 2,
						'error'  => 0,
					),
					$plan['counts']
				);
			}
		);

		it(
			'leaves empty cells out and clears on a dash',
			function () {
				$plan   = tbcards_test_plan( "email,city,landline\njan@firma.example,,-\n", tbcards_test_importer( array( 'jan@firma.example' => 5 ) ) );
				$values = $plan['rows'][0]['values'];

				assert_false( array_key_exists( 'city', $values ) );
				assert_same( '', $values['landline'] );
			}
		);

		it(
			'reads translated columns per language',
			function () {
				$plan = tbcards_test_plan( "first_name,job_title_pl,job_title_de\nJan,Prezes,-\n", tbcards_test_importer() );

				assert_same(
					array(
						'pl' => 'Prezes',
						'de' => '',
					),
					$plan['rows'][0]['values']['job_title']
				);
			}
		);

		it(
			'reports every problem on its line and writes nothing',
			function () {
				$plan = tbcards_test_plan(
					"first_name,email,website,photo,status\n"
					. "Jan,not-an-email,,,\n"
					. "Ola,,javascript:alert(1),,\n"
					. "Ewa,,,missing.jpg,\n"
					. "Adam,,,,retired\n"
					. ",,,,\n"
					. "Too,many,cells,here,now,extra\n",
					tbcards_test_importer()
				);

				assert_same( array_fill( 0, 6, 'error' ), array_column( $plan['rows'], 'action' ) );
				assert_same( array( 2, 3, 4, 5, 6, 7 ), array_column( $plan['rows'], 'line' ) );
				assert_contains( 'not-an-email', $plan['rows'][0]['messages'][0] );
				assert_contains( 'Website', $plan['rows'][1]['messages'][0] );
				assert_contains( 'missing.jpg', $plan['rows'][2]['messages'][0] );
				assert_contains( 'retired', $plan['rows'][3]['messages'][0] );
				assert_contains( 'first or last name', $plan['rows'][4]['messages'][0] );
				assert_contains( '6 cells', $plan['rows'][5]['messages'][0] );
				assert_same( array(), $GLOBALS['tbcards_stub']['post_meta'] );
			}
		);

		it(
			'warns about unknown columns and too many rows',
			function () {
				$csv  = "first_name,nickname\n" . str_repeat( "Jan,J\n", Importer::MAX_ROWS + 3 );
				$plan = tbcards_test_plan( $csv, tbcards_test_importer() );

				assert_same( Importer::MAX_ROWS, count( $plan['rows'] ) );
				assert_contains( 'nickname', $plan['warnings'][0] );
				assert_contains( (string) Importer::MAX_ROWS, $plan['warnings'][1] );
			}
		);

		it(
			'finds photos by reference',
			function () {
				$plan = tbcards_test_plan( "first_name,photo\nJan,jan.jpg\n", tbcards_test_importer( array(), array( 'jan.jpg' => 42 ) ) );

				assert_same( 42, $plan['rows'][0]['photo'] );
			}
		);
	}
);

describe(
	'importer: commit',
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
				$GLOBALS['tbcards_stub']['next_post_id']              = 100;
				$GLOBALS['tbcards_stub']['capabilities']['edit_post'] = true;
			}
		);

		it(
			'leaves a matched card alone when the importer may not edit it',
			function () {
				$GLOBALS['tbcards_stub']['capabilities']['edit_post'] = array( 6 );
				$GLOBALS['tbcards_stub']['posts_by_id'][5]            = (object) array(
					'ID'        => 5,
					'post_type' => 'tbcards_card',
				);
				$GLOBALS['tbcards_stub']['post_meta'][5]              = array( '_tbcards_email' => 'jan@firma.example' );
				$importer                                             = tbcards_test_importer( array( 'jan@firma.example' => 5 ) );

				assert_same( 0, $importer->commit( tbcards_test_plan( "email,city
jan@firma.example,Kraków
", $importer ), 0, 50 ) );
				assert_false( isset( $GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_city'] ) );
			}
		);

		it(
			'creates a published card with its fields, photo and status',
			function () {
				$importer = tbcards_test_importer( array(), array( 'jan.jpg' => 42 ) );
				$plan     = tbcards_test_plan( "first_name,last_name,slug,job_title_pl,photo,status\nJan,Kowalski,jan-k,Prezes,jan.jpg,inactive\n", $importer );

				assert_same( 1, $importer->commit( $plan, 0, 50 ) );

				$post = $GLOBALS['tbcards_stub']['inserted_posts'][0];
				assert_same( 'tbcards_card', $post['post_type'] );
				assert_same( 'publish', $post['post_status'] );
				assert_same( 'Jan Kowalski', $post['post_title'] );
				assert_same( 'jan-k', $post['post_name'] );

				$meta = $GLOBALS['tbcards_stub']['post_meta'][100];
				assert_same( 'Jan', $meta['_tbcards_first_name'] );
				assert_same( array( 'pl' => 'Prezes' ), $meta['_tbcards_job_title'] );
				assert_same( '1', $meta['_tbcards_inactive'] );
				assert_same( 42, $GLOBALS['tbcards_stub']['thumbnails'][100] );
			}
		);

		it(
			'keeps other languages when a file updates only one',
			function () {
				$GLOBALS['tbcards_stub']['posts_by_id'][5] = (object) array(
					'ID'        => 5,
					'post_type' => 'tbcards_card',
				);
				$GLOBALS['tbcards_stub']['post_meta'][5]   = array(
					'_tbcards_first_name' => 'Jan',
					'_tbcards_email'      => 'jan@firma.example',
					'_tbcards_job_title'  => array(
						'pl' => 'Prezes',
						'de' => 'Chef',
					),
				);
				$importer                              = tbcards_test_importer( array( 'jan@firma.example' => 5 ) );
				$plan                                  = tbcards_test_plan( "email,job_title_de\njan@firma.example,Geschäftsführer\n", $importer );

				$importer->commit( $plan, 0, 50 );

				assert_same(
					array(
						'pl' => 'Prezes',
						'de' => 'Geschäftsführer',
					),
					$GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_job_title']
				);
				assert_same( 'Jan', $GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_first_name'] );
			}
		);

		it(
			'works through a plan in slices and skips errors',
			function () {
				$importer = tbcards_test_importer();
				$plan     = tbcards_test_plan( "first_name\nA\n\nB\nC\n", $importer );
				$plan['rows'][1]['action'] = 'error';

				assert_same( 1, $importer->commit( $plan, 0, 2 ) );
				assert_same( 1, $importer->commit( $plan, 2, 2 ) );
				assert_same( 2, count( $GLOBALS['tbcards_stub']['inserted_posts'] ) );
			}
		);

		it(
			'reports each saved card, so caches are purged',
			function () {
				$saved = array();
				add_action(
					'tbcards_card_saved',
					function ( $id ) use ( &$saved ) {
						$saved[] = $id;
					}
				);
				$importer = tbcards_test_importer();

				$importer->commit( tbcards_test_plan( "first_name\nA\nB\n", $importer ), 0, 50 );

				assert_same( array( 100, 101 ), $saved );
			}
		);
	}
);

describe(
	'csv writer',
	function () {

		it(
			'guards formulas but not phone numbers',
			function () {
				assert_same( "'=SUM(A1)", Csv_Writer::guard( '=SUM(A1)' ) );
				assert_same( "'@cmd", Csv_Writer::guard( '@cmd' ) );
				assert_same( "'+cmd|' /C calc'!A0", Csv_Writer::guard( "+cmd|' /C calc'!A0" ) );
				assert_same( '+48 609 909 828', Csv_Writer::guard( '+48 609 909 828' ) );
				assert_same( '-', Csv_Writer::guard( '-' ) );
				assert_same( '(22) 123-45-67', Csv_Writer::guard( '(22) 123-45-67' ) );
			}
		);

		it(
			'undoes its own guard on import',
			function () {
				foreach ( array( '=SUM(A1)', '@cmd', '+48 609', 'plain', "'quoted" ) as $cell ) {
					assert_same( $cell, Csv_Writer::unguard( Csv_Writer::guard( $cell ) ), $cell );
				}
			}
		);

		it(
			'lists slug, every field with translated ones per language, photo and status',
			function () {
				$columns = Csv_Writer::columns( array( 'pl', 'de' ) );

				assert_same( 'slug', $columns[0] );
				assert_true( in_array( 'job_title_de', $columns, true ) );
				assert_true( in_array( 'custom_3_label_pl', $columns, true ) );
				assert_false( in_array( 'job_title', $columns, true ) );
				assert_same( array( 'photo', 'status' ), array_slice( $columns, -2 ) );
			}
		);
	}
);

require_once dirname( __DIR__ ) . '/includes/csv/class-exporter.php';

describe(
	'exporter',
	function () {

		before_each(
			function () {
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
				);
				$GLOBALS['tbcards_stub']['options']['tbcards_company']  = array( 'city' => 'Warszawa' );

				foreach ( array(
					5 => array(
						'jan',
						array(
							'_tbcards_first_name' => 'Jan',
							'_tbcards_last_name'  => 'Kowalski',
							'_tbcards_email'      => 'jan@firma.example',
							'_tbcards_mobile'     => '+48 609 909 828',
							'_tbcards_job_title'  => array(
								'pl' => 'Prezes',
								'de' => 'Geschäftsführer',
							),
							'_tbcards_intro'      => array( 'pl' => "Dwie\nlinie; z przecinkiem, i \"cudzysłowem\"" ),
						),
					),
					6 => array(
						'ola',
						array(
							'_tbcards_first_name' => '=Ola',
							'_tbcards_inactive'   => '1',
						),
					),
				) as $id => $row ) {
					$GLOBALS['tbcards_stub']['posts_by_id'][ $id ] = (object) array(
						'ID'        => $id,
						'post_type' => 'tbcards_card',
						'post_name' => $row[0],
					);
					$GLOBALS['tbcards_stub']['post_meta'][ $id ]   = $row[1];
					$GLOBALS['tbcards_stub']['posts'][]            = $GLOBALS['tbcards_stub']['posts_by_id'][ $id ];
				}

				$GLOBALS['tbcards_stub']['thumbnails'][5]              = 42;
				$GLOBALS['tbcards_stub']['capabilities']['edit_post'] = true;
			}
		);

		/**
		 * The exporter over fresh services.
		 */
		$make = static function (): Asymetria\TeamCards\Csv\Exporter {
			$settings = new Settings();
			return new Asymetria\TeamCards\Csv\Exporter( $settings, new Card_Repository( $settings, new Company_Profile( $settings ) ) );
		};

		it(
			'writes a BOM, semicolons for a Polish site and stored values only',
			function () use ( $make ) {
				$csv    = $make()->csv();
				$parsed = ( new Csv_Reader() )->parse( $csv );
				$jan    = array_combine( $parsed['header'], $parsed['rows'][0] );

				assert_same( "\xEF\xBB\xBF", substr( $csv, 0, 3 ) );
				assert_same( ';', $parsed['delimiter'] );
				assert_same( 'jan', $jan['slug'] );
				assert_same( 'Geschäftsführer', $jan['job_title_de'] );
				assert_same( '', $jan['city'] );
				assert_same( '42', $jan['photo'] );
				assert_same( 'active', $jan['status'] );
				assert_same( "Dwie\nlinie; z przecinkiem, i \"cudzysłowem\"", $jan['intro_pl'] );
			}
		);

		it(
			'uses commas elsewhere',
			function () use ( $make ) {
				$GLOBALS['tbcards_stub']['options']['locale'] = 'en_GB';

				assert_same( ',', $make()->delimiter() );
			}
		);

		it(
			'imports its own file back as updates that change nothing',
			function () use ( $make ) {
				$csv      = $make()->csv();
				$before   = $GLOBALS['tbcards_stub']['post_meta'];
				$importer = tbcards_test_importer(
					array(
						'jan@firma.example' => 5,
						'slug:ola'          => 6,
					),
					array( '42' => 42 )
				);
				$plan     = $importer->plan( ( new Csv_Reader() )->parse( $csv ) );

				assert_same( array( 'update', 'update' ), array_column( $plan['rows'], 'action' ) );
				assert_same( 'inactive', $plan['rows'][1]['status'] );

				assert_same( 2, $importer->commit( $plan, 0, 50 ) );
				assert_same( $before, $GLOBALS['tbcards_stub']['post_meta'] );
			}
		);
	}
);

describe(
	'importer: photo lookup',
	function () {

		it(
			'matches the exact file name, with or without year folders',
			function () {
				$GLOBALS['tbcards_stub']['posts']     = array(
					array(
						'ID'          => 41,
						'post_type'   => 'attachment',
						'post_status' => 'inherit',
					),
					array(
						'ID'          => 42,
						'post_type'   => 'attachment',
						'post_status' => 'inherit',
					),
					array(
						'ID'          => 43,
						'post_type'   => 'attachment',
						'post_status' => 'inherit',
					),
				);
				$GLOBALS['tbcards_stub']['post_meta'] = array(
					41 => array( '_wp_attached_file' => '2026/09/marjan.jpg' ),
					42 => array( '_wp_attached_file' => 'jan.jpg.webp' ),
					43 => array( '_wp_attached_file' => 'jan.jpg' ),
				);

				$settings = new Settings();
				$importer = new Importer( $settings, new Card_Repository( $settings, new Company_Profile( $settings ) ) );

				assert_same( 43, $importer->find_photo( 'jan.jpg' ) );
				assert_same( 0, $importer->find_photo( 'ola.jpg' ) );
			}
		);
	}
);
