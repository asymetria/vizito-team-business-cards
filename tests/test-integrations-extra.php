<?php
/**
 * Pins the Yoast allow-list and the download buffer cleaning, both found on a
 * live site.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/card/class-card-repository.php';
require_once dirname( __DIR__ ) . '/includes/integrations/class-seo-compat.php';
require_once dirname( __DIR__ ) . '/includes/class-http.php';

use Asymetria\TeamCards\Integrations\Seo_Compat;
use Asymetria\TeamCards\Http;

describe(
	'seo compatibility',
	function () {

		it(
			'lets lang and src through Yoast on card pages',
			function () {
				$GLOBALS['tbcards_stub']['is_singular'] = array( 'tbcards_card' );

				assert_same( array( 'utm_source', 'lang', 'src' ), ( new Seo_Compat() )->allow_card_params( array( 'utm_source' ) ) );
			}
		);

		it(
			'leaves the rest of the site to Yoast',
			function () {
				$GLOBALS['tbcards_stub']['is_singular'] = array( 'page' );

				assert_same( array( 'utm_source' ), ( new Seo_Compat() )->allow_card_params( array( 'utm_source' ) ) );
			}
		);

		it(
			'hooks the filter Yoast reads',
			function () {
				( new Seo_Compat() )->hooks();

				assert_true( isset( $GLOBALS['tbcards_stub']['filters']['Yoast\WP\SEO\allowlist_permalink_vars'] ) );
			}
		);
	}
);

describe(
	'http',
	function () {

		it(
			'throws away what other code printed before a download',
			function () {
				$outer = ob_get_level();
				ob_start();
				echo ' ';
				ob_start();
				echo "\n";

				Http::clean_buffers();
				$left = ob_get_level();

				// Restore the harness's own buffer depth for the next cases.
				while ( ob_get_level() < $outer ) {
					ob_start();
				}

				assert_same( 0, $left );
			}
		);
	}
);
