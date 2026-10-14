<?php
/**
 * Bricks element.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Embed;

use Asymetria\TeamCards\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Declared only where Bricks has loaded its element base class. Plugin
 * registers this file with Bricks on init at priority 11, after Bricks'
 * own init_elements() at 10 has required that base class. The class is named
 * to Bricks as a string, so the autoloader never touches this file on a site
 * without Bricks.
 */
if ( class_exists( '\\Bricks\\Element' ) && ! class_exists( __NAMESPACE__ . '\\Bricks_Element' ) ) {

	/**
	 * One card, through the same path as the shortcode and the block.
	 *
	 * The builder re-renders an element on every control change; the card
	 * needs no start-up script, because card.js listens on the document.
	 */
	class Bricks_Element extends \Bricks\Element {

		public const NAME = 'tbcards-team-card';

		/**
		 * Builder category.
		 *
		 * @var string
		 */
		public $category = 'general';

		/**
		 * Element name.
		 *
		 * @var string
		 */
		public $name = self::NAME;

		/**
		 * Builder icon.
		 *
		 * @var string
		 */
		public $icon = 'ti-id-badge';

		/**
		 * Label in the builder.
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'Team Card', 'vizito-team-business-cards' );
		}

		/**
		 * Search keywords in the builder.
		 *
		 * @return string[]
		 */
		public function get_keywords() {
			return array( 'business card', 'vcard', 'team', 'contact', 'person', 'qr' );
		}

		/**
		 * The controls.
		 *
		 * @return void
		 */
		public function set_controls() {
			foreach ( self::controls() as $key => $control ) {
				$this->controls[ $key ] = $control;
			}
		}

		/**
		 * Controls as Bricks expects them.
		 *
		 * @return array
		 */
		public static function controls(): array {
			$cards = array();

			foreach ( Plugin::instance()->cards()->choices() as $choice ) {
				$cards[ (string) $choice['id'] ] = esc_html( $choice['name'] );
			}

			$languages = array();

			foreach ( Plugin::instance()->settings()->languages() as $code ) {
				$languages[ $code ] = esc_html( strtoupper( $code ) );
			}

			return array(
				'card'     => array(
					'tab'         => 'content',
					'type'        => 'select',
					'label'       => esc_html__( 'Card', 'vizito-team-business-cards' ),
					'options'     => $cards,
					'placeholder' => esc_html__( 'Choose a card', 'vizito-team-business-cards' ),
					'searchable'  => true,
					'rerender'    => true,
				),
				'lang'     => array(
					'tab'         => 'content',
					'type'        => 'select',
					'label'       => esc_html__( 'Language', 'vizito-team-business-cards' ),
					'options'     => $languages,
					'placeholder' => esc_html__( 'Page language', 'vizito-team-business-cards' ),
					'inline'      => true,
					'rerender'    => true,
				),
				'inactive' => array(
					'tab'         => 'content',
					'type'        => 'select',
					'label'       => esc_html__( 'If the person has left', 'vizito-team-business-cards' ),
					'options'     => array(
						'hide'   => esc_html__( 'Show nothing', 'vizito-team-business-cards' ),
						'notice' => esc_html__( 'Show company details', 'vizito-team-business-cards' ),
					),
					'placeholder' => esc_html__( 'Show nothing', 'vizito-team-business-cards' ),
					'inline'      => true,
					'rerender'    => true,
				),
			);
		}

		/**
		 * Assets in the builder canvas too; the front end adds them on render.
		 *
		 * @return void
		 */
		public function enqueue_scripts() {
			Plugin::instance()->embed()->enqueue();
		}

		/**
		 * The element's HTML.
		 *
		 * @return void
		 */
		public function render() {
			$settings = is_array( $this->settings ) ? $this->settings : array();
			$html     = Plugin::instance()->embed()->render(
				array(
					'id'       => absint( $settings['card'] ?? 0 ),
					'lang'     => (string) ( $settings['lang'] ?? '' ),
					'inactive' => (string) ( $settings['inactive'] ?? 'hide' ),
				)
			);

			if ( '' === $html && ! $this->is_frontend ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Bricks builds and escapes the placeholder.
				echo $this->render_element_placeholder( array( 'title' => esc_html__( 'Choose a card in the element settings.', 'vizito-team-business-cards' ) ) );
				return;
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Bricks escapes its attributes; the card is escaped by Card_Renderer.
			echo '<div ' . $this->render_attributes( '_root' ) . '>' . $html . '</div>';
		}
	}
}
