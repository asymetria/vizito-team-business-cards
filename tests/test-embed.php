<?php
/**
 * Pins the embeds: shared rendering, shortcode, block, Bricks element.
 *
 * @package Vizito_Team_Business_Cards
 */

$tbcards_root = dirname( __DIR__ );
require_once $tbcards_root . '/includes/class-autoloader.php';
\Asymetria\TeamCards\Autoloader::register();
require_once __DIR__ . '/bricks-stub.php';
require_once __DIR__ . '/bricks-registry-stub.php';

use Asymetria\TeamCards\Plugin;
use Asymetria\TeamCards\Embed\Block;
use Asymetria\TeamCards\Embed\Embed_Renderer;
use Asymetria\TeamCards\Embed\Shortcode;

describe(
	'embeds',
	function () {

		before_each(
			function () {
				Plugin::reset_for_tests();
				Embed_Renderer::reset_for_tests();
				unset( $_GET['lang'] );

				$cards = array(
					5 => array( 'jan', 'publish', array( '_tbcards_first_name' => 'Jan' ) ),
					6 => array(
						'ola',
						'publish',
						array(
							'_tbcards_first_name' => 'Ola',
							'_tbcards_inactive'   => '1',
						),
					),
					7 => array( 'draft', 'draft', array( '_tbcards_first_name' => 'Draft' ) ),
				);

				foreach ( $cards as $id => $row ) {
					$GLOBALS['tbcards_stub']['posts_by_id'][ $id ] = (object) array(
						'ID'          => $id,
						'post_type'   => 'tbcards_card',
						'post_name'   => $row[0],
						'post_status' => $row[1],
						'post_title'  => $row[2]['_tbcards_first_name'],
					);
					$GLOBALS['tbcards_stub']['post_meta'][ $id ]   = $row[2];
					$GLOBALS['tbcards_stub']['permalinks'][ $id ]  = 'https://example.test/card/' . $row[0] . '/';
				}

				$GLOBALS['tbcards_stub']['options']['tbcards_company'] = array( 'company_name' => 'Acme' );
			}
		);

		it(
			'renders a card as an embed, with an h2 and a vCard link',
			function () {
				$html = Plugin::instance()->embed()->render( array( 'id' => 5 ) );

				assert_contains( '<div class="tbcards-embed"><article class="tbcards-card tbcards-card--embed"', $html );
				assert_contains( '<h2 class="tbcards-card__name">Jan</h2>', $html );
				assert_contains( 'card/jan/vcard/', $html );
				assert_false( str_contains( $html, 'hreflang' ) );
			}
		);

		it(
			'adds the stylesheet, the script and its settings once, however many cards',
			function () {
				$embed = Plugin::instance()->embed();
				$embed->render( array( 'id' => 5 ) );
				$embed->render( array( 'id' => 5 ) );

				assert_same( array( 'tbcards-card' ), $GLOBALS['tbcards_stub']['style_queue'] );
				assert_same( array( 'tbcards-card' ), $GLOBALS['tbcards_stub']['script_queue'] );
				assert_same( 1, count( $GLOBALS['tbcards_stub']['styles']['tbcards-card']['inline'] ) );
				assert_same( 1, count( $GLOBALS['tbcards_stub']['scripts']['tbcards-card']['extra']['before'] ) );
				assert_contains( '"auto":false', $GLOBALS['tbcards_stub']['scripts']['tbcards-card']['extra']['before'][0] );
				assert_false( str_contains( $GLOBALS['tbcards_stub']['scripts']['tbcards-card']['extra']['before'][0], '"card"' ) );
			}
		);

		it(
			'hides a person who has left, or shows the company when asked',
			function () {
				$embed = Plugin::instance()->embed();

				assert_same( '', $embed->render( array( 'id' => 6 ) ) );

				$notice = $embed->render(
					array(
						'id'       => 6,
						'inactive' => 'notice',
					)
				);
				assert_contains( 'Acme', $notice );
				assert_false( str_contains( $notice, 'Ola' ) );
			}
		);

		it(
			'shows unpublished or unknown cards to nobody, with a hint for editors',
			function () {
				$embed = Plugin::instance()->embed();

				assert_same( '', $embed->render( array( 'id' => 7 ) ) );
				assert_same( '', $embed->render( array( 'id' => 404 ) ) );

				$GLOBALS['tbcards_stub']['capabilities']['edit_posts'] = true;
				assert_contains( '<!-- ', $embed->render( array( 'id' => 404 ) ) );
			}
		);

		it(
			'finds a card by slug through the shortcode',
			function () {
				$GLOBALS['tbcards_stub']['posts'] = array(
					array(
						'ID'          => 5,
						'post_type'   => 'tbcards_card',
						'post_status' => 'publish',
					),
				);
				$shortcode                    = new Shortcode( Plugin::instance()->embed() );

				assert_contains( 'Jan', $shortcode->render( array( 'slug' => 'jan' ) ) );
				assert_same( 'jan', $GLOBALS['tbcards_stub']['post_queries'][0]['name'] );
				assert_same( 'publish', $GLOBALS['tbcards_stub']['post_queries'][0]['post_status'] );
			}
		);

		it(
			'registers the shortcode on init',
			function () {
				( new Shortcode( Plugin::instance()->embed() ) )->hooks();
				do_action( 'init' );

				assert_true( isset( $GLOBALS['tbcards_stub']['shortcodes']['vizito_card'] ) );
			}
		);

		it(
			'registers the block from its block.json with a PHP render callback',
			function () {
				$block = new Block( Plugin::instance()->embed(), Plugin::instance()->cards() );
				$block->register();

				$registered = $GLOBALS['tbcards_stub']['blocks'][0];
				$json       = json_decode( (string) file_get_contents( TBCARDS_DIR . 'blocks/team-card/block.json' ), true );

				assert_same( TBCARDS_DIR . 'blocks/team-card', $registered['block_type'] );
				assert_same( 'vizito-team-business-cards/card', $json['name'] );
				assert_same( Block::EDITOR_HANDLE, $json['editorScript'] );
				assert_true( isset( $GLOBALS['tbcards_stub']['scripts'][ Block::EDITOR_HANDLE ] ) );
				assert_contains( 'Jan', $block->render( array( 'cardId' => 5 ) ) );
			}
		);

		it(
			'keeps the card list route for editors only',
			function () {
				$block = new Block( Plugin::instance()->embed(), Plugin::instance()->cards() );
				$block->register_route();

				$route = $GLOBALS['tbcards_stub']['rest_routes'][0];
				assert_same( '/cards', $route['route'] );
				assert_false( $route['args']['permission_callback']() );

				$GLOBALS['tbcards_stub']['capabilities']['edit_posts'] = true;
				assert_true( $route['args']['permission_callback']() );
			}
		);

		it(
			'hands the Bricks element to Bricks by file and class name',
			function () {
				Plugin::register_bricks_element();

				$registered = $GLOBALS['tbcards_stub']['bricks_elements'][0];
				assert_same( Plugin::BRICKS_ELEMENT, $registered['class'] );
				assert_true( is_readable( $registered['file'] ) );
			}
		);

		it(
			'renders the Bricks element through the shared path',
			function () {
				require_once TBCARDS_DIR . 'includes/embed/class-bricks-element.php';

				$element = new \Asymetria\TeamCards\Embed\Bricks_Element(
					array(
						'id'          => 'abc',
						'is_frontend' => true,
						'settings'    => array( 'card' => '5' ),
					)
				);

				ob_start();
				$element->render();
				$html = (string) ob_get_clean();

				assert_contains( 'Jan', $html );
				assert_same( 'tbcards-team-card', $element->name );
			}
		);

		it(
			'shows a placeholder in the builder until a card is chosen',
			function () {
				require_once TBCARDS_DIR . 'includes/embed/class-bricks-element.php';

				$element = new \Asymetria\TeamCards\Embed\Bricks_Element( array( 'is_frontend' => false ) );

				ob_start();
				$element->render();
				$html = (string) ob_get_clean();

				assert_false( str_contains( $html, 'tbcards-embed' ) );
			}
		);
	}
);
