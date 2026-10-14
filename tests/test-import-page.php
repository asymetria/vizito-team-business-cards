<?php
/**
 * Pins the Import / Export handlers: guards, the per-user plan, batching.
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
require_once $tbcards_root . '/includes/csv/class-exporter.php';
require_once $tbcards_root . '/includes/admin/class-settings-page.php';
require_once $tbcards_root . '/includes/admin/class-import-page.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Csv\Exporter;
use Asymetria\TeamCards\Csv\Importer;
use Asymetria\TeamCards\Admin\Import_Page;

if ( ! function_exists( 'tbcards_test_import_page' ) ) {
	/**
	 * The page, recording redirects and sent files; uploads read from a table.
	 *
	 * @param array $log     Receives array( kind, value ).
	 * @param array $uploads Temp name => contents.
	 * @return Import_Page
	 */
	function tbcards_test_import_page( array &$log, array $uploads = array() ): Import_Page {
		$settings = new Settings();
		$cards    = new Card_Repository( $settings, new Company_Profile( $settings ) );

		return new Import_Page(
			new Importer(
				$settings,
				$cards,
				static function () {
					return 0;
				},
				static function () {
					return 0;
				}
			),
			new Exporter( $settings, $cards ),
			static function ( string $url ) use ( &$log ) {
				$log[] = array( 'redirect', $url );
			},
			static function ( array $headers, string $body ) use ( &$log ) {
				$log[] = array( 'send', $headers );
			},
			static function ( array $file ) use ( $uploads ) {
				return $uploads[ $file['tmp_name'] ] ?? '';
			}
		);
	}

	/**
	 * Whether a callable ends in wp_die(), and with what.
	 *
	 * @param callable $fn Callable.
	 * @return string|null The message, or null when it did not die.
	 */
	function tbcards_test_die_message( callable $fn ): ?string {
		try {
			$fn();
		} catch ( Tbcards_Died $e ) {
			return $e->getMessage();
		}
		return null;
	}
}

describe(
	'import page',
	function () {

		before_each(
			function () {
				$_POST                                                = array();
				$_FILES                                               = array();
				$_REQUEST                                             = array( '_wpnonce' => 'nonce:tbcards_import' );
				$GLOBALS['tbcards_stub']['capabilities']['edit_others_posts'] = true;
				$GLOBALS['tbcards_stub']['current_user_id']               = 3;
				$GLOBALS['tbcards_stub']['next_post_id']                  = 100;
			}
		);

		it(
			'keeps a pasted preview for this user only, then shows it',
			function () {
				$log                   = array();
				$_POST['tbcards_csv_text'] = "first_name\nJan\nOla\n";

				tbcards_test_import_page( $log )->preview();

				$plan = $GLOBALS['tbcards_stub']['transients']['tbcards_import_3'];
				assert_same( 2, $plan['value']['counts']['create'] );
				assert_same( 3600, $plan['ttl'] );
				assert_contains( 'step=preview', $log[0][1] );
				assert_false( isset( $GLOBALS['tbcards_stub']['inserted_posts'] ) );
			}
		);

		it(
			'reads an uploaded .csv and refuses other files or empty input',
			function () {
				$log                   = array();
				$_FILES['tbcards_csv_file'] = array(
					'name'     => 'team.csv',
					'tmp_name' => 'tmp1',
					'size'     => 20,
				);

				tbcards_test_import_page( $log, array( 'tmp1' => "first_name\nJan\n" ) )->preview();
				assert_same( 1, $GLOBALS['tbcards_stub']['transients']['tbcards_import_3']['value']['counts']['create'] );

				$_FILES['tbcards_csv_file']['name'] = 'team.php';
				assert_contains( '.csv', (string) tbcards_test_die_message( array( tbcards_test_import_page( $log ), 'preview' ) ) );

				$_FILES = array();
				assert_contains( 'Paste', (string) tbcards_test_die_message( array( tbcards_test_import_page( $log ), 'preview' ) ) );
			}
		);

		it(
			'refuses every handler without the nonce or the capability',
			function () {
				$log = array();

				foreach ( array( 'preview', 'run', 'export' ) as $handler ) {
					$_REQUEST = array();
					assert_true( null !== tbcards_test_die_message( array( tbcards_test_import_page( $log ), $handler ) ), $handler . ' nonce' );

					$_REQUEST                                             = array( '_wpnonce' => 'nonce:tbcards_import' );
					$GLOBALS['tbcards_stub']['capabilities']['edit_others_posts'] = false;
					assert_true( null !== tbcards_test_die_message( array( tbcards_test_import_page( $log ), $handler ) ), $handler . ' capability' );
					$GLOBALS['tbcards_stub']['capabilities']['edit_others_posts'] = true;
				}

				assert_same( array(), $log );
			}
		);

		it(
			'imports in batches of 50 through a redirect loop, then forgets the plan',
			function () {
				$log                   = array();
				$_POST['tbcards_csv_text'] = "first_name\n" . str_repeat( "Jan\n", 120 );
				$page                  = tbcards_test_import_page( $log );

				$page->preview();
				$page->run();

				assert_same( 50, count( $GLOBALS['tbcards_stub']['inserted_posts'] ) );
				assert_contains( 'offset=50', $log[1][1] );
				assert_contains( 'done=50', $log[1][1] );

				$_REQUEST['offset'] = '50';
				$_REQUEST['done']   = '50';
				$page->run();
				$_REQUEST['offset'] = '100';
				$_REQUEST['done']   = '100';
				$page->run();

				assert_same( 120, count( $GLOBALS['tbcards_stub']['inserted_posts'] ) );
				assert_contains( 'step=done', $log[3][1] );
				assert_contains( 'done=120', $log[3][1] );
				assert_false( isset( $GLOBALS['tbcards_stub']['transients']['tbcards_import_3'] ) );
			}
		);

		it(
			'does not run a batch twice when the same request comes again',
			function () {
				$log                   = array();
				$_POST['tbcards_csv_text'] = "first_name
Jan
Ola
";
				$page                  = tbcards_test_import_page( $log );

				$page->preview();
				$page->run();

				$GLOBALS['tbcards_stub']['transients']['tbcards_import_3'] = array(
					'value'   => $GLOBALS['tbcards_stub']['transients']['tbcards_import_3']['value'] ?? array(),
					'ttl'     => 3600,
					'expires' => 0,
				);
				$_POST['tbcards_csv_text'] = "first_name
Jan
Ola
";
				$page->preview();
				$plan              = $GLOBALS['tbcards_stub']['transients']['tbcards_import_3']['value'];
				$plan['committed'] = 50;
				$GLOBALS['tbcards_stub']['transients']['tbcards_import_3']['value'] = $plan;

				$page->run();

				assert_same( 2, count( $GLOBALS['tbcards_stub']['inserted_posts'] ) );
			}
		);

		it(
			'says so when the preview has expired',
			function () {
				$log = array();

				assert_contains( 'expired', (string) tbcards_test_die_message( array( tbcards_test_import_page( $log ), 'run' ) ) );
			}
		);

		it(
			'sends the export as a dated CSV attachment',
			function () {
				$log = array();

				tbcards_test_import_page( $log )->export();

				assert_same( 'send', $log[0][0] );
				assert_same( 'Content-Type: text/csv; charset=utf-8', $log[0][1][0] );
				assert_contains( 'business-cards-', $log[0][1][1] );
			}
		);
	}
);
