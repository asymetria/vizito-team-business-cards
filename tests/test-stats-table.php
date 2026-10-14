<?php
/**
 * Pins the statistics table: schema, counting SQL, reads, retention.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/stats/class-stats-table.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Stats\Stats_Table;

describe(
	'stats table',
	function () {

		it(
			'creates the table with one row per card, day, event and source',
			function () {
				$db = new Tbcards_Fake_Db();
				( new Stats_Table( $db ) )->install();

				$sql = $GLOBALS['tbcards_stub']['db_delta'][0];

				assert_contains( 'CREATE TABLE wp_tbcards_stats', $sql );
				assert_contains( 'PRIMARY KEY  (card_id,day,event,source)', $sql );
				assert_contains( 'KEY day (day)', $sql );
				assert_same( Stats_Table::VERSION, $GLOBALS['tbcards_stub']['options']['tbcards_db_version'] );
			}
		);

		it(
			'installs only when the schema version is behind',
			function () {
				$db = new Tbcards_Fake_Db();
				$GLOBALS['tbcards_stub']['options']['tbcards_db_version'] = Stats_Table::VERSION;

				( new Stats_Table( $db ) )->maybe_upgrade();
				assert_false( isset( $GLOBALS['tbcards_stub']['db_delta'] ) );

				$GLOBALS['tbcards_stub']['options']['tbcards_db_version'] = '0';
				( new Stats_Table( $db ) )->maybe_upgrade();
				assert_same( 1, count( $GLOBALS['tbcards_stub']['db_delta'] ) );
			}
		);

		it(
			'counts with one upsert per hit, on today\'s date',
			function () {
				$db = new Tbcards_Fake_Db();

				( new Stats_Table( $db ) )->increment( 5, 'view', 'qr' );

				assert_same(
					"INSERT INTO wp_tbcards_stats (card_id, day, event, source, count) VALUES (5, '2026-01-01', 'view', 'qr', 1) ON DUPLICATE KEY UPDATE count = count + 1",
					$db->queries[0]
				);
			}
		);

		it(
			'sums events per card over a date range, in one query for many cards',
			function () {
				$db            = new Tbcards_Fake_Db();
				$db->results[] = array(
					(object) array(
						'card_id' => '5',
						'event'   => 'view',
						'total'   => '12',
					),
					(object) array(
						'card_id' => '5',
						'event'   => 'vcard',
						'total'   => '3',
					),
					(object) array(
						'card_id' => '6',
						'event'   => 'view',
						'total'   => '1',
					),
				);

				$totals = ( new Stats_Table( $db ) )->totals( array( 5, 6, 7 ), '2026-01-01', '2026-01-30' );

				assert_same( 1, count( $db->queries ) );
				assert_contains( 'card_id IN (5,6,7)', $db->queries[0] );
				assert_contains( "day BETWEEN '2026-01-01' AND '2026-01-30'", $db->queries[0] );
				assert_same( 12, $totals[5]['view'] );
				assert_same( 3, $totals[5]['vcard'] );
				assert_same( 1, $totals[6]['view'] );
				assert_same( array(), $totals[7] );
			}
		);

		it(
			'answers an empty map for no cards without querying',
			function () {
				$db = new Tbcards_Fake_Db();

				assert_same( array(), ( new Stats_Table( $db ) )->totals( array(), '2026-01-01', '2026-01-30' ) );
				assert_same( array(), $db->queries );
			}
		);

		it(
			'builds a zero-filled daily view series split by source',
			function () {
				$db            = new Tbcards_Fake_Db();
				$db->results[] = array(
					(object) array(
						'day'    => '2026-01-02',
						'source' => 'qr',
						'total'  => '4',
					),
					(object) array(
						'day'    => '2026-01-02',
						'source' => 'link',
						'total'  => '1',
					),
				);

				$series = ( new Stats_Table( $db ) )->daily_views( 5, '2026-01-01', '2026-01-03' );

				assert_same(
					array(
						'2026-01-01' => array( 'qr' => 0, 'link' => 0 ),
						'2026-01-02' => array( 'qr' => 4, 'link' => 1 ),
						'2026-01-03' => array( 'qr' => 0, 'link' => 0 ),
					),
					$series
				);
			}
		);

		it(
			'deletes rows older than the retention window',
			function () {
				$db = new Tbcards_Fake_Db();

				( new Stats_Table( $db ) )->purge_older_than( 30 );

				assert_same( "DELETE FROM wp_tbcards_stats WHERE day < '2025-12-02'", $db->queries[0] );
			}
		);

		it(
			'schedules the daily purge only while retention is set',
			function () {
				$table = new Stats_Table( new Tbcards_Fake_Db() );

				$GLOBALS['tbcards_stub']['options']['tbcards_settings'] = array( 'retention_days' => 90 );
				$table->sync_schedule( new Settings() );
				assert_true( isset( $GLOBALS['tbcards_stub']['cron']['tbcards_stats_purge'] ) );

				$GLOBALS['tbcards_stub']['options']['tbcards_settings'] = array( 'retention_days' => 0 );
				$table->sync_schedule( new Settings() );
				assert_false( isset( $GLOBALS['tbcards_stub']['cron']['tbcards_stats_purge'] ) );

				$table->hooks( new Settings() );
				assert_true( isset( $GLOBALS['tbcards_stub']['actions']['add_option_tbcards_settings'] ), 'first save is add_option' );
				assert_true( isset( $GLOBALS['tbcards_stub']['actions']['update_option_tbcards_settings'] ) );
			}
		);
	}
);
