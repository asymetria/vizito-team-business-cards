<?php
/**
 * CSV parsing.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Csv;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Csv_Reader' ) ) {

	/**
	 * Reads what people actually export from spreadsheets: comma, semicolon
	 * (Polish and German Excel) or tab separated, UTF-8 with or without a BOM,
	 * or Windows-1250 from an older Excel. Quoted fields may hold separators
	 * and line breaks, so records are split outside quotes, not on every line.
	 */
	final class Csv_Reader {

		public const DELIMITERS = array( ',', ';', "\t" );

		/**
		 * Windows-1250 bytes for Polish and German letters, the last resort when
		 * neither mbstring nor iconv is available.
		 */
		private const CP1250 = array(
			"\xA5" => 'Ą',
			"\xB9" => 'ą',
			"\xC6" => 'Ć',
			"\xE6" => 'ć',
			"\xCA" => 'Ę',
			"\xEA" => 'ę',
			"\xA3" => 'Ł',
			"\xB3" => 'ł',
			"\xD1" => 'Ń',
			"\xF1" => 'ń',
			"\xD3" => 'Ó',
			"\xF3" => 'ó',
			"\x8C" => 'Ś',
			"\x9C" => 'ś',
			"\x8F" => 'Ź',
			"\x9F" => 'ź',
			"\xAF" => 'Ż',
			"\xBF" => 'ż',
			"\xC4" => 'Ä',
			"\xE4" => 'ä',
			"\xD6" => 'Ö',
			"\xF6" => 'ö',
			"\xDC" => 'Ü',
			"\xFC" => 'ü',
			"\xDF" => 'ß',
			"\xC9" => 'É',
			"\xE9" => 'é',
		);

		/**
		 * Parses a CSV document. No file or stream functions are used: the
		 * text is split in memory.
		 *
		 * @param string $bytes File contents.
		 * @return array{header: string[], rows: string[][], delimiter: string, encoding: string}
		 */
		public function parse( string $bytes ): array {
			$encoding = 'UTF-8';

			if ( str_starts_with( $bytes, "\xEF\xBB\xBF" ) ) {
				$bytes = substr( $bytes, 3 );
			} elseif ( 1 !== preg_match( '//u', $bytes ) ) {
				$bytes    = self::from_cp1250( $bytes );
				$encoding = 'Windows-1250';
			}

			$bytes     = str_replace( array( "\r\n", "\r" ), "\n", $bytes );
			$delimiter = $this->delimiter( $bytes );
			$records   = $this->records( $bytes, $delimiter );
			$header    = array_map( 'trim', array_shift( $records ) ?? array() );

			return array(
				'header'    => $header,
				'rows'      => $records,
				'delimiter' => $delimiter,
				'encoding'  => $encoding,
			);
		}

		/**
		 * Windows-1250 to UTF-8: mbstring, then iconv, then the built-in table.
		 *
		 * @param string $bytes      Windows-1250 text.
		 * @param bool   $table_only Skip the extensions (tests of the fallback).
		 * @return string
		 */
		public static function from_cp1250( string $bytes, bool $table_only = false ): string {
			if ( ! $table_only && function_exists( 'mb_convert_encoding' ) ) {
				return (string) mb_convert_encoding( $bytes, 'UTF-8', 'Windows-1250' );
			}

			if ( ! $table_only && function_exists( 'iconv' ) ) {
				$converted = iconv( 'Windows-1250', 'UTF-8//TRANSLIT', $bytes );

				if ( false !== $converted ) {
					return $converted;
				}
			}

			$out = '';
			$len = strlen( $bytes );

			for ( $i = 0; $i < $len; $i++ ) {
				$byte = $bytes[ $i ];
				$out .= ord( $byte ) < 0x80 ? $byte : ( self::CP1250[ $byte ] ?? '?' );
			}

			return $out;
		}

		/**
		 * The separator that splits the first record into the most fields.
		 *
		 * @param string $bytes Normalised text.
		 * @return string
		 */
		private function delimiter( string $bytes ): string {
			$first = (string) strtok( $bytes, "\n" ) . "\n";
			$best  = ',';
			$most  = 0;

			foreach ( self::DELIMITERS as $candidate ) {
				$count = count( $this->records( $first, $candidate, 1 )[0] ?? array() );

				if ( $count > $most ) {
					$most = $count;
					$best = $candidate;
				}
			}

			return $best;
		}

		/**
		 * Records, split on line breaks outside quotes, so quoted fields may
		 * span lines. Each record is then read by str_getcsv() with no escape
		 * character (a backslash is data). Blank lines are dropped.
		 *
		 * @param string $bytes     Normalised text (\n line breaks).
		 * @param string $delimiter Separator.
		 * @param int    $limit     Stop after this many records, 0 for all.
		 * @return string[][]
		 */
		private function records( string $bytes, string $delimiter, int $limit = 0 ): array {
			$records = array();
			$quoted  = false;
			$start   = 0;
			$length  = strlen( $bytes );

			for ( $i = 0; $i <= $length; $i++ ) {
				$char = $i < $length ? $bytes[ $i ] : "\n";

				if ( '"' === $char ) {
					$quoted = ! $quoted; // A doubled quote toggles twice and changes nothing.
					continue;
				}

				if ( "\n" !== $char || $quoted ) {
					continue;
				}

				$line  = substr( $bytes, $start, $i - $start );
				$start = $i + 1;

				if ( '' === trim( $line ) ) {
					continue;
				}

				$records[] = array_map( 'strval', str_getcsv( $line, $delimiter, '"', '' ) );

				if ( $limit > 0 && count( $records ) >= $limit ) {
					break;
				}
			}

			return $records;
		}
	}
}
