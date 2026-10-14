<?php
/**
 * Pins the class → file mapping every other file relies on.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/class-autoloader.php';

use Asymetria\TeamCards\Autoloader;

describe(
	'autoloader',
	function () {

		it(
			'maps a top-level class into includes/',
			function () {
				assert_same( 'includes/class-plugin.php', Autoloader::path_for( 'Asymetria\\TeamCards\\Plugin' ) );
			}
		);

		it(
			'maps a sub-namespace to a lowercase directory under includes/',
			function () {
				assert_same(
					'includes/card/class-card-repository.php',
					Autoloader::path_for( 'Asymetria\\TeamCards\\Card\\Card_Repository' )
				);
			}
		);

		it(
			'maps an underscored sub-namespace to a dashed directory',
			function () {
				assert_same(
					'includes/some-area/class-thing.php',
					Autoloader::path_for( 'Asymetria\\TeamCards\\Some_Area\\Thing' )
				);
			}
		);

		it(
			'ignores classes outside its namespace',
			function () {
				assert_same( null, Autoloader::path_for( 'Asymetria\\StoreLocator\\Plugin' ) );
				assert_same( null, Autoloader::path_for( 'Plugin' ) );
			}
		);

		it(
			'loads a real class file from disk',
			function () {
				Autoloader::register();
				assert_true( class_exists( 'Asymetria\\TeamCards\\Plugin' ) );
			}
		);
	}
);
