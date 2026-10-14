<?php
/**
 * Company profile: shared values cards inherit.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Company;

use Asymetria\TeamCards\Card\Field_Sanitizer;
use Asymetria\TeamCards\Card\Fields;
use Asymetria\TeamCards\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Company_Profile' ) ) {

	/**
	 * The `tbcards_company` option.
	 */
	final class Company_Profile {

		public const OPTION = 'tbcards_company';

		/**
		 * Settings, for the language list and the default language.
		 *
		 * @var Settings
		 */
		private Settings $settings;

		/**
		 * Constructor.
		 *
		 * @param Settings $settings Plugin settings.
		 */
		public function __construct( Settings $settings ) {
			$this->settings = $settings;
		}

		/**
		 * Company fields: name, logo, then every inheritable card field.
		 *
		 * @return array<string, array>
		 */
		public static function fields(): array {
			$fields = array(
				'company_name' => array(
					'label'      => __( 'Company name', 'vizito-team-business-cards' ),
					'type'       => 'text',
					'group'      => 'company',
					'translated' => false,
					'inherits'   => false,
				),
				'logo_id'      => array(
					'label'      => __( 'Logo', 'vizito-team-business-cards' ),
					'type'       => 'attachment',
					'group'      => 'company',
					'translated' => false,
					'inherits'   => false,
				),
			);

			foreach ( Fields::inheritable() as $key ) {
				$fields[ $key ] = Fields::get( $key );
			}

			return $fields;
		}

		/**
		 * The raw option.
		 *
		 * @return array
		 */
		public function raw(): array {
			$saved = get_option( self::OPTION, array() );
			return is_array( $saved ) ? $saved : array();
		}

		/**
		 * A value in a language, falling back to the default language.
		 *
		 * @param string $key  Field key.
		 * @param string $lang Language code.
		 * @return string
		 */
		public function value( string $key, string $lang ): string {
			$raw = $this->raw()[ $key ] ?? '';

			if ( is_array( $raw ) ) {
				return self::pick( $raw, $lang, $this->settings->default_language() );
			}

			return is_scalar( $raw ) ? trim( (string) $raw ) : '';
		}

		/**
		 * The logo attachment id, 0 when none.
		 *
		 * @return int
		 */
		public function logo_id(): int {
			return absint( $this->raw()['logo_id'] ?? 0 );
		}

		/**
		 * The register_setting() sanitize callback.
		 *
		 * @param mixed $input Raw input.
		 * @return array
		 */
		public function sanitize( $input ): array {
			$input = is_array( $input ) ? $input : array();
			$codes = $this->settings->languages();
			$clean = array();

			foreach ( self::fields() as $key => $def ) {
				if ( ! array_key_exists( $key, $input ) ) {
					continue;
				}

				if ( 'attachment' === $def['type'] ) {
					$clean[ $key ] = absint( $input[ $key ] );
				} elseif ( $def['translated'] ) {
					$clean[ $key ] = Field_Sanitizer::translated( $def['type'], $input[ $key ], $codes );
				} else {
					$clean[ $key ] = Field_Sanitizer::value( $def['type'], $input[ $key ] );
				}
			}

			return $clean;
		}

		/**
		 * First non-empty value among the asked and the default language.
		 *
		 * @param array  $values  Values keyed by language.
		 * @param string $lang    Asked language.
		 * @param string $default Default language.
		 * @return string
		 */
		public static function pick( array $values, string $lang, string $default ): string {
			foreach ( array( $lang, $default ) as $code ) {
				if ( isset( $values[ $code ] ) && is_scalar( $values[ $code ] ) && '' !== trim( (string) $values[ $code ] ) ) {
					return trim( (string) $values[ $code ] );
				}
			}

			return '';
		}
	}
}
