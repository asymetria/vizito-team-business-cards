<?php
/**
 * Pins version choice, the matrix and the card QR address.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/card/class-card.php';
require_once dirname( __DIR__ ) . '/includes/qr/class-qr-service.php';

use Asymetria\TeamCards\Card\Card;
use Asymetria\TeamCards\Qr\Qr_Service;

describe(
	'qr service',
	function () {

		it(
			'picks the smallest version that holds the data, like upstream within version 10',
			function () {
				$service = new Qr_Service();

				assert_same( 6, $service->version_for( 58, 'H' ) );
				assert_same( 1, $service->version_for( 17, 'L' ) );
				assert_same( 2, $service->version_for( 18, 'L' ) );
				assert_same( 1, $service->version_for( 7, 'H' ) );
				assert_same( 2, $service->version_for( 8, 'H' ) );
			}
		);

		it(
			'keeps going past version 10, where upstream stops',
			function () {
				$service = new Qr_Service();

				// Version 10 at M holds 213 bytes; 214 needs version 11.
				assert_same( 10, $service->version_for( 213, 'M' ) );
				assert_same( 11, $service->version_for( 214, 'M' ) );
				assert_same( 40, $service->version_for( 2331, 'M' ) );
			}
		);

		it(
			'answers null when nothing holds the data, or for an unknown level',
			function () {
				$service = new Qr_Service();

				assert_same( null, $service->version_for( 2332, 'M' ) );
				assert_same( null, $service->version_for( 10, 'X' ) );
				assert_same( null, $service->matrix( str_repeat( 'a', 5000 ) ) );
				assert_same( null, $service->matrix( 'abc', 'X' ) );
				assert_same( null, $service->matrix( '' ) );
			}
		);

		it(
			'returns a square boolean matrix of the right size',
			function () {
				$matrix = ( new Qr_Service() )->matrix( 'https://acme-firma.example/wizytowka/jozef-wezycki?src=qr', 'H' );

				assert_same( 41, count( $matrix ) );
				assert_same( 41, count( $matrix[0] ) );
				assert_true( $matrix[0][0] );   // Finder pattern corner is dark.
				assert_false( $matrix[7][7] );  // Separator is light.
			}
		);

		it(
			'builds a large symbol that upstream would get wrong',
			function () {
				$matrix = ( new Qr_Service() )->matrix( str_repeat( 'x', 300 ), 'M' );

				assert_same( 17 + 4 * 13, count( $matrix ) );
			}
		);

		it(
			'marks card addresses as scanned from a printed code',
			function () {
				$card = new Card( 5, 'jan', 'pl', array(), 0, 'https://example.test/card/jan/' );

				assert_same( 'https://example.test/card/jan/?src=qr', ( new Qr_Service() )->card_url( $card ) );
			}
		);
	}
);
