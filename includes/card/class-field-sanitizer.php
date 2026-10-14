<?php
/**
 * Field sanitising.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Card;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Field_Sanitizer' ) ) {

	/**
	 * Sanitises one value by field type. Callers unslash request data first.
	 *
	 * Phones are stored as typed, minus anything that cannot be part of a
	 * number. The dialable form used for tel:, wa.me and the vCard is computed
	 * on output by dial(), so there is one stored value and nothing to drift
	 * out of sync.
	 */
	final class Field_Sanitizer {

		public const URL_SCHEMES = array( 'http', 'https' );

		/**
		 * Sanitises a single value.
		 *
		 * @param string $type Field type from Fields::TYPES.
		 * @param mixed  $raw  Raw value.
		 * @return string
		 */
		public static function value( string $type, $raw ): string {
			if ( ! is_scalar( $raw ) ) {
				return '';
			}

			$raw = (string) $raw;

			switch ( $type ) {
				case 'textarea':
					return sanitize_textarea_field( $raw );
				case 'email':
					return (string) sanitize_email( $raw );
				case 'url':
					return (string) esc_url_raw( trim( $raw ), self::URL_SCHEMES );
				case 'tel':
					$tel = preg_replace( '/[^0-9+()\-.\/ ]/', '', $raw );
					return trim( (string) preg_replace( '/\s+/', ' ', (string) $tel ) );
				default:
					return sanitize_text_field( $raw );
			}
		}

		/**
		 * Sanitises a per-language value, keeping configured, non-empty languages only.
		 *
		 * @param string   $type  Field type.
		 * @param mixed    $raw   Raw value keyed by language code.
		 * @param string[] $codes Configured language codes.
		 * @return array<string, string>
		 */
		public static function translated( string $type, $raw, array $codes ): array {
			if ( ! is_array( $raw ) ) {
				return array();
			}

			$clean = array();

			foreach ( $codes as $code ) {
				$value = self::value( $type, $raw[ $code ] ?? '' );

				if ( '' !== trim( $value ) ) {
					$clean[ $code ] = $value;
				}
			}

			return $clean;
		}

		/**
		 * The dialable form of a stored phone number. A number without "+" or
		 * "00" has no country code and is dialled as digits only: the plugin
		 * never guesses a country.
		 *
		 * @param string $phone Phone as stored.
		 * @return string "+" and digits for international numbers, digits otherwise, "" when none.
		 */
		public static function dial( string $phone ): string {
			$phone  = trim( $phone );
			$digits = (string) preg_replace( '/\D/', '', $phone );

			if ( '' === $digits ) {
				return '';
			}

			if ( str_starts_with( $phone, '+' ) ) {
				return '+' . $digits;
			}

			if ( str_starts_with( $digits, '00' ) ) {
				return '+' . substr( $digits, 2 );
			}

			return $digits;
		}
	}
}
