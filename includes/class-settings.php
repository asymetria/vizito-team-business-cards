<?php
/**
 * Plugin-wide settings.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Settings' ) ) {

	/**
	 * Reads and sanitises the `tbcards_settings` option. Values are sanitised on the
	 * way in (register_setting), so reading only merges over defaults.
	 */
	final class Settings {

		public const OPTION        = 'tbcards_settings';
		public const FLUSH_OPTION  = 'tbcards_flush_rewrite';
		public const BASE_CHANGED  = 'tbcards_base_changed';
		public const SCHEMES       = array( 'auto', 'light', 'dark' );
		public const SHAPES        = array( 'round', 'square' );
		public const MAX_LANGUAGES = 10;
		public const MAX_RETENTION = 3650;

		private const CODE_PATTERN   = '/^[a-z]{2,3}$/';
		private const LOCALE_PATTERN = '/^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/';

		/**
		 * Default values. The first language comes from the site locale.
		 *
		 * @return array
		 */
		public function defaults(): array {
			$locale = function_exists( 'determine_locale' ) ? (string) determine_locale() : 'en_US';
			$code   = strtolower( (string) strtok( $locale, '_' ) );

			if ( ! preg_match( self::CODE_PATTERN, $code ) ) {
				$code   = 'en';
				$locale = 'en_US';
			}

			return array(
				'languages'        => array(
					array(
						'code'   => $code,
						'locale' => $locale,
					),
				),
				'default_language' => $code,
				'url_base'         => 'card',
				'auto_language'    => true,
				'accent'           => '#1d4ed8',
				'scheme'           => 'auto',
				'photo_shape'      => 'round',
				'vcard_photo'      => true,
				'retention_days'   => 0,
				'delete_data'      => false,
				'qr_dark'          => '#000000',
				'qr_light'         => '#ffffff',
				'qr_logo'          => false,
			);
		}

		/**
		 * All settings, saved over defaults, unknown keys dropped.
		 *
		 * @return array
		 */
		public function all(): array {
			$defaults = $this->defaults();
			$saved    = get_option( self::OPTION, array() );

			if ( ! is_array( $saved ) ) {
				$saved = array();
			}

			return array_replace( $defaults, array_intersect_key( $saved, $defaults ) );
		}

		/**
		 * One setting.
		 *
		 * @param string $key Setting key.
		 * @return mixed Null for an unknown key.
		 */
		public function get( string $key ) {
			return $this->all()[ $key ] ?? null;
		}

		/**
		 * Language codes, in switcher order.
		 *
		 * @return string[]
		 */
		public function languages(): array {
			return array_values(
				array_map(
					static function ( $row ) {
						return $row['code'];
					},
					$this->get( 'languages' )
				)
			);
		}

		/**
		 * The default language code.
		 *
		 * @return string
		 */
		public function default_language(): string {
			return (string) $this->get( 'default_language' );
		}

		/**
		 * The WordPress locale mapped to a language code.
		 *
		 * @param string $code Language code.
		 * @return string Empty when unknown or not mapped.
		 */
		public function locale_for( string $code ): string {
			foreach ( $this->get( 'languages' ) as $row ) {
				if ( $row['code'] === $code ) {
					return (string) $row['locale'];
				}
			}

			return '';
		}

		/**
		 * The register_setting() sanitize callback.
		 *
		 * @param mixed $input Raw form input.
		 * @return array
		 */
		public function sanitize( $input ): array {
			$input    = is_array( $input ) ? $input : array();
			$current  = $this->all();
			$defaults = $this->defaults();
			$clean    = $current;

			if ( array_key_exists( 'languages', $input ) ) {
				$clean['languages'] = $this->sanitize_languages( $input['languages'] );

				if ( array() === $clean['languages'] ) {
					$clean['languages'] = $defaults['languages'];
				}
			}

			$codes = array_map(
				static function ( $row ) {
					return $row['code'];
				},
				$clean['languages']
			);
			$want  = isset( $input['default_language'] ) ? sanitize_key( (string) $input['default_language'] ) : $clean['default_language'];

			$clean['default_language'] = in_array( $want, $codes, true ) ? $want : $codes[0];

			if ( array_key_exists( 'url_base', $input ) ) {
				$base              = sanitize_title( (string) $input['url_base'] );
				$clean['url_base'] = '' === $base ? $defaults['url_base'] : $base;
			}

			if ( array_key_exists( 'accent', $input ) ) {
				$accent          = sanitize_hex_color( (string) $input['accent'] );
				$clean['accent'] = $accent ? $accent : $defaults['accent'];

				// White text sits on the accent (the Save contact button). Saved
				// anyway, since a brand colour is the owner's call, but flagged.
				if ( 7 === strlen( $clean['accent'] ) && Qr\Qr_Colors::contrast( $clean['accent'], '#ffffff' ) < Qr\Qr_Colors::MIN_CONTRAST ) {
					add_settings_error(
						self::OPTION,
						'tbcards_accent_contrast',
						__( 'The accent colour is light: white text on it is hard to read. A darker shade works better for people with low vision.', 'vizito-team-business-cards' ),
						'warning'
					);
				}
			}

			if ( array_key_exists( 'scheme', $input ) ) {
				$clean['scheme'] = in_array( $input['scheme'], self::SCHEMES, true ) ? $input['scheme'] : $defaults['scheme'];
			}

			if ( array_key_exists( 'photo_shape', $input ) ) {
				$clean['photo_shape'] = in_array( $input['photo_shape'], self::SHAPES, true ) ? $input['photo_shape'] : $defaults['photo_shape'];
			}

			foreach ( array( 'auto_language', 'vcard_photo', 'delete_data', 'qr_logo' ) as $flag ) {
				if ( array_key_exists( $flag, $input ) ) {
					$clean[ $flag ] = ! empty( $input[ $flag ] );
				}
			}

			if ( array_key_exists( 'qr_dark', $input ) || array_key_exists( 'qr_light', $input ) ) {
				$dark  = strtolower( (string) ( $input['qr_dark'] ?? $current['qr_dark'] ) );
				$light = strtolower( (string) ( $input['qr_light'] ?? $current['qr_light'] ) );
				$error = Qr\Qr_Colors::validate( $dark, $light );

				if ( null === $error ) {
					$clean['qr_dark']  = $dark;
					$clean['qr_light'] = $light;
				} else {
					add_settings_error( self::OPTION, 'tbcards_qr_colors', $error );
				}
			}

			if ( array_key_exists( 'retention_days', $input ) ) {
				$clean['retention_days'] = min( self::MAX_RETENTION, absint( $input['retention_days'] ) );
			}

			if ( $clean['url_base'] !== $current['url_base'] ) {
				update_option( self::FLUSH_OPTION, 1 );
				set_transient( self::BASE_CHANGED, $current['url_base'], DAY_IN_SECONDS );
			}

			return $clean;
		}

		/**
		 * Language rows from a textarea string or an array of rows.
		 *
		 * @param mixed $raw Raw input.
		 * @return array<int, array{code: string, locale: string}>
		 */
		private function sanitize_languages( $raw ): array {
			if ( is_string( $raw ) ) {
				$rows = array();

				// Not \R: without the u flag it also matches byte 0x85, which is
				// the second byte of "ą" and other UTF-8 letters.
				foreach ( preg_split( '/\r\n|\n|\r/', $raw ) as $line ) {
					$bits   = preg_split( '/\s+/', trim( $line ) );
					$rows[] = array(
						'code'   => $bits[0] ?? '',
						'locale' => $bits[1] ?? '',
					);
				}

				$raw = $rows;
			}

			$clean = array();

			foreach ( is_array( $raw ) ? $raw : array() as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$code   = strtolower( trim( (string) ( $row['code'] ?? '' ) ) );
				$locale = trim( (string) ( $row['locale'] ?? '' ) );

				if ( ! preg_match( self::CODE_PATTERN, $code ) || isset( $clean[ $code ] ) ) {
					continue;
				}

				$clean[ $code ] = array(
					'code'   => $code,
					'locale' => preg_match( self::LOCALE_PATTERN, $locale ) ? $locale : '',
				);

				if ( count( $clean ) >= self::MAX_LANGUAGES ) {
					break;
				}
			}

			return array_values( $clean );
		}
	}
}
