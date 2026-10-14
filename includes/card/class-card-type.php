<?php
/**
 * The card post type.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Card;

use Asymetria\TeamCards\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Card_Type' ) ) {

	/**
	 * Registers `tbcards_card`, its vCard endpoint, and keeps cards out of indexes.
	 *
	 * The type is not public, so core sitemaps skip it on their own. Yoast and
	 * Rank Math treat any publicly queryable type as viewable, so both get an
	 * explicit exclusion. The full-page card template does not call wp_head(),
	 * so wp_robots never runs there: the noindex travels as a response header.
	 */
	final class Card_Type {

		public const QUERY_VAR = 'tbcards_vcard';

		/**
		 * Plugin settings.
		 *
		 * @var Settings
		 */
		private Settings $settings;

		/**
		 * Constructor.
		 *
		 * @param Settings $settings Plugin settings.
		 */
		public function __construct( Settings $settings ) {
			$this->settings = $settings;
		}

		/**
		 * Hooks.
		 *
		 * @return void
		 */
		public function hooks(): void {
			add_action( 'init', array( $this, 'register' ) );
			add_action( 'init', array( $this, 'maybe_flush' ), 99 );
			add_action( 'template_redirect', array( $this, 'send_noindex' ), 1 );
			add_filter( 'wp_sitemaps_post_types', array( $this, 'core_sitemap_types' ) );
			add_filter( 'wpseo_sitemap_exclude_post_type', array( $this, 'exclude_from_sitemap' ), 10, 2 );
			add_filter( 'rank_math/sitemap/exclude_post_type', array( $this, 'exclude_from_sitemap' ), 10, 2 );
		}

		/**
		 * Registers the post type and the vCard rewrite.
		 *
		 * @return void
		 */
		public function register(): void {
			$base = (string) $this->settings->get( 'url_base' );

			register_post_type(
				Card_Repository::POST_TYPE,
				array(
					'labels'              => array(
						'name'          => __( 'Business cards', 'vizito-team-business-cards' ),
						'singular_name' => __( 'Business card', 'vizito-team-business-cards' ),
						'add_new_item'  => __( 'Add business card', 'vizito-team-business-cards' ),
						'edit_item'     => __( 'Edit business card', 'vizito-team-business-cards' ),
						'all_items'     => __( 'All cards', 'vizito-team-business-cards' ),
						'menu_name'     => __( 'Team Cards', 'vizito-team-business-cards' ),
					),
					'public'              => false,
					'publicly_queryable'  => true,
					'show_ui'             => true,
					'show_in_menu'        => true,
					'show_in_nav_menus'   => false,
					'show_in_rest'        => false,
					'exclude_from_search' => true,
					'has_archive'         => false,
					'query_var'           => Card_Repository::POST_TYPE,
					'rewrite'             => array(
						'slug'       => $base,
						'with_front' => false,
					),
					'supports'            => array( 'title', 'thumbnail' ),
					'menu_icon'           => 'dashicons-id',
				)
			);

			add_rewrite_tag( '%' . self::QUERY_VAR . '%', '1' );
			add_rewrite_rule(
				'^' . preg_quote( $base, '/' ) . '/([^/]+)/vcard/?$',
				'index.php?' . Card_Repository::POST_TYPE . '=$matches[1]&' . self::QUERY_VAR . '=1',
				'top'
			);
		}

		/**
		 * The vCard address of a card.
		 *
		 * @param int    $id   Post id.
		 * @param string $lang Language code, "" for none.
		 * @return string
		 */
		public function vcard_url( int $id, string $lang = '' ): string {
			$url = trailingslashit( (string) get_permalink( $id ) ) . 'vcard/';

			return '' === $lang ? $url : add_query_arg( 'lang', $lang, $url );
		}

		/**
		 * Flushes rewrite rules once after the URL base changed.
		 *
		 * @return void
		 */
		public function maybe_flush(): void {
			if ( ! get_option( Settings::FLUSH_OPTION ) ) {
				return;
			}

			delete_option( Settings::FLUSH_OPTION );
			flush_rewrite_rules( false );
		}

		/**
		 * Sends X-Robots-Tag on every card response.
		 *
		 * @return void
		 */
		public function send_noindex(): void {
			if ( is_singular( Card_Repository::POST_TYPE ) && ! headers_sent() ) {
				header( 'X-Robots-Tag: noindex, noarchive', true );
			}
		}

		/**
		 * Core sitemaps filter.
		 *
		 * @param array $types Post types keyed by name.
		 * @return array
		 */
		public function core_sitemap_types( $types ) {
			if ( is_array( $types ) ) {
				unset( $types[ Card_Repository::POST_TYPE ] );
			}

			return $types;
		}

		/**
		 * Yoast and Rank Math exclusion filter.
		 *
		 * @param bool   $excluded  Current decision.
		 * @param string $post_type Post type.
		 * @return bool
		 */
		public function exclude_from_sitemap( $excluded, $post_type ) {
			return Card_Repository::POST_TYPE === $post_type ? true : $excluded;
		}
	}
}
