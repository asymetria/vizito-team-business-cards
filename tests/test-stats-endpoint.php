<?php
/**
 * Pins the public hit endpoint: what it counts, what it drops, what it keeps.
 *
 * @package Vizito_Team_Business_Cards
 */

$tbcards_root = dirname( __DIR__ );
require_once $tbcards_root . '/includes/class-settings.php';
require_once $tbcards_root . '/includes/card/class-fields.php';
require_once $tbcards_root . '/includes/card/class-card.php';
require_once $tbcards_root . '/includes/company/class-company-profile.php';
require_once $tbcards_root . '/includes/card/class-card-repository.php';
require_once $tbcards_root . '/includes/stats/class-stats-table.php';
require_once $tbcards_root . '/includes/stats/class-stats-endpoint.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Stats\Stats_Endpoint;
use Asymetria\TeamCards\Stats\Stats_Table;

if ( ! function_exists( 'tbcards_test_hit' ) ) {
	/**
	 * Sends one hit and returns the queries it made.
	 *
	 * @param array $params Request parameters.
	 * @return array{0: WP_REST_Response, 1: string[]}
	 */
	function tbcards_test_hit( array $params ): array {
		$db       = new Tbcards_Fake_Db();
		$settings = new Settings();
		$endpoint = new Stats_Endpoint( new Card_Repository( $settings, new Company_Profile( $settings ) ), new Stats_Table( $db ) );

		$response = $endpoint->handle( new WP_REST_Request( $params ) );

		return array( $response, $db->queries );
	}
}

describe(
	'stats endpoint',
	function () {

		before_each(
			function () {
				$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)';
				$_SERVER['REMOTE_ADDR']     = '203.0.113.7';

				$GLOBALS['tbcards_stub']['posts_by_id'][5] = (object) array(
					'ID'          => 5,
					'post_type'   => 'tbcards_card',
					'post_name'   => 'jan',
					'post_status' => 'publish',
				);
				$GLOBALS['tbcards_stub']['post_meta'][5]   = array( '_tbcards_first_name' => 'Jan' );
			}
		);

		it(
			'registers a public POST route under tbcards/v1/hit',
			function () {
				$settings = new Settings();
				( new Stats_Endpoint( new Card_Repository( $settings, new Company_Profile( $settings ) ), new Stats_Table( new Tbcards_Fake_Db() ) ) )->register_route();

				$route = $GLOBALS['tbcards_stub']['rest_routes'][0];

				assert_same( 'tbcards/v1', $route['namespace'] );
				assert_same( '/hit', $route['route'] );
				assert_same( 'POST', $route['args']['methods'] );
				assert_same( '__return_true', $route['args']['permission_callback'] );
			}
		);

		it(
			'counts a view from a printed code',
			function () {
				list( $response, $queries ) = tbcards_test_hit(
					array(
						'card'  => 5,
						'event' => 'view',
						'src'   => 'qr',
					)
				);

				assert_same( 204, $response->get_status() );
				assert_contains( "(5, '2026-01-01', 'view', 'qr', 1)", $queries[0] );
			}
		);

		it(
			'treats an unknown source as a link',
			function () {
				list( , $queries ) = tbcards_test_hit(
					array(
						'card'  => 5,
						'event' => 'call',
						'src'   => 'evil',
					)
				);

				assert_contains( "'call', 'link'", $queries[0] );
			}
		);

		it(
			'drops unknown events, and vCard downloads, which the server counts itself',
			function () {
				foreach ( array( 'hack', 'vcard' ) as $event ) {
					list( $response, $queries ) = tbcards_test_hit(
						array(
							'card'  => 5,
							'event' => $event,
						)
					);

					assert_same( 204, $response->get_status(), $event );
					assert_same( array(), $queries, $event );
				}
			}
		);

		it(
			'drops hits for missing and inactive cards',
			function () {
				list( , $missing ) = tbcards_test_hit(
					array(
						'card'  => 404,
						'event' => 'view',
					)
				);

				$GLOBALS['tbcards_stub']['post_meta'][5]['_tbcards_inactive'] = '1';
				list( , $inactive ) = tbcards_test_hit(
					array(
						'card'  => 5,
						'event' => 'view',
					)
				);

				assert_same( array(), $missing );
				assert_same( array(), $inactive );
			}
		);

		it(
			'drops hits for drafts and private cards',
			function () {
				$GLOBALS['tbcards_stub']['posts_by_id'][5]->post_status = 'draft';

				list( , $queries ) = tbcards_test_hit(
					array(
						'card'  => 5,
						'event' => 'view',
					)
				);

				assert_same( array(), $queries );
			}
		);

		it(
			'rate-limits by the address a site trusts, when it says so',
			function () {
				add_filter(
					'tbcards_client_ip',
					function () {
						return '198.51.100.9';
					}
				);

				tbcards_test_hit(
					array(
						'card'  => 5,
						'event' => 'view',
					)
				);

				$key = 'tbcards_hit_' . substr( hash_hmac( 'sha256', '198.51.100.9', wp_salt( 'nonce' ) ), 0, 24 );
				assert_true( isset( $GLOBALS['tbcards_stub']['transients'][ $key ] ) );
			}
		);

		it(
			'drops bots and link-preview fetchers',
			function () {
				foreach ( array( 'Googlebot/2.1', 'WhatsApp/2.23.20', 'facebookexternalhit/1.1', 'Slackbot-LinkExpanding', '' ) as $agent ) {
					$_SERVER['HTTP_USER_AGENT'] = $agent;

					list( , $queries ) = tbcards_test_hit(
						array(
							'card'  => 5,
							'event' => 'view',
						)
					);

					assert_same( array(), $queries, $agent );
				}
			}
		);

		it(
			'stops counting one address after 30 hits a minute, without storing the address',
			function () {
				$counted = 0;

				for ( $i = 0; $i < 35; $i++ ) {
					list( , $queries ) = tbcards_test_hit(
						array(
							'card'  => 5,
							'event' => 'view',
						)
					);
					$counted += count( $queries );
				}

				assert_same( 30, $counted );

				foreach ( $GLOBALS['tbcards_stub']['transients'] as $key => $stored ) {
					assert_false( str_contains( $key . serialize( $stored ), '203.0.113.7' ), 'address stored' );
					assert_same( 60, $stored['ttl'] );
				}
			}
		);

		it(
			'counts the server-side vCard download with its own hook',
			function () {
				$db       = new Tbcards_Fake_Db();
				$settings = new Settings();
				$endpoint = new Stats_Endpoint( new Card_Repository( $settings, new Company_Profile( $settings ) ), new Stats_Table( $db ) );

				$endpoint->vcard_downloaded( 5 );

				assert_contains( "'vcard', 'link'", $db->queries[0] );
			}
		);
	}
);
