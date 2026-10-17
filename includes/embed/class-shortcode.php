<?php
/**
 * [vizito_card] shortcode.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Embed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Shortcode' ) ) {

	/**
	 * [vizito_card id="12"] or [vizito_card slug="jan-kowalski"], with optional
	 * lang="de" and inactive="hide|notice".
	 */
	final class Shortcode {

		public const TAG = 'vizito_card';

		/**
		 * Shared embed path.
		 *
		 * @var Embed_Renderer
		 */
		private Embed_Renderer $embed;

		/**
		 * Constructor.
		 *
		 * @param Embed_Renderer $embed Shared embed path.
		 */
		public function __construct( Embed_Renderer $embed ) {
			$this->embed = $embed;
		}

		/**
		 * Hooks.
		 *
		 * @return void
		 */
		public function hooks(): void {
			add_action(
				'init',
				function () {
					add_shortcode( self::TAG, array( $this, 'render' ) );
				}
			);
		}

		/**
		 * The shortcode callback.
		 *
		 * @param array|string $atts Attributes.
		 * @return string
		 */
		public function render( $atts ): string {
			return $this->embed->render( shortcode_atts( Embed_Renderer::ATTRIBUTES, is_array( $atts ) ? $atts : array(), self::TAG ) );
		}
	}
}
