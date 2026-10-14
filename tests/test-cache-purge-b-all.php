<?php
/**
 * Cache purge with every supported caching plugin present.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/integrations/class-cache-purge.php';

use Asymetria\TeamCards\Integrations\Cache_Purge;

foreach ( array( 'clean_post_cache', 'rocket_clean_post', 'rocket_clean_domain', 'w3tc_flush_post', 'w3tc_flush_all', 'wpsc_delete_post_cache', 'wp_cache_clear_cache' ) as $tbcards_fn ) {
	if ( ! function_exists( $tbcards_fn ) ) {
		// phpcs:ignore Squiz.PHP.Eval.Discouraged -- test-only recorders for third-party functions.
		eval( 'function ' . $tbcards_fn . '( ...$args ) { $GLOBALS["tbcards_stub"]["purges"][] = array( "' . $tbcards_fn . '", $args[0] ?? null ); }' );
	}
}

describe(
	'cache purge: every caching plugin present',
	function () {

		it(
			'purges one card everywhere, once each',
			function () {
				$fired = array();
				add_action(
					'litespeed_purge_post',
					function ( $id ) use ( &$fired ) {
						$fired[] = $id;
					}
				);

				( new Cache_Purge() )->card( 5 );

				assert_same(
					array(
						array( 'clean_post_cache', 5 ),
						array( 'rocket_clean_post', 5 ),
						array( 'w3tc_flush_post', 5 ),
						array( 'wpsc_delete_post_cache', 5 ),
					),
					$GLOBALS['tbcards_stub']['purges']
				);
				assert_same( array( 5 ), $fired );
			}
		);

		it(
			'purges everything when shared data changes',
			function () {
				$all = 0;
				add_action(
					'litespeed_purge_all',
					function () use ( &$all ) {
						$all++;
					}
				);

				( new Cache_Purge() )->everything();

				assert_same(
					array( 'rocket_clean_domain', 'w3tc_flush_all', 'wp_cache_clear_cache' ),
					array_column( $GLOBALS['tbcards_stub']['purges'], 0 )
				);
				assert_same( 1, $all );
			}
		);

		it(
			'does nothing when a site opts out',
			function () {
				add_filter( 'tbcards_purge_cache', '__return_false' );

				( new Cache_Purge() )->card( 5 );
				( new Cache_Purge() )->everything();

				assert_same( array(), $GLOBALS['tbcards_stub']['purges'] ?? array() );
			}
		);

		it(
			'hooks card saves and both option updates',
			function () {
				( new Cache_Purge() )->hooks();

				assert_true( isset( $GLOBALS['tbcards_stub']['actions']['tbcards_card_saved'] ) );
				assert_true( isset( $GLOBALS['tbcards_stub']['actions']['update_option_tbcards_company'] ) );
				assert_true( isset( $GLOBALS['tbcards_stub']['actions']['update_option_tbcards_settings'] ) );
			}
		);
	}
);
