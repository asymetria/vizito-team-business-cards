<?php
/**
 * Cache purge with no caching plugin present. This file sorts before
 * test-cache-purge-b-all.php, which defines the caching plugins' functions;
 * once defined they cannot be undefined, so "none present" must run first.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/integrations/class-cache-purge.php';

use Asymetria\TeamCards\Integrations\Cache_Purge;

describe(
	'cache purge: no caching plugin',
	function () {

		it(
			'does not fail when no caching plugin is installed',
			function () {
				assert_false( function_exists( 'rocket_clean_post' ) );

				$purge = new Cache_Purge();
				$purge->card( 5 );
				$purge->everything();

				assert_same( 1, $GLOBALS['tbcards_stub']['action_counts']['litespeed_purge_post'] );
				assert_same( 1, $GLOBALS['tbcards_stub']['action_counts']['litespeed_purge_all'] );
			}
		);

		it(
			'still tells LiteSpeed, whose API is an action and always safe to fire',
			function () {
				$fired = array();
				add_action(
					'litespeed_purge_post',
					function ( $id ) use ( &$fired ) {
						$fired[] = $id;
					}
				);

				( new Cache_Purge() )->card( 5 );

				assert_same( array( 5 ), $fired );
			}
		);
	}
);
