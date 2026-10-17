<?php
/**
 * The full card page.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Front;

use Asymetria\TeamCards\Card\Card;
use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Card\Card_Type;
use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Template_Loader' ) ) {

	/**
	 * Swaps the theme template for the plugin's own document on card pages,
	 * and prepares everything that document prints.
	 */
	final class Template_Loader {

		public const OVERRIDE = 'vizito-team-business-cards/card.php';

		/**
		 * The view model of the current request, read by the template.
		 *
		 * @var array|null
		 */
		private static ?array $view = null;

		/**
		 * Plugin settings.
		 *
		 * @var Settings
		 */
		private Settings $settings;

		/**
		 * Company profile.
		 *
		 * @var Company_Profile
		 */
		private Company_Profile $company;

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
		 * @param Company_Profile $company  Company profile.
		 * @param Card_Repository $cards    Card repository.
		 * @param Card_Type       $type     Card post type.
		 * @param Card_Renderer   $renderer Card renderer.
		 * @param Language        $language Language resolution.
		 */
		public function __construct( Settings $settings, Company_Profile $company, Card_Repository $cards, Card_Type $type, Card_Renderer $renderer, Language $language ) {
			$this->settings = $settings;
			$this->company  = $company;
			$this->cards    = $cards;
			$this->type     = $type;
			$this->renderer = $renderer;
			$this->language = $language;
		}

		/**
		 * Hooks.
		 *
		 * @return void
		 */
		public function hooks(): void {
			add_filter( 'template_include', array( $this, 'template' ), 99 );
		}

		/**
		 * The template_include filter.
		 *
		 * @param string $template Template WordPress chose.
		 * @return string
		 */
		public function template( $template ) {
			if ( ! is_singular( Card_Repository::POST_TYPE ) || get_query_var( Card_Type::QUERY_VAR ) ) {
				return $template;
			}

			$card = $this->cards->find( get_queried_object_id(), $this->language->current( 'page' ) );

			if ( null === $card ) {
				return $template;
			}

			self::$view = $this->build_view( $card );

			// Registered here and printed by the template with wp_print_styles()
			// and wp_print_scripts(): the page deliberately skips wp_head() and
			// wp_footer(), so nothing from the theme or other plugins reaches it.
			wp_register_style(
				\Asymetria\TeamCards\Embed\Embed_Renderer::HANDLE,
				TBCARDS_URL . 'assets/card.css',
				array(),
				TBCARDS_VERSION
			);
			wp_add_inline_style( \Asymetria\TeamCards\Embed\Embed_Renderer::HANDLE, self::$view['vars'] );

			wp_register_script(
				\Asymetria\TeamCards\Embed\Embed_Renderer::HANDLE,
				TBCARDS_URL . 'assets/card.js',
				array(),
				TBCARDS_VERSION,
				array( 'strategy' => 'defer' )
			);
			wp_add_inline_script(
				\Asymetria\TeamCards\Embed\Embed_Renderer::HANDLE,
				'window.tbcardsCard = ' . wp_json_encode( self::$view['config'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE ) . ';',
				'before'
			);

			$override = locate_template( self::OVERRIDE );

			return '' !== $override ? $override : TBCARDS_DIR . 'templates/card.php';
		}

		/**
		 * The view model of the current request, null outside card pages.
		 *
		 * @return array|null
		 */
		public static function view(): ?array {
			return self::$view;
		}

		/**
		 * Forgets the view model. For tests, and for long-running processes.
		 *
		 * @return void
		 */
		public static function reset_view(): void {
			self::$view = null;
		}

		/**
		 * Everything the template prints.
		 *
		 * @param Card $card Card.
		 * @return array
		 */
		private function build_view( Card $card ): array {
			$lang      = $card->lang();
			$default   = $this->settings->default_language();
			$languages = array();

			foreach ( $this->settings->languages() as $code ) {
				$languages[ $code ] = $this->language->switch_url( $card->url(), $code );
			}

			$args = array(
				'context'   => 'page',
				'vcard_url' => $this->type->vcard_url( $card->id(), $lang === $default ? '' : $lang ),
				'languages' => $languages,
			);

			$renderer = $this->renderer;
			$labels   = $this->language->with_locale(
				$lang,
				static function () use ( $renderer, $card, $args ) {
					return array(
						'html'   => $card->is_active() ? $renderer->render( $card, $args ) : $renderer->notice( $card ),
						'copied' => __( 'Link copied', 'vizito-team-business-cards' ),
					);
				}
			);

			$company = $this->company->value( 'company_name', $lang );
			$title   = $card->is_active() ? $card->full_name() : '';

			return array(
				'card'       => $card,
				'lang'       => $lang,
				'title'      => implode( ' — ', array_filter( array( $title, $company ) ) ),
				'html'       => $labels['html'],
				'vars'       => $this->css_vars(),
				'scheme'     => (string) $this->settings->get( 'scheme' ),
				'accent'     => (string) $this->settings->get( 'accent' ),
				'config'     => array(
					'lang'      => $lang,
					'languages' => $this->settings->languages(),
					'default'   => $default,
					'auto'      => (bool) $this->settings->get( 'auto_language' ),
					'copied'    => $labels['copied'],
					'card'      => $card->id(),
					'hit'       => $card->is_active() ? rest_url( 'tbcards/v1/hit' ) : '',
				),
			);
		}

		/**
		 * Appearance settings as custom property declarations.
		 *
		 * @return string
		 */
		private function css_vars(): string {
			$accent = sanitize_hex_color( (string) $this->settings->get( 'accent' ) );
			$radius = 'square' === $this->settings->get( 'photo_shape' ) ? '14px' : '50%';

			return ':root{--tbcards-accent:' . ( $accent ? $accent : '#1d4ed8' ) . ';--tbcards-photo-radius:' . $radius . '}';
		}
	}
}
