<?php
/**
 * QR colour rules.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Qr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Qr_Colors' ) ) {

	/**
	 * Which colour pairs scan reliably. Readers need dark modules on a light
	 * background, clearly apart; inverted codes fail on many phones.
	 */
	final class Qr_Colors {

		public const MIN_CONTRAST = 4.5;

		/**
		 * Why a pair is refused, or null when it is fine.
		 *
		 * @param string $dark  Module colour, #rrggbb.
		 * @param string $light Background colour, #rrggbb.
		 * @return string|null
		 */
		public static function validate( string $dark, string $light ): ?string {
			if ( ! self::is_hex( $dark ) || ! self::is_hex( $light ) ) {
				return __( 'QR colours must be given as #rrggbb.', 'vizito-team-business-cards' );
			}

			if ( self::luminance( $dark ) >= self::luminance( $light ) ) {
				return __( 'QR modules must be darker than the background; inverted codes fail on many phones.', 'vizito-team-business-cards' );
			}

			if ( self::contrast( $dark, $light ) < self::MIN_CONTRAST ) {
				return __( 'QR colours are too close together to scan reliably. Choose a darker module colour or a lighter background.', 'vizito-team-business-cards' );
			}

			return null;
		}

		/**
		 * WCAG contrast ratio, 1 to 21.
		 *
		 * @param string $a Colour, #rrggbb.
		 * @param string $b Colour, #rrggbb.
		 * @return float
		 */
		public static function contrast( string $a, string $b ): float {
			$la = self::luminance( $a );
			$lb = self::luminance( $b );

			return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
		}

		/**
		 * The colour as [r, g, b], 0–255.
		 *
		 * @param string $hex Colour, #rrggbb.
		 * @return int[]
		 */
		public static function rgb( string $hex ): array {
			return array(
				(int) hexdec( substr( $hex, 1, 2 ) ),
				(int) hexdec( substr( $hex, 3, 2 ) ),
				(int) hexdec( substr( $hex, 5, 2 ) ),
			);
		}

		/**
		 * WCAG relative luminance.
		 *
		 * @param string $hex Colour, #rrggbb.
		 * @return float
		 */
		private static function luminance( string $hex ): float {
			$channels = array_map(
				static function ( $c ) {
					$c /= 255;
					return $c <= 0.03928 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
				},
				self::rgb( $hex )
			);

			return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
		}

		/**
		 * Whether a value is #rrggbb.
		 *
		 * @param string $value Candidate.
		 * @return bool
		 */
		private static function is_hex( string $value ): bool {
			return (bool) preg_match( '/^#[0-9a-fA-F]{6}$/', $value );
		}
	}
}
