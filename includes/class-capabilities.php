<?php
/**
 * Who may do what.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\Capabilities' ) ) {

	/**
	 * The two capabilities the plugin checks beyond per-card edit_post, both
	 * filterable. Managing the team's cards as a whole (import, export, print
	 * codes, statistics) touches every person's data, so it needs more than
	 * the right to edit one's own posts.
	 */
	final class Capabilities {

		/**
		 * Import, export, print codes, statistics.
		 *
		 * @return string
		 */
		public static function manage(): string {
			return (string) apply_filters( 'tbcards_manage_capability', 'edit_others_posts' );
		}

		/**
		 * Settings and the company profile.
		 *
		 * @return string
		 */
		public static function settings(): string {
			return (string) apply_filters( 'tbcards_settings_capability', 'manage_options' );
		}
	}
}
