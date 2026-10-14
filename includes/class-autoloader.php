<?php
/**
 * Class autoloader.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Autoloader' ) ) {

	/**
	 * Maps Asymetria\TeamCards\Sub_Area\Class_Name to includes/sub-area/class-class-name.php.
	 */
	final class Autoloader {

		private const PREFIX = 'Asymetria\\TeamCards\\';

		/**
		 * Registers the loader with SPL.
		 *
		 * @return void
		 */
		public static function register(): void {
			spl_autoload_register( array( __CLASS__, 'load' ) );
		}

		/**
		 * Loads the file for a class, if it is one of ours and exists.
		 *
		 * @param string $class Fully qualified class name.
		 * @return void
		 */
		public static function load( string $class ): void {
			if ( ! defined( 'TBCARDS_DIR' ) ) {
				return;
			}

			$relative = self::path_for( $class );

			if ( null !== $relative && is_readable( TBCARDS_DIR . $relative ) ) {
				require_once TBCARDS_DIR . $relative;
			}
		}

		/**
		 * The file a class lives in, relative to the plugin root.
		 *
		 * @param string $class Fully qualified class name.
		 * @return string|null Null for classes outside this plugin.
		 */
		public static function path_for( string $class ): ?string {
			if ( ! str_starts_with( $class, self::PREFIX ) ) {
				return null;
			}

			$parts = explode( '\\', substr( $class, strlen( self::PREFIX ) ) );
			$name  = array_pop( $parts );
			$dir   = 'includes';

			if ( array() !== $parts ) {
				$dir .= '/' . str_replace( '_', '-', strtolower( implode( '/', $parts ) ) );
			}

			return $dir . '/class-' . str_replace( '_', '-', strtolower( $name ) ) . '.php';
		}
	}
}
