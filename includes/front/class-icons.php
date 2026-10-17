<?php
/**
 * Inline SVG icons.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Icons' ) ) {

	/**
	 * A small, original icon set drawn on a 24-unit grid, stroke-based so it
	 * takes the text colour. Social networks are monogram badges rather than
	 * brand logos; no third-party icon set is copied here.
	 */
	final class Icons {

		public const NAMES = array( 'phone', 'mail', 'pin', 'globe', 'share', 'download', 'link', 'whatsapp', 'linkedin', 'facebook', 'instagram', 'x', 'youtube', 'tiktok', 'github', 'xing' );

		private const PATHS = array(
			'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
			'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
			'pin'      => '<path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>',
			'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18"/>',
			'share'    => '<circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="m8.2 10.8 7.6-3.6M8.2 13.2l7.6 3.6"/>',
			'download' => '<path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/>',
			'link'     => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
			'whatsapp' => '<path d="M4 20l1.5-4.2A8 8 0 1 1 8.2 18.6z"/><path d="M9 9.5c.5 2 2 3.5 4.5 4.5"/>',
		);

		private const MONOGRAMS = array(
			'linkedin'  => 'in',
			'facebook'  => 'f',
			'instagram' => 'IG',
			'x'         => 'X',
			'youtube'   => 'YT',
			'tiktok'    => 'TT',
			'github'    => 'GH',
			'xing'      => 'XI',
		);

		/**
		 * One icon as inline SVG, decorative (the link carries the name).
		 *
		 * @param string $name Icon name from NAMES; unknown names get "link".
		 * @return string
		 */
		public static function svg( string $name ): string {
			$open = '<svg class="tbcards-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="24" height="24"';

			if ( isset( self::MONOGRAMS[ $name ] ) ) {
				return $open . '><circle cx="12" cy="12" r="11" fill="currentColor"/>'
					. '<text x="12" y="16" text-anchor="middle" font-size="9.5" font-weight="700" font-family="system-ui,sans-serif" fill="var(--tbcards-bg,#fff)">'
					. self::MONOGRAMS[ $name ] . '</text></svg>';
			}

			$inner = self::PATHS[ $name ] ?? self::PATHS['link'];

			return $open . ' fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $inner . '</svg>';
		}
	}
}
