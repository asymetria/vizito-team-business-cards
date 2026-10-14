<?php
/**
 * "Team Card" block.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Embed;

use Asymetria\TeamCards\Card\Card_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Block' ) ) {

	/**
	 * A dynamic block over the shared embed path. The editor picks a card
	 * from a private REST route open to people who can edit posts only; the
	 * card list is never public.
	 */
	final class Block {

		public const EDITOR_HANDLE = 'tbc-block-editor';

		/**
		 * Shared embed path.
		 *
		 * @var Embed_Renderer
		 */
		private Embed_Renderer $embed;

		/**
		 * Card repository.
		 *
		 * @var Card_Repository
		 */
		private Card_Repository $cards;

		/**
		 * Constructor.
		 *
		 * @param Embed_Renderer  $embed Shared embed path.
		 * @param Card_Repository $cards Card repository.
		 */
		public function __construct( Embed_Renderer $embed, Card_Repository $cards ) {
			$this->embed = $embed;
			$this->cards = $cards;
		}

		/**
		 * Hooks.
		 *
		 * @return void
		 */
		public function hooks(): void {
			add_action( 'init', array( $this, 'register' ) );
			add_action( 'rest_api_init', array( $this, 'register_route' ) );
		}

		/**
		 * Registers the editor script and the block.
		 *
		 * @return void
		 */
		public function register(): void {
			wp_register_script(
				self::EDITOR_HANDLE,
				TBCARDS_URL . 'assets/block.js',
				array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-api-fetch', 'wp-i18n' ),
				TBCARDS_VERSION,
				true
			);

			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( self::EDITOR_HANDLE, 'vizito-team-business-cards', TBCARDS_DIR . 'languages' );
			}

			register_block_type( TBCARDS_DIR . 'blocks/team-card', array( 'render_callback' => array( $this, 'render' ) ) );
		}

		/**
		 * The render callback.
		 *
		 * @param array $attributes Block attributes.
		 * @return string
		 */
		public function render( $attributes ): string {
			$attributes = is_array( $attributes ) ? $attributes : array();

			return $this->embed->render(
				array(
					'id'       => absint( $attributes['cardId'] ?? 0 ),
					'lang'     => (string) ( $attributes['lang'] ?? '' ),
					'inactive' => (string) ( $attributes['inactive'] ?? 'hide' ),
				)
			);
		}

		/**
		 * GET /tbcards/v1/cards for the picker.
		 *
		 * @return void
		 */
		public function register_route(): void {
			register_rest_route(
				'tbcards/v1',
				'/cards',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_cards' ),
					'permission_callback' => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}

		/**
		 * The route callback.
		 *
		 * @return \WP_REST_Response
		 */
		public function list_cards() {
			return new \WP_REST_Response( $this->cards->choices(), 200 );
		}
	}
}
