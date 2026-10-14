<?php
/**
 * Pins the composition root.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/class-autoloader.php';

use Asymetria\TeamCards\Autoloader;
use Asymetria\TeamCards\Plugin;

Autoloader::register();

describe(
	'plugin',
	function () {

		before_each(
			function () {
				Plugin::reset_for_tests();
			}
		);

		it(
			'is idempotent: booting twice registers hooks once',
			function () {
				Plugin::instance()->boot();
				$once = $GLOBALS['tbcards_stub']['actions'];
				Plugin::instance()->boot();

				assert_true( count( $once['init'] ) > 0 );
				assert_same( array_map( 'count', $once ), array_map( 'count', $GLOBALS['tbcards_stub']['actions'] ) );
			}
		);

		it(
			'shares one settings object across its services',
			function () {
				$plugin = Plugin::instance();
				$plugin->boot();

				assert_true( $plugin->settings() instanceof \Asymetria\TeamCards\Settings );
				assert_true( $plugin->cards() instanceof \Asymetria\TeamCards\Card\Card_Repository );
				assert_true( $plugin->type() instanceof \Asymetria\TeamCards\Card\Card_Type );
				assert_true( $plugin->company() instanceof \Asymetria\TeamCards\Company\Company_Profile );
			}
		);
	}
);
