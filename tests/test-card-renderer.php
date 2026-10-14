<?php
/**
 * Pins the card HTML and the icon set.
 *
 * @package Vizito_Team_Business_Cards
 */

$tbcards_root = dirname( __DIR__ );
require_once $tbcards_root . '/includes/class-settings.php';
require_once $tbcards_root . '/includes/card/class-fields.php';
require_once $tbcards_root . '/includes/card/class-field-sanitizer.php';
require_once $tbcards_root . '/includes/card/class-card.php';
require_once $tbcards_root . '/includes/company/class-company-profile.php';
require_once $tbcards_root . '/includes/front/class-icons.php';
require_once $tbcards_root . '/includes/front/class-card-renderer.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Card\Card;
use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Front\Card_Renderer;
use Asymetria\TeamCards\Front\Icons;

if ( ! function_exists( 'tbcards_test_card' ) ) {
	/**
	 * A card with the given values over a full default set.
	 *
	 * @param array $values Overrides.
	 * @param int   $photo  Photo id.
	 * @return Card
	 */
	function tbcards_test_card( array $values = array(), int $photo = 0 ): Card {
		return new Card(
			5,
			'jan-kowalski',
			'pl',
			array_merge(
				array(
					'first_name' => 'Jan',
					'last_name'  => 'Kowalski',
					'job_title'  => 'Prezes',
					'intro'      => "Linia 1\nLinia 2",
					'mobile'     => '+48 609 909 828',
					'email'      => 'jan@firma.example',
					'whatsapp'   => '+48 609 909 828',
					'street'     => 'Twarda 18',
					'postcode'   => '00-105',
					'city'       => 'Warszawa',
					'country'    => 'Polska',
					'linkedin'   => 'https://www.linkedin.com/company/x',
				),
				$values
			),
			$photo,
			'https://example.test/card/jan-kowalski/'
		);
	}

	/**
	 * Renders with default page arguments.
	 *
	 * @param Card  $card Card.
	 * @param array $args Overrides.
	 * @return string
	 */
	function tbcards_test_render( Card $card, array $args = array() ): string {
		$renderer = new Card_Renderer( new Company_Profile( new Settings() ) );

		return $renderer->render(
			$card,
			array_merge(
				array(
					'context'   => 'page',
					'vcard_url' => 'https://example.test/card/jan-kowalski/vcard/',
					'languages' => array(),
				),
				$args
			)
		);
	}
}

describe(
	'card renderer',
	function () {

		it(
			'puts the name in h1 on the page and h2 in an embed',
			function () {
				assert_contains( '<h1 class="tbc-card__name">Jan Kowalski</h1>', tbcards_test_render( tbcards_test_card() ) );
				assert_contains( '<h2 class="tbc-card__name">Jan Kowalski</h2>', tbcards_test_render( tbcards_test_card(), array( 'context' => 'embed' ) ) );
			}
		);

		it(
			'escapes values',
			function () {
				$html = tbcards_test_render( tbcards_test_card( array( 'job_title' => '<script>x</script>' ) ) );

				assert_contains( '&lt;script&gt;', $html );
				assert_false( str_contains( $html, '<script>x' ) );
			}
		);

		it(
			'keeps line breaks of the intro',
			function () {
				assert_contains( "Linia 1<br />\nLinia 2", tbcards_test_render( tbcards_test_card() ) );
			}
		);

		it(
			'dials the mobile in the call link and in WhatsApp',
			function () {
				$html = tbcards_test_render( tbcards_test_card() );

				assert_contains( 'href="tel:+48609909828"', $html );
				assert_contains( 'href="https://wa.me/48609909828"', $html );
				assert_contains( 'href="mailto:jan@firma.example"', $html );
			}
		);

		it(
			'omits actions whose value is empty, and Save contact without a vCard address',
			function () {
				$html = tbcards_test_render(
					tbcards_test_card(
						array(
							'mobile'   => '',
							'email'    => '',
							'whatsapp' => '',
						)
					),
					array( 'vcard_url' => '' )
				);

				foreach ( array( 'call', 'email', 'whatsapp', 'vcard' ) as $event ) {
					assert_false( str_contains( $html, 'data-tbc-event="' . $event . '"' ), $event );
				}
			}
		);

		it(
			'links Save contact to the vCard with a download attribute',
			function () {
				assert_contains( 'href="https://example.test/card/jan-kowalski/vcard/" download data-tbc-event="vcard"', tbcards_test_render( tbcards_test_card() ) );
			}
		);

		it(
			'uses the map link for directions when there is one, a search otherwise',
			function () {
				$own    = tbcards_test_render( tbcards_test_card( array( 'map_url' => 'https://maps.example/x' ) ) );
				$search = tbcards_test_render( tbcards_test_card() );

				assert_contains( 'href="https://maps.example/x"', $own );
				assert_contains( 'href="https://www.google.com/maps/search/?api=1&#038;query=Twarda%2018%2C%2000-105%20Warszawa%2C%20Polska"', $search );
			}
		);

		it(
			'renders only networks that have an address, in catalogue order, with a name',
			function () {
				$html = tbcards_test_render( tbcards_test_card( array( 'github' => 'https://github.com/jan' ) ) );

				assert_contains( 'aria-label="LinkedIn: Jan Kowalski"', $html );
				assert_contains( 'aria-label="GitHub: Jan Kowalski"', $html );
				assert_false( str_contains( $html, 'Facebook:' ) );
				assert_true( strpos( $html, 'LinkedIn:' ) < strpos( $html, 'GitHub:' ) );
			}
		);

		it(
			'needs both label and address for a custom button',
			function () {
				$half = tbcards_test_render( tbcards_test_card( array( 'custom_1_label' => 'Umów spotkanie' ) ) );
				$full = tbcards_test_render(
					tbcards_test_card(
						array(
							'custom_1_label' => 'Umów spotkanie',
							'custom_1_url'   => 'https://cal.example/jan',
						)
					)
				);

				assert_false( str_contains( $half, 'Umów spotkanie' ) );
				assert_contains( 'href="https://cal.example/jan"', $full );
				assert_contains( 'Umów spotkanie', $full );
			}
		);

		it(
			'shows initials without a photo and a named image with one',
			function () {
				$GLOBALS['tbcards_stub']['attachment_urls'][9] = 'https://example.test/jan.jpg';

				assert_contains( '>JK<', tbcards_test_render( tbcards_test_card() ) );
				assert_contains( 'src="https://example.test/jan.jpg" alt="Jan Kowalski"', tbcards_test_render( tbcards_test_card( array(), 9 ) ) );
			}
		);

		it(
			'prints the language switcher on the page only, with the current language marked',
			function () {
				$languages = array(
					'pl' => 'https://example.test/card/jan-kowalski/?lang=pl',
					'de' => 'https://example.test/card/jan-kowalski/?lang=de',
				);

				$page  = tbcards_test_render( tbcards_test_card(), array( 'languages' => $languages ) );
				$embed = tbcards_test_render(
					tbcards_test_card(),
					array(
						'languages' => $languages,
						'context'   => 'embed',
					)
				);
				$one   = tbcards_test_render( tbcards_test_card(), array( 'languages' => array( 'pl' => 'x' ) ) );

				assert_contains( 'hreflang="de"', $page );
				assert_contains( 'aria-current="true"', $page );
				assert_false( str_contains( $embed, 'hreflang=' ) );
				assert_false( str_contains( $one, 'hreflang=' ) );
			}
		);

		it(
			'uses only known statistic event names',
			function () {
				$html = tbcards_test_render(
					tbcards_test_card(
						array(
							'custom_1_label' => 'A',
							'custom_1_url'   => 'https://a.example',
						)
					)
				);

				preg_match_all( '/data-tbc-event="([^"]+)"/', $html, $found );

				foreach ( $found[1] as $event ) {
					assert_true( in_array( $event, Card_Renderer::EVENTS, true ), $event );
				}

				assert_true( count( $found[1] ) >= 6 );
			}
		);

		it(
			'shows the company logo and name from the profile',
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_company'] = array(
					'company_name' => 'Acme',
					'logo_id'      => 3,
				);
				$GLOBALS['tbcards_stub']['attachment_urls'][3]     = 'https://example.test/logo.png';

				$html = tbcards_test_render( tbcards_test_card() );

				assert_contains( 'src="https://example.test/logo.png" alt="Acme"', $html );
				assert_contains( 'class="tbc-card__company">Acme<', $html );
			}
		);
	}
);

describe(
	'icons',
	function () {

		it(
			'returns decorative inline svg for every declared name',
			function () {
				foreach ( Icons::NAMES as $name ) {
					$svg = Icons::svg( $name );
					assert_contains( '<svg', $svg, $name );
					assert_contains( 'aria-hidden="true"', $svg, $name );
					assert_contains( 'focusable="false"', $svg, $name );
				}
			}
		);

		it(
			'falls back to the link icon for an unknown name',
			function () {
				assert_same( Icons::svg( 'link' ), Icons::svg( 'nope' ) );
			}
		);
	}
);

describe(
	'card renderer: accessibility',
	function () {

		it(
			'tells screen readers which links open a new tab',
			function () {
				$html = tbcards_test_render(
					tbcards_test_card(
						array(
							'website'        => 'https://firma.example',
							'custom_1_label' => 'Kalendarz',
							'custom_1_url'   => 'https://cal.example',
						)
					)
				);

				preg_match_all( '/<a [^>]*target="_blank"[^>]*>(.*?)<\/a>/s', $html, $links );

				foreach ( $links[0] as $i => $link ) {
					if ( str_contains( $link, 'aria-label=' ) ) {
						continue; // Icon links carry their whole name in aria-label.
					}
					assert_contains( '(opens in a new tab)', $links[1][ $i ], $link );
				}

				assert_true( count( $links[0] ) >= 4 );
			}
		);

		it(
			'gives every link and button an accessible name',
			function () {
				$html = tbcards_test_render( tbcards_test_card() );

				preg_match_all( '/<(a|button)\b([^>]*)>(.*?)<\/\1>/s', $html, $controls, PREG_SET_ORDER );

				foreach ( $controls as $control ) {
					$text = trim( wp_strip_all_tags( $control[3] ) );
					assert_true( '' !== $text || str_contains( $control[2], 'aria-label=' ), 'unnamed: ' . $control[0] );
				}
			}
		);
	}
);
