<?php
/**
 * Pins the Card value object.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/card/class-card.php';

use Asymetria\TeamCards\Card\Card;

describe(
	'card',
	function () {

		it(
			'exposes identity and values, and answers empty for unknown keys',
			function () {
				$card = new Card(
					7,
					'jan-kowalski',
					'pl',
					array(
						'first_name' => 'Jan',
						'last_name'  => 'Kowalski',
					),
					12,
					'https://example.test/card/jan-kowalski/'
				);

				assert_same( 7, $card->id() );
				assert_same( 'jan-kowalski', $card->slug() );
				assert_same( 'pl', $card->lang() );
				assert_same( 12, $card->photo_id() );
				assert_same( 'https://example.test/card/jan-kowalski/', $card->url() );
				assert_same( 'Jan', $card->get( 'first_name' ) );
				assert_same( '', $card->get( 'nope' ) );
				assert_true( $card->has( 'first_name' ) );
				assert_false( $card->has( 'email' ) );
			}
		);

		it(
			'builds a full name without stray spaces',
			function () {
				$both = new Card(
					1,
					's',
					'pl',
					array(
						'first_name' => 'Jan',
						'last_name'  => 'Kowalski',
					),
					0,
					''
				);
				$last = new Card( 1, 's', 'pl', array( 'last_name' => 'Kowalski' ), 0, '' );

				assert_same( 'Jan Kowalski', $both->full_name() );
				assert_same( 'Kowalski', $last->full_name() );
			}
		);

		it(
			'is active unless told otherwise',
			function () {
				assert_true( ( new Card( 1, 's', 'pl', array(), 0, '' ) )->is_active() );
				assert_false( ( new Card( 1, 's', 'pl', array(), 0, '', false ) )->is_active() );
			}
		);
	}
);
