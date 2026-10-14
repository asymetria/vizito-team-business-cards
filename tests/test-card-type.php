<?php
/**
 * Pins the post type registration, the vCard rewrite and the indexing rules.
 *
 * @package Vizito_Team_Business_Cards
 */

$tbcards_root = dirname( __DIR__ );
require_once $tbcards_root . '/includes/class-settings.php';
require_once $tbcards_root . '/includes/card/class-card-repository.php';
require_once $tbcards_root . '/includes/card/class-card-type.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Card\Card_Type;

describe(
	'card type',
	function () {

		it(
			'registers a non-public but queryable post type under the configured base',
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_settings'] = array( 'url_base' => 'wizytowka' );
				( new Card_Type( new Settings() ) )->register();

				$registered = $GLOBALS['tbcards_stub']['post_types'][0];
				$args       = $registered['args'];

				assert_same( 'tbcards_card', $registered['name'] );
				assert_false( $args['public'] );
				assert_true( $args['publicly_queryable'] );
				assert_true( $args['show_ui'] );
				assert_false( $args['has_archive'] );
				assert_true( $args['exclude_from_search'] );
				assert_false( $args['show_in_rest'] );
				assert_same(
					array(
						'slug'       => 'wizytowka',
						'with_front' => false,
					),
					$args['rewrite']
				);
				assert_same( array( 'title', 'thumbnail' ), $args['supports'] );
			}
		);

		it(
			'registers the vcard endpoint under the same base',
			function () {
				( new Card_Type( new Settings() ) )->register();

				$rule = $GLOBALS['tbcards_stub']['rewrite_rules'][0];
				assert_same( '^card/([^/]+)/vcard/?$', $rule['regex'] );
				assert_same( 'index.php?tbcards_card=$matches[1]&tbcards_vcard=1', $rule['query'] );
				assert_same( 'top', $rule['after'] );
				assert_same( '%tbcards_vcard%', $GLOBALS['tbcards_stub']['rewrite_tags'][0]['tag'] );
			}
		);

		it(
			'builds the vcard url with an optional language',
			function () {
				$GLOBALS['tbcards_stub']['permalinks'][5] = 'https://example.test/card/jan/';
				$type                                 = new Card_Type( new Settings() );

				assert_same( 'https://example.test/card/jan/vcard/', $type->vcard_url( 5 ) );
				assert_same( 'https://example.test/card/jan/vcard/?lang=de', $type->vcard_url( 5, 'de' ) );
			}
		);

		it(
			'flushes rewrite rules once when asked to, and clears the request',
			function () {
				$GLOBALS['tbcards_stub']['options']['tbcards_flush_rewrite'] = 1;
				$type = new Card_Type( new Settings() );

				$type->maybe_flush();
				$type->maybe_flush();

				assert_same( 1, $GLOBALS['tbcards_stub']['rewrite_flushes'] );
				assert_same( array( 'tbcards_flush_rewrite' ), $GLOBALS['tbcards_stub']['options_deleted'] );
			}
		);

		it(
			'excludes cards from Yoast and Rank Math sitemaps and leaves other types alone',
			function () {
				$type = new Card_Type( new Settings() );

				assert_true( $type->exclude_from_sitemap( false, 'tbcards_card' ) );
				assert_false( $type->exclude_from_sitemap( false, 'post' ) );
			}
		);

		it(
			'removes cards from core sitemaps',
			function () {
				$kept = ( new Card_Type( new Settings() ) )->core_sitemap_types(
					array(
						'post'     => 1,
						'tbcards_card' => 2,
					)
				);

				assert_same( array( 'post' => 1 ), $kept );
			}
		);

		it(
			'hooks registration, flushing, noindex and all three sitemap filters',
			function () {
				( new Card_Type( new Settings() ) )->hooks();

				$actions = $GLOBALS['tbcards_stub']['actions'];
				$filters = $GLOBALS['tbcards_stub']['filters'];

				assert_same( 2, count( $actions['init'] ) );
				assert_same( 99, $actions['init'][1]['priority'] );
				assert_true( isset( $actions['template_redirect'] ) );
				assert_true( isset( $filters['wp_sitemaps_post_types'] ) );
				assert_true( isset( $filters['wpseo_sitemap_exclude_post_type'] ) );
				assert_true( isset( $filters['rank_math/sitemap/exclude_post_type'] ) );
			}
		);
	}
);
