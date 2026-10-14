<?php
/**
 * Pins CSV parsing: separators, encodings, quoting.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/csv/class-csv-reader.php';

use Asymetria\TeamCards\Csv\Csv_Reader;

describe(
	'csv reader',
	function () {

		it(
			'reads comma, semicolon and tab separated files',
			function () {
				$reader = new Csv_Reader();

				foreach ( array( ',', ';', "\t" ) as $sep ) {
					$parsed = $reader->parse( "first_name{$sep}last_name\nJan{$sep}Kowalski\n" );

					assert_same( $sep, $parsed['delimiter'] );
					assert_same( array( 'first_name', 'last_name' ), $parsed['header'] );
					assert_same( array( array( 'Jan', 'Kowalski' ) ), $parsed['rows'] );
				}
			}
		);

		it(
			'prefers the separator that splits the header most, even with commas in the data',
			function () {
				$parsed = ( new Csv_Reader() )->parse( "first_name;job_title;city\nJan;CEO, Sales;Kraków\n" );

				assert_same( ';', $parsed['delimiter'] );
				assert_same( array( 'Jan', 'CEO, Sales', 'Kraków' ), $parsed['rows'][0] );
			}
		);

		it(
			'strips a UTF-8 BOM and trims header names',
			function () {
				$parsed = ( new Csv_Reader() )->parse( "\xEF\xBB\xBF first_name ,email\nJan,jan@example.com\n" );

				assert_same( array( 'first_name', 'email' ), $parsed['header'] );
				assert_same( 'UTF-8', $parsed['encoding'] );
			}
		);

		it(
			'converts Windows-1250 from an older Excel',
			function () {
				$bytes  = "city;name\n\xA3\xF3d\x9F;W\xEA\xBFycki\n";
				$parsed = ( new Csv_Reader() )->parse( $bytes );

				assert_same( 'Windows-1250', $parsed['encoding'] );
				assert_same( 'Wężycki', $parsed['rows'][0][1] );
				assert_same( 'Łódź', $parsed['rows'][0][0] );
			}
		);

		it(
			'converts Polish and German letters without any extension',
			function () {
				assert_same( 'Łódź Wężycki Zażółć Größe', Csv_Reader::from_cp1250( "\xA3\xF3d\x9F W\xEA\xBFycki Za\xBF\xF3\xB3\xE6 Gr\xF6\xDFe", true ) );
			}
		);

		it(
			'keeps separators and line breaks inside quotes, and doubled quotes',
			function () {
				$parsed = ( new Csv_Reader() )->parse( "intro,job_title\n\"Line 1\nLine 2, still\",\"The \"\"best\"\"\"\n" );

				assert_same( array( "Line 1\nLine 2, still", 'The "best"' ), $parsed['rows'][0] );
			}
		);

		it(
			'handles Windows line ends and skips blank lines',
			function () {
				$parsed = ( new Csv_Reader() )->parse( "a,b\r\n1,2\r\n\r\n3,4\r\n\r\n" );

				assert_same( array( array( '1', '2' ), array( '3', '4' ) ), $parsed['rows'] );
			}
		);

		it(
			'keeps ragged rows as they are, for the importer to report',
			function () {
				$parsed = ( new Csv_Reader() )->parse( "a,b,c\n1,2\n1,2,3,4\n" );

				assert_same( array( '1', '2' ), $parsed['rows'][0] );
				assert_same( array( '1', '2', '3', '4' ), $parsed['rows'][1] );
			}
		);

		it(
			'returns an empty header for an empty file',
			function () {
				$parsed = ( new Csv_Reader() )->parse( '' );

				assert_same( array(), $parsed['header'] );
				assert_same( array(), $parsed['rows'] );
			}
		);

		it(
			'does not treat a backslash as an escape',
			function () {
				$parsed = ( new Csv_Reader() )->parse( "a,b\n\"C:\\\\\",x\n" );

				assert_same( array( 'C:\\\\', 'x' ), $parsed['rows'][0] );
			}
		);
	}
);
