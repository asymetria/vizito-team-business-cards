<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Cards, settings, statistics and the table are removed only when
 * Settings → Data → "Delete all cards, settings and statistics" is on. The
 * default keeps everything: deleting the plugin by mistake must not wipe a
 * company's business cards, whose printed QR codes are still in people's
 * wallets. Transients are temporary and always go.
 *
 * On multisite every site is handled, except on a large network, where
 * looping over every site in one request is not safe.
 *
 * @package Vizito_Team_Business_Cards
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! function_exists( 'tbcards_uninstall_site' ) ) {

	/**
	 * Cleans up the current site.
	 *
	 * @return void
	 */
	function tbcards_uninstall_site(): void {
		global $wpdb;

		foreach ( array( '_transient_tbcards_', '_transient_timeout_tbcards_' ) as $prefix ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall; no API deletes by prefix.
				$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' )
			);
		}

		wp_clear_scheduled_hook( 'tbcards_stats_purge' );

		$settings = get_option( 'tbcards_settings', array() );

		if ( ! is_array( $settings ) || empty( $settings['delete_data'] ) ) {
			return;
		}

		register_post_type( 'tbcards_card', array( 'public' => false ) );

		$previous = null;

		do {
			$ids = get_posts(
				array(
					'post_type'        => 'tbcards_card',
					'post_status'      => array_keys( get_post_stati() ),
					'fields'           => 'ids',
					'numberposts'      => 100,
					'orderby'          => 'ID',
					'order'            => 'ASC',
				)
			);
			$ids = is_array( $ids ) ? array_map( 'intval', $ids ) : array();

			if ( $ids === $previous ) {
				break; // A filter refused the deletes; trying again changes nothing.
			}

			$previous = $ids;

			foreach ( $ids as $id ) {
				wp_delete_post( $id, true );
			}
		} while ( array() !== $ids );

		foreach ( array( 'tbcards_settings', 'tbcards_company', 'tbcards_db_version', 'tbcards_flush_rewrite' ) as $option ) {
			delete_option( $option );
		}

		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tbcards_stats" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- the plugin's own table, on request.
	}
}

if ( ! function_exists( 'tbcards_uninstall' ) ) {

	/**
	 * Cleans up this site, then every other site of a normal-sized network.
	 *
	 * @return void
	 */
	function tbcards_uninstall(): void {
		tbcards_uninstall_site();

		if ( ! is_multisite() || wp_is_large_network( 'sites' ) ) {
			return;
		}

		$current = get_current_blog_id();
		$offset  = 0;

		do {
			$ids = get_sites(
				array(
					'fields'  => 'ids',
					'number'  => 100,
					'offset'  => $offset,
					'orderby' => 'id',
					'order'   => 'ASC',
				)
			);
			$ids = is_array( $ids ) ? $ids : array();

			foreach ( $ids as $id ) {
				if ( (int) $id === $current ) {
					continue;
				}

				switch_to_blog( (int) $id );
				tbcards_uninstall_site();
				restore_current_blog();
			}

			$offset += 100;
		} while ( 100 === count( $ids ) );
	}
}

tbcards_uninstall();
