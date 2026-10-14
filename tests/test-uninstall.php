<?php
/**
 * Pins uninstall: data stays unless the owner asked, goes entirely when asked.
 *
 * The file is included, not required once, because core includes it once
 * per uninstall and each case here is one uninstall.
 *
 * @package Vizito_Team_Business_Cards
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'vizito-team-business-cards/vizito-team-business-cards.php' );
}

if ( ! function_exists( 'tbcards_test_uninstall' ) ) {
	/**
	 * Runs uninstall.php once.
	 *
	 * @return void
	 */
	function tbcards_test_uninstall(): void {
		include dirname( __DIR__ ) . '/uninstall.php';
	}
}

describe(
	'uninstall',
	function () {

		before_each(
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_company']    = array( 'company_name' => 'Acme' );
				$GLOBALS['tbcards_stub']['options']['tbcards_db_version'] = '1';
				$GLOBALS['tbcards_stub']['db_options']                = array(
					'_transient_tbcards_hit_abc'         => 1,
					'_transient_timeout_tbcards_hit_abc' => 1,
					'_transient_other_plugin'        => 1,
				);
				$GLOBALS['tbcards_stub']['posts']                     = array(
					array(
						'ID'          => 5,
						'post_type'   => 'tbcards_card',
						'post_status' => 'publish',
					),
					array(
						'ID'          => 6,
						'post_type'   => 'tbcards_card',
						'post_status' => 'draft',
					),
					array(
						'ID'          => 9,
						'post_type'   => 'page',
						'post_status' => 'publish',
					),
				);
				$GLOBALS['tbcards_stub']['cron']['tbcards_stats_purge']   = 123;
			}
		);

		it(
			'keeps cards, settings and statistics by default, and clears temporary data',
			function () {
				tbcards_test_uninstall();

				assert_same( array(), $GLOBALS['tbcards_stub']['deleted_posts'] );
				assert_same( array(), $GLOBALS['tbcards_stub']['dropped_tables'] ?? array() );
				assert_same( 'Acme', $GLOBALS['tbcards_stub']['options']['tbcards_company']['company_name'] );
				assert_same( array( '_transient_other_plugin' ), array_keys( $GLOBALS['tbcards_stub']['db_options'] ) );
				assert_false( isset( $GLOBALS['tbcards_stub']['cron']['tbcards_stats_purge'] ) );
			}
		);

		it(
			'removes every card, option and the statistics table when asked to',
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_settings'] = array( 'delete_data' => true );

				tbcards_test_uninstall();

				assert_same( array( 5, 6 ), array_column( $GLOBALS['tbcards_stub']['deleted_posts'], 'id' ) );
				assert_true( $GLOBALS['tbcards_stub']['deleted_posts'][0]['force'] );
				assert_same( array( 'wp_tbcards_stats' ), $GLOBALS['tbcards_stub']['dropped_tables'] );

				foreach ( array( 'tbcards_settings', 'tbcards_company', 'tbcards_db_version' ) as $option ) {
					assert_false( isset( $GLOBALS['tbcards_stub']['options'][ $option ] ), $option );
				}
			}
		);

		it(
			'does not loop forever when a filter refuses to delete',
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_settings'] = array( 'delete_data' => true );
				$GLOBALS['tbcards_stub']['undeletable_posts']       = array( 5 );

				tbcards_test_uninstall();

				assert_true( count( $GLOBALS['tbcards_stub']['deleted_posts'] ) < 10 );
			}
		);
	}
);
