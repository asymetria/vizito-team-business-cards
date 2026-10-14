<?php
/**
 * CSV output and the shared column layout.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Csv;

use Asymetria\TeamCards\Card\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Csv_Writer' ) ) {

	/**
	 * Writes CSV lines that a spreadsheet will not execute, and defines the
	 * columns the importer reads and the exporter writes, so an exported file
	 * imports back unchanged.
	 */
	final class Csv_Writer {

		/**
		 * Column names: slug, every field (translated ones once per language
		 * as key_code), photo, status.
		 *
		 * @param string[] $codes Language codes.
		 * @return string[]
		 */
		public static function columns( array $codes ): array {
			$columns = array( 'slug' );

			foreach ( Fields::all() as $key => $def ) {
				if ( $def['translated'] ) {
					foreach ( $codes as $code ) {
						$columns[] = $key . '_' . $code;
					}
				} else {
					$columns[] = $key;
				}
			}

			$columns[] = 'photo';
			$columns[] = 'status';

			return $columns;
		}

		/**
		 * One CSV line.
		 *
		 * @param array  $cells     Cells.
		 * @param string $delimiter Separator.
		 * @return string
		 */
		public static function line( array $cells, string $delimiter = ',' ): string {
			return implode(
				$delimiter,
				array_map(
					static function ( $cell ) {
						return '"' . str_replace( '"', '""', self::guard( (string) $cell ) ) . '"';
					},
					$cells
				)
			);
		}

		/**
		 * Prefixes a cell a spreadsheet would run as a formula with an apostrophe.
		 * Phone numbers ("+48 …", "-") and plain numbers are left alone, since
		 * they are data, not formulas.
		 *
		 * @param string $cell Cell.
		 * @return string
		 */
		public static function guard( string $cell ): string {
			if ( '' === $cell || is_numeric( $cell ) || 1 === preg_match( '/^[+\-][\d\s().\/\-]*$/', $cell ) ) {
				return $cell;
			}

			return in_array( $cell[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ? "'" . $cell : $cell;
		}

		/**
		 * Reverses guard() on import.
		 *
		 * @param string $cell Cell as read.
		 * @return string
		 */
		public static function unguard( string $cell ): string {
			return 1 === preg_match( "/^'[=+\\-@\t\r]/", $cell ) ? substr( $cell, 1 ) : $cell;
		}
	}
}
