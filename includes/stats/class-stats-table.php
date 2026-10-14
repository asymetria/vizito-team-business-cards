<?php
/**
 * The statistics table.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Stats;

use Asymetria\TeamCards\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Stats_Table' ) ) {

	/**
	 * Daily counters: one row per card, day, event and source, never per
	 * visit. No IP, no user agent, nothing personal is stored.
	 *
	 * Every query goes through $wpdb->prepare(); the table name is the only
	 * interpolated part and is built from $wpdb->prefix.
	 */
	final class Stats_Table {

		public const VERSION        = '1';
		public const VERSION_OPTION = 'tbcards_db_version';
		public const CRON_HOOK      = 'tbcards_stats_purge';
		public const SOURCES        = array( 'qr', 'link' );

		/**
		 * The database, $wpdb in production.
		 *
		 * @var object
		 */
		private $db;

		/**
		 * Constructor.
		 *
		 * @param object|null $db Database; the global $wpdb by default.
		 */
		public function __construct( $db = null ) {
			$this->db = $db ?? $GLOBALS['wpdb'];
		}

		/**
		 * Hooks.
		 *
		 * @param Settings $settings Plugin settings, for retention.
		 * @return void
		 */
		public function hooks( Settings $settings ): void {
			add_action( 'init', array( $this, 'maybe_upgrade' ), 5 );
			add_action( self::CRON_HOOK, array( $this, 'purge' ) );
			$sync = function () use ( $settings ) {
				$this->sync_schedule( $settings );
			};

			// add_option_* on the very first save, update_option_* afterwards.
			add_action( 'add_option_' . Settings::OPTION, $sync );
			add_action(
				'update_option_' . Settings::OPTION,
				$sync
			);
		}

		/**
		 * The table name.
		 *
		 * @return string
		 */
		public function table(): string {
			return $this->db->prefix . 'tbcards_stats';
		}

		/**
		 * Creates or updates the table.
		 *
		 * @return void
		 */
		public function install(): void {
			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			$collate = method_exists( $this->db, 'get_charset_collate' ) ? $this->db->get_charset_collate() : '';

			// dbDelta wants two spaces after PRIMARY KEY and one field per line.
			dbDelta(
				'CREATE TABLE ' . $this->table() . " (
card_id bigint(20) unsigned NOT NULL,
day date NOT NULL,
event varchar(16) NOT NULL,
source varchar(8) NOT NULL,
count int(10) unsigned NOT NULL DEFAULT 0,
PRIMARY KEY  (card_id,day,event,source),
KEY day (day)
) $collate;"
			);

			update_option( self::VERSION_OPTION, self::VERSION );
		}

		/**
		 * Installs when the stored schema version is behind.
		 *
		 * @return void
		 */
		public function maybe_upgrade(): void {
			if ( self::VERSION !== (string) get_option( self::VERSION_OPTION, '' ) ) {
				$this->install();
			}
		}

		/**
		 * Counts one event for today.
		 *
		 * @param int    $card_id Card id.
		 * @param string $event   Event name.
		 * @param string $source  "qr" or "link".
		 * @return void
		 */
		public function increment( int $card_id, string $event, string $source ): void {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table; values prepared.
			$this->db->query(
				$this->db->prepare(
					'INSERT INTO ' . $this->table() . ' (card_id, day, event, source, count) VALUES (%d, %s, %s, %s, 1) ON DUPLICATE KEY UPDATE count = count + 1',
					$card_id,
					current_time( 'Y-m-d' ),
					$event,
					$source
				)
			);
		}

		/**
		 * Event totals per card over a date range, in one query.
		 *
		 * @param int[]  $ids  Card ids.
		 * @param string $from First day, Y-m-d.
		 * @param string $to   Last day, Y-m-d.
		 * @return array<int, array<string, int>> card id => event => total; every asked id present.
		 */
		public function totals( array $ids, string $from, string $to ): array {
			$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

			if ( array() === $ids ) {
				return array();
			}

			$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- own table; placeholders built above.
			$rows = $this->db->get_results(
				$this->db->prepare(
					'SELECT card_id, event, SUM(count) AS total FROM ' . $this->table() . " WHERE card_id IN ($in) AND day BETWEEN %s AND %s GROUP BY card_id, event",
					array_merge( $ids, array( $from, $to ) )
				)
			);

			$totals = array_fill_keys( $ids, array() );

			foreach ( (array) $rows as $row ) {
				$totals[ (int) $row->card_id ][ (string) $row->event ] = (int) $row->total;
			}

			return $totals;
		}

		/**
		 * Views per day for one card, split by source, zero-filled.
		 *
		 * @param int    $card_id Card id.
		 * @param string $from    First day, Y-m-d.
		 * @param string $to      Last day, Y-m-d.
		 * @return array<string, array{qr: int, link: int}>
		 */
		public function daily_views( int $card_id, string $from, string $to ): array {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table; values prepared.
			$rows = $this->db->get_results(
				$this->db->prepare(
					'SELECT day, source, SUM(count) AS total FROM ' . $this->table() . " WHERE card_id = %d AND event = 'view' AND day BETWEEN %s AND %s GROUP BY day, source",
					$card_id,
					$from,
					$to
				)
			);

			$series = array();

			for ( $day = strtotime( $from . ' 00:00:00 UTC' ), $end = strtotime( $to . ' 00:00:00 UTC' ); $day <= $end; $day += DAY_IN_SECONDS ) {
				$series[ gmdate( 'Y-m-d', $day ) ] = array(
					'qr'   => 0,
					'link' => 0,
				);
			}

			foreach ( (array) $rows as $row ) {
				if ( isset( $series[ $row->day ][ $row->source ] ) ) {
					$series[ $row->day ][ $row->source ] = (int) $row->total;
				}
			}

			return $series;
		}

		/**
		 * Deletes rows older than a number of days.
		 *
		 * @param int $days Days to keep.
		 * @return void
		 */
		public function purge_older_than( int $days ): void {
			$cutoff = gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) - $days * DAY_IN_SECONDS );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table; value prepared.
			$this->db->query( $this->db->prepare( 'DELETE FROM ' . $this->table() . ' WHERE day < %s', $cutoff ) );
		}

		/**
		 * The cron handler.
		 *
		 * @return void
		 */
		public function purge(): void {
			$days = (int) ( new Settings() )->get( 'retention_days' );

			if ( $days > 0 ) {
				$this->purge_older_than( $days );
			}
		}

		/**
		 * Keeps the daily purge scheduled exactly while retention is set.
		 *
		 * @param Settings $settings Plugin settings.
		 * @return void
		 */
		public function sync_schedule( Settings $settings ): void {
			$on = (int) $settings->get( 'retention_days' ) > 0;

			if ( $on && ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
			} elseif ( ! $on ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
			}
		}
	}
}
