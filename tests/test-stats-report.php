<?php
/**
 * Pins the statistics screens: columns, chart, CSV.
 *
 * @package Vizito_Team_Business_Cards
 */

$tbcards_root = dirname( __DIR__ );
require_once $tbcards_root . '/includes/class-settings.php';
require_once $tbcards_root . '/includes/card/class-fields.php';
require_once $tbcards_root . '/includes/card/class-card.php';
require_once $tbcards_root . '/includes/company/class-company-profile.php';
require_once $tbcards_root . '/includes/card/class-card-repository.php';
require_once $tbcards_root . '/includes/admin/class-settings-page.php';
require_once $tbcards_root . '/includes/stats/class-stats-table.php';
require_once $tbcards_root . '/includes/csv/class-csv-writer.php';
require_once $tbcards_root . '/includes/stats/class-stats-report.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Stats\Stats_Report;
use Asymetria\TeamCards\Stats\Stats_Table;

if ( ! function_exists( 'tbcards_test_report' ) ) {
	/**
	 * A report over a fake database.
	 *
	 * @param Tbcards_Fake_Db $db   Database.
	 * @param array       $sent Receives responses.
	 * @return Stats_Report
	 */
	function tbcards_test_report( Tbcards_Fake_Db $db, array &$sent = array() ): Stats_Report {
		$settings = new Settings();

		return new Stats_Report(
			new Stats_Table( $db ),
			new Card_Repository( $settings, new Company_Profile( $settings ) ),
			static function ( array $headers, string $body ) use ( &$sent ) {
				$sent[] = array( $headers, $body );
			}
		);
	}
}

describe(
	'stats report',
	function () {

		before_each(
			function () {
				$_REQUEST = array();

				foreach ( array( 5 => array( 'jan', 'Jan' ), 6 => array( 'ola', '=HYPERLINK("x")' ) ) as $id => $row ) {
					$GLOBALS['tbcards_stub']['posts_by_id'][ $id ] = (object) array(
						'ID'        => $id,
						'post_type' => 'tbcards_card',
						'post_name' => $row[0],
					);
					$GLOBALS['tbcards_stub']['post_meta'][ $id ]   = array( '_tbcards_first_name' => $row[1] );
					$GLOBALS['tbcards_stub']['posts'][]            = $GLOBALS['tbcards_stub']['posts_by_id'][ $id ];
				}

				$GLOBALS['tbcards_stub']['capabilities']['edit_others_posts'] = true;
			}
		);

		it(
			'adds both columns right after the title',
			function () {
				$columns = tbcards_test_report( new Tbcards_Fake_Db() )->columns(
					array(
						'cb'    => '',
						'title' => 'Title',
						'date'  => 'Date',
					)
				);

				assert_same( array( 'cb', 'title', 'tbcards_views', 'tbcards_vcard', 'date' ), array_keys( $columns ) );
			}
		);

		it(
			'fills every row of the list from one query',
			function () {
				$GLOBALS['wp_query'] = (object) array( 'posts' => $GLOBALS['tbcards_stub']['posts'] );
				$db                  = new Tbcards_Fake_Db();
				$db->results[]       = array(
					(object) array(
						'card_id' => '5',
						'event'   => 'view',
						'total'   => '1234',
					),
				);
				$report              = tbcards_test_report( $db );

				ob_start();
				$report->column( 'tbcards_views', 5 );
				$report->column( 'tbcards_vcard', 5 );
				$report->column( 'tbcards_views', 6 );
				$report->column( 'title', 6 );
				$html = (string) ob_get_clean();

				unset( $GLOBALS['wp_query'] );

				assert_same( 1, count( $db->queries ) );
				assert_contains( 'card_id IN (5,6)', $db->queries[0] );
				assert_same( '1,23400', $html );
			}
		);

		it(
			'draws stacked bars with a text alternative and the numbers',
			function () {
				$html = tbcards_test_report( new Tbcards_Fake_Db() )->chart(
					array(
						'2026-01-01' => array(
							'qr'   => 0,
							'link' => 0,
						),
						'2026-01-02' => array(
							'qr'   => 3,
							'link' => 1,
						),
					)
				);

				assert_contains( 'role="img"', $html );
				assert_contains( 'aria-label="Daily views: 3 from printed codes, 1 from links."', $html );
				assert_same( 2, substr_count( $html, '<rect' ) );
				assert_contains( '<td>2026-01-02</td><td>3</td><td>1</td>', $html );
			}
		);

		it(
			'draws an empty chart without dividing by zero',
			function () {
				$html = tbcards_test_report( new Tbcards_Fake_Db() )->chart( array() );

				assert_contains( '<svg', $html );
				assert_same( 0, substr_count( $html, '<rect' ) );
			}
		);

		it(
			'exports a CSV with a header, a BOM and a formula guard',
			function () {
				$sent                 = array();
				$_REQUEST['_wpnonce'] = 'nonce:tbcards_stats_csv';
				$db                   = new Tbcards_Fake_Db();
				$db->results[]        = array(
					(object) array(
						'card_id' => '5',
						'event'   => 'view',
						'total'   => '7',
					),
				);

				tbcards_test_report( $db, $sent )->csv();
				$body  = $sent[0][1];
				$lines = explode( "\r\n", substr( $body, 3 ) );

				assert_same( "\xEF\xBB\xBF", substr( $body, 0, 3 ) );
				assert_same( '"Name","Address","Views","Contacts saved","Calls","Emails","WhatsApp","Directions","Shares","Links"', $lines[0] );
				assert_same( '"Jan","jan","7","0","0","0","0","0","0","0"', $lines[1] );
				assert_same( '"\'=HYPERLINK(""x"")","ola","0","0","0","0","0","0","0","0"', $lines[2] );
				assert_contains( 'filename="card-statistics-2025-12-03-2026-01-01.csv"', $sent[0][0][1] );
			}
		);

		it(
			'refuses the CSV without the nonce',
			function () {
				$died = false;

				try {
					$sent = array();
					tbcards_test_report( new Tbcards_Fake_Db(), $sent )->csv();
				} catch ( Tbcards_Died $e ) {
					$died = true;
				}

				assert_true( $died );
			}
		);

		it(
			'ignores a malformed date range and swaps a reversed one',
			function () {
				$sent                 = array();
				$_REQUEST['_wpnonce'] = 'nonce:tbcards_stats_csv';
				$_REQUEST['from']     = '2026-01-10';
				$_REQUEST['to']       = '2026-01-01';

				tbcards_test_report( new Tbcards_Fake_Db(), $sent )->csv();
				assert_contains( '2026-01-01-2026-01-10.csv', $sent[0][0][1] );

				$_REQUEST['from'] = "'; DROP TABLE x; --";
				tbcards_test_report( new Tbcards_Fake_Db(), $sent )->csv();
				assert_contains( '2025-12-03-', $sent[1][0][1] );
			}
		);
	}
);
