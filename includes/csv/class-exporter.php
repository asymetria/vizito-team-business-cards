<?php
/**
 * CSV export.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Csv;

use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Card\Fields;
use Asymetria\TeamCards\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Exporter' ) ) {

	/**
	 * Every card as CSV in the importer's own layout, so a file can be edited
	 * in a spreadsheet and imported back. Stored values are exported, not
	 * values inherited from the company profile, so a round trip never turns
	 * an inherited value into a card's own.
	 *
	 * UTF-8 with a BOM, which Excel needs to read Polish and German letters;
	 * semicolons where Excel expects them (Polish and German locales).
	 */
	final class Exporter {

		/**
		 * Plugin settings.
		 *
		 * @var Settings
		 */
		private Settings $settings;

		/**
		 * Card repository.
		 *
		 * @var Card_Repository
		 */
		private Card_Repository $cards;

		/**
		 * Constructor.
		 *
		 * @param Settings        $settings Plugin settings.
		 * @param Card_Repository $cards    Card repository.
		 */
		public function __construct( Settings $settings, Card_Repository $cards ) {
			$this->settings = $settings;
			$this->cards    = $cards;
		}

		/**
		 * The separator Excel expects for the site's locale.
		 *
		 * @return string
		 */
		public function delimiter(): string {
			$locale = function_exists( 'determine_locale' ) ? (string) determine_locale() : '';

			return in_array( substr( $locale, 0, 3 ), array( 'pl_', 'de_' ), true ) ? ';' : ',';
		}

		/**
		 * The whole file.
		 *
		 * @return string
		 */
		public function csv(): string {
			$codes     = $this->settings->languages();
			$delimiter = $this->delimiter();
			$lines     = array( Csv_Writer::line( Csv_Writer::columns( $codes ), $delimiter ) );

			$posts = get_posts(
				array(
					'post_type'   => Card_Repository::POST_TYPE,
					'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'numberposts' => -1,
					'orderby'     => 'title',
					'order'       => 'ASC',
				)
			);

			foreach ( $posts as $post ) {
				$lines[] = Csv_Writer::line( $this->row( $post, $codes ), $delimiter );
			}

			return "\xEF\xBB\xBF" . implode( "\r\n", $lines ) . "\r\n";
		}

		/**
		 * One card's cells, in column order.
		 *
		 * @param object   $post  Post.
		 * @param string[] $codes Language codes.
		 * @return string[]
		 */
		private function row( $post, array $codes ): array {
			$id    = (int) $post->ID;
			$raw   = $this->cards->raw( $id );
			$cells = array( (string) $post->post_name );

			foreach ( Fields::all() as $key => $def ) {
				if ( $def['translated'] ) {
					foreach ( $codes as $code ) {
						$cells[] = is_array( $raw[ $key ] ) ? (string) ( $raw[ $key ][ $code ] ?? '' ) : '';
					}
				} else {
					$cells[] = is_scalar( $raw[ $key ] ) ? (string) $raw[ $key ] : '';
				}
			}

			$photo   = (int) get_post_thumbnail_id( $id );
			$cells[] = $photo > 0 ? (string) $photo : '';
			$cells[] = '' === (string) get_post_meta( $id, Card_Repository::INACTIVE_META, true ) ? 'active' : 'inactive';

			return $cells;
		}
	}
}
