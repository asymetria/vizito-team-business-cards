<?php
/**
 * Statistics in the admin.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Stats;

use Asymetria\TeamCards\Admin\Settings_Page;
use Asymetria\TeamCards\Card\Card_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Stats_Report' ) ) {

	/**
	 * Card list columns, a 30-day chart on the card screen, a Statistics page
	 * and its CSV export. Totals for a whole list screen come from one query.
	 */
	final class Stats_Report {

		public const PAGE_SLUG   = 'tbc-stats';
		public const CSV_NONCE   = 'tbcards_stats_csv';
		public const DAYS        = 30;
		public const PAGE_EVENTS = array( 'view', 'vcard', 'call', 'email', 'whatsapp', 'directions', 'share', 'link' );

		/**
		 * Statistics table.
		 *
		 * @var Stats_Table
		 */
		private Stats_Table $table;

		/**
		 * Card repository.
		 *
		 * @var Card_Repository
		 */
		private Card_Repository $cards;

		/**
		 * Sends headers and a body, then ends the request.
		 *
		 * @var callable
		 */
		private $send;

		/**
		 * Totals for the current list screen, loaded on first use.
		 *
		 * @var array|null
		 */
		private ?array $list_totals = null;

		/**
		 * Constructor.
		 *
		 * @param Stats_Table     $table Statistics table.
		 * @param Card_Repository $cards Card repository.
		 * @param callable|null   $send  function( array $headers, string $body ): void.
		 */
		public function __construct( Stats_Table $table, Card_Repository $cards, ?callable $send = null ) {
			$this->table = $table;
			$this->cards = $cards;
			$this->send  = $send ?? static function ( array $headers, string $body ): void {
				\Asymetria\TeamCards\Http::clean_buffers();
				nocache_headers();

				foreach ( $headers as $header ) {
					header( $header );
				}

				echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV file.
				exit;
			};
		}

		/**
		 * Hooks.
		 *
		 * @return void
		 */
		public function hooks(): void {
			add_filter( 'manage_' . Card_Repository::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
			add_action( 'manage_' . Card_Repository::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
			add_action( 'add_meta_boxes_' . Card_Repository::POST_TYPE, array( $this, 'add_meta_box' ) );
			add_action( 'admin_menu', array( $this, 'register_menu' ) );
			add_action( 'admin_post_tbcards_stats_csv', array( $this, 'csv' ) );
		}

		/**
		 * Adds the two columns after the title.
		 *
		 * @param array $columns Columns.
		 * @return array
		 */
		public function columns( $columns ): array {
			$out = array();

			foreach ( (array) $columns as $key => $label ) {
				$out[ $key ] = $label;

				if ( 'title' === $key ) {
					$out['tbcards_views'] = __( 'Views (30 days)', 'vizito-team-business-cards' );
					$out['tbcards_vcard'] = __( 'Contacts saved (30 days)', 'vizito-team-business-cards' );
				}
			}

			return $out;
		}

		/**
		 * Prints one column cell.
		 *
		 * @param string $column  Column key.
		 * @param int    $post_id Post id.
		 * @return void
		 */
		public function column( $column, $post_id ): void {
			$events = array(
				'tbcards_views' => 'view',
				'tbcards_vcard' => 'vcard',
			);

			if ( ! isset( $events[ $column ] ) ) {
				return;
			}

			if ( null === $this->list_totals ) {
				$ids               = array_map(
					static function ( $post ) {
						return (int) $post->ID;
					},
					(array) ( $GLOBALS['wp_query']->posts ?? array() )
				);
				$range             = $this->range( self::DAYS );
				$this->list_totals = $this->table->totals( $ids, $range[0], $range[1] );
			}

			echo esc_html( number_format_i18n( $this->list_totals[ (int) $post_id ][ $events[ $column ] ] ?? 0 ) );
		}

		/**
		 * The side box on the card screen.
		 *
		 * @return void
		 */
		public function add_meta_box(): void {
			add_meta_box( 'tbcards_card_stats', __( 'Last 30 days', 'vizito-team-business-cards' ), array( $this, 'render_meta_box' ), Card_Repository::POST_TYPE, 'side' );
		}

		/**
		 * Chart and numbers for one card.
		 *
		 * @param object $post The post being edited.
		 * @return void
		 */
		public function render_meta_box( $post ): void {
			$range  = $this->range( self::DAYS );
			$series = $this->table->daily_views( (int) $post->ID, $range[0], $range[1] );
			$totals = $this->table->totals( array( (int) $post->ID ), $range[0], $range[1] )[ (int) $post->ID ] ?? array();

			echo $this->chart( $series ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from integers and escaped labels.

			echo '<p>';
			printf(
				/* translators: 1: views, 2: views from printed codes, 3: contacts saved */
				esc_html__( '%1$s views (%2$s from printed codes), %3$s contacts saved.', 'vizito-team-business-cards' ),
				esc_html( number_format_i18n( $totals['view'] ?? 0 ) ),
				esc_html( number_format_i18n( array_sum( array_column( $series, 'qr' ) ) ) ),
				esc_html( number_format_i18n( $totals['vcard'] ?? 0 ) )
			);
			echo '</p>';
		}

		/**
		 * A stacked bar chart of daily views: codes over links. Accessible as
		 * an image with a summary, with the numbers in a table below.
		 *
		 * @param array<string, array{qr: int, link: int}> $series Daily views.
		 * @return string
		 */
		public function chart( array $series ): string {
			$max   = max( 1, ...array_map( 'array_sum', array_values( $series ) ?: array( array( 0 ) ) ) );
			$bar   = 8;
			$gap   = 2;
			$h     = 60;
			$w     = max( 1, count( $series ) * ( $bar + $gap ) - $gap );
			$qr    = array_sum( array_column( $series, 'qr' ) );
			$link  = array_sum( array_column( $series, 'link' ) );
			$label = sprintf(
				/* translators: 1: views from printed codes, 2: views from links */
				__( 'Daily views: %1$s from printed codes, %2$s from links.', 'vizito-team-business-cards' ),
				number_format_i18n( $qr ),
				number_format_i18n( $link )
			);

			$svg = '<svg class="tbc-chart" role="img" aria-label="' . esc_attr( $label ) . '" viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" height="' . $h . '" preserveAspectRatio="none">';
			$x   = 0;

			foreach ( $series as $counts ) {
				$qr_h   = (int) round( $counts['qr'] / $max * $h );
				$link_h = (int) round( $counts['link'] / $max * $h );

				if ( $link_h > 0 ) {
					$svg .= '<rect x="' . $x . '" y="' . ( $h - $qr_h - $link_h ) . '" width="' . $bar . '" height="' . $link_h . '" fill="#a7aaad"/>';
				}

				if ( $qr_h > 0 ) {
					$svg .= '<rect x="' . $x . '" y="' . ( $h - $qr_h ) . '" width="' . $bar . '" height="' . $qr_h . '" fill="#2271b1"/>';
				}

				$x += $bar + $gap;
			}

			$svg .= '</svg>';

			$rows = '';
			foreach ( $series as $day => $counts ) {
				$rows .= '<tr><td>' . esc_html( $day ) . '</td><td>' . (int) $counts['qr'] . '</td><td>' . (int) $counts['link'] . '</td></tr>';
			}

			return $svg . '<details><summary>' . esc_html__( 'Numbers', 'vizito-team-business-cards' ) . '</summary><table class="widefat striped"><thead><tr><th>'
				. esc_html__( 'Day', 'vizito-team-business-cards' ) . '</th><th>' . esc_html__( 'Code', 'vizito-team-business-cards' ) . '</th><th>' . esc_html__( 'Link', 'vizito-team-business-cards' )
				. '</th></tr></thead><tbody>' . $rows . '</tbody></table></details>';
		}

		/**
		 * The Statistics page.
		 *
		 * @return void
		 */
		public function register_menu(): void {
			add_submenu_page( Settings_Page::PARENT, __( 'Statistics', 'vizito-team-business-cards' ), __( 'Statistics', 'vizito-team-business-cards' ), \Asymetria\TeamCards\Capabilities::manage(), self::PAGE_SLUG, array( $this, 'render_page' ) );
		}

		/**
		 * Per-card totals over a chosen range.
		 *
		 * @return void
		 */
		public function render_page(): void {
			if ( ! current_user_can( \Asymetria\TeamCards\Capabilities::manage() ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to view statistics.', 'vizito-team-business-cards' ) );
			}

			list( $from, $to ) = $this->requested_range();
			$rows              = $this->rows( $from, $to );

			echo '<div class="wrap"><h1>' . esc_html__( 'Statistics', 'vizito-team-business-cards' ) . '</h1>';
			echo '<form method="get"><input type="hidden" name="post_type" value="' . esc_attr( Card_Repository::POST_TYPE ) . '"><input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '">';
			echo '<label>' . esc_html__( 'From', 'vizito-team-business-cards' ) . ' <input type="date" name="from" value="' . esc_attr( $from ) . '"></label> ';
			echo '<label>' . esc_html__( 'To', 'vizito-team-business-cards' ) . ' <input type="date" name="to" value="' . esc_attr( $to ) . '"></label> ';
			submit_button( __( 'Show', 'vizito-team-business-cards' ), 'secondary', '', false );
			echo ' <a class="button" href="' . esc_url( $this->csv_url( $from, $to ) ) . '">' . esc_html__( 'Download CSV', 'vizito-team-business-cards' ) . '</a></form>';

			echo '<table class="widefat striped"><thead><tr>';
			foreach ( $this->headings() as $heading ) {
				echo '<th scope="col">' . esc_html( $heading ) . '</th>';
			}
			echo '</tr></thead><tbody>';

			foreach ( $rows as $row ) {
				echo '<tr>';
				foreach ( $row as $cell ) {
					echo '<td>' . esc_html( (string) $cell ) . '</td>';
				}
				echo '</tr>';
			}

			echo '</tbody></table>';
			echo '<p class="description">' . esc_html__( 'Counts are indicative: no cookies and no personal data are used, bots are filtered out, and repeated hits from one address are capped.', 'vizito-team-business-cards' ) . '</p></div>';
		}

		/**
		 * admin-post handler for the CSV.
		 *
		 * @return void
		 */
		public function csv(): void {
			check_admin_referer( self::CSV_NONCE );

			if ( ! current_user_can( \Asymetria\TeamCards\Capabilities::manage() ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to view statistics.', 'vizito-team-business-cards' ), '', array( 'response' => 403 ) );
			}

			list( $from, $to ) = $this->requested_range();

			$lines = array( self::csv_line( $this->headings() ) );

			foreach ( $this->rows( $from, $to ) as $row ) {
				$lines[] = self::csv_line( $row );
			}

			( $this->send )(
				array(
					'Content-Type: text/csv; charset=utf-8',
					'Content-Disposition: attachment; filename="card-statistics-' . $from . '-' . $to . '.csv"',
				),
				"\xEF\xBB\xBF" . implode( "\r\n", $lines ) . "\r\n"
			);
		}

		/**
		 * One CSV line, guarded against formulas (see Csv_Writer::guard()).
		 *
		 * @param array $cells Cells.
		 * @return string
		 */
		public static function csv_line( array $cells ): string {
			return \Asymetria\TeamCards\Csv\Csv_Writer::line( $cells, ',' );
		}

		/**
		 * Table rows for every card: name, address, events, share of views from codes.
		 *
		 * @param string $from First day.
		 * @param string $to   Last day.
		 * @return array<int, array>
		 */
		private function rows( string $from, string $to ): array {
			$posts = get_posts(
				array(
					'post_type'   => Card_Repository::POST_TYPE,
					'post_status' => array( 'publish', 'private', 'draft' ),
					'numberposts' => -1,
					'orderby'     => 'title',
					'order'       => 'ASC',
				)
			);

			$ids    = array_map(
				static function ( $post ) {
					return (int) $post->ID;
				},
				$posts
			);
			$totals = $this->table->totals( $ids, $from, $to );
			$rows   = array();

			foreach ( $ids as $id ) {
				$card = $this->cards->find( $id );

				if ( null === $card ) {
					continue;
				}

				$row = array( $card->full_name(), $card->slug() );

				foreach ( self::PAGE_EVENTS as $event ) {
					$row[] = $totals[ $id ][ $event ] ?? 0;
				}

				$rows[] = $row;
			}

			return $rows;
		}

		/**
		 * Column headings, the same on the page and in the CSV.
		 *
		 * @return string[]
		 */
		private function headings(): array {
			return array(
				__( 'Name', 'vizito-team-business-cards' ),
				__( 'Address', 'vizito-team-business-cards' ),
				__( 'Views', 'vizito-team-business-cards' ),
				__( 'Contacts saved', 'vizito-team-business-cards' ),
				__( 'Calls', 'vizito-team-business-cards' ),
				__( 'Emails', 'vizito-team-business-cards' ),
				'WhatsApp',
				__( 'Directions', 'vizito-team-business-cards' ),
				__( 'Shares', 'vizito-team-business-cards' ),
				__( 'Links', 'vizito-team-business-cards' ),
			);
		}

		/**
		 * The last $days days, today included.
		 *
		 * @param int $days Days.
		 * @return string[] First and last day, Y-m-d.
		 */
		private function range( int $days ): array {
			$today = (int) current_time( 'timestamp' );

			return array( gmdate( 'Y-m-d', $today - ( $days - 1 ) * DAY_IN_SECONDS ), gmdate( 'Y-m-d', $today ) );
		}

		/**
		 * The range from the request, or the last 30 days.
		 *
		 * @return string[]
		 */
		private function requested_range(): array {
			$default = $this->range( self::DAYS );
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filter; the CSV handler checks its own nonce first.
			$from = isset( $_REQUEST['from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['from'] ) ) : '';
			$to   = isset( $_REQUEST['to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['to'] ) ) : '';
			// phpcs:enable

			$valid = static function ( string $day ): bool {
				return 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day );
			};

			$from = $valid( $from ) ? $from : $default[0];
			$to   = $valid( $to ) ? $to : $default[1];

			return $from <= $to ? array( $from, $to ) : array( $to, $from );
		}

		/**
		 * The CSV address for a range.
		 *
		 * @param string $from First day.
		 * @param string $to   Last day.
		 * @return string
		 */
		private function csv_url( string $from, string $to ): string {
			$url = admin_url( 'admin-post.php' );

			foreach ( array(
				'action'   => 'tbcards_stats_csv',
				'from'     => $from,
				'to'       => $to,
				'_wpnonce' => wp_create_nonce( self::CSV_NONCE ),
			) as $key => $value ) {
				$url = add_query_arg( $key, $value, $url );
			}

			return $url;
		}
	}
}
