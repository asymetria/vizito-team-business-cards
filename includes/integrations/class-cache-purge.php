<?php
/**
 * Page cache purging.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Cache_Purge' ) ) {

	/**
	 * Tells page caches that a card changed, so an edit is visible at once.
	 * The earlier single-client plugin found this out in production: a 30-day
	 * LiteSpeed TTL hid every edit made to a card.
	 *
	 * Every caching plugin is reached only through its public API and only
	 * when that API exists. LiteSpeed's API is an action, always safe to fire.
	 */
	final class Cache_Purge {

		/**
		 * Hooks.
		 *
		 * @return void
		 */
		public function hooks(): void {
			add_action( 'tbcards_card_saved', array( $this, 'card' ) );
			add_action( 'update_option_tbcards_company', array( $this, 'everything' ) );
			add_action( 'update_option_tbcards_settings', array( $this, 'everything' ) );
			add_action( 'add_option_tbcards_company', array( $this, 'everything' ) );
			add_action( 'add_option_tbcards_settings', array( $this, 'everything' ) );
		}

		/**
		 * Purges one card's page.
		 *
		 * @param int $post_id Post id.
		 * @return void
		 */
		public function card( $post_id ): void {
			$post_id = (int) $post_id;

			if ( ! apply_filters( 'tbcards_purge_cache', true, $post_id ) ) {
				return;
			}

			if ( function_exists( 'clean_post_cache' ) ) {
				clean_post_cache( $post_id );
			}

			do_action( 'litespeed_purge_post', $post_id );

			if ( function_exists( 'rocket_clean_post' ) ) {
				rocket_clean_post( $post_id );
			}

			if ( function_exists( 'w3tc_flush_post' ) ) {
				w3tc_flush_post( $post_id );
			}

			if ( function_exists( 'wpsc_delete_post_cache' ) ) {
				wpsc_delete_post_cache( $post_id );
			}
		}

		/**
		 * Purges every page, because shared data (company profile or
		 * settings) appears on every card.
		 *
		 * @return void
		 */
		public function everything(): void {
			if ( ! apply_filters( 'tbcards_purge_cache', true, null ) ) {
				return;
			}

			do_action( 'litespeed_purge_all' );

			if ( function_exists( 'rocket_clean_domain' ) ) {
				rocket_clean_domain();
			}

			if ( function_exists( 'w3tc_flush_all' ) ) {
				w3tc_flush_all();
			}

			if ( function_exists( 'wp_cache_clear_cache' ) ) {
				wp_cache_clear_cache();
			}
		}
	}
}
