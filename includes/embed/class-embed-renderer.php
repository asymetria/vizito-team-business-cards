<?php
/**
 * A card placed on an ordinary page.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Embed;

use Asymetria\TeamCards\Card\Card;
use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Card\Card_Type;
use Asymetria\TeamCards\Front\Card_Renderer;
use Asymetria\TeamCards\Front\Language;
use Asymetria\TeamCards\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Embed_Renderer' ) ) {

	/**
	 * The one path the shortcode, the block and the Bricks element share, so a
	 * card looks and behaves the same in all three. Stylesheet, script and
	 * their inline settings are added once per page however many cards it has.
	 *
	 * An embed counts clicks, not views: a team page that lists ten people is
	 * one visit to that page, not ten visits to ten cards.
	 */
	final class Embed_Renderer {

		public const HANDLE    = 'tbcards-card';
		public const INACTIVE  = array( 'hide', 'notice' );
		public const ATTRIBUTES = array(
			'id'       => 0,
			'slug'     => '',
			'lang'     => '',
			'inactive' => 'hide',
		);

		/**
		 * Whether the assets are already on this page.
		 *
		 * @var bool
		 */
		private static bool $enqueued = false;

		/**
		 * Plugin settings.
		 *
		 * @var Settings
		 */
		private Settings $settings;

		/**
		 * Card repository.
		 *
		 * @var Card_Repository
		 */
		private Card_Repository $cards;

		/**
		 * Card post type.
		 *
		 * @var Card_Type
		 */
		private Card_Type $type;

		/**
		 * Card renderer.
		 *
		 * @var Card_Renderer
		 */
		private Card_Renderer $renderer;

		/**
		 * Language resolution.
		 *
		 * @var Language
		 */
		private Language $language;

		/**
		 * Constructor.
		 *
		 * @param Settings        $settings Plugin settings.
		 * @param Card_Repository $cards    Card repository.
		 * @param Card_Type       $type     Card post type.
		 * @param Card_Renderer   $renderer Card renderer.
		 * @param Language        $language Language resolution.
		 */
		public function __construct( Settings $settings, Card_Repository $cards, Card_Type $type, Card_Renderer $renderer, Language $language ) {
			$this->settings = $settings;
			$this->cards    = $cards;
			$this->type     = $type;
			$this->renderer = $renderer;
			$this->language = $language;
		}

		/**
		 * The HTML of one embedded card.
		 *
		 * @param array $atts { id, slug, lang, inactive }.
		 * @return string
		 */
		public function render( array $atts ): string {
			$atts = array_merge( self::ATTRIBUTES, array_intersect_key( $atts, self::ATTRIBUTES ) );
			$lang = in_array( (string) $atts['lang'], $this->settings->languages(), true ) ? (string) $atts['lang'] : $this->language->current( 'embed' );
			$card = $this->find( $atts, $lang );

			if ( null === $card ) {
				return current_user_can( 'edit_posts' ) ? '<!-- ' . esc_html__( 'Vizito Team Business Cards: card not found.', 'vizito-team-business-cards' ) . ' -->' : '';
			}

			if ( ! $card->is_active() && 'notice' !== $atts['inactive'] ) {
				return '';
			}

			$this->enqueue();

			$default  = $this->settings->default_language();
			$renderer = $this->renderer;
			$type     = $this->type;
			$html     = $this->language->with_locale(
				$lang,
				static function () use ( $renderer, $type, $card, $lang, $default ) {
					if ( ! $card->is_active() ) {
						return $renderer->notice( $card, 'embed' );
					}

					return $renderer->render(
						$card,
						array(
							'context'   => 'embed',
							'vcard_url' => $type->vcard_url( $card->id(), $lang === $default ? '' : $lang ),
						)
					);
				}
			);

			return '<div class="tbcards-embed">' . $html . '</div>';
		}

		/**
		 * Adds the stylesheet, the script and their settings, once.
		 *
		 * @return void
		 */
		public function enqueue(): void {
			if ( self::$enqueued ) {
				return;
			}

			self::$enqueued = true;

			$accent = sanitize_hex_color( (string) $this->settings->get( 'accent' ) );
			$radius = 'square' === $this->settings->get( 'photo_shape' ) ? '14px' : '50%';

			wp_enqueue_style( self::HANDLE, TBCARDS_URL . 'assets/card.css', array(), TBCARDS_VERSION );
			wp_add_inline_style( self::HANDLE, '.tbcards-embed{--tbcards-accent:' . ( $accent ? $accent : '#1d4ed8' ) . ';--tbcards-photo-radius:' . $radius . '}' );

			wp_enqueue_script(
				self::HANDLE,
				TBCARDS_URL . 'assets/card.js',
				array(),
				TBCARDS_VERSION,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);

			$config = array(
				'auto'   => false,
				'hit'    => rest_url( 'tbcards/v1/hit' ),
				'copied' => __( 'Link copied', 'vizito-team-business-cards' ),
			);

			wp_add_inline_script( self::HANDLE, 'window.tbcardsCard = window.tbcardsCard || ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );
		}

		/**
		 * Forgets that the assets were added. Tests only.
		 *
		 * @return void
		 */
		public static function reset_for_tests(): void {
			if ( defined( 'TBCARDS_TESTING' ) && TBCARDS_TESTING ) {
				self::$enqueued = false;
			}
		}

		/**
		 * The card an embed asks for, by id or slug.
		 *
		 * @param array  $atts Attributes.
		 * @param string $lang Language code.
		 * @return Card|null
		 */
		private function find( array $atts, string $lang ): ?Card {
			$id = absint( $atts['id'] );

			if ( $id > 0 ) {
				$post = get_post( $id );

				return $post && 'publish' === ( $post->post_status ?? '' ) ? $this->cards->find( $id, $lang ) : null;
			}

			return $this->cards->find_by_slug( (string) $atts['slug'], $lang );
		}
	}
}
