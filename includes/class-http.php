<?php
/**
 * Helpers for file responses.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\Http' ) ) {

	/**
	 * Shared by every download (vCard, QR, ZIP, CSV).
	 */
	final class Http {

		/**
		 * Discards whatever other code has already printed into the output
		 * buffers. A stray space or blank line from a theme or plugin file is
		 * harmless in HTML, but it lands in front of BEGIN:VCARD or a ZIP header
		 * and breaks the file. Seen on a live site that sent a space before
		 * every response.
		 *
		 * @return void
		 */
		public static function clean_buffers(): void {
			while ( ob_get_level() > 0 && ob_end_clean() ) {
				// Keep closing until none is left or one refuses.
			}
		}
	}
}
