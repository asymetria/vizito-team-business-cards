<?php
/**
 * CSV import.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Csv;

use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Card\Field_Sanitizer;
use Asymetria\TeamCards\Card\Fields;
use Asymetria\TeamCards\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Importer' ) ) {

	/**
	 * Two steps. plan() reads a parsed file and decides, per row, whether it
	 * creates a card, updates one or is an error, writing nothing. commit()
	 * then carries out a slice of that plan.
	 *
	 * Rows match an existing card by email, then by slug. An empty cell leaves
	 * the field alone; a cell holding only "-" clears it.
	 */
	final class Importer {

		public const MAX_ROWS = 500;
		public const CLEAR    = '-';

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
		 * Finds a card id by email and slug; 0 when none.
		 *
		 * @var callable
		 */
		private $lookup;

		/**
		 * Finds an attachment id by id or file name; 0 when none.
		 *
		 * @var callable
		 */
		private $photo;

		/**
		 * Constructor.
		 *
		 * @param Settings        $settings Plugin settings.
		 * @param Card_Repository $cards    Card repository.
		 * @param callable|null   $lookup   function( string $email, string $slug ): int.
		 * @param callable|null   $photo    function( string $reference ): int.
		 */
		public function __construct( Settings $settings, Card_Repository $cards, ?callable $lookup = null, ?callable $photo = null ) {
			$this->settings = $settings;
			$this->cards    = $cards;
			$this->lookup   = $lookup ?? array( $this, 'find_card' );
			$this->photo    = $photo ?? array( $this, 'find_photo' );
		}

		/**
		 * The plan for a parsed file. Writes nothing.
		 *
		 * @param array $parsed Csv_Reader::parse() output.
		 * @return array{rows: array, warnings: string[], counts: array<string, int>}
		 */
		public function plan( array $parsed ): array {
			$codes    = $this->settings->languages();
			$known    = Csv_Writer::columns( $codes );
			$header   = $parsed['header'];
			$warnings = array();
			$rows     = array();

			foreach ( $header as $column ) {
				if ( '' !== $column && ! in_array( $column, $known, true ) ) {
					/* translators: %s: column name */
					$warnings[] = sprintf( __( 'Column "%s" is not known and was ignored.', 'vizito-team-business-cards' ), $column );
				}
			}

			if ( count( $parsed['rows'] ) > self::MAX_ROWS ) {
				/* translators: %d: maximum number of rows */
				$warnings[] = sprintf( __( 'Only the first %d rows are imported at once.', 'vizito-team-business-cards' ), self::MAX_ROWS );
			}

			foreach ( array_slice( $parsed['rows'], 0, self::MAX_ROWS ) as $i => $cells ) {
				$rows[] = $this->plan_row( $header, $cells, $i + 2, $codes );
			}

			$counts = array(
				'create' => 0,
				'update' => 0,
				'error'  => 0,
			);

			foreach ( $rows as $row ) {
				$counts[ $row['action'] ]++;
			}

			return array(
				'rows'     => $rows,
				'warnings' => $warnings,
				'counts'   => $counts,
			);
		}

		/**
		 * Carries out part of a plan.
		 *
		 * @param array $plan   plan() output.
		 * @param int   $offset First row.
		 * @param int   $length Rows to process.
		 * @return int Rows written.
		 */
		public function commit( array $plan, int $offset, int $length ): int {
			$done = 0;

			foreach ( array_slice( $plan['rows'], $offset, $length ) as $row ) {
				if ( 'error' === $row['action'] ) {
					continue;
				}

				if ( 'update' === $row['action'] && ! current_user_can( 'edit_post', (int) $row['id'] ) ) {
					continue; // An importer may only change cards they could edit by hand.
				}

				$id = 'update' === $row['action'] ? (int) $row['id'] : $this->create( $row );

				if ( $id <= 0 ) {
					continue;
				}

				$this->cards->save( $id, $this->merge( $id, $row['values'] ) );

				if ( 'update' === $row['action'] && ( isset( $row['values']['first_name'] ) || isset( $row['values']['last_name'] ) ) ) {
					$this->retitle( $id );
				}

				if ( $row['photo'] > 0 ) {
					set_post_thumbnail( $id, $row['photo'] );
				}

				if ( 'inactive' === $row['status'] ) {
					update_post_meta( $id, Card_Repository::INACTIVE_META, '1' );
				} elseif ( 'active' === $row['status'] ) {
					delete_post_meta( $id, Card_Repository::INACTIVE_META );
				}

				do_action( 'tbcards_card_saved', $id );
				$done++;
			}

			return $done;
		}

		/**
		 * One row of the plan.
		 *
		 * @param string[] $header Column names.
		 * @param string[] $cells  Cells.
		 * @param int      $line   Line number in the file, for messages.
		 * @param string[] $codes  Language codes.
		 * @return array
		 */
		private function plan_row( array $header, array $cells, int $line, array $codes ): array {
			$row = array(
				'line'     => $line,
				'action'   => 'error',
				'id'       => 0,
				'name'     => '',
				'slug'     => '',
				'values'   => array(),
				'photo'    => 0,
				'status'   => '',
				'messages' => array(),
			);

			if ( count( $cells ) !== count( $header ) ) {
				/* translators: 1: cells in the row, 2: columns in the header */
				$row['messages'][] = sprintf( __( 'The row has %1$d cells but the header has %2$d.', 'vizito-team-business-cards' ), count( $cells ), count( $header ) );
				return $row;
			}

			$data = array();

			foreach ( $header as $i => $column ) {
				$data[ $column ] = Csv_Writer::unguard( trim( (string) $cells[ $i ] ) );
			}

			foreach ( Fields::all() as $key => $def ) {
				if ( $def['translated'] ) {
					foreach ( $codes as $code ) {
						$cell = $data[ $key . '_' . $code ] ?? '';

						if ( '' !== $cell ) {
							$row['values'][ $key ][ $code ] = self::CLEAR === $cell ? '' : Field_Sanitizer::value( $def['type'], $cell );
						}
					}

					continue;
				}

				$cell = $data[ $key ] ?? '';

				if ( '' === $cell ) {
					continue;
				}

				if ( self::CLEAR === $cell ) {
					$row['values'][ $key ] = '';
					continue;
				}

				$clean = Field_Sanitizer::value( $def['type'], $cell );

				if ( '' === $clean ) {
					/* translators: 1: field label, 2: the value given */
					$row['messages'][] = sprintf( __( '%1$s "%2$s" is not valid.', 'vizito-team-business-cards' ), $def['label'], $cell );
					continue;
				}

				$row['values'][ $key ] = $clean;
			}

			$row['slug'] = sanitize_title( $data['slug'] ?? '' );
			$row['name'] = trim( ( $row['values']['first_name'] ?? '' ) . ' ' . ( $row['values']['last_name'] ?? '' ) );

			if ( '' !== ( $data['photo'] ?? '' ) ) {
				$row['photo'] = (int) ( $this->photo )( $data['photo'] );

				if ( 0 === $row['photo'] ) {
					/* translators: %s: photo reference from the file */
					$row['messages'][] = sprintf( __( 'Photo "%s" is not in the Media Library.', 'vizito-team-business-cards' ), $data['photo'] );
				}
			}

			$status = strtolower( $data['status'] ?? '' );

			if ( '' !== $status ) {
				if ( in_array( $status, array( 'active', 'inactive' ), true ) ) {
					$row['status'] = $status;
				} else {
					/* translators: %s: status from the file */
					$row['messages'][] = sprintf( __( 'Status "%s" must be "active" or "inactive".', 'vizito-team-business-cards' ), $data['status'] );
				}
			}

			if ( array() !== $row['messages'] ) {
				return $row;
			}

			$email     = strtolower( (string) ( $row['values']['email'] ?? '' ) );
			$row['id'] = (int) ( $this->lookup )( $email, $row['slug'] );

			if ( $row['id'] > 0 ) {
				$row['action'] = 'update';

				// The preview names the card being changed, even when the file
				// carries no name (an update by email or slug).
				if ( '' === $row['name'] ) {
					$stored      = $this->cards->raw( $row['id'] );
					$row['name'] = trim( (string) $stored['first_name'] . ' ' . (string) $stored['last_name'] );
				}
			} elseif ( '' === $row['name'] ) {
				$row['messages'][] = __( 'A new card needs a first or last name.', 'vizito-team-business-cards' );
			} else {
				$row['action'] = 'create';
			}

			return $row;
		}

		/**
		 * Creates the post for a new card.
		 *
		 * @param array $row Plan row.
		 * @return int Post id, 0 on failure.
		 */
		private function create( array $row ): int {
			$args = array(
				'post_type'   => Card_Repository::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $row['name'],
			);

			if ( '' !== $row['slug'] ) {
				$args['post_name'] = $row['slug'];
			}

			$id = wp_insert_post( wp_slash( $args ), true );

			return is_wp_error( $id ) ? 0 : (int) $id;
		}

		/**
		 * Merges translated cells into what the card already holds, so a file
		 * with only the German title keeps the Polish one.
		 *
		 * @param int   $id     Card id.
		 * @param array $values Planned values.
		 * @return array
		 */
		private function merge( int $id, array $values ): array {
			$raw = $this->cards->raw( $id );

			foreach ( Fields::translated() as $key ) {
				if ( ! isset( $values[ $key ] ) ) {
					continue;
				}

				$merged = is_array( $raw[ $key ] ) ? $raw[ $key ] : array();

				foreach ( $values[ $key ] as $code => $value ) {
					if ( '' === $value ) {
						unset( $merged[ $code ] );
					} else {
						$merged[ $code ] = $value;
					}
				}

				$values[ $key ] = $merged;
			}

			return $values;
		}

		/**
		 * Keeps the post title in step with the names after an update.
		 *
		 * @param int $id Card id.
		 * @return void
		 */
		private function retitle( int $id ): void {
			$raw  = $this->cards->raw( $id );
			$name = trim( (string) $raw['first_name'] . ' ' . (string) $raw['last_name'] );

			if ( '' !== $name ) {
				wp_update_post(
					wp_slash(
						array(
							'ID'         => $id,
							'post_title' => $name,
						)
					)
				);
			}
		}

		/**
		 * Default lookup: by email meta, then by slug.
		 *
		 * @param string $email Lowercase email or "".
		 * @param string $slug  Slug or "".
		 * @return int
		 */
		public function find_card( string $email, string $slug ): int {
			$base = array(
				'post_type'   => Card_Repository::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => 1,
				'fields'      => 'ids',
			);

			if ( '' !== $email ) {
				$ids = get_posts(
					$base + array(
						'meta_key'   => Card_Repository::META_PREFIX . 'email', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- import only.
						'meta_value' => $email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- import only.
					)
				);

				if ( array() !== $ids ) {
					return (int) $ids[0];
				}
			}

			if ( '' !== $slug ) {
				$ids = get_posts( $base + array( 'name' => $slug ) );

				if ( array() !== $ids ) {
					return (int) $ids[0];
				}
			}

			return 0;
		}

		/**
		 * Default photo lookup: an attachment id, or the file name of an
		 * image already in the Media Library.
		 *
		 * @param string $reference Id or file name.
		 * @return int
		 */
		public function find_photo( string $reference ): int {
			if ( ctype_digit( $reference ) ) {
				$post = get_post( (int) $reference );
				return $post && 'attachment' === ( $post->post_type ?? '' ) ? (int) $reference : 0;
			}

			$name = sanitize_file_name( wp_basename( $reference ) );

			if ( '' === $name ) {
				return 0;
			}

			// LIKE narrows the search; the exact file name decides, so "jan.jpg"
			// neither misses a file uploaded without year/month folders nor
			// matches "jan.jpg.webp" or "marjan.jpg".
			$ids = get_posts(
				array(
					'post_type'   => 'attachment',
					'post_status' => 'inherit',
					'numberposts' => 20,
					'fields'      => 'ids',
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- import only.
						array(
							'key'     => '_wp_attached_file',
							'value'   => $name,
							'compare' => 'LIKE',
						),
					),
				)
			);

			foreach ( (array) $ids as $id ) {
				if ( wp_basename( (string) get_post_meta( (int) $id, '_wp_attached_file', true ) ) === $name ) {
					return (int) $id;
				}
			}

			return 0;
		}
	}
}
