<?php
/**
 * Plugin Name:       Vizito Team Business Cards
 * Description:       Digital business cards for your whole team, on your own domain: QR codes for print, vCard download, several languages, privacy-friendly statistics.
 * Version:           1.0.3
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            Asymetria
 * Author URI:        https://asymetria.com.pl
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vizito-team-business-cards
 * Domain Path:       /languages
 *
 * Every declaration below is guarded — defined() for constants, class_exists()
 * for classes, function_exists() for functions. PHP binds unconditional class
 * and function declarations at compile time, so a second copy of this code
 * loaded from elsewhere would otherwise be a fatal error on a live site.
 *
 * There is deliberately no Plugin URI header: WordPress.org rejects a
 * submission whose Plugin URI equals its Author URI.
 *
 * @package Vizito_Team_Business_Cards
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'TBCARDS_VERSION' ) ) {
	define( 'TBCARDS_VERSION', '1.0.3' );
	define( 'TBCARDS_FILE', __FILE__ );
	define( 'TBCARDS_DIR', plugin_dir_path( __FILE__ ) );
	define( 'TBCARDS_URL', plugin_dir_url( __FILE__ ) );
}

require_once __DIR__ . '/includes/class-autoloader.php';

if ( ! function_exists( 'tbcards_bootstrap' ) ) {

	/**
	 * Registers the autoloader and boots the plugin.
	 *
	 * @return void
	 */
	function tbcards_bootstrap(): void {
		\Asymetria\TeamCards\Autoloader::register();
		\Asymetria\TeamCards\Plugin::instance()->boot();
	}
}

tbcards_bootstrap();

if ( ! function_exists( 'tbcards_activate' ) ) {

	/**
	 * Registers the post type and flushes rewrite rules, so card addresses
	 * work straight after activation.
	 *
	 * @return void
	 */
	function tbcards_activate(): void {
		\Asymetria\TeamCards\Autoloader::register();
		\Asymetria\TeamCards\Plugin::instance()->type()->register();
		\Asymetria\TeamCards\Plugin::instance()->stats()->install();
		flush_rewrite_rules( false );
	}
}

register_activation_hook( __FILE__, 'tbcards_activate' );
if ( ! function_exists( 'tbcards_deactivate' ) ) {

	/**
	 * Removes the rewrite rules and the statistics cron job; data stays.
	 *
	 * @return void
	 */
	function tbcards_deactivate(): void {
		wp_clear_scheduled_hook( 'tbcards_stats_purge' );
		flush_rewrite_rules( false );
	}
}

register_deactivation_hook( __FILE__, 'tbcards_deactivate' );
